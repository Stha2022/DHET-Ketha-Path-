<?php
require_once __DIR__ . '/config/db.php';
try {
    $db = $pdo->query('SELECT DATABASE() AS db_name')->fetch();
    $users = $pdo->query('SELECT COUNT(*) AS total FROM users')->fetch();
    echo '<h2>Khetha database connection: OK</h2>';
    echo '<p>Database: ' . htmlspecialchars($db['db_name']) . '</p>';
    echo '<p>Users currently stored: ' . htmlspecialchars($users['total']) . '</p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>Database test failed</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>