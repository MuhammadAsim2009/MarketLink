<?php
/**
 * MarketLink - Email & SMTP Configuration Example
 * Copy this file to config/mail.php and configure your SMTP credentials.
 */

if (!defined('MARKETLINK_INIT')) {
    require_once __DIR__ . '/constants.php';
}

define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls'); // 'tls', 'ssl', or 'none'
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');
define('SMTP_TIMEOUT', 15);

define('MAIL_FROM_ADDRESS', 'noreply@marketlink.com');
define('MAIL_FROM_NAME', 'MarketLink Security');
define('MAIL_REPLY_TO', 'support@marketlink.com');
define('SMTP_DEBUG', false);
