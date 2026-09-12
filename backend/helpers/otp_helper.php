<?php
/**
 * otp_helper.php
 * Core Multi-Factor Authentication OTP helper.
 *
 * Security rules enforced in this file:
 * - OTPs are generated via random_int() (cryptographically secure).
 * - Only the SHA-256 hash is stored in the database; raw OTP is never persisted.
 * - Raw OTP values are NEVER passed to error_log(), echoed, or written to any log.
 * - Comparisons use hash_equals() to prevent timing attacks.
 * - Attempts are capped at OTP_MAX_ATTEMPTS (5). After that, the record is locked.
 * - OTPs expire after OTP_EXPIRY_MINUTES (10 minutes).
 * - A successful verification immediately marks the OTP used=1.
 * - Generating a new OTP for a user invalidates all previous unused OTPs for that user.
 */

define('OTP_EXPIRY_MINUTES', 10);
define('OTP_MAX_ATTEMPTS',   5);
define('OTP_RESEND_COOLDOWN', 60); // seconds between resends

// ---------------------------------------------------------------------------
// Generation
// ---------------------------------------------------------------------------

/**
 * Generate a cryptographically secure 6-digit OTP string.
 * Zero-padded so it is always exactly 6 characters.
 *
 * @return string  e.g. "042817"
 */
function otp_generate(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Hash a raw OTP for safe storage.
 * Using SHA-256 is appropriate here because the input space (000000–999999)
 * is iterated server-side with attempt limiting, making brute force infeasible
 * within the 10-minute window even without bcrypt.
 */
function otp_hash(string $rawOtp): string {
    return hash('sha256', $rawOtp);
}

// ---------------------------------------------------------------------------
// Storage (Admin)
// ---------------------------------------------------------------------------

/**
 * Store a hashed OTP for an admin or receptionist user.
 * Any existing unused OTPs for that user and user_type are invalidated first.
 *
 * @param int    $adminId   The user's primary key.
 * @param string $rawOtp    The raw 6-digit code (NOT stored — only hashed).
 * @param mysqli $conn      Active MySQLi connection.
 * @param string $userType  'admin' or 'receptionist'
 */
function otp_store_for_admin(int $adminId, string $rawOtp, mysqli $conn, string $userType = 'admin'): void {
    // Invalidate all previous unused OTPs for this user and type
    $stmt = $conn->prepare(
        "UPDATE admin_otps SET used = 1 WHERE admin_id = ? AND user_type = ? AND used = 0"
    );
    if ($stmt) {
        $stmt->bind_param('is', $adminId, $userType);
        $stmt->execute();
        $stmt->close();
    }

    $hash      = otp_hash($rawOtp);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

    $stmt = $conn->prepare(
        "INSERT INTO admin_otps (admin_id, user_type, otp_hash, expires_at) VALUES (?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('isss', $adminId, $userType, $hash, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }

    // IMPORTANT: $rawOtp is NOT logged anywhere in this function.
}

// ---------------------------------------------------------------------------
// Storage (Guest)
// ---------------------------------------------------------------------------

/**
 * Store a hashed OTP for a guest (identified by booking_id).
 * Any existing unused OTPs for that booking are invalidated first.
 *
 * @param int    $bookingId  The booking's primary key.
 * @param string $rawOtp     The raw 6-digit code (NOT stored — only hashed).
 * @param mysqli $conn       Active MySQLi connection.
 */
function otp_store_for_guest(int $bookingId, string $rawOtp, mysqli $conn): void {
    // Invalidate all previous unused OTPs for this booking
    $stmt = $conn->prepare(
        "UPDATE guest_otps SET used = 1 WHERE booking_id = ? AND used = 0"
    );
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $stmt->close();

    $hash      = otp_hash($rawOtp);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

    $stmt = $conn->prepare(
        "INSERT INTO guest_otps (booking_id, otp_hash, expires_at) VALUES (?, ?, ?)"
    );
    $stmt->bind_param('iss', $bookingId, $hash, $expiresAt);
    $stmt->execute();
    $stmt->close();
}

// ---------------------------------------------------------------------------
// Verification (Admin)
// ---------------------------------------------------------------------------

/**
 * Verify a submitted OTP for an admin or receptionist.
 *
 * @param  int    $adminId       User primary key.
 * @param  string $submittedCode The raw code entered by the user.
 * @param  mysqli $conn          Active MySQLi connection.
 * @param  string $userType      'admin' or 'receptionist'
 * @return array {
 *   success:   bool,
 *   reason:    string  ('ok'|'expired_or_not_found'|'locked_out'|'invalid'),
 *   remaining: int     (attempts remaining, present on 'invalid')
 * }
 */
function otp_verify_admin(int $adminId, string $submittedCode, mysqli $conn, string $userType = 'admin'): array {
    $stmt = $conn->prepare(
        "SELECT id, otp_hash, attempts
         FROM admin_otps
         WHERE admin_id = ? AND user_type = ? AND used = 0 AND expires_at > NOW()
         ORDER BY created_at DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return ['success' => false, 'reason' => 'expired_or_not_found'];
    }
    $stmt->bind_param('is', $adminId, $userType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'reason' => 'expired_or_not_found'];
    }

    if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['success' => false, 'reason' => 'locked_out'];
    }

    // hash_equals prevents timing attacks
    if (!hash_equals($row['otp_hash'], otp_hash($submittedCode))) {
        $newAttempts = (int)$row['attempts'] + 1;
        $upd = $conn->prepare("UPDATE admin_otps SET attempts = ? WHERE id = ?");
        $upd->bind_param('ii', $newAttempts, $row['id']);
        $upd->execute();
        $upd->close();

        $remaining = OTP_MAX_ATTEMPTS - $newAttempts;
        return ['success' => false, 'reason' => 'invalid', 'remaining' => max(0, $remaining)];
    }

    // ✓ Valid — mark as used immediately
    $upd = $conn->prepare("UPDATE admin_otps SET used = 1 WHERE id = ?");
    $upd->bind_param('i', $row['id']);
    $upd->execute();
    $upd->close();

    return ['success' => true, 'reason' => 'ok'];
}

