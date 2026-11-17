<?php
declare(strict_types=1);

/**
 * chatbot_api.php — Native PHP Endpoint for the Intelligent Virtual Guest Assistant
 *
 * Actions (POST JSON or form-encoded):
 *  - message              : Send a guest message and receive a grounded AI response
 *  - feedback             : Rate a bot message (-1 or +1)
 *  - handoff              : Escalate conversation transcript to human staff (inquiries + notifications)
 *  - track_booking_click  : Increment chat-to-booking conversion counter
 *  - request_booking_otp  : Initiate email OTP verification for "My Booking" lookup in chat
 *  - verify_booking_otp   : Complete email OTP verification and return masked booking summary
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/security_headers.php';
require_once __DIR__ . '/../helpers/csrf_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../helpers/guest_auth_helper.php';
require_once __DIR__ . '/../helpers/otp_helper.php';
require_once __DIR__ . '/../services/mailer.php';
require_once __DIR__ . '/../services/chatbot_service.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Parse JSON body or fallback to $_POST
$rawBody = file_get_contents('php://input');
$input = [];
if (is_string($rawBody) && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

// Verify CSRF token from JSON body, POST field, or X-CSRF-Token header
$csrfToken = isset($input['csrf_token']) ? (string)$input['csrf_token'] : null;
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Security validation failed (invalid or expired CSRF token). Please refresh the page.',
    ]);
    exit;
}

$action = trim((string)($input['action'] ?? 'message'));

try {
    if ($action === 'feedback') {
        RateLimiter::enforce($conn, 'chatbot_feedback', 30, 60);
        $messageId = (int)($input['message_id'] ?? 0);
        $rating = (int)($input['feedback'] ?? 0);
        if (!in_array($rating, [-1, 1], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid feedback value.']);
            exit;
        }
        $ok = sf_chatbot_record_feedback($conn, $messageId, $rating);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if ($action === 'list_faqs') {
        RateLimiter::enforce($conn, 'chatbot_faqs', 60, 60);
        $lang = isset($input['language']) && in_array($input['language'], sf_chatbot_supported_languages(), true)
            ? (string)$input['language']
            : 'English';
        $category = isset($input['category']) ? trim((string)$input['category']) : '';

        $sql = "SELECT id, category, question, answer, language FROM chatbot_knowledge WHERE is_active = 1";
        if ($category !== '') {
            $sql .= " AND category = '" . $conn->real_escape_string($category) . "'";
        }
        $sql .= " ORDER BY category ASC, id ASC LIMIT 50";
        $res = $conn->query($sql);
        $faqs = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $faqs[] = $row;
            }
        }
        echo json_encode(['success' => true, 'faqs' => $faqs], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reset_context') {
        $_SESSION['chatbot_history'] = [];
        $_SESSION['chatbot_session_token'] = bin2hex(random_bytes(32));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'track_booking_click') {
        $conv = sf_chatbot_get_or_create_conversation($conn, 'English');
        $convId = (int)($conv['id'] ?? 0);
        if ($convId > 0 && sf_chatbot_tables_ready($conn)) {
            $stmt = $conn->prepare("UPDATE chatbot_conversations SET booking_link_clicks = booking_link_clicks + 1 WHERE id = ?");
            $stmt->bind_param('i', $convId);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'handoff') {
        RateLimiter::enforce($conn, 'chatbot_handoff', 25, 600);
        $guestName = strip_tags(trim((string)($input['guest_name'] ?? '')));
        $guestEmail = trim((string)($input['guest_email'] ?? ''));
        $note = strip_tags(trim((string)($input['note'] ?? '')));
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }
        $res = sf_chatbot_create_handoff($conn, $guestName, $guestEmail, $note);
        if (empty($res['success'])) {
            http_response_code(400);
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'sync_messages') {
        RateLimiter::enforce($conn, 'chatbot_sync', 60, 60);
        $afterId = max(0, (int)($input['after_id'] ?? 0));
        $payload = sf_chatbot_get_guest_sync_payload($conn, $afterId);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'resume_ai') {
        RateLimiter::enforce($conn, 'chatbot_sync', 30, 60);
        $res = sf_chatbot_set_guest_live_mode($conn, false);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'request_booking_otp') {
        RateLimiter::enforce($conn, 'guest_lookup', 10, 900);
        $email = trim((string)($input['email'] ?? ''));
        $ref = strtoupper(trim((string)($input['booking_ref'] ?? '')));

        if ($email === '' || $ref === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Please enter both a valid booking reference (e.g. REF-001) and email address.']);
            exit;
        }
        if (!preg_match('/^REF-(\d+)$/i', $ref, $m)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Booking reference must be in REF-001 format.']);
            exit;
        }
        $bookingId = (int)$m[1];

        $stmt = $conn->prepare("
            SELECT id, guest_name, guest_email, guest_type, accommodation_name, room_id,
                   check_in, check_out, guests_count, eta, status, cancellation_token,
                   checkin_token, payment_method, guest_phone
            FROM bookings
            WHERE id = ? AND LOWER(guest_email) = LOWER(?)
            LIMIT 1
        ");
        $stmt->bind_param('is', $bookingId, $email);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            echo json_encode(['success' => false, 'error' => 'Booking not found. Please check your reference and email.']);
            exit;
        }

        $pStmt = $conn->prepare("SELECT id AS pay_id, amount, payment_method, transaction_id, status AS payment_status, paid_at FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
        $pStmt->bind_param('i', $bookingId);
        $pStmt->execute();
        $payment = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();

        $booking['booking_ref'] = 'REF-' . str_pad((string)$booking['id'], 3, '0', STR_PAD_LEFT);

        $rawOtp = otp_generate();
        otp_store_for_guest((int)$booking['id'], $rawOtp, $conn);
        otp_send_email($email, $rawOtp, (string)$booking['guest_name']);

        $_SESSION['mfa_pending_guest'] = [
            'booking' => $booking,
            'payment' => $payment,
            'booking_id' => (int)$booking['id'],
            'guest_email' => $email,
            'email_hint' => mask_email($email),
            'otp_sent_at' => time(),
        ];

        echo json_encode([
            'success' => true,
            'mfa_pending' => true,
            'email_hint' => mask_email($email),
            'message' => 'A 6-digit verification code has been sent to ' . mask_email($email) . '.',
        ]);
        exit;
    }

    if ($action === 'verify_booking_otp') {
        RateLimiter::enforce($conn, 'guest_otp_verify', 10, 600);
        if (empty($_SESSION['mfa_pending_guest'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No verification in progress. Please request a code first.']);
            exit;
        }
        $otp = trim((string)($input['otp'] ?? ''));
        if (!preg_match('/^\d{6}$/', $otp)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Please enter the 6-digit numeric code.']);
            exit;
        }

        $pending = $_SESSION['mfa_pending_guest'];
        $bookingId = (int)$pending['booking_id'];
        $verify = otp_verify_guest($bookingId, $otp, $conn);

        if (!empty($verify['success'])) {
            set_guest_booking([
                'booking' => $pending['booking'],
                'payment' => $pending['payment'],
            ]);
            unset($_SESSION['mfa_pending_guest']);

            $summary = sf_chatbot_send_guest_message($conn, 'Show my verified booking status', 'English');
            echo json_encode([
                'success' => true,
                'verified' => true,
                'reply' => $summary['reply'],
                'suggested_actions' => $summary['suggested_actions'] ?? [],
            ]);
            exit;
        }

        $rem = (int)($verify['remaining'] ?? 0);
        echo json_encode([
            'success' => false,
            'error' => ($verify['reason'] ?? '') === 'expired_or_not_found'
                ? 'Verification code expired. Please request a new code.'
                : "Invalid code. {$rem} attempt(s) remaining.",
        ]);
        exit;
    }

    // Default action: 'message'
    RateLimiter::enforce($conn, 'chatbot_message', 12, 60);

    $message = (string)($input['message'] ?? '');
    // Strip control characters except newlines/tabs
    $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '';
    $message = trim($message);

    if ($message === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please enter a message.']);
        exit;
    }

    if (mb_strlen($message) > 500) {
        $message = mb_substr($message, 0, 500);
    }

    $language = isset($input['language']) && trim((string)$input['language']) !== ''
        ? trim((string)$input['language'])
        : null;

    $response = sf_chatbot_send_guest_message($conn, $message, $language);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Chatbot API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'The resort concierge is temporarily unavailable. Please try again or contact reception.',
    ]);
}
