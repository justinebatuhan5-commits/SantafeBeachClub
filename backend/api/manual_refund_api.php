<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/csrf_helper.php';
require_once __DIR__ . '/../helpers/private_storage_helper.php';
require_once __DIR__ . '/../services/mailer.php';
require_once __DIR__ . '/../helpers/refund_method_helper.php';
require_once __DIR__ . '/../helpers/sms_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../helpers/security_logger.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid action.'];

function manual_refund_admin(): void {
    if (($_SESSION['admin_logged_in'] ?? false) !== true || ($_SESSION['admin_role'] ?? '') !== 'admin') {
        http_response_code(403);
        throw new RuntimeException('Administrator access is required.');
    }
}

function manual_refund_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('POST is required.');
    }
    if (!verify_csrf_token()) {
        http_response_code(403);
        throw new RuntimeException('Invalid or expired security token.');
    }
}

try {
    $action = trim((string)($_GET['action'] ?? ''));
    if ($action === 'generate_link') {
        manual_refund_admin(); manual_refund_post();
        sf_require_refund_method($conn, 'manual_gcash');
        $requestId = (int)($_POST['request_id'] ?? 0);
        $stmt = $conn->prepare("SELECT r.id,r.booking_id,r.net_refund,r.status,b.guest_name,b.guest_email,p.payment_method
            FROM refund_requests r JOIN bookings b ON b.id=r.booking_id JOIN payments p ON p.id=r.payment_id
            WHERE r.id=? AND r.status IN ('approved','awaiting_guest_details') LIMIT 1");
        $stmt->bind_param('i', $requestId); $stmt->execute();
        $refund = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$refund) throw new RuntimeException('Approved refund request not found.');
        if (!filter_var($refund['guest_email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('The guest email is invalid.');
        $paymentMethod = (string)$refund['payment_method'];
        $isPayMongo = stripos($paymentMethod, 'PayMongo') !== false;
        $isGcashOrigin = stripos($paymentMethod, 'GCash') !== false;
        if ($isPayMongo && !$isGcashOrigin) {
            throw new RuntimeException('PayMongo card and non-GCash payments must be refunded to their original source.');
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 48 * 3600);
        $conn->begin_transaction();
        try {
            $upsert = $conn->prepare("INSERT INTO manual_refund_details (refund_request_id,token_hash,token_expires_at)
                VALUES (?,?,?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_expires_at=VALUES(token_expires_at),submitted_at=NULL,gcash_account_name=NULL,gcash_mobile=NULL,ownership_confirmed=0,verification_otp_hash=NULL,verification_otp_expires_at=NULL,verification_attempts=0,verification_send_count=0,verification_last_sent_at=NULL,phone_verified_at=NULL,verification_locked_at=NULL");
            $upsert->bind_param('iss', $requestId, $hash, $expires); $upsert->execute(); $upsert->close();
            $update = $conn->prepare("UPDATE refund_requests SET status='awaiting_guest_details',approved_refund_method='manual_gcash' WHERE id=? AND status IN ('approved','awaiting_guest_details')");
            $update->bind_param('i', $requestId); $update->execute(); $update->close();
            $book = $conn->prepare("UPDATE bookings SET refund_status='awaiting_guest_details' WHERE id=?");
            $book->bind_param('i', $refund['booking_id']); $book->execute(); $book->close();
            $conn->commit();
        } catch (Throwable $e) { $conn->rollback(); throw $e; }

        $base = rtrim((string)(getenv('PUBLIC_APP_URL') ?: 'http://localhost/SantaBeachClub-BookingSystem'), '/');
        $frontendBase = str_ends_with($base, '/frontend') ? $base : $base . '/frontend';
        $link = $frontendBase . '/refund_details.php?token=' . rawurlencode($token);
        $mail = sendManualRefundDetailsEmail($refund['guest_email'], $refund['guest_name'], 'REF-' . str_pad((string)$refund['booking_id'], 3, '0', STR_PAD_LEFT), (float)$refund['net_refund'], $link);
        if (!$mail['success']) throw new RuntimeException('The link was created, but email delivery failed. Generate a new link after checking SMTP.');
        $routeNote = $isPayMongo ? ' Manual fallback selected for a PayMongo GCash payment; no PayMongo refund may also be processed.' : '';
        log_activity($conn, (string)($_SESSION['admin_username'] ?? 'Admin'), 'Manual Refund Link Sent', 'Secure GCash details link sent for refund request #' . $requestId . '.' . $routeNote);
        $response = ['success' => true, 'message' => 'Secure GCash details link emailed to the guest.'];

    } elseif ($action === 'send_otp') {
        manual_refund_post();
        sf_require_refund_method($conn, 'manual_gcash');
        $token = trim((string)($_POST['token'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) throw new RuntimeException('Invalid refund link.');
        $limit = RateLimiter::check($conn, 'refund_otp_send', 10, 3600);
        if (!$limit['allowed']) throw new RuntimeException('Too many OTP requests. Please try again later.');
        RateLimiter::hit($conn, 'refund_otp_send');
        $hash = hash('sha256', $token);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT d.refund_request_id,d.verification_send_count,d.verification_last_sent_at,d.verification_locked_at,b.guest_phone,b.guest_country,b.guest_name,r.booking_id FROM manual_refund_details d JOIN refund_requests r ON r.id=d.refund_request_id JOIN bookings b ON b.id=r.booking_id WHERE d.token_hash=? AND d.token_expires_at>NOW() AND d.submitted_at IS NULL AND d.phone_verified_at IS NULL AND r.status='awaiting_guest_details' LIMIT 1 FOR UPDATE");
            $stmt->bind_param('s', $hash); $stmt->execute(); $refund = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$refund) throw new RuntimeException('This refund link is expired, already used, or invalid.');
            if (!empty($refund['verification_locked_at'])) throw new RuntimeException('Phone verification is locked. Please contact the resort.');
            if ((int)$refund['verification_send_count'] >= 3) throw new RuntimeException('The OTP send limit has been reached. Please contact the resort.');
            if (!empty($refund['verification_last_sent_at']) && strtotime($refund['verification_last_sent_at']) > time() - 60) throw new RuntimeException('Please wait 60 seconds before requesting another OTP.');
            $defaultDialCode = strcasecmp(trim((string)($refund['guest_country'] ?? '')), 'Philippines') === 0 ? '+63' : '';
            $phone = sf_normalize_e164((string)$refund['guest_phone'], $defaultDialCode);
            if (!$phone) throw new RuntimeException('The booking does not have a valid saved phone number. Please contact the resort.');
            if (!sf_sms_provider_configured()) throw new RuntimeException('Phone verification is temporarily unavailable. Please contact the resort.');
            $otp = (string)random_int(100000, 999999);
            $sent = sf_send_sms($phone, "Santa Fe Beach Club: Your refund verification code is {$otp}. It expires in 5 minutes. Never share this code.");
            if (!$sent['success']) throw new RuntimeException('We could not send the verification code. Please contact the resort.');
            $otpHash = password_hash($otp, PASSWORD_DEFAULT); $expires = date('Y-m-d H:i:s', time() + 300);
            $update = $conn->prepare('UPDATE manual_refund_details SET verification_otp_hash=?,verification_otp_expires_at=?,verification_attempts=0,verification_send_count=verification_send_count+1,verification_last_sent_at=NOW() WHERE refund_request_id=?');
            $update->bind_param('ssi', $otpHash, $expires, $refund['refund_request_id']); $update->execute(); $update->close();
            $conn->commit();
            SecurityLogger::log($conn, 'REFUND_OTP_SENT', 'Refund phone verification code sent for request #' . $refund['refund_request_id'] . '.', SecurityLogger::LEVEL_INFO, 'Guest');
            $response = ['success' => true, 'message' => 'A verification code was sent to the saved phone number.'];
        } catch (Throwable $e) { $conn->rollback(); throw $e; }

    } elseif ($action === 'verify_otp') {
        manual_refund_post();
        $token = trim((string)($_POST['token'] ?? '')); $otp = trim((string)($_POST['otp'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $token) || !preg_match('/^\d{6}$/', $otp)) throw new RuntimeException('Enter the six-digit verification code.');
        $limit = RateLimiter::check($conn, 'refund_otp_verify', 20, 900);
        if (!$limit['allowed']) throw new RuntimeException('Too many verification attempts. Please try again later.');
        RateLimiter::hit($conn, 'refund_otp_verify');
        $hash = hash('sha256', $token);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT d.refund_request_id,d.verification_otp_hash,d.verification_otp_expires_at,d.verification_attempts,d.verification_locked_at FROM manual_refund_details d JOIN refund_requests r ON r.id=d.refund_request_id WHERE d.token_hash=? AND d.token_expires_at>NOW() AND d.submitted_at IS NULL AND d.phone_verified_at IS NULL AND r.status='awaiting_guest_details' LIMIT 1 FOR UPDATE");
            $stmt->bind_param('s', $hash); $stmt->execute(); $refund = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$refund) throw new RuntimeException('This refund link is expired, already used, or invalid.');
            if (!empty($refund['verification_locked_at']) || (int)$refund['verification_attempts'] >= 5) throw new RuntimeException('Phone verification is locked. Please contact the resort.');
            $valid = !empty($refund['verification_otp_hash']) && !empty($refund['verification_otp_expires_at']) && strtotime($refund['verification_otp_expires_at']) >= time() && password_verify($otp, $refund['verification_otp_hash']);
            if (!$valid) {
                $attempts = (int)$refund['verification_attempts'] + 1;
                $failed = $conn->prepare('UPDATE manual_refund_details SET verification_attempts=?,verification_locked_at=IF(? >= 5,NOW(),verification_locked_at) WHERE refund_request_id=?');
                $failed->bind_param('iii', $attempts, $attempts, $refund['refund_request_id']); $failed->execute(); $failed->close();
                $conn->commit();
                SecurityLogger::log($conn, $attempts >= 5 ? 'REFUND_OTP_LOCKED' : 'REFUND_OTP_FAILED', 'Failed refund OTP verification for request #' . $refund['refund_request_id'] . '.', $attempts >= 5 ? SecurityLogger::LEVEL_WARNING : SecurityLogger::LEVEL_INFO, 'Guest');
                throw new RuntimeException($attempts >= 5 ? 'Phone verification is locked. Please contact the resort.' : 'The verification code is incorrect or expired.');
            }
            $verified = $conn->prepare('UPDATE manual_refund_details SET phone_verified_at=NOW(),verification_otp_hash=NULL,verification_otp_expires_at=NULL,verification_attempts=0 WHERE refund_request_id=?');
            $verified->bind_param('i', $refund['refund_request_id']); $verified->execute(); $verified->close(); $conn->commit();
            SecurityLogger::log($conn, 'REFUND_PHONE_VERIFIED', 'Saved booking phone verified for refund request #' . $refund['refund_request_id'] . '.', SecurityLogger::LEVEL_INFO, 'Guest');
            $response = ['success' => true, 'message' => 'Phone verified. You can now enter your GCash details.'];
        } catch (Throwable $e) { try { $conn->rollback(); } catch (Throwable $ignored) {} throw $e; }

    } elseif ($action === 'reset_verification') {
        manual_refund_admin(); manual_refund_post();
        $requestId = (int)($_POST['request_id'] ?? 0);
        $stmt = $conn->prepare('UPDATE manual_refund_details SET verification_otp_hash=NULL,verification_otp_expires_at=NULL,verification_attempts=0,verification_send_count=0,verification_last_sent_at=NULL,phone_verified_at=NULL,verification_locked_at=NULL WHERE refund_request_id=? AND submitted_at IS NULL');
        $stmt->bind_param('i', $requestId); $stmt->execute();
        if ($stmt->affected_rows !== 1) throw new RuntimeException('Verification could not be reset for this request.');
        $stmt->close();
        $actor = (string)($_SESSION['admin_username'] ?? 'Admin');
        SecurityLogger::log($conn, 'REFUND_VERIFICATION_RESET', 'Refund phone verification reset for request #' . $requestId . '.', SecurityLogger::LEVEL_WARNING, $actor);
        $response = ['success' => true, 'message' => 'Guest phone verification was reset.'];

    } elseif ($action === 'guest_submit') {
        manual_refund_post();
        sf_require_refund_method($conn, 'manual_gcash');
        $token = trim((string)($_POST['token'] ?? ''));
        $name = trim(preg_replace('/\s+/', ' ', (string)($_POST['gcash_account_name'] ?? '')));
        $mobile = preg_replace('/\D+/', '', (string)($_POST['gcash_mobile'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) throw new RuntimeException('Invalid refund link.');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) throw new RuntimeException('Enter the GCash account holder name.');
        if (!preg_match('/^09\d{9}$/', $mobile)) throw new RuntimeException('Enter a valid 11-digit Philippine mobile number beginning with 09.');
        if (empty($_POST['ownership_confirmed'])) throw new RuntimeException('Confirm that you own or are authorized to use this GCash account.');
        $hash = hash('sha256', $token);
        $stmt = $conn->prepare("SELECT d.refund_request_id,r.booking_id FROM manual_refund_details d JOIN refund_requests r ON r.id=d.refund_request_id
            WHERE d.token_hash=? AND d.token_expires_at>NOW() AND d.submitted_at IS NULL AND d.phone_verified_at IS NOT NULL AND r.status='awaiting_guest_details' LIMIT 1");
        $stmt->bind_param('s', $hash); $stmt->execute(); $refund = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$refund) throw new RuntimeException('Phone verification is required before submitting GCash details.');
        $conn->begin_transaction();
        try {
            $update = $conn->prepare("UPDATE manual_refund_details SET gcash_account_name=?,gcash_mobile=?,ownership_confirmed=1,submitted_at=NOW(),token_hash=NULL WHERE refund_request_id=? AND submitted_at IS NULL");
            $update->bind_param('ssi', $name, $mobile, $refund['refund_request_id']); $update->execute();
            if ($update->affected_rows !== 1) throw new RuntimeException('Refund details were already submitted.');
            $update->close();
            $status = $conn->prepare("UPDATE refund_requests SET status='details_submitted' WHERE id=? AND status='awaiting_guest_details'");
            $status->bind_param('i', $refund['refund_request_id']); $status->execute(); $status->close();
            $book = $conn->prepare("UPDATE bookings SET refund_status='details_submitted' WHERE id=?");
            $book->bind_param('i', $refund['booking_id']); $book->execute(); $book->close();
            $conn->commit();
        } catch (Throwable $e) { $conn->rollback(); throw $e; }
        unset($_SESSION['manual_refund_token']);
        $response = ['success' => true, 'message' => 'Your GCash details were submitted securely. The resort will verify and process the refund.'];

    } elseif ($action === 'complete') {
        manual_refund_admin(); manual_refund_post();
        sf_require_refund_method($conn, 'manual_gcash');
        $requestId = (int)($_POST['request_id'] ?? 0);
        $reference = strtoupper(trim((string)($_POST['transfer_reference'] ?? '')));
        $notes = trim((string)($_POST['notes'] ?? ''));
        if (!preg_match('/^[A-Z0-9][A-Z0-9 -]{5,99}$/', $reference)) throw new RuntimeException('Enter a valid GCash transfer reference.');
        $file = $_FILES['receipt'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 2 * 1024 * 1024) throw new RuntimeException('Upload the GCash receipt (JPG or PNG, up to 2 MB).');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if (!$ext) throw new RuntimeException('The receipt must be a genuine JPG or PNG image.');
        $filename = 'refund_' . $requestId . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (!store_private_receipt($file['tmp_name'], $filename)) throw new RuntimeException('Could not store the receipt securely.');
        $receiptPath = 'storage/receipts/' . $filename;
        $actor = (string)($_SESSION['admin_username'] ?? 'Admin');
        $confirmationToken = bin2hex(random_bytes(32));
        $confirmationHash = hash('sha256', $confirmationToken);
        $confirmationExpires = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare("SELECT r.booking_id,r.payment_id,r.net_refund,b.guest_name,b.guest_email FROM refund_requests r JOIN manual_refund_details d ON d.refund_request_id=r.id JOIN bookings b ON b.id=r.booking_id WHERE r.id=? AND r.status='details_submitted' AND d.submitted_at IS NOT NULL FOR UPDATE");
            $stmt->bind_param('i', $requestId); $stmt->execute(); $refund = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$refund) throw new RuntimeException('Submitted manual refund details were not found.');
            $detail = $conn->prepare('UPDATE manual_refund_details SET transfer_reference=?,receipt_path=?,processed_by=?,processed_at=NOW(),notes=?,confirmation_token_hash=?,confirmation_token_expires_at=?,guest_confirmed_at=NULL WHERE refund_request_id=? AND processed_at IS NULL');
            $detail->bind_param('ssssssi', $reference, $receiptPath, $actor, $notes, $confirmationHash, $confirmationExpires, $requestId); $detail->execute();
            if ($detail->affected_rows !== 1) throw new RuntimeException('This refund was already processed.');
            $detail->close();
            $status = $conn->prepare("UPDATE refund_requests SET status='completed',completed_at=NOW() WHERE id=? AND status='details_submitted'");
            $status->bind_param('i', $requestId); $status->execute(); $status->close();
            $book = $conn->prepare("UPDATE bookings SET refund_status='completed' WHERE id=?");
            $book->bind_param('i', $refund['booking_id']); $book->execute(); $book->close();
            $payment = $conn->prepare("UPDATE payments SET status='refunded' WHERE id=? AND booking_id=? AND status='verified'");
            $payment->bind_param('ii', $refund['payment_id'], $refund['booking_id']); $payment->execute();
            if ($payment->affected_rows !== 1) throw new RuntimeException('The payment could not be marked refunded.');
            $payment->close();
            $conn->commit();
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ignored) {}
            $stored = private_storage_file('receipts/' . $filename); if ($stored) @unlink($stored);
            throw $e;
        }
        log_activity($conn, $actor, 'Manual GCash Refund Completed', 'Refund request #' . $requestId . ' completed with reference ' . $reference . '.');
        $base = rtrim((string)(getenv('PUBLIC_APP_URL') ?: 'http://localhost/SantaBeachClub-BookingSystem'), '/');
        $frontendBase = str_ends_with($base, '/frontend') ? $base : $base . '/frontend';
        $confirmationLink = $frontendBase . '/refund_confirmation.php?token=' . rawurlencode($confirmationToken);
        if (!empty($refund['guest_email'])) {
            @sendRefundCompletedEmail($refund['guest_email'], $refund['guest_name'], 'REF-' . str_pad((string)$refund['booking_id'], 3, '0', STR_PAD_LEFT), (float)$refund['net_refund'], 'Manual GCash transfer reference ' . $reference, $confirmationLink);
        }
        $response = ['success' => true, 'message' => 'Manual GCash refund completed. A confirmation link was emailed to the guest.'];
    }
} catch (Throwable $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
