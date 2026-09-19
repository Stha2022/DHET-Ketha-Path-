<?php
/** One controlled database entry point. Local development uses khetha_path by default. */
function kp_db(): ?mysqli {
    static $cached = false;
    if ($cached !== false) return $cached;
    $cached = null;
    require_once __DIR__ . '/../database/db-connection.php';
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) $cached = $conn;
    return $cached;
}

function kp_db_required(): mysqli {
    $db = kp_db();
    if (!$db) throw new RuntimeException('Khetha could not connect to MySQL. Start MySQL in XAMPP and import database/khetha_path.sql.');
    return $db;
}
