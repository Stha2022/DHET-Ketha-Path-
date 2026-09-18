<?php
/**
 * Khetha Path — CV builder data layer.
 *
 *   cv_build($userId, $db, $account)   a read-only starting-point CV from what Khetha knows
 *   cv_get_edits / cv_save_edits / cv_reset_edits   the learner's own changes, kept separately
 *   cv_apply($built, $edits)           the CV to show: built values with the learner's edits on top
 *   cv_nudges($cv)                     what is still missing, with a link to fix each thing
 *
 * WHERE THE DATA COMES FROM
 *   1. The database, when a live mysqli connection is passed in AND the learner has a `users` row.
 *      All queries are prepared statements, SELECT-only, with explicit column lists (never
 *      SELECT *), so passwordHash / tempToken / tempTokenExpiry / consentAt can never reach a CV.
 *   2. Otherwise the shared learner profile (includes/profile.php): demo mode.
 *   cv_db() returns a connection only when the environment variable KHETHA_USE_DB=1 is set, so
 *   demo mode never touches the database.
 *
 * STORAGE BOUNDARY (same rule as profile.php): the learner's CV edits live in the
 * learner_cv_edits table when the CV came from the database, and in $_SESSION['khetha_cv_edits']
 * in demo mode. Only this file touches either.
 *
 * Reference data (occupation titles, qualifications, providers) still comes from
 * occupation-data.php: the database `careers` table has integer ids and no slug to join on.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/../occupation-data.php';
require_once __DIR__ . '/../subject-data.php';
require_once __DIR__ . '/../data/interests.php';

const CV_SESSION_KEY = 'khetha_cv_edits';

// users.grade is a lower-case enum in the database; the app says "Grade 10" / "Post-school".
const CV_DB_GRADES = ['grade 9' => 'Grade 9', 'grade 10' => 'Grade 10', 'grade 11' => 'Grade 11', 'grade 12' => 'Grade 12', 'out of school' => 'Post-school'];

// ---------------------------------------------------------------------------
// Connection
// ---------------------------------------------------------------------------

/** A live database connection, or null in demo mode (see includes/db.php). */
function cv_db(): ?mysqli {
    return kp_db();
}

// ---------------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------------

/**
 * @param array $account The signed-in account ('name', 'email'), used only when the CV comes from the profile.
 * @return array The CV: source, summary, contact, school, subjects, results, interests, pathways,
 *   recommendations, achievements.
 */
function cv_build(int $userId, ?mysqli $db = null, array $account = []): array {
    $raw = $db ? _cv_read_db($db, $userId) : null;
    $source = 'db';
    if ($raw === null) { $raw = _cv_read_profile($account); $source = 'profile'; }
    return _cv_assemble($raw, $source);
}

/** @return array Normalised raw learner data from the shared profile. */
function _cv_read_profile(array $account): array {
    $p = profile_get();
    return [
        'name' => $p['name'] !== '' ? $p['name'] : (string)($account['name'] ?? ''),
        'email' => (string)($account['email'] ?? ''),
        'phone' => '',
        'grade' => $p['grade'],
        'home_language' => $p['home_language'], 'fal' => $p['fal'], 'maths_track' => $p['maths_track'],
        'subjects' => $p['subjects'], 'marks' => $p['marks'],
        'interests' => $p['interests'],
        'career_quiz' => $p['career_quiz'],
        'intended_careers' => $p['intended_careers'],
        'job_fit' => $p['job_fit'],
    ];
}

