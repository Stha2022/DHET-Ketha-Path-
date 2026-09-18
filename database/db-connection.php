<?php
    mysqli_report(MYSQLI_REPORT_OFF);

    // Locally, credentials come from a gitignored .env file.
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }

    $isLocal = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1';

    if ($isLocal) {
        $username = getenv('username') ?: 'root';
        $password = getenv('password') ?: '';
        $host     = getenv('host')     ?: 'localhost';
        $port     = getenv('port')     ?: '3306';
        $database = getenv('database') ?: 'likhwezi';
    } else {
        // Render no fallback defaults.
        $username = getenv('username');
        $password = getenv('password');
        $host     = getenv('host');
        $port     = getenv('port');
        $database = getenv('database');
    }

    $CA_Path = PHP_OS_FAMILY === 'Windows' ? __DIR__ . '/../isrgrootx1.pem' : '/etc/ssl/certs/ca-certificates.crt';

    $conn = mysqli_init();
    $conn->ssl_set(null, null, $CA_Path, null, null);
    $conn->real_connect($host, $username, $password, $database, $port, null, MYSQLI_CLIENT_SSL);

    if ($conn->connect_error) {
        error_log("DB connection failed: " . $conn->connect_error);
    }
?>