<?php
/**
 * Khetha Path — account credentials: name, email and password.
 *
 * SECURITY RULES ENFORCED HERE (the page only renders what these return):
 *   1. Passwords are only ever stored as a password_hash() digest, never in plain
 *      text, never in a cookie, never written to a log or echoed back to the page.
 *   2. Changing the email or the password requires the CURRENT password
 *      (re-authentication), so a hijacked or borrowed session cannot lock the owner
 *      out of their own account. Changing the display name does not.
 *   3. Wrong-password attempts are throttled (ACCOUNT_MAX_ATTEMPTS in
 *      ACCOUNT_LOCKOUT_SECONDS), so the current password cannot be brute forced.
 *   4. A password change re-hashes with the current default algorithm and forces a
 *      new session id, so a stolen session id stops working.
 *   5. Errors are deliberately vague about which detail was wrong, and an email
 *      change never reveals whether another account already uses that address.
 *
 * STORAGE BOUNDARY (same rule as profile.php): credentials live in the users table
 * when a database is configured, and in $_SESSION['khetha_account'] in demo mode.
 * Only this file touches either.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/profile.php';

const ACCOUNT_SESSION_KEY = 'khetha_account';
const ACCOUNT_THROTTLE_KEY = 'khetha_account_throttle';
const ACCOUNT_MIN_PASSWORD = 8;
const ACCOUNT_MAX_ATTEMPTS = 5;
const ACCOUNT_LOCKOUT_SECONDS = 900; // 15 minutes

// ---------------------------------------------------------------------------
// Public API. Every one returns ['ok' => bool, 'error' => string].
// ---------------------------------------------------------------------------

/** @return array{name:string, email:string} What the account page displays. */
function account_get(int $userId, ?mysqli $db = null): array {
    $db = $db ?? kp_db();
    if ($db) {
        $row = _account_db_user($db, $userId);
        if ($row) return ['name' => trim($row['firstName'] . ' ' . $row['lastName']), 'email' => (string)$row['email']];
    }
    _account_session();
    $a = $_SESSION[ACCOUNT_SESSION_KEY] ?? [];
    return ['name' => (string)($a['name'] ?? ''), 'email' => (string)($a['email'] ?? '')];
}

/**
 * Records the credentials for a newly registered or signed-in learner.
 * $plainPassword is hashed immediately and never kept.
 */
function account_set_credentials(int $userId, string $name, string $email, string $plainPassword): void {
    _account_session();
    $_SESSION[ACCOUNT_SESSION_KEY] = [
        'name' => $name,
        'email' => _account_normalise_email($email),
        'hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
    ];
    unset($_SESSION[ACCOUNT_THROTTLE_KEY]);
}

/** The display name. Low risk, so no password is required. */
function account_change_name(int $userId, string $newName, ?mysqli $db = null): array {
    $newName = trim(preg_replace('/\s+/', ' ', $newName));
    if ($newName === '' || mb_strlen($newName) > 120) return _account_fail('Enter your name (up to 120 characters).');

    $db = $db ?? kp_db();
    if ($db) {
        [$first, $last] = _account_split_name($newName);
        if (!_account_db_write($db, 'UPDATE users SET firstName = ?, lastName = ? WHERE userID = ?', 'ssi', [$first, $last, $userId])) {
            return _account_fail('Could not save your name. Please try again.');
        }
    }
    _account_session();
    $_SESSION[ACCOUNT_SESSION_KEY]['name'] = $newName;
    if (isset($_SESSION['user'])) $_SESSION['user']['name'] = $newName;
    profile_update(['name' => $newName]);   // the CV and every greeting read this
    return ['ok' => true, 'error' => ''];
}

/** The email address. Requires the current password. */
function account_change_email(int $userId, string $newEmail, string $currentPassword, ?mysqli $db = null): array {
    $locked = _account_locked();
    if ($locked) return $locked;

    $newEmail = _account_normalise_email($newEmail);
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($newEmail) > 190) return _account_fail('Enter a valid email address.');

    $db = $db ?? kp_db();
    if (!account_verify_password($userId, $currentPassword, $db)) return _account_wrong_password();
    _account_clear_attempts();

    $current = account_get($userId, $db);
    if ($newEmail === _account_normalise_email($current['email'])) return _account_fail('That is already your email address.');

    if ($db) {
        // The unique index on users.email is what actually prevents a duplicate;
        // the message stays vague either way so this can't be used to discover
        // which addresses are registered.
        if (!_account_db_write($db, 'UPDATE users SET email = ? WHERE userID = ?', 'si', [$newEmail, $userId])) {
            return _account_fail('Could not change your email address. Please try a different one.');
        }
    }
    _account_session();
    $_SESSION[ACCOUNT_SESSION_KEY]['email'] = $newEmail;
    if (isset($_SESSION['user'])) $_SESSION['user']['email'] = $newEmail;
    return ['ok' => true, 'error' => ''];
}