/** @return array|null Normalised raw learner data from the database, or null if there is no such user. */
function _cv_read_db(mysqli $db, int $userId): ?array {
    $rows = _cv_query($db, 'SELECT firstName, lastName, email, phoneNumber, grade FROM users WHERE userID = ?', 'i', [$userId]);
    if (!$rows) return null;
    $u = $rows[0];

    // interests lives in a column added by database/migration_cv_edits.sql; older databases lack it.
    $interests = [];
    $ir = _cv_query($db, 'SELECT interests FROM users WHERE userID = ?', 'i', [$userId]);
    if ($ir && $ir[0]['interests'] !== null) $interests = (array)(json_decode((string)$ir[0]['interests'], true) ?: []);

    $raw = [
        'name' => trim($u['firstName'] . ' ' . $u['lastName']), 'email' => (string)$u['email'], 'phone' => (string)($u['phoneNumber'] ?? ''),
        'grade' => CV_DB_GRADES[strtolower((string)$u['grade'])] ?? (string)$u['grade'],
        'home_language' => '', 'fal' => '', 'maths_track' => '', 'subjects' => [], 'marks' => [],
        'interests' => array_values(array_filter(array_map('strval', $interests), 'strlen')),
        'career_quiz' => ['code' => '', 'scores' => [], 'top_careers' => [], 'completed_at' => null],
        'intended_careers' => [], 'job_fit' => [],
    ];

    // Newest row first; the first row seen per source (per occupation for job_fit) wins.
    $results = _cv_query($db, 'SELECT source, payload, derived, createdAt FROM assessmentResults WHERE userID = ? ORDER BY assessmentID DESC LIMIT 300', 'i', [$userId]) ?: [];
    $seen = [];
    foreach ($results as $row) {
        $payload = (array)(json_decode((string)($row['payload'] ?? ''), true) ?: []);
        $derived = (array)(json_decode((string)($row['derived'] ?? ''), true) ?: []);
        $when = strtotime((string)$row['createdAt']) ?: null;
        switch ($row['source']) {
            case 'subject_chooser':
                if (isset($seen['sc'])) break;
                $seen['sc'] = true;
                if (!empty($payload['grade'])) $raw['grade'] = (string)$payload['grade'];
                $raw['home_language'] = (string)($payload['home_language'] ?? '');
                $raw['fal'] = (string)($payload['fal'] ?? '');
                $raw['maths_track'] = (string)($payload['maths_track'] ?? '');
                $raw['subjects'] = array_values(array_map('strval', (array)($payload['subjects'] ?? [])));
                $raw['marks'] = (array)($payload['marks'] ?? []);
                $raw['intended_careers'] = array_values(array_map('strval', (array)($payload['intended_careers'] ?? [])));
                break;
            case 'career_choice':
                if (isset($seen['cc'])) break;
                $seen['cc'] = true;
                $raw['career_quiz'] = [
                    'code' => (string)($derived['code'] ?? ''), 'scores' => (array)($derived['scores'] ?? []),
                    'top_careers' => array_values(array_map('strval', (array)($derived['top_careers'] ?? []))), 'completed_at' => $when,
                ];
                break;
            case 'job_fit':
                $id = (string)($derived['occupation_id'] ?? '');
                if ($id === '' || isset($seen['jf' . $id])) break;
                $seen['jf' . $id] = true;
                $raw['job_fit'][$id] = ['overall' => (int)($derived['overall'] ?? 0), 'flags' => (array)($derived['flags'] ?? []), 'completed_at' => $when];
                break;
        }
    }
    return $raw;
}

