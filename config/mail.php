<?php
/**
 * MarketLink - Email & SMTP Configuration
 * 
 * Configure your SMTP mail server settings here for sending 
 * password reset URLs, transactional emails, and notifications.
 */

if (!defined('MARKETLINK_INIT')) {
    require_once __DIR__ . '/constants.php';
}

// SMTP Server Configuration
define('SMTP_ENABLED', true);                       // Set to true to send emails via SMTP
define('SMTP_HOST', 'smtp.gmail.com');              // SMTP server host (e.g., smtp.gmail.com, mail.yourdomain.com, smtp.mailtrap.io)
define('SMTP_PORT', 587);                           // SMTP port: 587 (TLS/STARTTLS), 465 (SSL), or 25
define('SMTP_ENCRYPTION', 'tls');                   // Encryption type: 'tls', 'ssl', or 'none'
define('SMTP_AUTH', true);                          // Whether SMTP authentication is required
define('SMTP_USERNAME', 'sasim4589@gmail.com');        // SMTP username / Gmail address
define('SMTP_PASSWORD', 'qovhhfqtncfojfkq');        // Gmail 16-character App Password
define('SMTP_TIMEOUT', 15);                         // Connection timeout in seconds

// Sender identity
define('MAIL_FROM_ADDRESS', 'sasim4589@gmail.com');
define('MAIL_FROM_NAME', 'MarketLink');
define('MAIL_REPLY_TO', 'sasim4589@gmail.com');

// Debug mode: logs detailed SMTP communication in error_log
define('SMTP_DEBUG', false);
