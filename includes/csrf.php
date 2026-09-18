<?php
/**
 * Khetha Path — session CSRF token for state-changing forms.
 *
 * Same approach settings.php already uses (a random token in $_SESSION['csrf'],
 * checked with hash_equals), and the same session key, so a token from either
 * is valid for the other. Put csrf_token() in a hidden field named "csrf" and
 * guard the POST with csrf_check($_POST['csrf'] ?? '').
 */

function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_check($submitted): bool {
    return hash_equals(csrf_token(), (string)$submitted);
}
