<?php
/**
 * MarketLink - Database Connection
 * PDO connection with prepared statement enforcement
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
    die('<div style="font-family: \'Poppins\', sans-serif; text-align:center; padding:60px 20px; color:#2B2B28; background:#FBF9F4; min-height:100vh;">
        <h2 style="color:#C0392B;">Service Temporarily Unavailable</h2>
        <p style="color:#6E6A62; max-width:500px; margin:0 auto 20px;">Could not connect to the database. Please ensure your MySQL server is running in XAMPP and the <code>marketlink_db</code> schema has been imported.</p>
        <p><small style="color:#999;">Error logged server-side for administrator review.</small></p>
    </div>');
}
