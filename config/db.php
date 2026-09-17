<?php
// Khetha Path - XAMPP/MySQL connection
// For a default XAMPP installation, MySQL user is root and password is blank.
$host = '127.0.0.1';
$db   = 'khetha_path';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Khetha DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Khetha could not connect to the database. Please check that MySQL is running and config/db.php is correct.');
}
?>