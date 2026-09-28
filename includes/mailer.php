<?php
/**
 * MarketLink - SMTP Mailer Helper
 * 
 * Standalone, lightweight SMTP client supporting STARTTLS (Port 587),
 * SSL/TLS (Port 465), and standard SMTP Authentication without third-party dependencies.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/mail.php';

/**
 * Send an email via SMTP Socket connection
 * 
 * @param string $to_email Destination email address
 * @param string $to_name  Recipient display name
 * @param string $subject  Email subject line
 * @param string $html_body HTML formatted body
 * @param string $alt_body  Optional plain text body
 * @return array ['success' => bool, 'message' => string, 'debug' => array]
 */
function send_smtp_email($to_email, $to_name, $subject, $html_body, $alt_body = '') {
    $debug_logs = [];
    $log = function($msg) use (&$debug_logs) {
        $debug_logs[] = $msg;
        if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
            error_log("[MarketLink SMTP] " . $msg);
        }
    };

    if (!defined('SMTP_ENABLED') || !SMTP_ENABLED) {
        return [
            'success' => false,
            'message' => 'SMTP mail sending is disabled in configuration.',
            'debug' => $debug_logs
        ];
    }

    $host       = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
    $port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $encryption = defined('SMTP_ENCRYPTION') ? strtolower(SMTP_ENCRYPTION) : 'tls';
    $auth       = defined('SMTP_AUTH') ? SMTP_AUTH : true;
    $username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $from_email = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@marketlink.com';
    $from_name  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'MarketLink';
    $reply_to   = defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : $from_email;
    $timeout    = defined('SMTP_TIMEOUT') ? (int)SMTP_TIMEOUT : 15;

    // Validate recipient
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid recipient email address.', 'debug' => $debug_logs];
    }

    // Determine connection prefix
    $socket_host = $host;
    if ($encryption === 'ssl' || $port === 465) {
        $socket_host = 'ssl://' . $host;
    } else {
        $socket_host = 'tcp://' . $host;
    }

    $errno = 0;
    $errstr = '';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ]);

    $log("Connecting to {$socket_host}:{$port}...");
    $socket = @stream_socket_client("{$socket_host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        $msg = "Connection to SMTP server failed ({$errno}): {$errstr}";
        $log($msg);
        return ['success' => false, 'message' => $msg, 'debug' => $debug_logs];
    }

    stream_set_timeout($socket, $timeout);

    // Read initial banner (220)
    $response = smtp_read_response($socket);
    $log("<< " . trim($response));
    if (!smtp_check_code($response, 220)) {
        fclose($socket);
        return ['success' => false, 'message' => 'Unexpected server greeting: ' . trim($response), 'debug' => $debug_logs];
    }

    // EHLO handshake
    $client_domain = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
    smtp_write_command($socket, "EHLO {$client_domain}");
    $log(">> EHLO {$client_domain}");
    $response = smtp_read_response($socket);
    $log("<< " . trim($response));

    // Upgrade to STARTTLS if configured
    if ($encryption === 'tls' && $port !== 465) {
        smtp_write_command($socket, "STARTTLS");
        $log(">> STARTTLS");
        $response = smtp_read_response($socket);
        $log("<< " . trim($response));

        if (!smtp_check_code($response, 220)) {
            fclose($socket);
            return ['success' => false, 'message' => 'STARTTLS failed: ' . trim($response), 'debug' => $debug_logs];
        }

        // Enable crypto on socket
        $crypto_ok = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        if (!$crypto_ok) {
            fclose($socket);
            return ['success' => false, 'message' => 'TLS handshake negotiation failed.', 'debug' => $debug_logs];
        }

        // Re-issue EHLO after TLS negotiation
        smtp_write_command($socket, "EHLO {$client_domain}");
        $log(">> EHLO {$client_domain} (post-TLS)");
        $response = smtp_read_response($socket);
        $log("<< " . trim($response));
    }

    // SMTP Authentication
    if ($auth && !empty($username)) {
        smtp_write_command($socket, "AUTH LOGIN");
        $log(">> AUTH LOGIN");
        $response = smtp_read_response($socket);
        $log("<< " . trim($response));

        if (!smtp_check_code($response, 334)) {
            fclose($socket);
            return ['success' => false, 'message' => 'AUTH LOGIN command rejected: ' . trim($response), 'debug' => $debug_logs];
        }

        // Send username
        smtp_write_command($socket, base64_encode($username));
        $log(">> [Base64 Username]");
        $response = smtp_read_response($socket);
        $log("<< " . trim($response));

        if (!smtp_check_code($response, 334)) {
            fclose($socket);
            return ['success' => false, 'message' => 'SMTP Username rejected: ' . trim($response), 'debug' => $debug_logs];
        }

        // Send password
        smtp_write_command($socket, base64_encode($password));
        $log(">> [Base64 Password]");
        $response = smtp_read_response($socket);
        $log("<< " . trim($response));

        if (!smtp_check_code($response, 235)) {
            fclose($socket);
            return ['success' => false, 'message' => 'SMTP Authentication failed. Please check username/password.', 'debug' => $debug_logs];
        }
        $log("Authentication successful.");
    }

    // MAIL FROM
    $envelope_from = !empty($username) ? $username : $from_email;
    smtp_write_command($socket, "MAIL FROM:<{$envelope_from}>");
    $log(">> MAIL FROM:<{$envelope_from}>");
    $response = smtp_read_response($socket);
    $log("<< " . trim($response));
    if (!smtp_check_code($response, 250)) {
        fclose($socket);
        return ['success' => false, 'message' => 'MAIL FROM command rejected: ' . trim($response), 'debug' => $debug_logs];
    }

    // RCPT TO
    smtp_write_command($socket, "RCPT TO:<{$to_email}>");
    $log(">> RCPT TO:<{$to_email}>");
    $response = smtp_read_response($socket);
    $log("<< " . trim($response));
    if (!smtp_check_code($response, [250, 251])) {
        fclose($socket);
        return ['success' => false, 'message' => 'Recipient address rejected: ' . trim($response), 'debug' => $debug_logs];
    }

    // DATA
    smtp_write_command($socket, "DATA");
    $log(">> DATA");
    $response = smtp_read_response($socket);
    $log("<< " . trim($response));
    if (!smtp_check_code($response, 354)) {
        fclose($socket);
        return ['success' => false, 'message' => 'DATA command rejected: ' . trim($response), 'debug' => $debug_logs];
    }

    // Prepare MIME message
    $boundary = "=_ml_mime_" . md5(uniqid(time(), true));
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encoded_from_name = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
    $encoded_to_name = !empty($to_name) ? '=?UTF-8?B?' . base64_encode($to_name) . '?=' : '';

    $headers = [];
    $headers[] = "Date: " . date('r');
    $headers[] = "From: {$encoded_from_name} <{$from_email}>";
    $headers[] = "Reply-To: <{$reply_to}>";
    $headers[] = "To: " . (!empty($encoded_to_name) ? "{$encoded_to_name} <{$to_email}>" : "<{$to_email}>");
    $headers[] = "Subject: {$encoded_subject}";
    $headers[] = "Message-ID: <" . md5(uniqid(microtime(), true)) . "@{$client_domain}>";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
    $headers[] = "X-Mailer: MarketLink-PHP-SMTP/1.0";

    if (empty($alt_body)) {
        $alt_body = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html_body));
    }

    $message_body = implode("\r\n", $headers) . "\r\n\r\n";
    $message_body .= "--{$boundary}\r\n";
    $message_body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message_body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message_body .= chunk_split(base64_encode($alt_body)) . "\r\n";
    $message_body .= "--{$boundary}\r\n";
    $message_body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message_body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message_body .= chunk_split(base64_encode($html_body)) . "\r\n";
    $message_body .= "--{$boundary}--\r\n";
    $message_body .= ".\r\n"; // End of DATA stream

    // Write message
    fwrite($socket, $message_body);
    $response = smtp_read_response($socket);
    $log("<< " . trim($response));

    if (!smtp_check_code($response, 250)) {
        fclose($socket);
        return ['success' => false, 'message' => 'Sending message body failed: ' . trim($response), 'debug' => $debug_logs];
    }

    // QUIT
    smtp_write_command($socket, "QUIT");
    $log(">> QUIT");
    fclose($socket);

    return [
        'success' => true,
        'message' => 'Email sent successfully via SMTP.',
        'debug' => $debug_logs
    ];
}

