<?php
/**
 * Khetha Path — learner accounts and results in the database (TiDB: khetha_path).
 *
 * Used only when kp_db() returns a connection (KHETHA_USE_DB=1); otherwise every caller keeps
 * the demo-mode behaviour. All queries are prepared statements with explicit column lists.
 *
 *   learner_db_register()   INSERT a users row; null when the email is already registered
 *   learner_db_login()      the users row for an email + password, or null
 *   learner_db_profile()    a learner's saved profile, rebuilt from users + assessmentResults
 *   learner_db_persist()    called by profile_update(): writes what changed back to the database
 *
 * WHAT GOES WHERE (the same contract includes/cv.php and admin/insights.php read):
 *   users               firstName, lastName, grade, interests (JSON chip labels)
 *   assessmentResults   one appended row per completed tool — history is never overwritten:
 *     subject_chooser   payload {grade, home_language, fal, maths_track, subjects, marks, intended_careers}
 *     career_choice     derived {code, scores, top_careers}
 *     job_fit           derived {occupation_id, overall, flags}   (one row per occupation)
 */

require_once __DIR__ . '/../database/db-connection.php';

// The app says "Grade 10" / "Post-school"; users.grade is a lower-case enum.
const LEARNER_DB_GRADES = ['Grade 9' => 'grade 9', 'Grade 10' => 'grade 10', 'Grade 11' => 'grade 11', 'Grade 12' => 'grade 12', 'Post-school' => 'out of school'];

/** The signed-in learner's userID when they have a database account, else 0. */
function learner_db_user_id(): int {
    return !empty($_SESSION['user']['db']) ? (int)($_SESSION['user']['id'] ?? 0) : 0;
}

/**
 * @param string[] $interests Chip labels.
 * @return int|null The new userID, or null if the email is taken or the insert failed.
 */
function learner_db_register(mysqli $db, string $name, string $email, string $plainPassword, string $grade, array $interests): ?int {
    [$first, $last] = _learner_db_split_name($name);
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $dbGrade = LEARNER_DB_GRADES[$grade] ?? 'grade 10';
    $json = json_encode(array_values($interests), JSON_UNESCAPED_UNICODE);
    $stmt = @$db->prepare('INSERT INTO users (firstName, lastName, email, grade, interests, passwordHash, consentAt) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) return null;
    $stmt->bind_param('ssssss', $first, $last, $email, $dbGrade, $json, $hash);
    $ok = @$stmt->execute(); // fails on the unique email index when the address is taken
    $id = $ok ? (int)$stmt->insert_id : null;
    $stmt->close();
    return $id ?: null;
}

/** @return array{userID:int, name:string, email:string}|null */
function learner_db_login(mysqli $db, string $email, string $plainPassword): ?array {
    $row = _learner_db_row($db, 'SELECT userID, firstName, lastName, email, passwordHash FROM users WHERE email = ?', 's', [$email]);
    // Verify against a dummy hash when there's no such user, so response time doesn't reveal it.
    $hash = $row['passwordHash'] ?? password_hash(random_bytes(12), PASSWORD_DEFAULT);
    if (!password_verify($plainPassword, (string)$hash) || !$row) return null;
    return ['userID' => (int)$row['userID'], 'name' => trim($row['firstName'] . ' ' . $row['lastName']), 'email' => (string)$row['email']];
}

/** @return array Changes for profile_update()-style keys, from the database. */
function learner_db_profile(mysqli $db, int $userId): array {
    $u = _learner_db_row($db, 'SELECT firstName, lastName, grade, interests FROM users WHERE userID = ?', 'i', [$userId]) ?? [];
    $profile = [
        'name' => trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '')),
        'grade' => (string)(array_search($u['grade'] ?? '', LEARNER_DB_GRADES, true) ?: ''),
        'interests' => (array)(json_decode((string)($u['interests'] ?? ''), true) ?: []),
    ];

    $stmt = @$db->prepare('SELECT source, payload, derived, createdAt FROM assessmentResults WHERE userID = ? ORDER BY assessmentID DESC LIMIT 300');
    if (!$stmt) return $profile;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $seen = [];
    while ($res && ($r = $res->fetch_assoc())) {
        $payload = (array)(json_decode((string)$r['payload'], true) ?: []);
        $derived = (array)(json_decode((string)$r['derived'], true) ?: []);
        $when = strtotime((string)$r['createdAt']) ?: null;
        if ($r['source'] === 'subject_chooser' && !isset($seen['sc'])) {
            $seen['sc'] = true;
            foreach (['home_language', 'fal', 'maths_track', 'subjects', 'marks', 'intended_careers'] as $k) {
                if (isset($payload[$k])) $profile[$k] = $payload[$k];
            }
        } elseif ($r['source'] === 'career_choice' && !isset($seen['cc'])) {
            $seen['cc'] = true;
            $profile['career_quiz'] = ['code' => (string)($derived['code'] ?? ''), 'scores' => (array)($derived['scores'] ?? []),
                                       'top_careers' => (array)($derived['top_careers'] ?? []), 'completed_at' => $when];
        } elseif ($r['source'] === 'job_fit') {
            $id = (string)($derived['occupation_id'] ?? '');
            if ($id === '' || isset($profile['job_fit'][$id])) continue;
            $profile['job_fit'][$id] = ['overall' => (int)($derived['overall'] ?? 0), 'flags' => (array)($derived['flags'] ?? []),
                                        'completed_at' => $when, 'mode' => (string)($derived['mode'] ?? '')];
        }
    }
    $stmt->close();
    return $profile;
}

