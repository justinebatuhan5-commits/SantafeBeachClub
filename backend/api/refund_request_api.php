<?php
/**
 * Refund Request API - Handle multi-step refund approval workflow
 * - Submit refund requests (guest or admin)
 * - Manager approval/rejection
 * - PayMongo refund processing
 * - Refund status tracking
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/api_auth_helper.php';
require_once __DIR__ . '/../helpers/csrf_helper.php';
require_once __DIR__ . '/../services/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$action = trim($_GET['action'] ?? '');
$response = ['success' => false, 'message' => '', 'data' => null];

function refund_admin_authenticated(): bool
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function refund_request_token(): string
{
    $token = trim((string)($_POST['booking_token'] ?? $_GET['booking_token'] ?? ''));
    return preg_match('/\A[a-f0-9]{32}\z/', $token) ? $token : '';
}

function require_refund_state_change_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
        exit;
    }
    if (!verify_csrf_token()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh and try again.']);
        exit;
    }
}

if (in_array($action, ['submit_request', 'approve', 'reject', 'cancel_approval', 'process_paymongo'], true)) {
    require_refund_state_change_request();
}

try {
    if ($action === 'submit_request') {
        // Admins may select a booking by ID. Guests must present the random
        // cancellation token and the booking ID is then derived from it.
        $is_admin = refund_admin_authenticated();
        $booking_id = $is_admin ? intval($_POST['booking_id'] ?? 0) : 0;
        $booking_token = refund_request_token();
        $reason = trim($_POST['reason'] ?? '');

        if ($reason === '') {
            throw new Exception('A refund reason is required.');
        }
        if (!$is_admin && $booking_token === '') {
            http_response_code(401);
            throw new Exception('A valid booking refund token is required.');
        }
        if ($is_admin && !$booking_id) {
            throw new Exception('Booking ID is required.');
        }

        // Resolve the booking from trusted server state. The guest path never
        // accepts a numeric booking ID as proof of ownership.
        $bstmt = $conn->prepare("
            SELECT b.id, b.guest_name, b.guest_email, b.check_in, b.check_out,
                   b.status
            FROM bookings b
            WHERE " . ($is_admin ? 'b.id = ?' : 'b.cancellation_token = ?') . "
            LIMIT 1
        ");
        if ($is_admin) {
            $bstmt->bind_param('i', $booking_id);
        } else {
            $bstmt->bind_param('s', $booking_token);
        }
        $bstmt->execute();
        $booking = $bstmt->get_result()->fetch_assoc();
        $bstmt->close();

        if (!$booking) {
            throw new Exception('Booking not found.');
        }
        $booking_id = (int)$booking['id'];
        $request_type = $is_admin ? 'admin' : 'guest';
        $requested_by = $is_admin
            ? (string)($_SESSION['admin_username'] ?? 'Administrator')
            : (string)$booking['guest_name'];

        // Never accept payment_id or amount from the client. Select a verified
        // payment that belongs to the authorized booking.
        $pstmt = $conn->prepare("
            SELECT id, amount
            FROM payments
            WHERE booking_id = ? AND status = 'verified'
            ORDER BY paid_at DESC, id DESC
            LIMIT 1
        ");
        $pstmt->bind_param('i', $booking_id);
        $pstmt->execute();
        $payment = $pstmt->get_result()->fetch_assoc();
        $pstmt->close();

        if (!$payment || (float)$payment['amount'] <= 0) {
            throw new Exception('No verified payment found for this booking.');
        }
        $payment_id = (int)$payment['id'];

        // Calculate refund amount based on cancellation policy
        $policy = sf_get_cancellation_policy($booking['check_in'], $booking['check_out']);
        $refund_percentage = $policy['refund_percentage'] ?? 0;
        
        $total_amount = (float)$payment['amount'];

        $refund_amount = ($total_amount * $refund_percentage) / 100;
        $refund_fee = $total_amount - $refund_amount;

        // Check if refund request already exists and is pending
        $check_stmt = $conn->prepare("
            SELECT id, status FROM refund_requests 
            WHERE booking_id = ? AND status IN ('requested', 'approved', 'processing')
            LIMIT 1
        ");
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($existing) {
            throw new Exception('A refund request is already pending for this booking (Status: ' . $existing['status'] . ')');
        }

        // Create refund request
        $req_stmt = $conn->prepare("
            INSERT INTO refund_requests (
                booking_id, payment_id, requested_by, request_type, status, reason,
                refund_amount, refund_fee, net_refund
            ) VALUES (?, ?, ?, ?, 'requested', ?, ?, ?, ?)
        ");
        $net_refund = $refund_amount;
        $req_stmt->bind_param(
            "iisssddd",
            $booking_id,
            $payment_id,
            $requested_by,
            $request_type,
            $reason,
            $refund_amount,
            $refund_fee,
            $net_refund
        );
        $req_stmt->execute();
        $refund_request_id = $conn->insert_id;
        $req_stmt->close();

        if (!$refund_request_id) {
            throw new Exception('Failed to create refund request.');
        }

        // Update booking with refund tracking
        $update_stmt = $conn->prepare("
            UPDATE bookings 
            SET refund_request_id = ?, refund_status = 'requested'
            WHERE id = ?
        ");
        $update_stmt->bind_param("ii", $refund_request_id, $booking_id);
        $update_stmt->execute();
        $update_stmt->close();

        // Log to activity_logs
        $admin_user = $_SESSION['admin_username'] ?? $_SESSION['receptionist_username'] ?? $requested_by;
        $log_action = 'Refund Request Submitted';
        $log_details = "Booking REF-" . str_pad($booking_id, 3, '0', STR_PAD_LEFT) . " - Reason: {$reason}. Amount: PHP " . number_format($refund_amount, 2) . " (Policy: {$policy['policy_name']})";
        $log_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $log_stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_username, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $log_stmt->bind_param("ssss", $admin_user, $log_action, $log_details, $log_ip);
        $log_stmt->execute();
        $log_stmt->close();

        // Create notification for admin
        $notif_title = 'Refund Request Submitted';
        $notif_msg = "{$booking['guest_name']} has requested a refund for booking REF-" . str_pad($booking_id, 3, '0', STR_PAD_LEFT) . ". Amount: PHP " . number_format($refund_amount, 2) . " (Fee: PHP " . number_format($refund_fee, 2) . ")";
        $notif_type = 'alert';
        
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications (title, message, type, booking_id)
            VALUES (?, ?, ?, ?)
        ");
        $notif_stmt->bind_param("sssi", $notif_title, $notif_msg, $notif_type, $booking_id);
        $notif_stmt->execute();
        $notif_stmt->close();

        // Send confirmation email to guest
        if (!empty($booking['guest_email'])) {
            @sendRefundRequestConfirmationEmail(
                $booking['guest_email'],
                $booking['guest_name'],
                'REF-' . str_pad($booking_id, 3, '0', STR_PAD_LEFT),
                $net_refund,
                $reason
            );
        }

        $response['success'] = true;
        $response['message'] = 'Refund request submitted successfully. Awaiting manager approval.';
        $response['data'] = [
            'refund_request_id' => $refund_request_id,
            'refund_amount' => $refund_amount,
            'refund_fee' => $refund_fee,
            'policy' => $policy['policy_name']
        ];

    } elseif ($action === 'get_request') {
        // Get refund request details
        $request_id = intval($_GET['request_id'] ?? 0);
        $is_admin = refund_admin_authenticated();
        $booking_token = refund_request_token();

        if (!$request_id) {
            throw new Exception('Request ID is required.');
        }
        if (!$is_admin && $booking_token === '') {
            http_response_code(401);
            throw new Exception('Authentication or a valid booking refund token is required.');
        }

        $stmt = $conn->prepare("
            SELECT r.*, b.guest_name, b.guest_email, b.accommodation_name,
                   b.check_in, b.check_out, p.amount as original_amount
            FROM refund_requests r
            JOIN bookings b ON r.booking_id = b.id
            LEFT JOIN payments p ON r.payment_id = p.id
            WHERE r.id = ?
              " . ($is_admin ? '' : 'AND b.cancellation_token = ?') . "
            LIMIT 1
        ");
        if ($is_admin) {
            $stmt->bind_param('i', $request_id);
        } else {
            $stmt->bind_param('is', $request_id, $booking_token);
        }
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            throw new Exception('Refund request not found.');
        }

        $response['success'] = true;
        $response['data'] = $request;

    } elseif ($action === 'approve') {
        // Manager approves a refund request
        require_admin_access();

        $request_id = intval($_POST['request_id'] ?? 0);
        $approved_by = $_SESSION['admin_username'] ?? 'system';

        if (!$request_id) {
            throw new Exception('Request ID is required.');
        }

        $stmt = $conn->prepare("
            SELECT r.*, b.guest_name, b.guest_email, b.accommodation_name,
                   p.transaction_id, p.amount
            FROM refund_requests r
            JOIN bookings b ON r.booking_id = b.id
            LEFT JOIN payments p ON r.payment_id = p.id
            WHERE r.id = ? AND r.status = 'requested'
            LIMIT 1
        ");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $refund = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$refund) {
            throw new Exception('Refund request not found or already processed.');
        }

        // Update refund request status
        $upd_stmt = $conn->prepare("
            UPDATE refund_requests
            SET status = 'approved', approved_by = ?, approved_at = NOW()
            WHERE id = ?
        ");
        $upd_stmt->bind_param("si", $approved_by, $request_id);
        $upd_stmt->execute();
        $upd_stmt->close();

        // Update booking refund status
        $bupd_stmt = $conn->prepare("
            UPDATE bookings
            SET refund_status = 'approved'
            WHERE id = ?
        ");
        $bupd_stmt->bind_param("i", $refund['booking_id']);
        $bupd_stmt->execute();
        $bupd_stmt->close();

        // Log approval
        $log_action = 'Refund Approved';
        $log_details = "Refund request #{$request_id} for booking REF-" . str_pad($refund['booking_id'], 3, '0', STR_PAD_LEFT) . " approved. Amount: PHP " . number_format($refund['net_refund'], 2);
        $log_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $log_stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_username, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $log_stmt->bind_param("ssss", $approved_by, $log_action, $log_details, $log_ip);
        $log_stmt->execute();
        $log_stmt->close();

        // Send notification to guest
        $notif_title = 'Refund Request Approved';
        $notif_msg = "Your refund request for {$refund['accommodation_name']} has been approved for PHP " . number_format($refund['net_refund'], 2) . ". Payment processing is pending and the front desk will notify you when the refund has actually been issued.";
        $notif_type = 'success';
        
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications (title, message, type, booking_id)
            VALUES (?, ?, ?, ?)
        ");
        $notif_stmt->bind_param("sssi", $notif_title, $notif_msg, $notif_type, $refund['booking_id']);
        $notif_stmt->execute();
        $notif_stmt->close();

        // Send email to guest
        if (!empty($refund['guest_email'])) {
            @sendRefundApprovedEmail(
                $refund['guest_email'],
                $refund['guest_name'],
                'REF-' . str_pad($refund['booking_id'], 3, '0', STR_PAD_LEFT),
                (float)$refund['net_refund']
            );
        }

        $response['success'] = true;
        $response['message'] = 'Refund request approved. Payment processing remains pending.';
        $response['data'] = ['refund_request_id' => $request_id];

    } elseif ($action === 'reject') {
        // Manager rejects a refund request
        require_admin_access();

        $request_id = intval($_POST['request_id'] ?? 0);
        $rejection_reason = trim($_POST['rejection_reason'] ?? 'No reason provided');
        $approved_by = $_SESSION['admin_username'] ?? 'system';

        if (!$request_id) {
            throw new Exception('Request ID is required.');
        }

        $stmt = $conn->prepare("
            SELECT r.*, b.guest_name, b.guest_email, b.accommodation_name
            FROM refund_requests r
            JOIN bookings b ON r.booking_id = b.id
            WHERE r.id = ? AND r.status = 'requested'
            LIMIT 1
        ");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $refund = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$refund) {
            throw new Exception('Refund request not found or already processed.');
        }

        // Update refund request status
        $upd_stmt = $conn->prepare("
            UPDATE refund_requests
            SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ?
            WHERE id = ?
        ");
        $upd_stmt->bind_param("ssi", $approved_by, $rejection_reason, $request_id);
        $upd_stmt->execute();
        $upd_stmt->close();

        // Update booking refund status
        $bupd_stmt = $conn->prepare("
            UPDATE bookings
            SET refund_status = 'rejected'
            WHERE id = ?
        ");
        $bupd_stmt->bind_param("i", $refund['booking_id']);
        $bupd_stmt->execute();
        $bupd_stmt->close();

        // Log rejection
        $log_action = 'Refund Rejected';
        $log_details = "Refund request #{$request_id} for booking REF-" . str_pad($refund['booking_id'], 3, '0', STR_PAD_LEFT) . " rejected. Reason: {$rejection_reason}";
        $log_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $log_stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_username, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $log_stmt->bind_param("ssss", $approved_by, $log_action, $log_details, $log_ip);
        $log_stmt->execute();
        $log_stmt->close();

        // Send notification to guest
        $notif_title = 'Refund Request Rejected';
        $notif_msg = "Your refund request for {$refund['accommodation_name']} has been rejected. Reason: {$rejection_reason}";
        $notif_type = 'warning';
        
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications (title, message, type, booking_id)
            VALUES (?, ?, ?, ?)
        ");
        $notif_stmt->bind_param("sssi", $notif_title, $notif_msg, $notif_type, $refund['booking_id']);
        $notif_stmt->execute();
        $notif_stmt->close();

        // Send email to guest
        if (!empty($refund['guest_email'])) {
            @sendRefundRejectedEmail(
                $refund['guest_email'],
                $refund['guest_name'],
                'REF-' . str_pad($refund['booking_id'], 3, '0', STR_PAD_LEFT),
                $rejection_reason
            );
        }

        $response['success'] = true;
        $response['message'] = 'Refund request rejected.';

    } elseif ($action === 'cancel_approval') {
        // Revoke an approval before any external refund has been processed.
        require_admin_access();
        $request_id = intval($_POST['request_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? 'Refund approval cancelled by management.'));
        $actor = (string)($_SESSION['admin_username'] ?? 'system');
        if (!$request_id || $reason === '') throw new Exception('Request ID and reason are required.');

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                SELECT r.booking_id, b.guest_name, b.guest_email, b.accommodation_name
                FROM refund_requests r
                JOIN bookings b ON b.id = r.booking_id
                WHERE r.id = ? AND r.status = 'approved'
                FOR UPDATE
            ");
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $refund = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$refund) throw new RuntimeException('Only an approved, unprocessed refund can be cancelled.');

            $update = $conn->prepare("
                UPDATE refund_requests
                SET status='rejected', rejection_reason=?, approved_by=?, approved_at=NOW()
                WHERE id=? AND status='approved'
            ");
            $update->bind_param('ssi', $reason, $actor, $request_id);
            $update->execute();
            if ($update->affected_rows !== 1) throw new RuntimeException('Refund approval was not cancelled.');
            $update->close();

            $bookingUpdate = $conn->prepare("UPDATE bookings SET refund_status='rejected' WHERE id=?");
            $bookingUpdate->bind_param('i', $refund['booking_id']);
            $bookingUpdate->execute();
            $bookingUpdate->close();

            $actionName = 'Refund Approval Cancelled';
            $details = 'Refund request #' . $request_id . ' was cancelled before payment processing. Reason: ' . $reason;
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log = $conn->prepare("INSERT INTO activity_logs (admin_username,action,details,ip_address) VALUES (?,?,?,?)");
            $log->bind_param('ssss', $actor, $actionName, $details, $ip);
            $log->execute();
            $log->close();

            $title = 'Refund Approval Cancelled';
            $message = "The approved refund request for {$refund['accommodation_name']} was cancelled. Reason: {$reason}";
            $type = 'warning';
            $notification = $conn->prepare("INSERT INTO notifications (title,message,type,booking_id) VALUES (?,?,?,?)");
            $notification->bind_param('sssi', $title, $message, $type, $refund['booking_id']);
            $notification->execute();
            $notification->close();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        if (!empty($refund['guest_email'])) {
            @sendRefundRejectedEmail(
                $refund['guest_email'],
                $refund['guest_name'],
                'REF-' . str_pad($refund['booking_id'], 3, '0', STR_PAD_LEFT),
                $reason
            );
        }
        $response['success'] = true;
        $response['message'] = 'Refund approval cancelled. The booking and payment were not changed.';

    } elseif ($action === 'process_paymongo') {
        // Deliberately disabled until a real PayMongo refund request and its
        // provider response can be verified. This branch must not mutate any
        // refund, payment, or booking record.
        require_admin_access();
        http_response_code(501);
        $response['message'] = 'PayMongo refund processing is not available yet. No records or payment statuses were changed.';

    } elseif ($action === 'list_pending') {
        // Get all pending refund requests (admin only)
        require_admin_access();

        $status = trim($_GET['status'] ?? 'requested');

        $query = "
            SELECT r.id, r.booking_id, r.requested_by, r.status, r.reason,
                   r.refund_amount, r.refund_fee, r.net_refund,
                   r.approved_by, r.approved_at, r.requested_at,
                   b.guest_name, b.guest_email, b.accommodation_name,
                   b.check_in, b.check_out
            FROM refund_requests r
            JOIN bookings b ON r.booking_id = b.id
            WHERE r.status = ?
            ORDER BY r.requested_at DESC
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $refunds = [];

        while ($row = $result->fetch_assoc()) {
            $refunds[] = $row;
        }
        $stmt->close();

        $response['success'] = true;
        $response['data'] = $refunds;

    } else {
        throw new Exception('Invalid action.');
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
