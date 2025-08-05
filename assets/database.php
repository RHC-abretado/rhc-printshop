<?php
// database.php

$dbHost = 'localhost';
$dbName = 'riohondoprint_printing';
$dbUser = 'riohondoprint_printing';
$dbPass = 'USarmy2016!?';

/**
 * Convert a MySQL timestamp string (assumed UTC) to Los Angeles time.
 * @param string $mysqlTs  Timestamp from DB (YYYY-MM-DD HH:MM:SS)
 * @param string $fmt      PHP date() format to return
 * @return string          Formatted Pacific-time string
 */
if (!function_exists('toLA')) {
    function toLA(string $mysqlTs, string $fmt = 'm-d-Y H:i:s'): string
    {
        $dt = new DateTime($mysqlTs, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
        return $dt->format($fmt);
    }
}

date_default_timezone_set('America/Los_Angeles');   // for PHP’s own date()

// ----------------------------------------------------
// existing connection code …
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database Connection Failed: ' . $e->getMessage());
}