/** Prepared, read-only query. @return array[]|false Rows, or false if the statement could not run. */
function _cv_query(mysqli $db, string $sql, string $types, array $params) {
    $stmt = @$db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { $stmt->close(); return false; }
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** Turns normalised raw data into the CV structure. */
function _cv_assemble(array $raw, string $source): array {
    $occ = kp_occupations();
    $sjOcc = sj_occupations();
    $title = fn(string $id) => $occ[$id]['title'] ?? null;
    $friendly = kp_riasec_friendly_names();

    // Subjects: Maths track first, then the electives.
    $subjects = array_values(array_unique(array_filter(array_merge([$raw['maths_track']], $raw['subjects']), 'strlen')));

    $results = [];
    foreach ($raw['marks'] as $subject => $mark) {
        if (!is_numeric($mark)) continue;
        $band = sj_band_for_mark((int)$mark); // stored marks are band midpoints: show the band, not false precision
        $results[] = ['subject' => (string)$subject, 'value' => $band . '%'];
    }

    // Pathway ideas: careers the learner picked, then the ones Career Choice pointed to.
    $ideas = [];
    foreach ($raw['intended_careers'] as $id) if ($title($id)) $ideas[$id] = 'You are aiming for this';
    foreach ($raw['career_quiz']['top_careers'] as $id) if ($title($id) && !isset($ideas[$id])) $ideas[$id] = 'Suggested by Career Choice';
    $pathways = [];
    foreach (array_slice($ideas, 0, 5, true) as $id => $why) {
        $quals = kp_qualifications_for_occupation($id);
        $providers = $quals ? array_slice(array_column(kp_providers_for_qualification($quals[0]['id']), 'name'), 0, 3) : [];
        $pathways[] = [
            'id' => $id, 'title' => $occ[$id]['title'], 'field' => $occ[$id]['field'], 'why' => $why,
            'qualifications' => array_map(fn($q) => $q['title'] . ' (NQF ' . (int)$q['nqf_level'] . ')', $quals),
            'providers' => $providers,
        ];
    }

    // Khetha recommendations.
    $canJudge = $raw['maths_track'] !== '' && !empty($raw['subjects']);
    $subjectChooser = [];
    foreach ($raw['intended_careers'] as $id) {
        if (!$title($id)) continue;
        $subjectChooser[] = ['title' => $title($id), 'open' => ($canJudge && isset($sjOcc[$id])) ? sj_open_status($raw['subjects'], $raw['marks'], $raw['maths_track'], $sjOcc[$id])['open'] : null];
    }
    $code = $raw['career_quiz']['code'];
    $careerChoice = $code === '' ? null : [
        'code' => $code,
        'leans' => array_map(fn($l) => $friendly[$l] ?? $l, str_split($code)),
        'top' => array_values(array_filter(array_map($title, array_slice($raw['career_quiz']['top_careers'], 0, 3)))),
    ];
    $jobFit = [];
    foreach ($raw['job_fit'] as $id => $r) {
        if (!$title((string)$id)) continue;
        $o = (int)($r['overall'] ?? 0);
        $jobFit[] = ['title' => $title((string)$id), 'verdict' => $o >= 75 ? 'Strong fit' : ($o >= 45 ? 'Partial fit' : 'Weak fit')];
    }

    $cv = [
        'source' => $source,
        'summary' => '',
        'contact' => ['name' => $raw['name'], 'email' => $raw['email'], 'phone' => $raw['phone']],
        'school' => ['grade' => $raw['grade'], 'school_name' => '', 'notes' => '', 'home_language' => $raw['home_language'], 'fal' => $raw['fal'], 'maths_track' => $raw['maths_track']],
        'subjects' => $subjects,
        'results' => $results,
        'interests' => $raw['interests'],
        'pathways' => $pathways,
        'pathway_extra' => [],
        'recommendations' => ['career_choice' => $careerChoice, 'job_fit' => $jobFit, 'subject_chooser' => $subjectChooser],
        'achievements' => [],
    ];
    $cv['summary'] = _cv_summary($cv);
    return $cv;
}

/** A short first-person-neutral summary drafted from the data; the learner can rewrite it. */
function _cv_summary(array $cv): string {
    $name = $cv['contact']['name'] !== '' ? $cv['contact']['name'] : 'This learner';
    $grade = $cv['school']['grade'];
    $s = $grade === 'Post-school' ? "$name is looking for the next step after school." : ($grade !== '' ? "$name is a $grade learner." : "$name is a learner exploring careers.");
    if ($cv['subjects']) $s .= ' Currently taking ' . sj_join_and($cv['subjects']) . '.';
    if ($cv['interests']) $s .= ' Interested in ' . sj_join_and(array_slice($cv['interests'], 0, 4)) . '.';
    $cc = $cv['recommendations']['career_choice'];
    if ($cc) $s .= ' Career Choice points to ' . sj_join_and(array_map('strtolower', $cc['leans'])) . ' work (' . $cc['code'] . ').';
    $aim = array_column($cv['recommendations']['subject_chooser'], 'title');
    if ($aim) $s .= ' Aiming for ' . sj_join_and(array_slice($aim, 0, 3)) . '.';
    return $s;
}

// ---------------------------------------------------------------------------
// The learner's own edits
// ---------------------------------------------------------------------------

/** Keys a learner may edit, and their type. Everything else in the CV is derived and read-only. */
function cv_editable_fields(): array {
    return [
        'summary' => 'text', 'contact_name' => 'line', 'contact_email' => 'line', 'contact_phone' => 'line',
        'school_name' => 'line', 'school_grade' => 'line', 'school_notes' => 'text',
        'subjects' => 'list', 'results' => 'results', 'interests' => 'list', 'pathway_extra' => 'list', 'achievements' => 'list',
    ];
}

/** @return array The learner's saved edits (sparse: only what they changed). $db is used only for a database-sourced CV. */
function cv_get_edits(int $userId, ?mysqli $db = null): array {
    if ($db) {
        $rows = _cv_query($db, 'SELECT edits FROM learner_cv_edits WHERE userID = ?', 'i', [$userId]);
        return $rows ? (array)(json_decode((string)$rows[0]['edits'], true) ?: []) : [];
    }
    _cv_session();
    return is_array($_SESSION[CV_SESSION_KEY] ?? null) ? $_SESSION[CV_SESSION_KEY] : [];
}

/**
 * Saves what the learner submitted. Only fields that differ from what Khetha built are kept, so
 * a field they never touched keeps following their profile. @return bool false if the database write failed.
 */
function cv_save_edits(int $userId, array $submitted, array $built, ?mysqli $db = null): bool {
    $edits = [];
    foreach (cv_editable_fields() as $key => $type) {
        if (!array_key_exists($key, $submitted)) continue;
        $value = _cv_clean($submitted[$key], $type);
        if ($value !== _cv_built_value($built, $key)) $edits[$key] = $value;
    }
    return _cv_store($userId, $edits, $db);
}

/** "Reset to what Khetha knows": drop every edit. */
function cv_reset_edits(int $userId, ?mysqli $db = null): bool {
    return _cv_store($userId, [], $db);
}

function _cv_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

function _cv_store(int $userId, array $edits, ?mysqli $db): bool {
    if ($db) {
        if (!$edits) {
            $stmt = @$db->prepare('DELETE FROM learner_cv_edits WHERE userID = ?');
            if (!$stmt) return false;
            $stmt->bind_param('i', $userId);
        } else {
            $json = json_encode($edits, JSON_UNESCAPED_UNICODE);
            $stmt = @$db->prepare('INSERT INTO learner_cv_edits (userID, edits) VALUES (?, ?) ON DUPLICATE KEY UPDATE edits = VALUES(edits)');
            if (!$stmt) return false;
            $stmt->bind_param('is', $userId, $json);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    _cv_session();
    if ($edits) $_SESSION[CV_SESSION_KEY] = $edits; else unset($_SESSION[CV_SESSION_KEY]);
    return true;
}

/** The built CV's value for an editable key, in the same shape _cv_clean() produces. */
function _cv_built_value(array $cv, string $key) {
    return match ($key) {
        'summary' => $cv['summary'],
        'contact_name' => $cv['contact']['name'], 'contact_email' => $cv['contact']['email'], 'contact_phone' => $cv['contact']['phone'],
        'school_name' => $cv['school']['school_name'], 'school_grade' => $cv['school']['grade'], 'school_notes' => $cv['school']['notes'],
        'subjects' => $cv['subjects'], 'interests' => $cv['interests'],
        'results' => array_map(fn($r) => $r['subject'] . ': ' . $r['value'], $cv['results']),
        'pathway_extra' => $cv['pathway_extra'], 'achievements' => $cv['achievements'],
    };
}

/** Normalises one submitted field: trims, caps length, drops empties. */
function _cv_clean($value, string $type) {
    if ($type === 'line' || $type === 'text') {
        $s = trim(preg_replace('/\r\n?/', "\n", (string)(is_array($value) ? '' : $value)));
        if ($type === 'line') $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_substr($s, 0, $type === 'text' ? 1500 : 200);
    }
    $items = is_array($value) ? $value : preg_split('/[\r\n]+|,(?![^(]*\))/u', (string)$value);
    $out = [];
    foreach ($items as $item) {
        $item = mb_substr(trim(preg_replace('/\s+/', ' ', (string)$item)), 0, 120);
        if ($item === '') continue;
        if ($type === 'results') {
            // "Physical Sciences: 72%" or "...: 60-69%": a subject, a colon, then a mark or band.
            if (!preg_match('/^(.+?)\s*:\s*(\d{1,3}(?:\s*[-–]\s*\d{1,3})?)\s*%?$/u', $item, $m)) continue;
            $item = trim($m[1]) . ': ' . preg_replace('/\s*[-–]\s*/', '-', $m[2]) . '%';
        }
        if (!in_array($item, $out, true)) $out[] = $item;
        if (count($out) >= 30) break;
    }
    return $out;
}

/** @return array The CV with the learner's edits on top; 'edited' lists the keys they changed. */
function cv_apply(array $built, array $edits): array {
    $cv = $built;
    $cv['edited'] = [];
    foreach (cv_editable_fields() as $key => $type) {
        if (!array_key_exists($key, $edits)) continue;
        $v = $edits[$key];
        $cv['edited'][] = $key;
        switch ($key) {
            case 'summary': $cv['summary'] = $v; break;
            case 'contact_name': $cv['contact']['name'] = $v; break;
            case 'contact_email': $cv['contact']['email'] = $v; break;
            case 'contact_phone': $cv['contact']['phone'] = $v; break;
            case 'school_name': $cv['school']['school_name'] = $v; break;
            case 'school_grade': $cv['school']['grade'] = $v; break;
            case 'school_notes': $cv['school']['notes'] = $v; break;
            case 'subjects': $cv['subjects'] = (array)$v; break;
            case 'interests': $cv['interests'] = (array)$v; break;
            case 'pathway_extra': $cv['pathway_extra'] = (array)$v; break;
            case 'achievements': $cv['achievements'] = (array)$v; break;
            case 'results':
                $cv['results'] = [];
                foreach ((array)$v as $line) {
                    [$subject, $value] = array_pad(explode(':', $line, 2), 2, '');
                    $cv['results'][] = ['subject' => trim($subject), 'value' => trim($value)];
                }
                break;
        }
    }
    return $cv;
}

// ---------------------------------------------------------------------------
// Completeness
// ---------------------------------------------------------------------------

/**
 * How complete the CV is, judged on the CV as it will print (edits included).
 * @return array{percent:int, missing: array<int, array{label:string, url:string}>}
 */
function cv_nudges(array $cv): array {
    $chips = array_filter(array_unique(array_map(fn($i) => kp_interest_label((string)$i), $cv['interests'])));
    $checks = [
        ['ok' => $cv['contact']['phone'] !== '', 'label' => 'Add a phone number', 'url' => 'cv.php?edit=1#contact'],
        ['ok' => $cv['school']['school_name'] !== '', 'label' => 'Add your school', 'url' => 'cv.php?edit=1#school'],
        ['ok' => !empty($cv['subjects']), 'label' => 'Add your subjects', 'url' => 'subject.php'],
        ['ok' => !empty($cv['results']), 'label' => 'Add your latest marks', 'url' => 'subject.php?restart=1'],
        ['ok' => count($chips) >= 3, 'label' => 'Pick at least 3 interests', 'url' => 'my-profile.php#interests'],
        ['ok' => $cv['recommendations']['career_choice'] !== null, 'label' => 'Take the Career Choice questionnaire', 'url' => 'career-quiz.php'],
        ['ok' => !empty($cv['recommendations']['job_fit']), 'label' => 'Check your Job Fit for a career', 'url' => 'occupation.php'],
        ['ok' => !empty($cv['achievements']), 'label' => 'Add an achievement or activity', 'url' => 'cv.php?edit=1#achievements'],
    ];
    $missing = [];
    foreach ($checks as $c) if (!$c['ok']) $missing[] = ['label' => $c['label'], 'url' => $c['url']];
    return ['percent' => (int)round((count($checks) - count($missing)) / count($checks) * 100), 'missing' => $missing];
}
