<?php
// Stores the learner's language choice, then sends them back where they were.
require_once __DIR__ . '/assets/lang.php';

$code = $_GET['lang'] ?? '';
if (isset(KP_LANGS[$code])) {
    setcookie('khetha_lang', $code, [
        'expires'  => time() + 60 * 60 * 24 * 365,
        'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/',
        'samesite' => 'Lax',
    ]);
}

// Only ever redirect to a plain page in this folder, never off-site.
$next = $_GET['next'] ?? '';
if (!preg_match('/^[a-z0-9-]+\.php(\?[\w=&.%-]*)?$/i', $next) || $next === 'set-lang.php') {
    $next = 'index.php';
}
header('Location: ' . $next);
exit;