/**
 * Send password reset email with branded template
 */
function send_password_reset_email($to_email, $to_name, $reset_url, $expires_minutes = 30) {
    $subject = "Reset Your MarketLink Password";
    $safe_name = !empty($to_name) ? htmlspecialchars($to_name) : 'Valued User';

    $html_body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Your Password</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; color: #1E293B; margin: 0; padding: 20px; line-height: 1.6; }
            .email-wrapper { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #E2E8F0; }
            .header { background: linear-gradient(135deg, #1B4D3E, #2E7D4F); padding: 36px 30px; text-align: center; color: #ffffff; }
            .brand-name { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; margin: 0; }
            .brand-name span { color: #A3E635; }
            .content { padding: 36px 30px; }
            .greeting { font-size: 18px; font-weight: 700; color: #0F172A; margin-bottom: 12px; }
            .btn-reset { display: inline-block; background-color: #2E7D4F; color: #ffffff !important; padding: 14px 32px; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px; margin: 24px 0; text-align: center; box-shadow: 0 4px 12px rgba(46, 125, 79, 0.25); }
            .btn-reset:hover { background-color: #256640; }
            .notice-box { background: #F1F5F9; border-left: 4px solid #2E7D4F; padding: 14px 18px; border-radius: 6px; font-size: 13px; color: #475569; margin: 20px 0; }
            .link-fallback { word-break: break-all; font-size: 12px; color: #2E7D4F; background: #F8FAFC; padding: 12px; border-radius: 8px; border: 1px dashed #CBD5E1; }
            .footer { padding: 24px 30px; background: #F8FAFC; text-align: center; font-size: 12px; color: #94A3B8; border-top: 1px solid #E2E8F0; }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="header">
                <div class="brand-name">🌿 Market<span>Link</span></div>
                <p style="margin: 6px 0 0; opacity: 0.85; font-size: 14px;">Secure Account Password Recovery</p>
            </div>
            <div class="content">
                <div class="greeting">Hello, ' . $safe_name . '!</div>
                <p>We received a request to reset the password for your MarketLink account associated with <strong>' . htmlspecialchars($to_email) . '</strong>.</p>
                <p>Click the secure button below to set a new password:</p>
                
                <div style="text-align: center;">
                    <a href="' . htmlspecialchars($reset_url) . '" class="btn-reset" target="_blank">Reset Password Now</a>
                </div>

                <div class="notice-box">
                    ⏱️ <strong>Link Expiration:</strong> This password reset link is valid for <strong>' . (int)$expires_minutes . ' minutes</strong> and can only be used once.
                </div>

                <p style="font-size: 13px; color: #64748B;">If you are having trouble clicking the button, copy and paste the following URL into your web browser:</p>
                <div class="link-fallback">' . htmlspecialchars($reset_url) . '</div>

                <p style="font-size: 12px; color: #94A3B8; margin-top: 24px;">
                    🛡️ <em>If you did not request a password reset, please ignore this email or contact support if you suspect unauthorized access. Your password will remain unchanged.</em>
                </p>
            </div>
            <div class="footer">
                <p style="margin: 0 0 6px;">&copy; ' . date('Y') . ' MarketLink. All rights reserved.</p>
                <p style="margin: 0;">Connecting Local Farmers With Community Shoppers</p>
            </div>
        </div>
    </body>
    </html>
    ';

    $alt_body = "Hello " . ($to_name ?: 'User') . ",\n\n"
              . "We received a request to reset your MarketLink password.\n"
              . "Please click the link below or copy and paste it into your browser to reset your password (valid for {$expires_minutes} minutes):\n\n"
              . "{$reset_url}\n\n"
              . "If you did not make this request, please ignore this message.\n\n"
              . "MarketLink Team";

    return send_smtp_email($to_email, $to_name, $subject, $html_body, $alt_body);
}

// ─────────────────────────────────────────────────────────────────────────────
// Socket Helper Functions
// ─────────────────────────────────────────────────────────────────────────────

function smtp_write_command($socket, $cmd) {
    return fwrite($socket, $cmd . "\r\n");
}

function smtp_read_response($socket) {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) break;
        $response .= $line;
        // Check if last line of multi-line response (e.g. "250-..." vs "250 ...")
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_check_code($response, $expected_codes) {
    if (!is_array($expected_codes)) {
        $expected_codes = [$expected_codes];
    }
    $code = (int)substr(trim($response), 0, 3);
    return in_array($code, $expected_codes, true);
}
