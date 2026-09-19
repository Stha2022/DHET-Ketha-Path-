<?php
/**
 * Khetha Path — the ONE shared learner profile.
 *
 * Every page reads the learner's grade, languages, maths track, subjects,
 * marks, interests, Career Choice result, intended careers and Job Fit
 * results through profile_get() and writes them through profile_update(),
 * so a learner is never asked for the same thing twice.
 *
 * STORAGE BOUNDARY: this is the only file that may touch
 * $_SESSION['khetha_profile'] (the demo-mode store) or the legacy session
 * keys it migrates from. No other file should read or write either directly.
 * To move the profile to MySQL later, change _profile_load() and
 * _profile_save() below — the public functions, and every page that calls
 * them, stay exactly as they are.
 *
 * Auth is NOT part of the profile: $_SESSION['user'] (id, email — the
 * signed-in marker every page guards on) is left alone.
 */

require_once __DIR__ . '/../data/interests.php';

const PROFILE_SESSION_KEY = 'khetha_profile';
const PROFILE_MATHS_TRACKS = ['Mathematics', 'Mathematical Literacy', 'Technical Mathematics'];
// Subject names the old register.php checklist used that differ from the
// canonical CAPS names in subject-data.php.
const PROFILE_SUBJECT_ALIASES = ['IT' => 'Information Technology'];

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * The learner's profile, with a default for every missing key.
 * The first call in a session migrates any legacy session keys into it.
 */
function profile_get(): array {
    $stored = _profile_load();
    if ($stored === null) {
        $stored = _profile_migrate_legacy();
        _profile_save($stored);
    } elseif (empty($stored['riasec_from_interests']) && !empty($stored['interests'])) {
        // Saved before interests were tied to RIASEC: fill it in once.
        $stored['riasec_from_interests'] = kp_interests_to_riasec($stored['interests']);
        _profile_save($stored);
    }
    return array_replace(_profile_defaults(), $stored);
}

/**
 * Merge $changes into the profile and stamp updated_at. Unknown keys are
 * ignored. Lists and scalars replace the stored value; 'job_fit' merges per
 * occupation id so one result never wipes another. Values are normalised
 * (see _profile_apply()).
 */
function profile_update(array $changes): void {
    _profile_save(_profile_apply(profile_get(), $changes));
}

/**
 * Discard the profile and start blank — for a brand-new learner (register),
 * so a previous demo user's answers never carry over. Legacy keys are not
 * re-migrated afterwards.
 */
function profile_reset(): void {
    _profile_save(_profile_defaults());
}

/**
 * How far along the learner is: five equally-weighted steps.
 *   basics           grade + Maths track
 *   interests        at least 3 interest chips (free text that isn't a chip doesn't count)
 *   subject_chooser  the wizard was finished with careers picked (languages + intended careers)
 *   career_choice    the quiz has a saved result
 *   job_fit          Job Fit done for at least one career
 * @return array{percent:int, done:string[], next:?array{label:string,url:string}, steps:array<string,array{label:string,url:string,done:bool}>}
 *   'next' is the first unfinished step in that order, or null once all five are done.
 */
function profile_completion(): array {
    $p = profile_get();
    $chips = array_filter(array_unique(array_map(fn($i) => kp_interest_label((string)$i), $p['interests'])));

    $steps = [
        'basics'          => ['label' => 'Profile basics',   'url' => 'my-profile.php',         'done' => $p['grade'] !== '' && $p['maths_track'] !== ''],
        'interests'       => ['label' => 'Interests',        'url' => 'my-profile.php#interests', 'done' => count($chips) >= 3],
        'subject_chooser' => ['label' => 'Subject Chooser',  'url' => 'subject.php',            'done' => $p['home_language'] !== '' && $p['fal'] !== '' && !empty($p['intended_careers'])],
        'career_choice'   => ['label' => 'Career Choice',    'url' => 'career-quiz.php',        'done' => $p['career_quiz']['code'] !== ''],
        'job_fit'         => ['label' => 'Job Fit',          'url' => 'occupation.php?fit=1',   'done' => !empty($p['job_fit'])],
    ];

    $tr = fn(string $s) => function_exists('t') ? t($s) : $s;
    $done = [];
    $next = null;
    foreach ($steps as $key => $s) {
        $steps[$key]['label'] = $tr($s['label']);
        if ($s['done']) $done[] = $steps[$key]['label'];
        elseif ($next === null) $next = ['label' => $steps[$key]['label'], 'url' => $s['url']];
    }
    return ['percent' => (int)round(count($done) / count($steps) * 100), 'done' => $done, 'next' => $next, 'steps' => $steps];
}