// ---------------------------------------------------------------------------
// Verification (Guest)
// ---------------------------------------------------------------------------

/**
 * Verify a submitted OTP for a guest booking.
 *
 * @param  int    $bookingId     Booking primary key.
 * @param  string $submittedCode The raw code entered by the user.
 * @param  mysqli $conn          Active MySQLi connection.
 * @return array {success, reason, remaining}
 */
function otp_verify_guest(int $bookingId, string $submittedCode, mysqli $conn): array {
    $stmt = $conn->prepare(
        "SELECT id, otp_hash, attempts
         FROM guest_otps
         WHERE booking_id = ? AND used = 0 AND expires_at > NOW()
         ORDER BY created_at DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'reason' => 'expired_or_not_found'];
    }

    if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['success' => false, 'reason' => 'locked_out'];
    }

    if (!hash_equals($row['otp_hash'], otp_hash($submittedCode))) {
        $newAttempts = (int)$row['attempts'] + 1;
        $upd = $conn->prepare("UPDATE guest_otps SET attempts = ? WHERE id = ?");
        $upd->bind_param('ii', $newAttempts, $row['id']);
        $upd->execute();
        $upd->close();

        $remaining = OTP_MAX_ATTEMPTS - $newAttempts;
        return ['success' => false, 'reason' => 'invalid', 'remaining' => max(0, $remaining)];
    }

    // ✓ Valid — mark as used immediately
    $upd = $conn->prepare("UPDATE guest_otps SET used = 1 WHERE id = ?");
    $upd->bind_param('i', $row['id']);
    $upd->execute();
    $upd->close();

    return ['success' => true, 'reason' => 'ok'];
}

// ---------------------------------------------------------------------------
// Email Sending
// ---------------------------------------------------------------------------

