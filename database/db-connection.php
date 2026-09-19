<?php
/** Khetha Path MySQL connection. Local XAMPP uses plain localhost; production can enable TLS. */
mysqli_report(MYSQLI_REPORT_OFF);

function kp_db(): ?mysqli {
    global $conn;
    return isset($conn) && $conn instanceof mysqli && !$conn->connect_errno ? $conn : null;
}

function kp_db_required(): mysqli {
    $db = kp_db();
    if (!$db) throw new RuntimeException('Khetha could not connect to MySQL. Start MySQL in XAMPP and import database/khetha_path.sql.');
    return $db;
}

$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}

$isLocal = in_array(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), ['localhost','127.0.0.1','[::1]'], true)
    || in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1','::1'], true);

$username = getenv('KHETHA_DB_USER') ?: ($isLocal ? (getenv('username') ?: 'root') : (getenv('username') ?: null));
$password = getenv('KHETHA_DB_PASSWORD') !== false ? getenv('KHETHA_DB_PASSWORD') : ($isLocal ? (getenv('password') !== false ? getenv('password') : '') : (getenv('password') !== false ? getenv('password') : null));
$host     = getenv('KHETHA_DB_HOST') ?: ($isLocal ? (getenv('host') ?: '127.0.0.1') : (getenv('host') ?: null));
$port     = getenv('KHETHA_DB_PORT') ?: ($isLocal ? (getenv('port') ?: '3306') : (getenv('port') ?: '3306'));
$database = getenv('KHETHA_DB_NAME') ?: ($isLocal ? 'khetha_path' : (getenv('database') ?: null));
$useSSL   = getenv('KHETHA_DB_SSL') === '1' && !$isLocal;

$conn = mysqli_init();
if (!$conn || $username === null || $password === null || $host === null || $database === null) {
    error_log('Khetha DB configuration is incomplete.');
    return;
}

if ($useSSL) {
    $caPath = PHP_OS_FAMILY === 'Windows' ? __DIR__ . '/../isrgrootx1.pem' : '/etc/ssl/certs/ca-certificates.crt';
    $conn->ssl_set(null, null, $caPath, null, null);
}

$flags = $useSSL ? MYSQLI_CLIENT_SSL : 0;
$conn->real_connect($host, $username, $password, $database, (int)$port, null, $flags);
if ($conn->connect_error) error_log('Khetha DB connection failed: ' . $conn->connect_error);
else $conn->set_charset('utf8mb4');
