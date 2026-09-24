<?php
/**
 * MarketLink - Database Connection Configuration (Example)
 * Copy this file to config/db.php and adjust your database credentials.
 */

$db_host = 'localhost';
$db_port = '3306';
$db_name = 'marketlink_db';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset={$db_charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log("MarketLink Database Connection Error: " . $e->getMessage());
    die('<div style="font-family:sans-serif; text-align:center; padding:50px;">
        <h2>Service Temporarily Unavailable</h2>
        <p>We are experiencing a temporary database connection issue. Please make sure MySQL is running and database schema is imported.</p>
    </div>');
}
