<?php
/**
 * password_reset_helper.php
 * Secure password reset token generation, verification, and email dispatch.
 *
 * Security rules:
 * - Cryptographically random 64-char hex token via random_bytes(32).
 * - Plaintext token is NEVER stored; only SHA-256 hash is persisted.
 * - Single-use enforced (used = 1 immediately upon completion).
 * - Expiry enforced (15 minutes).
 * - Prevents email enumeration on requests.
 * - Password complexity enforced via password_helper.php.
 */

require_once __DIR__ . '/password_helper.php';

define('PWD_RESET_EXPIRY_MINUTES', 15);

/**
 * Generate a cryptographically secure token and store its SHA-256 hash in DB.
 * Invalidates any existing unused tokens for this admin.
 *
 * @param int    $adminId
 * @param mysqli $conn
 * @param string $ipAddress
 * @return string The raw 64-char hex token (to be emailed, never logged)
 */
function pwd_reset_create_token(int $adminId, mysqli $conn, string $ipAddress = ''): string {
    // Invalidate previous unused tokens for this user
    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE admin_id = ? AND used = 0");
    if ($stmt) {
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();
    }

    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . PWD_RESET_EXPIRY_MINUTES . ' minutes'));

    $stmt = $conn->prepare(
        "INSERT INTO password_resets (admin_id, token_hash, expires_at, ip_address) VALUES (?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('isss', $adminId, $tokenHash, $expiresAt, $ipAddress);
        $stmt->execute();
        $stmt->close();
    }

    return $rawToken;
}

/**
 * Verify if a submitted raw token is valid and unexpired.
 *
 * @param string $rawToken
 * @param mysqli $conn
 * @return array ['valid' => bool, 'admin_id' => int|null, 'username' => string|null, 'role' => string|null, 'error' => string|null]
 */
function pwd_reset_verify_token(string $rawToken, mysqli $conn): array {
    if (empty($rawToken) || strlen($rawToken) < 32) {
        return ['valid' => false, 'admin_id' => null, 'username' => null, 'role' => null, 'error' => 'Invalid or missing reset token.'];
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = $conn->prepare(
        "SELECT pr.id AS reset_id, pr.admin_id, pr.expires_at, pr.used, a.username, a.role
         FROM password_resets pr
         JOIN admins a ON pr.admin_id = a.id
         WHERE pr.token_hash = ?
         ORDER BY pr.id DESC
         LIMIT 1"
    );

    if (!$stmt) {
        return ['valid' => false, 'admin_id' => null, 'username' => null, 'role' => null, 'error' => 'Database error.'];
    }

    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['valid' => false, 'admin_id' => null, 'username' => null, 'role' => null, 'error' => 'Reset link is invalid or has expired.'];
    }

    if ((int)$row['used'] === 1) {
        return ['valid' => false, 'admin_id' => null, 'username' => null, 'role' => null, 'error' => 'This reset link has already been used.'];
    }

    if (strtotime($row['expires_at']) < time()) {
        return ['valid' => false, 'admin_id' => null, 'username' => null, 'role' => null, 'error' => 'This reset link has expired. Please request a new one.'];
    }

    return [
        'valid'     => true,
        'reset_id'  => (int)$row['reset_id'],
        'admin_id'  => (int)$row['admin_id'],
        'username'  => $row['username'],
        'role'      => $row['role'],
        'error'     => null,
    ];
}

/**
 * Complete the password reset: validates new password, updates admins table, and marks token used.
 */
