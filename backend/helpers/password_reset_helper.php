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
 *
 * @param string $rawToken
 * @param string $newPassword
 * @param string $confirmPassword
 * @param mysqli $conn
 * @return array ['success' => bool, 'message' => string]
 */
function pwd_reset_complete(string $rawToken, string $newPassword, string $confirmPassword, mysqli $conn): array {
    $verification = pwd_reset_verify_token($rawToken, $conn);
    if (!$verification['valid']) {
        return ['success' => false, 'message' => $verification['error']];
    }

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    // Validate password policy (min 8 chars, lowercase, uppercase, number, special char, common blocklist)
    $policy = pw_validate_policy($newPassword);
    if (!$policy['valid']) {
        return ['success' => false, 'message' => $policy['errors'][0] ?? 'Password does not meet security requirements.'];
    }

    $adminId = $verification['admin_id'];
    $newHash = pw_hash($newPassword);

    $conn->begin_transaction();
    try {
        // Update user's password
        $upd = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $upd->bind_param('si', $newHash, $adminId);
        $upd->execute();
        $upd->close();

        // Mark token as used
        $tokenHash = hash('sha256', $rawToken);
        $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token_hash = ?");
        $mark->bind_param('s', $tokenHash);
        $mark->execute();
        $mark->close();

        // Invalidate any active sessions / OTPs for this admin
        $invOtp = $conn->prepare("UPDATE admin_otps SET used = 1 WHERE admin_id = ? AND used = 0");
        if ($invOtp) {
            $invOtp->bind_param('i', $adminId);
            $invOtp->execute();
            $invOtp->close();
        }

        $conn->commit();

        return [
            'success'  => true,
            'message'  => 'Your password has been reset successfully! You can now log in.',
            'role'     => $verification['role']
        ];
    } catch (\Throwable $e) {
        $conn->rollback();
        error_log("[PWD_RESET] Error updating password: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update password. Please try again.'];
    }
}

/**
 * Dispatch Password Reset Email via PHPMailer.
 *
 * @param string $toEmail
 * @param string $name
 * @param string $resetUrl
 * @return array ['success' => bool, 'error' => string|null]
 */
function pwd_reset_send_email(string $toEmail, string $name, string $resetUrl): array {
    require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

    if (!defined('GMAIL_USER')) {
        require_once __DIR__ . '/../services/mailer.php';
    }

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
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $name);

        $expiryMin = PWD_RESET_EXPIRY_MINUTES;
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Password – Santa Fe Beach Club';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);'>
                <div style='background: #644B39; padding: 28px 32px; text-align: center;'>
                    <h2 style='color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;'>Password Reset Request</h2>
                    <p style='color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 13px;'>Santa Fe Beach Club Portal Security</p>
                </div>
                <div style='padding: 32px;'>
                    <p style='color: #334155; font-size: 15px; margin-top: 0;'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p style='color: #475569; font-size: 14px; line-height: 1.6;'>
                        We received a request to reset the password for your portal account. Click the button below to set a new password.
                    </p>
                    <div style='text-align: center; margin: 32px 0;'>
                        <a href='" . htmlspecialchars($resetUrl) . "' style='background: #644B39; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; display: inline-block; box-shadow: 0 4px 10px rgba(100,75,57,0.3);'>
                            Reset My Password
                        </a>
                    </div>
                    <p style='color: #64748b; font-size: 13px; line-height: 1.5;'>
                        ⏱️ This reset link will expire in <strong>{$expiryMin} minutes</strong> and can only be used once.
                    </p>
                    <p style='color: #94a3b8; font-size: 12px; line-height: 1.5;'>
                        If the button doesn't work, copy and paste this link into your browser:<br>
                        <a href='" . htmlspecialchars($resetUrl) . "' style='color: #644B39; word-break: break-all;'>" . htmlspecialchars($resetUrl) . "</a>
                    </p>
                    <hr style='border: none; border-top: 1px solid #f1f5f9; margin: 24px 0;'>
                    <p style='color: #94a3b8; font-size: 12px; margin: 0;'>
                        If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.
                    </p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hello {$name},\n\nWe received a request to reset your Santa Fe Beach Club account password.\n\nPlease visit this link to reset your password:\n{$resetUrl}\n\nThis link expires in {$expiryMin} minutes.\n\nIf you did not request this, please ignore this email.";

        $mail->send();
        error_log("[PWD_RESET] Reset email dispatched successfully to: " . substr($toEmail, 0, 3) . '***@' . explode('@', $toEmail)[1]);
        return ['success' => true, 'error' => null];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("[PWD_RESET] Failed to send email: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