// ---------------------------------------------------------------------------
// Storage — MySQL is the persistent source of truth; session is the offline/demo
// fallback. A single JSON profile keeps the prototype schema stable while the
// individual assessment pages continue to use their own result structures.
// ---------------------------------------------------------------------------

function _profile_load(): ?array {
    _profile_session();
    $id = (int)($_SESSION['user']['id'] ?? 0);
    if ($id > 0) {
        require_once __DIR__ . '/db.php';
        $db = kp_db();
        if ($db) {
            $stmt = @$db->prepare('SELECT profileData FROM learner_profiles WHERE userID=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute() && ($res = $stmt->get_result()) && ($row = $res->fetch_assoc())) {
                    $decoded = json_decode((string)$row['profileData'], true);
                    if (is_array($decoded)) return $decoded;
                }
                $stmt->close();
            }
            // Graceful recovery for an account created before learner_profiles existed.
            $stmt = @$db->prepare('SELECT firstName,lastName,grade,interests FROM users WHERE userID=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i',$id);
                if ($stmt->execute() && ($res=$stmt->get_result()) && ($u=$res->fetch_assoc())) {
                    $ints=json_decode((string)($u['interests']??''),true);
                    return ['name'=>trim($u['firstName'].' '.$u['lastName']), 'grade'=>(string)($u['grade']??''), 'interests'=>is_array($ints)?$ints:[]];
                }
                $stmt->close();
            }
        }
    }
    return isset($_SESSION[PROFILE_SESSION_KEY]) && is_array($_SESSION[PROFILE_SESSION_KEY])
        ? $_SESSION[PROFILE_SESSION_KEY] : null;
}

function _profile_save(array $profile): void {
    _profile_session();
    $_SESSION[PROFILE_SESSION_KEY] = $profile;
    $id = (int)($_SESSION['user']['id'] ?? 0);
    if ($id <= 0) return;

    require_once __DIR__ . '/db.php';
    $db = kp_db();
    if (!$db) return;

    $json = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return;

    $stmt = @$db->prepare('INSERT INTO learner_profiles (userID,profileData) VALUES (?,?) ON DUPLICATE KEY UPDATE profileData=VALUES(profileData)');
    if ($stmt) {
        $stmt->bind_param('is', $id, $json);
        $stmt->execute();
        $stmt->close();
    }

    // Keep the account-level fields queryable without opening the profile JSON.
    $grade = (string)($profile['grade'] ?? '');
    $interests = json_encode(array_values((array)($profile['interests'] ?? [])), JSON_UNESCAPED_UNICODE);
    $stmt = @$db->prepare('UPDATE users SET grade=?, interests=? WHERE userID=?');
    if ($stmt) {
        $stmt->bind_param('ssi', $grade, $interests, $id);
        $stmt->execute();
        $stmt->close();
    }
}

function _profile_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

// ---------------------------------------------------------------------------
// Shape, normalisation and legacy migration
// ---------------------------------------------------------------------------

function _profile_defaults(): array {
    return [
        'name' => '',
        'grade' => '',
        'home_language' => '',
        'fal' => '',
        'maths_track' => '',
        'subjects' => [],
        'marks' => [],
        'interests' => [],
        'riasec_from_interests' => [],
        'career_quiz' => ['code' => '', 'scores' => [], 'top_careers' => [], 'completed_at' => null],
        'intended_careers' => [],
        'job_fit' => [],
        'updated_at' => null,
    ];
}