/**
 * Writes what a profile_update() changed back to the database. $changes is what the caller
 * passed; $profile is the normalised result. Failures are logged, never shown: the session
 * copy is still saved, so the learner can carry on.
 */
function learner_db_persist(array $changes, array $profile): void {
    $userId = learner_db_user_id();
    $db = $userId ? kp_db() : null;
    if (!$db) return;

    // users columns
    $sets = []; $types = ''; $params = [];
    if (array_key_exists('name', $changes) && $profile['name'] !== '') {
        [$first, $last] = _learner_db_split_name($profile['name']);
        array_push($sets, 'firstName = ?', 'lastName = ?'); $types .= 'ss'; array_push($params, $first, $last);
    }
    if (array_key_exists('grade', $changes) && isset(LEARNER_DB_GRADES[$profile['grade']])) {
        $sets[] = 'grade = ?'; $types .= 's'; $params[] = LEARNER_DB_GRADES[$profile['grade']];
    }
    if (array_key_exists('interests', $changes)) {
        $sets[] = 'interests = ?'; $types .= 's'; $params[] = json_encode(array_values($profile['interests']), JSON_UNESCAPED_UNICODE);
    }
    if ($sets) _learner_db_write($db, 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE userID = ?', $types . 'i', [...$params, $userId]);

    // Completed tools. Only Subject Chooser sends intended_careers, so that marks its completion.
    if (array_key_exists('intended_careers', $changes)) {
        _learner_db_result($db, $userId, 'subject_chooser', [
            'grade' => $profile['grade'], 'home_language' => $profile['home_language'], 'fal' => $profile['fal'],
            'maths_track' => $profile['maths_track'], 'subjects' => $profile['subjects'], 'marks' => (object)$profile['marks'],
            'intended_careers' => $profile['intended_careers'],
        ], null);
    }
    if (array_key_exists('career_quiz', $changes) && $profile['career_quiz']['code'] !== '') {
        $q = $profile['career_quiz'];
        _learner_db_result($db, $userId, 'career_choice', null, ['code' => $q['code'], 'scores' => (object)$q['scores'], 'top_careers' => $q['top_careers']]);
    }
    if (array_key_exists('job_fit', $changes)) {
        foreach ((array)$changes['job_fit'] as $occupationId => $fit) {
            _learner_db_result($db, $userId, 'job_fit', null, [
                'occupation_id' => (string)$occupationId, 'overall' => (int)($fit['overall'] ?? 0),
                'flags' => array_values((array)($fit['flags'] ?? [])), 'mode' => (string)($fit['mode'] ?? ''),
            ]);
        }
    }
}

// ---------------------------------------------------------------------------
// Internals
// ---------------------------------------------------------------------------

function _learner_db_result(mysqli $db, int $userId, string $source, ?array $payload, ?array $derived): void {
    $p = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
    $d = $derived === null ? null : json_encode($derived, JSON_UNESCAPED_UNICODE);
    _learner_db_write($db, 'INSERT INTO assessmentResults (userID, source, payload, derived) VALUES (?, ?, ?, ?)', 'isss', [$userId, $source, $p, $d]);
}

/** @return array{0:string,1:string} firstName, lastName (users.lastName is NOT NULL, so '' when there's one name). */
function _learner_db_split_name(string $name): array {
    $parts = preg_split('/\s+/', trim($name));
    $first = array_shift($parts) ?: '';
    return [mb_substr($first, 0, 100), mb_substr(implode(' ', $parts), 0, 100)];
}

function _learner_db_row(mysqli $db, string $sql, string $types, array $params): ?array {
    $stmt = @$db->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param($types, ...$params);
    $row = $stmt->execute() ? $stmt->get_result()->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function _learner_db_write(mysqli $db, string $sql, string $types, array $params): void {
    $stmt = @$db->prepare($sql);
    if (!$stmt) { error_log('learner-db prepare failed: ' . $db->error); return; }
    $stmt->bind_param($types, ...$params);
    if (!@$stmt->execute()) error_log('learner-db write failed: ' . $stmt->error);
    $stmt->close();
}
