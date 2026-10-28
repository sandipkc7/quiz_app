<?php
/**
 * SMTP Mailer Utility for QuizMaster
 * 
 * Provides pure PHP RFC-compliant SMTP mailing capability without external dependencies.
 * Pulls all settings from .env via constants defined in config/database.php.
 */

require_once __DIR__ . '/../config/database.php';

class SmtpMailer {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromEmail;
    private string $fromName;
    private int $timeout;
    private $socket = null;
    private array $logs = [];

    public function __construct(array $config = []) {
        $this->host       = $config['host']       ?? (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com');
        $this->port       = (int)($config['port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 587));
        $this->username   = $config['username']   ?? (defined('SMTP_USER') ? SMTP_USER : '');
        $this->password   = $config['password']   ?? (defined('SMTP_PASS') ? SMTP_PASS : '');
        $this->encryption = strtolower($config['encryption'] ?? (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls'));
        $this->fromEmail  = $config['from_email'] ?? (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@quizmaster.com');
        $this->fromName   = $config['from_name']  ?? (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'QuizMaster');
        $this->timeout    = (int)($config['timeout'] ?? 15);
    }

    public function getLogs(): array {
        return $this->logs;
    }

    private function log(string $msg): void {
        $this->logs[] = date('Y-m-d H:i:s') . ' ' . $msg;
    }

    /**
     * Read multi-line or single-line response from SMTP server
     */
    private function readResponse(): array {
        $response = '';
        $code = 0;
        $startTime = time();

        while (!feof($this->socket)) {
            if ((time() - $startTime) > $this->timeout) {
                throw new RuntimeException("SMTP Timeout waiting for server response.");
            }

            $line = fgets($this->socket, 1024);
            if ($line === false) {
                break;
            }
            $response .= $line;

            // Check completion line: 3 digits followed by space or CRLF
            if (preg_match('/^([0-9]{3})[ ](.*)$/', $line, $matches)) {
                $code = (int)$matches[1];
                break;
            }
        }

        $this->log("<< " . trim($response));
        return ['code' => $code, 'text' => $response];
    }

    /**
     * Send command to SMTP server and verify status code
     */
    private function sendCommand(string $command, array $expectedCodes = [250], bool $maskInLog = false): array {
        $this->log(">> " . ($maskInLog ? '********' : $command));
        fwrite($this->socket, $command . "\r\n");
        $res = $this->readResponse();

        if (!in_array($res['code'], $expectedCodes, true)) {
            throw new RuntimeException("SMTP Command failed: [Expected: " . implode('/', $expectedCodes) . "] Received: {$res['code']} - {$res['text']}");
        }

        return $res;
    }

    /**
     * Connect and authenticate to the SMTP server
     */
    private function connect(): void {
        $isSSL = ($this->encryption === 'ssl' || $this->port === 465);
        $protocol = $isSSL ? 'ssl://' : 'tcp://';
        $remoteSocket = $protocol . $this->host . ':' . $this->port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            ]
        ]);

        $this->log("Connecting to {$remoteSocket} (timeout: {$this->timeout}s)...");
        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client(
            $remoteSocket,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new RuntimeException("Could not connect to SMTP host {$this->host}:{$this->port} - ($errno) $errstr");
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Read initial greeting (expect 220)
        $greeting = $this->readResponse();
        if ($greeting['code'] !== 220) {
            throw new RuntimeException("Unexpected greeting from server: {$greeting['text']}");
        }

        // Send EHLO
        $clientHost = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        try {
            $this->sendCommand("EHLO {$clientHost}", [250]);
        } catch (Throwable $e) {
            $this->sendCommand("HELO {$clientHost}", [250]);
        }

        // Handle STARTTLS for TLS encryption
        if (!$isSSL && ($this->encryption === 'tls' || $this->port === 587)) {
            $this->sendCommand("STARTTLS", [220]);
            $this->log("Negotiating TLS encryption...");

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            $cryptoOk = @stream_socket_enable_crypto($this->socket, true, $cryptoMethod);
            if (!$cryptoOk) {
                throw new RuntimeException("TLS encryption handshake failed with {$this->host}");
            }
            $this->log("TLS established successfully.");

            // Per RFC 3207, re-send EHLO after TLS negotiation
            $this->sendCommand("EHLO {$clientHost}", [250]);
        }

        // Authenticate if credentials provided
        if (!empty($this->username)) {
            $this->log("Authenticating as {$this->username}...");
            $this->sendCommand("AUTH LOGIN", [334]);

            // Username
            $this->sendCommand(base64_encode($this->username), [334]);

            // Password (clean spaces for Gmail app passwords if provided with spaces, or use as is)
            $passwordToSend = $this->password;
            // If host is Google/Gmail and password contains spaces, standard App Passwords are 16 alphanumeric characters
            if (str_contains(strtolower($this->host), 'gmail') || str_contains(strtolower($this->host), 'google')) {
                $passwordToSend = str_replace(' ', '', $passwordToSend);
            }

            try {
                $this->sendCommand(base64_encode($passwordToSend), [235], true);
            } catch (Throwable $authErr) {
                // If trimmed version failed and original had spaces, try original as fallback
                if ($passwordToSend !== $this->password) {
                    $this->sendCommand(base64_encode($this->password), [235], true);
                } else {
                    throw $authErr;
                }
            }

            $this->log("SMTP Authentication successful.");
        }
    }

    /**
     * Send email to recipient
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainText = ''): bool {
        if (empty($plainText)) {
            $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
        }

        try {
            $this->connect();

            // Set sender
            $this->sendCommand("MAIL FROM:<{$this->fromEmail}>", [250]);

            // Set recipient
            $this->sendCommand("RCPT TO:<{$toEmail}>", [250, 251]);

            // Initiate DATA
            $this->sendCommand("DATA", [354]);

            // Generate boundary and headers
            $boundary = 'b1_' . bin2hex(random_bytes(16));
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $fromFormatted = '=?UTF-8?B?' . base64_encode($this->fromName) . '?= <' . $this->fromEmail . '>';
            $toFormatted = !empty($toName) ? '=?UTF-8?B?' . base64_encode($toName) . '?= <' . $toEmail . '>' : $toEmail;

            $headers = [];
            $headers[] = "Date: " . date('r');
            $headers[] = "To: {$toFormatted}";
            $headers[] = "From: {$fromFormatted}";
            $headers[] = "Reply-To: {$this->fromEmail}";
            $headers[] = "Subject: {$encodedSubject}";
            $headers[] = "Message-ID: <" . bin2hex(random_bytes(12)) . '@' . (!empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'quizmaster.local') . ">";
            $headers[] = "X-Mailer: QuizMaster PHP Mailer";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

            // Build multipart body
            $payload = implode("\r\n", $headers) . "\r\n\r\n";

            // Plain text part
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $payload .= chunk_split(base64_encode($plainText)) . "\r\n";

            // HTML part
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Type: text/html; charset=UTF-8\r\n";
            $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $payload .= chunk_split(base64_encode($htmlBody)) . "\r\n";

            $payload .= "--{$boundary}--\r\n";

            // End of message is CRLF . CRLF
            $payload .= ".\r\n";

            $this->log("Sending message payload...");
            fwrite($this->socket, $payload);
            $res = $this->readResponse();

            if ($res['code'] !== 250) {
                throw new RuntimeException("Failed to finalize message DATA: {$res['text']}");
            }

            // Close connection cleanly
            try {
                $this->sendCommand("QUIT", [221]);
            } catch (Throwable $ignore) {
            }

            $this->log("Message sent successfully to {$toEmail}.");
            return true;
        } catch (Throwable $e) {
            $this->log("Mailer Error: " . $e->getMessage());
            error_log("SmtpMailer Exception: " . $e->getMessage());
            throw $e;
        } finally {
            if ($this->socket) {
                @fclose($this->socket);
                $this->socket = null;
            }
        }
    }
}

/**
 * High-level helper: Send Password Reset Email
 *
 * @param string $recipientEmail
 * @param string $recipientName
 * @param string $resetUrl
 * @return array ['success' => bool, 'message' => string, 'dev_link' => ?string]
 */
function send_password_reset_email(string $recipientEmail, string $recipientName, string $resetUrl): array {
    $appName = defined('APP_NAME') ? APP_NAME : 'QuizMaster';
    $subject = "Reset Your {$appName} Password";

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #0b0f19;
            color: #f3f4f6;
            margin: 0;
            padding: 30px 15px;
        }
        .container {
            max-width: 540px;
            margin: 0 auto;
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .logo {
            text-align: center;
            font-size: 28px;
            font-weight: 800;
            color: #6366f1;
            margin-bottom: 24px;
        }
        .logo span {
            color: #ec4899;
        }
        h1 {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 14px;
            text-align: center;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #9ca3af;
            margin-bottom: 20px;
        }
        .btn-wrapper {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
        }
        .alt-link-box {
            background: #1a2234;
            border: 1px solid #2d3748;
            border-radius: 8px;
            padding: 12px;
            word-break: break-all;
            font-size: 13px;
            color: #93c5fd;
            margin: 20px 0;
        }
        .alt-link-box a {
            color: #60a5fa;
            text-decoration: none;
        }
        .notice {
            background: rgba(245, 158, 11, 0.1);
            border-left: 3px solid #f59e0b;
            padding: 10px 14px;
            font-size: 13px;
            color: #fbbf24;
            margin-bottom: 24px;
            border-radius: 4px;
        }
        .footer {
            border-top: 1px solid #1f2937;
            padding-top: 20px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">⚡ {$appName}</div>
        <h1>Password Reset Request</h1>
        <p>Hello <strong>{$recipientName}</strong>,</p>
        <p>We received a request to reset the password associated with your {$appName} account. To choose a new password, click the button below:</p>
        
        <div class="btn-wrapper">
            <a href="{$resetUrl}" class="btn" target="_blank">Reset Password</a>
        </div>

        <div class="notice">
            ⏰ <strong>Note:</strong> This link is secure and will expire in <strong>60 minutes</strong>. It can only be used once.
        </div>

        <p style="font-size: 13px;">If the button above does not work, copy and paste this link into your web browser:</p>
        <div class="alt-link-box">
            <a href="{$resetUrl}" target="_blank">{$resetUrl}</a>
        </div>

        <p style="font-size: 13px; color: #6b7280;">If you did not request a password reset, you can safely ignore this email. Your current password remains unchanged and your account is secure.</p>

        <div class="footer">
            &copy; 2026 {$appName}. All rights reserved.<br>
            This is an automated system email. Please do not reply directly.
        </div>
    </div>
</body>
</html>
HTML;

    $plainText = "Hello {$recipientName},\n\n"
               . "We received a request to reset your {$appName} account password.\n\n"
               . "Click or paste the following link into your browser to reset your password:\n"
               . "{$resetUrl}\n\n"
               . "This link will expire in 60 minutes.\n\n"
               . "If you did not make this request, please disregard this message.\n\n"
               . "-- The {$appName} Team";

    // Attempt SMTP delivery
    try {
        $mailer = new SmtpMailer();
        $mailer->send($recipientEmail, $recipientName, $subject, $htmlBody, $plainText);

        return [
            'success'  => true,
            'message'  => 'Password reset instructions have been sent to your email address.',
            'dev_link' => null,
            'channel'  => 'smtp'
        ];
    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
        error_log("[QuizMaster Password Reset] SMTP Error: " . $errorMsg);

        // Save backup to temp/password_reset_log.txt for developer/local review
        $logDir = __DIR__ . '/../temp';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logEntry = "[" . date('Y-m-d H:i:s') . "] Password reset link for {$recipientEmail} ({$recipientName}):\n{$resetUrl}\nSMTP Error: {$errorMsg}\n\n";
        @file_put_contents($logDir . '/password_reset_log.txt', $logEntry, FILE_APPEND);

        // If in development mode, provide fallback dev link so user is never blocked
        if (defined('APP_ENV') && APP_ENV === 'development') {
            return [
                'success'  => true,
                'message'  => 'Reset link generated. (SMTP connection encountered an issue: ' . e($errorMsg) . ').',
                'dev_link' => $resetUrl,
                'channel'  => 'log'
            ];
        }

        return [
            'success'  => false,
            'message'  => 'Unable to send password reset email at this moment. Please try again later or contact support.',
            'dev_link' => null,
            'channel'  => 'failed'
        ];
    }
}