function pwd_reset_complete(string $rawToken, string $newPassword, string $confirmPassword, mysqli $conn): array {
    $verification = pwd_reset_verify_token($rawToken, $conn);
    if (!$verification['valid']) {
        return ['success' => false, 'message' => $verification['error']];
    }

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    $policyError = pw_validate($newPassword);
    if ($policyError !== null) {
        return ['success' => false, 'message' => $policyError];
    }

    $adminId = $verification['admin_id'];
    $newHash = pw_hash($newPassword);

    $conn->begin_transaction();
    try {
        $upd = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $upd->bind_param('si', $newHash, $adminId);
        $upd->execute();
        $upd->close();

        $tokenHash = hash('sha256', $rawToken);
        $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token_hash = ?");
        $mark->bind_param('s', $tokenHash);
        $mark->execute();
        $mark->close();

        $invOtp = $conn->prepare("UPDATE admin_otps SET used = 1 WHERE admin_id = ? AND used = 0");
        if ($invOtp) {
            $invOtp->bind_param('i', $adminId);
            $invOtp->execute();
            $invOtp->close();
        }

        $conn->commit();

        // Notify user that password was changed (OWASP checklist 1.4)
        $notifyStmt = $conn->prepare("SELECT email, username FROM admins WHERE id = ?");
        if ($notifyStmt) {
            $notifyStmt->bind_param('i', $adminId);
            $notifyStmt->execute();
            $notifyRow = $notifyStmt->get_result()->fetch_assoc();
            $notifyStmt->close();

            if ($notifyRow) {
                $notifyEmail = !empty($notifyRow['email']) ? $notifyRow['email'] : $notifyRow['username'];
                $notifyName  = !empty($notifyRow['username']) ? explode('@', $notifyRow['username'])[0] : 'User';
                if (filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
                    pwd_reset_send_change_notification($notifyEmail, ucfirst($notifyName));
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Your password has been reset successfully! You can now log in.',
            'role'    => $verification['role']
        ];
    } catch (\Throwable $e) {
        $conn->rollback();
        error_log("[PWD_RESET] Error updating password: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update password. Please try again.'];
    }
}

// ---------------------------------------------------------------------------
// Email helpers – SMTP first, PHP mail() fallback for InfinityFree
// ---------------------------------------------------------------------------

function _pwd_mailer_smtp_send(string $toEmail, string $name, string $subject, string $htmlBody, string $plainBody): bool {
    if (!defined('GMAIL_USER')) {
        require_once __DIR__ . '/../services/mailer.php';
    }
    $smtpFiles = [
        __DIR__ . '/../libs/PHPMailer/src/Exception.php',
        __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php',
        __DIR__ . '/../libs/PHPMailer/src/SMTP.php',
    ];
    if (!array_reduce($smtpFiles, fn($c, $f) => $c && file_exists($f), true)) return false;
    if (!defined('GMAIL_APP_PASSWORD')) return false;
    foreach ($smtpFiles as $f) { require_once $f; }
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->setFrom(GMAIL_USER, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Santa Fe Beach Club');
        $mail->addAddress($toEmail, $name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;
        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("[PWD_RESET] SMTP failed: " . $mail->ErrorInfo);
        return false;
    }
}

function _pwd_mailer_native_send(string $toEmail, string $subject, string $htmlBody, string $plainBody): bool {
    $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Santa Fe Beach Club';
    $fromEmail = defined('GMAIL_USER')     ? GMAIL_USER     : 'noreply@santafebeachclub.com';
    $boundary  = md5(uniqid((string)rand(), true));
    $headers   = "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers  .= "From: {$fromName} <{$fromEmail}>\r\nReply-To: {$fromEmail}\r\nX-Mailer: PHP/" . phpversion();
    $body  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$plainBody}\r\n";
    $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n--{$boundary}--";
    return (bool) @mail($toEmail, $subject, $body, $headers);
}

/**
 * Dispatch Password Reset Email.
 * Tries Gmail SMTP, falls back to PHP mail() for InfinityFree.
 */
function pwd_reset_send_email(string $toEmail, string $name, string $resetUrl): array {
    if (!defined('GMAIL_USER')) {
        require_once __DIR__ . '/../services/mailer.php';
    }
    $expiryMin = PWD_RESET_EXPIRY_MINUTES;
    $subject  = 'Reset Your Password - Santa Fe Beach Club';
    $htmlBody = "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #e2e8f0;'>
            <div style='background:#644B39;padding:28px 32px;text-align:center;border-radius:12px 12px 0 0;'>
                <h2 style='color:#fff;margin:0;font-size:22px;font-weight:700;'>Password Reset Request</h2>
                <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px;'>Santa Fe Beach Club Portal Security</p>
            </div>
            <div style='padding:32px;'>
                <p style='color:#334155;font-size:15px;margin-top:0;'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p style='color:#475569;font-size:14px;line-height:1.6;'>We received a request to reset the password for your portal account. Click the button below to set a new password.</p>
                <div style='text-align:center;margin:32px 0;'>
                    <a href='" . htmlspecialchars($resetUrl) . "' style='background:#644B39;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:600;display:inline-block;'>Reset My Password</a>
                </div>
                <p style='color:#64748b;font-size:13px;'>This link expires in <strong>{$expiryMin} minutes</strong> and can only be used once.</p>
                <p style='color:#94a3b8;font-size:12px;'>Or copy this link: <a href='" . htmlspecialchars($resetUrl) . "' style='color:#644B39;word-break:break-all;'>" . htmlspecialchars($resetUrl) . "</a></p>
                <hr style='border:none;border-top:1px solid #f1f5f9;margin:24px 0;'>
                <p style='color:#94a3b8;font-size:12px;margin:0;'>If you did not request a password reset, you can safely ignore this email.</p>
            </div>
        </div>
    ";
    $plainBody = "Hello {$name},\n\nReset your Santa Fe Beach Club password:\n{$resetUrl}\n\nExpires in {$expiryMin} minutes. If you did not request this, ignore this email.";

    if (_pwd_mailer_smtp_send($toEmail, $name, $subject, $htmlBody, $plainBody)) {
        error_log("[PWD_RESET] Reset email sent via SMTP.");
        return ['success' => true, 'error' => null];
    }
    if (_pwd_mailer_native_send($toEmail, $subject, $htmlBody, $plainBody)) {
        error_log("[PWD_RESET] Reset email sent via PHP mail() fallback.");
        return ['success' => true, 'error' => null];
    }
    error_log("[PWD_RESET] Both SMTP and PHP mail() failed.");
    return ['success' => false, 'error' => 'Could not send reset email. Please contact the administrator.'];
}

/**
 * Send a notification email after a password has been successfully changed.
 * OWASP Checklist 1.4: "Notify the user when the password has been changed."
 */
function pwd_reset_send_change_notification(string $toEmail, string $name): array {
    if (!defined('GMAIL_USER')) {
        require_once __DIR__ . '/../services/mailer.php';
    }
    $changedAt = date('F j, Y \at g:i A');
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $subject  = 'Your Password Was Changed - Santa Fe Beach Club';
    $htmlBody = "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;background:#fff;border-radius:12px;border:1px solid #e2e8f0;'>
            <div style='background:#644B39;padding:28px 32px;text-align:center;border-radius:12px 12px 0 0;'>
                <h2 style='color:#fff;margin:0;font-size:22px;font-weight:700;'>&#128274; Password Changed</h2>
                <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px;'>Santa Fe Beach Club - Security Alert</p>
            </div>
            <div style='padding:32px;'>
                <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p style='color:#475569;font-size:14px;line-height:1.6;'>Your Santa Fe Beach Club portal password was successfully changed.</p>
                <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;margin:20px 0;'>
                    <p style='margin:0 0 6px;font-size:13px;color:#64748b;'><strong>Date &amp; Time:</strong> {$changedAt}</p>
                    <p style='margin:0;font-size:13px;color:#64748b;'><strong>IP Address:</strong> {$ipAddress}</p>
                </div>
                <p style='color:#dc2626;font-size:13px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;'>
                    If you did not make this change, contact the resort administrator immediately.
                </p>
                <hr style='border:none;border-top:1px solid #f1f5f9;margin:24px 0;'>
                <p style='color:#94a3b8;font-size:12px;margin:0;'>Santa Fe Beach Club - Barangay Poblacion, Santa Fe, Cebu</p>
            </div>
        </div>
    ";
    $plainBody = "Hello {$name},\n\nYour Santa Fe Beach Club portal password was changed on {$changedAt} from IP {$ipAddress}.\nIf you did not make this change, contact the administrator immediately.";

    if (_pwd_mailer_smtp_send($toEmail, $name, $subject, $htmlBody, $plainBody)) {
        return ['success' => true, 'error' => null];
    }
    if (_pwd_mailer_native_send($toEmail, $subject, $htmlBody, $plainBody)) {
        return ['success' => true, 'error' => null];
    }
    return ['success' => false, 'error' => 'Could not send notification email.'];
}
