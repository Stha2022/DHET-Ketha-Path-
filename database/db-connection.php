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
$kpEnvFile = [];
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        $kpEnvFile[$key] = $value;
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}

// .env values first for the generic names: on Windows getenv('username') is the
// Windows login (env names ignore case there), which TiDB rejects.
$env = fn(string $key) => $kpEnvFile[$key] ?? getenv($key);

$isLocal = in_array(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), ['localhost','127.0.0.1','[::1]'], true)
    || in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1','::1'], true);

$username = getenv('KHETHA_DB_USER') ?: ($env('username') ?: ($isLocal ? 'root' : null));
$password = getenv('KHETHA_DB_PASSWORD') !== false ? getenv('KHETHA_DB_PASSWORD') : ($env('password') !== false ? $env('password') : ($isLocal ? '' : null));
$host     = getenv('KHETHA_DB_HOST') ?: ($env('host') ?: ($isLocal ? '127.0.0.1' : null));
$port     = getenv('KHETHA_DB_PORT') ?: ($env('port') ?: '3306');
$database = getenv('KHETHA_DB_NAME') ?: ($env('database') ?: ($isLocal ? 'khetha_path' : null));
// TLS is on for any remote database (TiDB Cloud refuses plain connections) and
// off for a local MySQL. KHETHA_DB_SSL=1 or 0 forces it either way.
$sslSetting = getenv('KHETHA_DB_SSL');
$isLocalHost = in_array(strtolower((string)$host), ['localhost', '127.0.0.1', '::1'], true);
$useSSL   = $sslSetting === '1' || ($sslSetting !== '0' && !$isLocalHost);

$conn = mysqli_init();
if (!$conn || $username === null || $password === null || $host === null || $database === null) {
    error_log('Khetha DB configuration is incomplete.');
    return;
}

if ($useSSL) {
    // The system bundle on Linux (Render's Docker image); the bundled ISRG root otherwise.
    $caPath = is_file('/etc/ssl/certs/ca-certificates.crt') ? '/etc/ssl/certs/ca-certificates.crt' : __DIR__ . '/../isrgrootx1.pem';
    $conn->ssl_set(null, null, $caPath, null, null);
}

$flags = $useSSL ? MYSQLI_CLIENT_SSL : 0;
// Silenced: a failed connect must never print into the page, or every later
// header() and session_start() fails with "headers already sent".
if (!@$conn->real_connect($host, $username, $password, $database, (int)$port, null, $flags) || $conn->connect_errno) {
    error_log('Khetha DB connection failed: ' . ($conn->connect_error ?: mysqli_connect_error()));
    return;
}
$conn->set_charset('utf8mb4');
