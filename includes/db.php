<?php
/**
 * Khetha Path — the one place a database connection is opened.
 *
 * Demo mode is the default: kp_db() returns null unless the environment variable
 * KHETHA_USE_DB=1 is set, so nothing in the app touches the database by accident.
 * Callers must treat null as "no database" and fall back to session storage.
 */

function kp_db(): ?mysqli {
    static $cached = false;
    if ($cached !== false) return $cached;
    $cached = null;
    if (getenv('KHETHA_USE_DB') !== '1') return null;
    require __DIR__ . '/../database/db-connection.php'; // defines $conn
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) $cached = $conn;
    return $cached;
}