/** Applies $changes to $profile with per-key normalisation, stamping updated_at. */
function _profile_apply(array $profile, array $changes): array {
    $defaults = _profile_defaults();
    unset($changes['updated_at']);

    // Maths tracks and old aliases arrive mixed into 'subjects' from the
    // registration checklist: split them out before anything else.
    if (isset($changes['subjects'])) {
        [$subjects, $mathsFromSubjects] = _profile_split_subjects($changes['subjects']);
        $changes['subjects'] = $subjects;
        if ($mathsFromSubjects !== '' && !isset($changes['maths_track']) && $profile['maths_track'] === '') {
            $changes['maths_track'] = $mathsFromSubjects;
        }
    }

    foreach ($changes as $key => $value) {
        if (!array_key_exists($key, $defaults)) continue;
        switch ($key) {
            case 'name': case 'grade': case 'home_language': case 'fal':
                $profile[$key] = trim((string)$value);
                break;
            case 'maths_track':
                $profile[$key] = in_array($value, PROFILE_MATHS_TRACKS, true) ? $value : '';
                break;
            case 'subjects':
                $profile[$key] = $value; // already normalised above
                break;
            case 'marks':
                $profile[$key] = [];
                foreach ((array)$value as $subject => $mark) {
                    if (is_numeric($mark)) $profile[$key][(string)$subject] = max(0, min(100, (int)$mark));
                }
                break;
            case 'interests':
                $profile[$key] = _profile_split_interests($value);
                break;
            case 'riasec_from_interests':
                $profile[$key] = [];
                foreach ((array)$value as $dim => $n) {
                    if (in_array($dim, ['R', 'I', 'A', 'S', 'E', 'C'], true) && is_numeric($n)) $profile[$key][$dim] = (int)$n;
                }
                break;
            case 'career_quiz':
                $profile[$key] = array_replace($defaults['career_quiz'], array_intersect_key((array)$value, $defaults['career_quiz']));
                break;
            case 'intended_careers':
                $profile[$key] = array_values(array_unique(array_filter(array_map('strval', (array)$value), 'strlen')));
                break;
            case 'job_fit':
                foreach ((array)$value as $occupationId => $result) $profile[$key][(string)$occupationId] = $result;
                break;
        }
    }

    // riasec_from_interests always follows the interests it was computed from
    // (unless a caller sets it explicitly in the same update).
    if (array_key_exists('interests', $changes) && !array_key_exists('riasec_from_interests', $changes)) {
        $profile['riasec_from_interests'] = kp_interests_to_riasec($profile['interests']);
    }

    $profile['updated_at'] = time();
    return $profile;
}

/** @return array{0: string[], 1: string} Elective subject names, and the maths track found among them ('' if none). */
function _profile_split_subjects($raw): array {
    $subjects = [];
    $maths = [];
    foreach ((array)$raw as $s) {
        if (!is_string($s)) continue;
        $s = trim($s);
        $s = PROFILE_SUBJECT_ALIASES[$s] ?? $s;
        if ($s === '') continue;
        if (in_array($s, PROFILE_MATHS_TRACKS, true)) $maths[$s] = true;
        elseif (!in_array($s, $subjects, true)) $subjects[] = $s;
    }
    // "Mathematics" wins if a learner ticked more than one track.
    $found = array_keys($maths);
    $track = isset($maths['Mathematics']) ? 'Mathematics' : ($found[0] ?? '');
    return [$subjects, $track];
}

/** Free text ("technology, healthcare") or a list of chip values → a clean list. */
function _profile_split_interests($raw): array {
    $parts = is_array($raw) ? $raw : preg_split('/[,;\n]+/', (string)$raw);
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
    }
    return $out;
}

/**
 * First-run migration from the session keys the pages used before this file
 * existed: user{}, name, subjects, grade, interest, subject_tool,
 * career_quiz. Legacy keys are left in place (auth and not-yet-migrated pages
 * still read them); only a copy is taken.
 */
function _profile_migrate_legacy(): array {
    $u = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
    $changes = [
        'name' => $_SESSION['name'] ?? $u['name'] ?? '',
        'grade' => $_SESSION['grade'] ?? $u['grade'] ?? '',
        'subjects' => $_SESSION['subjects'] ?? $u['subjects'] ?? [],
        'interests' => $_SESSION['interest'] ?? $u['interest'] ?? '',
    ];
    $profile = _profile_apply(_profile_defaults(), $changes);

    // Subject Chooser: its answers are more specific than registration's, so
    // they win for grade / maths track, and its subjects are added to (not
    // substituted for) the registration ones.
    $st = $_SESSION['subject_tool'] ?? null;
    if (is_array($st)) {
        $profile = _profile_apply($profile, [
            'grade' => $st['grade'] ?? $profile['grade'],
            'home_language' => $st['hl'] ?? '',
            'fal' => $st['fal'] ?? '',
            'maths_track' => $st['maths'] ?? $profile['maths_track'],
            'subjects' => array_merge($profile['subjects'], (array)($st['selectedSubjects'] ?? [])),
            'marks' => $st['marks'] ?? [],
            'intended_careers' => $st['intended'] ?? [],
        ]);
    }

    $cq = $_SESSION['career_quiz'] ?? null;
    if (is_array($cq)) {
        $scores = [];
        foreach ((array)($cq['score']['scores'] ?? []) as $dim => $row) $scores[$dim] = (int)($row['score'] ?? 0);
        $profile = _profile_apply($profile, ['career_quiz' => [
            'code' => (string)($cq['score']['code'] ?? ''),
            'scores' => $scores,
            'top_careers' => array_column((array)($cq['matches'] ?? []), 'key'),
            'completed_at' => $cq['completedAt'] ?? null,
        ]]);
    }

    // A profile migrated from nothing is still blank, not "updated just now".
    if (!$u && !$st && !$cq && empty($_SESSION['name'])) $profile['updated_at'] = null;
    return $profile;
}