/**
 * Send an OTP email to an admin.
 * Tries Gmail SMTP first (PHPMailer), falls back to PHP native mail()
 * for hosts that block port 587 (e.g. InfinityFree free hosting).
 *
 * @param  string $toEmail   Recipient email address.
 * @param  string $rawOtp    The 6-digit OTP code to send.
 * @param  string $name      Display name of the recipient.
 * @return array {success: bool, error: string|null}
 */
function otp_send_email(string $toEmail, string $rawOtp, string $name): array {
    // Load mailer constants (GMAIL_USER, GMAIL_APP_PASSWORD, MAIL_FROM_NAME)
    if (!defined('GMAIL_USER')) {
        require_once __DIR__ . '/../services/mailer.php';
    }

    $expiryMin = defined('OTP_EXPIRY_MINUTES') ? OTP_EXPIRY_MINUTES : 10;
    $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Santa Fe Beach Club';
    $fromEmail = defined('GMAIL_USER')     ? GMAIL_USER     : 'noreply@santafebeachclub.com';

    $subject = 'Your Verification Code - Santa Fe Beach Club';
    $htmlBody = "
        <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;'>
            <div style='background:#644B39;padding:28px 32px;border-radius:12px 12px 0 0;'>
                <h2 style='color:#fff;margin:0;font-size:20px;'>&#128272; Verification Code</h2>
                <p style='color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:13px;'>Santa Fe Beach Club - Secure Login</p>
            </div>
            <div style='background:#fff;padding:28px 32px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                <p>Hi " . htmlspecialchars($name) . ",</p>
                <p>Use the code below to complete your login. This code expires in <strong>{$expiryMin} minutes</strong>.</p>
                <div style='text-align:center;margin:28px 0;'>
                    <div style='display:inline-block;background:#f9fafb;border:2px dashed #644B39;border-radius:12px;padding:18px 36px;'>
                        <span style='font-size:36px;font-weight:800;letter-spacing:10px;color:#644B39;'>{$rawOtp}</span>
                    </div>
                </div>
                <p style='color:#6b7280;font-size:13px;'>Never share this code. Staff will never ask for it.</p>
                <p style='color:#6b7280;font-size:13px;'>If you did not request this, please ignore this email.</p>
                <hr style='border:none;border-top:1px solid #f0f0f0;margin:20px 0;'>
                <p style='color:#9ca3af;font-size:12px;margin:0;'>Santa Fe Beach Club - Barangay Poblacion, Santa Fe, Cebu</p>
            </div>
        </div>
    ";
    $plainBody = "Hi {$name}, your Santa Fe Beach Club verification code is: {$rawOtp}. "
        . "It expires in {$expiryMin} minutes. Do not share this code.";

    // ── Attempt 1: PHPMailer via Gmail SMTP ──────────────────────────────────
    $smtpFiles = [
        __DIR__ . '/../libs/PHPMailer/src/Exception.php',
        __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php',
        __DIR__ . '/../libs/PHPMailer/src/SMTP.php',
    ];
    $smtpAvailable = array_reduce($smtpFiles, fn($c, $f) => $c && file_exists($f), true);

    if ($smtpAvailable && defined('GMAIL_APP_PASSWORD')) {
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
            $mail->SMTPOptions = ['ssl' => [
                'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true
            ]];
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody;
            $mail->send();
            error_log("[OTP] Email sent via SMTP.");
            return ['success' => true, 'error' => null];
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("[OTP] SMTP failed (" . $mail->ErrorInfo . "), trying PHP mail() fallback.");
        }
    }

    // ── Attempt 2: PHP native mail() fallback (works on InfinityFree) ────────
    $boundary = md5(uniqid((string)rand(), true));
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $plainBody . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $htmlBody . "\r\n";
    $body .= "--{$boundary}--";

    if (@mail($toEmail, $subject, $body, $headers)) {
        error_log("[OTP] Email sent via PHP mail() fallback.");
        return ['success' => true, 'error' => null];
    }

    error_log("[OTP] Both SMTP and PHP mail() failed for OTP delivery.");
    return ['success' => false, 'error' => 'Could not send OTP email. Please contact the administrator.'];
}
