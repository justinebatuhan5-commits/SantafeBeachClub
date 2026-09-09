<?php
/**
 * security_logger.php — Security Audit Logging & Monitoring Helper
 * Records security-sensitive events, failed logins, and access violations.
 */

require_once __DIR__ . '/../config/db.php';

class SecurityLogger {
    const LEVEL_INFO     = 'INFO';
    const LEVEL_WARNING  = 'WARNING';
    const LEVEL_CRITICAL = 'CRITICAL';

    /**
     * Log a security event to the database and log file
     * @param mysqli $conn
     * @param string $eventType (e.g. 'FAILED_LOGIN', 'CSRF_MISMATCH', 'RATE_LIMIT_TRIGGER', 'UNAUTHORIZED_ACCESS', 'FILE_UPLOAD', 'PASSWORD_CHANGE')
     * @param string $description
     * @param string $level ('INFO', 'WARNING', 'CRITICAL')
     * @param string|null $username
     */
    public static function log(
        mysqli $conn,
        string $eventType,
        string $description,
        string $level = self::LEVEL_INFO,
        ?string $username = null
    ): void {
        $user = $username ?? ($_SESSION['admin_username'] ?? 'anonymous');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
        $requestUri = substr($_SERVER['REQUEST_URI'] ?? '', 0, 255);

        // 1. Insert into database
        $stmt = $conn->prepare("
            INSERT INTO security_logs (event_type, event_level, username, ip_address, user_agent, request_uri, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if ($stmt) {
            $stmt->bind_param("sssssss", $eventType, $level, $user, $ip, $userAgent, $requestUri, $description);
            $stmt->execute();
            $stmt->close();
        }

        // 2. Also append to secure log file for external monitoring tools / fail2ban
        $logFile = __DIR__ . '/../logs/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] [%s] [%s] User: %s | IP: %s | URI: %s | %s\n",
            $timestamp,
            $level,
            $eventType,
            $user,
            $ip,
            $requestUri,
            $description
        );
        @error_log($logEntry, 3, $logFile);

        // 3. Dispatch security alert email for CRITICAL events
        if ($level === self::LEVEL_CRITICAL) {
            self::sendSecurityAlertEmail($eventType, $description, $user, $ip, $requestUri, $timestamp);
        }
    }

    /**
     * Sends automated email notification to system admin for critical security alerts
     */
    public static function sendSecurityAlertEmail(
        string $eventType,
        string $description,
        string $user,
        string $ip,
        string $uri,
        string $timestamp
    ): void {
        try {
            $mailerPath = __DIR__ . '/../services/mailer.php';
            if (!file_exists($mailerPath)) {
                return;
            }
            require_once $mailerPath;

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('GMAIL_USER') ? GMAIL_USER : 'Justinebatuhan017@gmail.com';
            $mail->Password   = defined('GMAIL_APP_PASSWORD') ? GMAIL_APP_PASSWORD : '';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->Timeout    = 5;

            $adminEmail = defined('GMAIL_USER') ? GMAIL_USER : 'Justinebatuhan017@gmail.com';
            $fromName   = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Santa Fe Beach Club Security';

            $mail->setFrom($adminEmail, $fromName . ' Alert System');
            $mail->addAddress($adminEmail, 'Security Administrator');

            $mail->isHTML(true);
            $mail->Subject = "[CRITICAL ALERT] Security Incident Detected: {$eventType}";
            $mail->Body    = "
                <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>
                    <div style='background:#b91c1c;padding:18px 24px;'>
                        <h2 style='color:#fff;margin:0;font-size:18px;'>⚠️ Critical Security Event Alert</h2>
                        <p style='color:#fee2e2;margin:4px 0 0;font-size:13px;'>Santa Fe Beach Club - Real-time Intrusion & Threat Notification</p>
                    </div>
                    <div style='padding:24px;background:#ffffff;'>
                        <p style='color:#1e293b;font-size:14px;margin-top:0;'>An elevated security condition has been logged by the booking system:</p>
                        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;'>
                            <tr style='border-bottom:1px solid #f1f5f9;'>
                                <td style='padding:8px 0;color:#64748b;font-weight:600;'>Event Type:</td>
                                <td style='padding:8px 0;color:#0f172a;text-align:right;'><b>" . htmlspecialchars($eventType) . "</b></td>
                            </tr>
                            <tr style='border-bottom:1px solid #f1f5f9;'>
                                <td style='padding:8px 0;color:#64748b;font-weight:600;'>Severity:</td>
                                <td style='padding:8px 0;color:#b91c1c;text-align:right;'><b>CRITICAL</b></td>
                            </tr>
                            <tr style='border-bottom:1px solid #f1f5f9;'>
                                <td style='padding:8px 0;color:#64748b;font-weight:600;'>Target User:</td>
                                <td style='padding:8px 0;color:#0f172a;text-align:right;'>" . htmlspecialchars($user) . "</td>
                            </tr>
                            <tr style='border-bottom:1px solid #f1f5f9;'>
                                <td style='padding:8px 0;color:#64748b;font-weight:600;'>Origin IP:</td>
                                <td style='padding:8px 0;color:#0f172a;text-align:right;'>" . htmlspecialchars($ip) . "</td>
                            </tr>
                            <tr style='border-bottom:1px solid #f1f5f9;'>
                                <td style='padding:8px 0;color:#64748b;font-weight:600;'>Request URI:</td>
                                <td style='padding:8px 0;color:#0f172a;text-align:right;'>" . htmlspecialchars($uri) . "</td>
                            </tr>
                            <tr>
                                <td style='padding:8px 0;color:#64748b;font-weight:600;'>Timestamp:</td>
                                <td style='padding:8px 0;color:#0f172a;text-align:right;'>" . htmlspecialchars($timestamp) . "</td>
                            </tr>
                        </table>
                        <div style='background:#fef2f2;border-left:4px solid #ef4444;padding:12px;margin:16px 0;'>
                            <strong style='color:#991b1b;font-size:13px;'>Details:</strong>
                            <p style='color:#7f1d1d;font-size:13px;margin:4px 0 0;'>" . htmlspecialchars($description) . "</p>
                        </div>
                        <p style='font-size:12px;color:#64748b;margin-bottom:0;'>Check your admin security logs at <code>admin_logs.php</code> or review the Incident Response Plan for immediate containment steps.</p>
                    </div>
                </div>
            ";
            $mail->AltBody = "CRITICAL SECURITY ALERT: [{$eventType}] User: {$user} | IP: {$ip} | Time: {$timestamp} | {$description}";

            $mail->send();
        } catch (\Exception $e) {
            // Suppress exception so log flow doesn't disrupt end-user transaction
            @error_log("[SecurityLogger] Alert email dispatch failed: " . $e->getMessage());
        }
    }
}
?>
