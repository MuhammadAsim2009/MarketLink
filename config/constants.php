<?php
/**
 * MarketLink - System Constants
 * Plain PHP / Procedural Configuration
 */

if (!defined('MARKETLINK_INIT')) {
    define('MARKETLINK_INIT', true);
}

// App Details
define('APP_NAME', 'MarketLink');
define('APP_TAGLINE', 'Farm Fresh Just a Click Away');
define('APP_VERSION', '1.0.0');

// Base URL calculation for clean path generation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$scriptDir = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';

// Resolve base URL pointing to /marketlink root
$basePath = '/marketlink';
if (preg_match('#^(/[^/]+)#', $scriptDir, $matches)) {
    // If root directory or subdirectory matches
    if (strpos($scriptDir, '/marketlink') !== false) {
        $basePath = '/marketlink';
    }
}
define('BASE_URL', rtrim($protocol . $host . $basePath, '/') . '/');
define('ASSETS_URL', BASE_URL . 'assets/');

// Formatting & Currency
define('CURRENCY_SYMBOL', 'PKR ');
define('DATE_FORMAT', 'M d, Y');
define('TIME_FORMAT', 'h:i A');
define('DATETIME_FORMAT', 'M d, Y h:i A');

// User Roles
define('ROLE_VISITOR', 'visitor');
define('ROLE_CUSTOMER', 'customer');
define('ROLE_FARMER', 'farmer');
define('ROLE_ADMIN', 'admin');

// User Statuses
define('STATUS_ACTIVE', 'active');
define('STATUS_SUSPENDED', 'suspended');
define('STATUS_PENDING', 'pending');

// Order Statuses
define('ORDER_STATUS_PLACED', 'placed');
define('ORDER_STATUS_ACCEPTED', 'accepted');
define('ORDER_STATUS_DECLINED', 'declined');
define('ORDER_STATUS_READY', 'ready');
define('ORDER_STATUS_COMPLETED', 'completed');
define('ORDER_STATUS_CANCELLED', 'cancelled');
