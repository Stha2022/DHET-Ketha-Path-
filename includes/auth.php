<?php
require_once __DIR__ . '/../database/db-connection.php';
require_once __DIR__ . '/profile.php';

function kp_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function kp_require_auth(): void {
    kp_session_start();
    if (empty($_SESSION['user']['id'])) {
        header('Location: login.php');
        exit;
    }
    // Refresh the display identity from MySQL when available.
    $db = kp_db();
    if ($db) {
        $id = (int)$_SESSION['user']['id'];
        $stmt = @$db->prepare('SELECT firstName,lastName,email,grade FROM users WHERE userID=? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute() && ($res = $stmt->get_result()) && ($u = $res->fetch_assoc())) {
                $_SESSION['user']['name'] = trim($u['firstName'].' '.$u['lastName']);
                $_SESSION['user']['email'] = $u['email'];
                $_SESSION['user']['grade'] = kp_grade_from_db((string)$u['grade']);
            }
            $stmt->close();
        }
    }
}

function kp_user_id(): int { return (int)($_SESSION['user']['id'] ?? 0); }
