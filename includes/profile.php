<?php

require_once __DIR__ . '/../data/interests.php';

const PROFILE_SESSION_KEY = 'khetha_profile';
const PROFILE_MATHS_TRACKS = ['Mathematics', 'Mathematical Literacy', 'Technical Mathematics'];
// Maps legacy subject names to the canonical names.
const PROFILE_SUBJECT_ALIASES = ['IT' => 'Information Technology'];

function profile_get(): array {
    $stored = _profile_load();
    if ($stored === null) {
        $stored = _profile_migrate_legacy();
        _profile_save($stored);
    } elseif (empty($stored['riasec_from_interests']) && !empty($stored['interests'])) {
        $stored['riasec_from_interests'] = kp_interests_to_riasec($stored['interests']);
        _profile_save($stored);
    }
    return array_replace(_profile_defaults(), $stored);
}

function profile_update(array $changes): void {
    _profile_save(_profile_apply(profile_get(), $changes));
}

function profile_reset(): void {
    _profile_save(_profile_defaults());
}

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

// users.grade is an ENUM of lowercase values; the app uses the labels.
const KP_DB_GRADES = ['Grade 9' => 'grade 9', 'Grade 10' => 'grade 10', 'Grade 11' => 'grade 11', 'Grade 12' => 'grade 12', 'Post-school' => 'out of school'];

/** The users.grade value for an app grade label, or null if it has none. */
function kp_grade_to_db(string $grade): ?string {
    return KP_DB_GRADES[$grade] ?? (in_array(strtolower($grade), KP_DB_GRADES, true) ? strtolower($grade) : null);
}

/** The app grade label for a users.grade value. */
function kp_grade_from_db(string $grade): string {
    return array_search(strtolower($grade), KP_DB_GRADES, true) ?: $grade;
}

function _profile_load(): ?array {
    _profile_session();
    $id = (int)($_SESSION['user']['id'] ?? 0);
    if ($id > 0) {
        require_once __DIR__ . '/../database/db-connection.php';
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
            $stmt = @$db->prepare('SELECT firstName,lastName,grade,interests FROM users WHERE userID=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i',$id);
                if ($stmt->execute() && ($res=$stmt->get_result()) && ($u=$res->fetch_assoc())) {
                    $ints=json_decode((string)($u['interests']??''),true);
                    return ['name'=>trim($u['firstName'].' '.$u['lastName']), 'grade'=>kp_grade_from_db((string)($u['grade']??'')), 'interests'=>is_array($ints)?$ints:[]];
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

    require_once __DIR__ . '/../database/db-connection.php';
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

    $grade = kp_grade_to_db((string)($profile['grade'] ?? ''));
    $interests = json_encode(array_values((array)($profile['interests'] ?? [])), JSON_UNESCAPED_UNICODE);
    // Grade only when it maps onto the ENUM; an unknown value would fail the whole update.
    $stmt = $grade !== null
        ? @$db->prepare('UPDATE users SET grade=?, interests=? WHERE userID=?')
        : @$db->prepare('UPDATE users SET interests=? WHERE userID=?');
    if ($stmt) {
        $grade !== null ? $stmt->bind_param('ssi', $grade, $interests, $id) : $stmt->bind_param('si', $interests, $id);
        $stmt->execute();
        $stmt->close();
    }
}

function _profile_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

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

function _profile_apply(array $profile, array $changes): array {
    $defaults = _profile_defaults();
    unset($changes['updated_at']);

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
                $profile[$key] = $value;
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

    if (array_key_exists('interests', $changes) && !array_key_exists('riasec_from_interests', $changes)) {
        $profile['riasec_from_interests'] = kp_interests_to_riasec($profile['interests']);
    }

    $profile['updated_at'] = time();
    return $profile;
}

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
    $found = array_keys($maths);
    $track = isset($maths['Mathematics']) ? 'Mathematics' : ($found[0] ?? '');
    return [$subjects, $track];
}

function _profile_split_interests($raw): array {
    $parts = is_array($raw) ? $raw : preg_split('/[,;\n]+/', (string)$raw);
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
    }
    return $out;
}

function _profile_migrate_legacy(): array {
    $u = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
    $changes = [
        'name' => $_SESSION['name'] ?? $u['name'] ?? '',
        'grade' => $_SESSION['grade'] ?? $u['grade'] ?? '',
        'subjects' => $_SESSION['subjects'] ?? $u['subjects'] ?? [],
        'interests' => $_SESSION['interest'] ?? $u['interest'] ?? '',
    ];
    $profile = _profile_apply(_profile_defaults(), $changes);

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

    if (!$u && !$st && !$cq && empty($_SESSION['name'])) $profile['updated_at'] = null;
    return $profile;
}