/** The password. Requires the current one, plus a confirmed new one. */
function account_change_password(int $userId, string $currentPassword, string $newPassword, string $confirmPassword, ?mysqli $db = null): array {
    $locked = _account_locked();
    if ($locked) return $locked;

    $db = $db ?? kp_db();
    if (!account_verify_password($userId, $currentPassword, $db)) return _account_wrong_password();
    _account_clear_attempts();

    if (mb_strlen($newPassword) < ACCOUNT_MIN_PASSWORD) return _account_fail('Your new password needs at least ' . ACCOUNT_MIN_PASSWORD . ' characters.');
    if ($newPassword !== $confirmPassword) return _account_fail('The two new passwords do not match.');
    if (hash_equals($newPassword, $currentPassword)) return _account_fail('Your new password must be different from your current one.');

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    if ($db && !_account_db_write($db, 'UPDATE users SET passwordHash = ? WHERE userID = ?', 'si', [$hash, $userId])) {
        return _account_fail('Could not change your password. Please try again.');
    }
    _account_session();
    $_SESSION[ACCOUNT_SESSION_KEY]['hash'] = $hash;

    // A new session id means any other copy of this session (a shared phone, a
    // stolen cookie) is no longer signed in. It needs the response headers to still
    // be open, which they are on the normal POST path.
    if (!headers_sent()) session_regenerate_id(true);
    return ['ok' => true, 'error' => ''];
}

/** True when $plainPassword matches the stored hash. Timing-safe via password_verify(). */
function account_verify_password(int $userId, string $plainPassword, ?mysqli $db = null): bool {
    $db = $db ?? kp_db();
    if ($db) {
        $row = _account_db_user($db, $userId);
        if ($row && (string)$row['passwordHash'] !== '') return password_verify($plainPassword, (string)$row['passwordHash']);
    }
    _account_session();
    $hash = (string)($_SESSION[ACCOUNT_SESSION_KEY]['hash'] ?? '');
    if ($hash === '') return false;
    return password_verify($plainPassword, $hash);
}

/** True when this session has no stored password yet (an older session started before this feature). */
function account_has_password(int $userId, ?mysqli $db = null): bool {
    $db = $db ?? kp_db();
    if ($db) {
        $row = _account_db_user($db, $userId);
        if ($row) return (string)$row['passwordHash'] !== '';
    }
    _account_session();
    return (string)($_SESSION[ACCOUNT_SESSION_KEY]['hash'] ?? '') !== '';
}

// ---------------------------------------------------------------------------
// Throttling
// ---------------------------------------------------------------------------

/** @return array|null A failure result while locked out, or null when attempts are allowed. */
function _account_locked(): ?array {
    _account_session();
    $t = $_SESSION[ACCOUNT_THROTTLE_KEY] ?? null;
    if (is_array($t) && ($t['until'] ?? 0) > time()) {
        $minutes = (int)ceil((($t['until'] - time()) / 60));
        return _account_fail('Too many incorrect passwords. Try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.');
    }
    return null;
}

function _account_wrong_password(): array {
    _account_session();
    $t = $_SESSION[ACCOUNT_THROTTLE_KEY] ?? ['fails' => 0, 'until' => 0];
    $t['fails'] = (int)($t['fails'] ?? 0) + 1;
    if ($t['fails'] >= ACCOUNT_MAX_ATTEMPTS) {
        $t['until'] = time() + ACCOUNT_LOCKOUT_SECONDS;
        $t['fails'] = 0;
        $_SESSION[ACCOUNT_THROTTLE_KEY] = $t;
        return _account_fail('Too many incorrect passwords. Try again in ' . (int)(ACCOUNT_LOCKOUT_SECONDS / 60) . ' minutes.');
    }
    $_SESSION[ACCOUNT_THROTTLE_KEY] = $t;
    return _account_fail('That password is not correct.');
}

function _account_clear_attempts(): void {
    _account_session();
    unset($_SESSION[ACCOUNT_THROTTLE_KEY]);
}

// ---------------------------------------------------------------------------
// Internals
// ---------------------------------------------------------------------------

function _account_fail(string $message): array {
    return ['ok' => false, 'error' => $message];
}

function _account_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

function _account_normalise_email(string $email): string {
    return mb_strtolower(trim($email));
}

/** @return array{0:string,1:string} firstName, lastName for the database columns. */
function _account_split_name(string $name): array {
    $parts = preg_split('/\s+/', trim($name));
    $first = array_shift($parts) ?: '';
    return [mb_substr($first, 0, 100), mb_substr(implode(' ', $parts), 0, 100)];
}

/** @return array|null The one row this feature needs. Explicit columns: no SELECT *. */
function _account_db_user(mysqli $db, int $userId): ?array {
    $stmt = @$db->prepare('SELECT firstName, lastName, email, passwordHash FROM users WHERE userID = ?');
    if (!$stmt) return null;
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function _account_db_write(mysqli $db, string $sql, string $types, array $params): bool {
    $stmt = @$db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
