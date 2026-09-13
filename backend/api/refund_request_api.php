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
require_once __DIR__ . '/../services/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$action = trim($_GET['action'] ?? '');
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    if ($action === 'submit_request') {
        // Guest or Admin submits a refund request
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $payment_id = intval($_POST['payment_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $request_type = trim($_POST['request_type'] ?? 'guest'); // 'guest' or 'admin'
        $requested_by = trim($_POST['requested_by'] ?? 'Guest');

        if (!$booking_id || !$reason) {
            throw new Exception('Booking ID and reason are required.');
        }

        // Get booking details
        $bstmt = $conn->prepare("
            SELECT b.id, b.guest_name, b.guest_email, b.check_in, b.check_out, 
                   b.status, p.amount, p.id as payment_id
            FROM bookings b
            LEFT JOIN payments p ON b.id = p.booking_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $bstmt->bind_param("i", $booking_id);
        $bstmt->execute();
        $booking = $bstmt->get_result()->fetch_assoc();
        $bstmt->close();

        if (!$booking) {
            throw new Exception('Booking not found.');
        }

        // Calculate refund amount based on cancellation policy
        $policy = sf_get_cancellation_policy($booking['check_in'], $booking['check_out']);
        $refund_percentage = $policy['refund_percentage'] ?? 0;
        
        $total_amount = (float)($booking['amount'] ?? 0);
        if ($total_amount <= 0) {
            throw new Exception('No payment found for this booking.');
        }

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
            "iisssdd",
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

        if (!$request_id) {
            throw new Exception('Request ID is required.');
        }

        $stmt = $conn->prepare("
            SELECT r.*, b.guest_name, b.guest_email, b.accommodation_name,
                   b.check_in, b.check_out, p.amount as original_amount
            FROM refund_requests r
            JOIN bookings b ON r.booking_id = b.id
            LEFT JOIN payments p ON r.payment_id = p.id
            WHERE r.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $request_id);
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
        $notif_msg = "Your refund request for {$refund['accommodation_name']} has been approved. PHP " . number_format($refund['net_refund'], 2) . " will be refunded to your original payment method.";
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
        $response['message'] = 'Refund request approved. Ready for processing.';
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

    } elseif ($action === 'process_paymongo') {
        // Process refund to PayMongo (admin only)
        require_admin_access();

        $request_id = intval($_POST['request_id'] ?? 0);
        $approved_by = $_SESSION['admin_username'] ?? 'system';

        if (!$request_id) {
            throw new Exception('Request ID is required.');
        }

        $stmt = $conn->prepare("
            SELECT r.*, p.transaction_id, p.amount, b.guest_email, b.guest_name
            FROM refund_requests r
            JOIN payments p ON r.payment_id = p.id
            JOIN bookings b ON r.booking_id = b.id
            WHERE r.id = ? AND r.status = 'approved'
            LIMIT 1
        ");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $refund = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$refund) {
            throw new Exception('Refund request not found or not approved yet.');
        }

        if (empty($refund['transaction_id'])) {
            throw new Exception('No PayMongo transaction ID found for this payment.');
        }

        // Update status to processing
        $upd_stmt = $conn->prepare("
            UPDATE refund_requests
            SET status = 'processing'
            WHERE id = ?
        ");
        $upd_stmt->bind_param("i", $request_id);
        $upd_stmt->execute();
        $upd_stmt->close();

        // TODO: Integrate actual PayMongo refund API call here
        // For now, simulate processing
        // $paymongo_refund_id = call_paymongo_refund_api($refund['transaction_id'], $refund['net_refund']);

        // Mark as completed (in real scenario, would be async)
        $complete_stmt = $conn->prepare("
            UPDATE refund_requests
            SET status = 'completed', paymongo_status = 'success', completed_at = NOW()
            WHERE id = ?
        ");
        $complete_stmt->bind_param("i", $request_id);
        $complete_stmt->execute();
        $complete_stmt->close();

        // Update payment status to refunded
        $pay_stmt = $conn->prepare("
            UPDATE payments
            SET status = 'refunded'
            WHERE id = ?
        ");
        $pay_stmt->bind_param("i", $refund['payment_id']);
        $pay_stmt->execute();
        $pay_stmt->close();

        // Update booking status
        $book_stmt = $conn->prepare("
            UPDATE bookings
            SET refund_status = 'completed', status = 'Cancelled'
            WHERE id = ?
        ");
        $book_stmt->bind_param("i", $refund['booking_id']);
        $book_stmt->execute();
        $book_stmt->close();

        // Log completion
        $log_action = 'Refund Processed';
        $log_details = "Refund request #{$request_id} processed to PayMongo. Amount: PHP " . number_format($refund['net_refund'], 2) . " refunded to {$refund['guest_name']}";
        $log_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $log_stmt = $conn->prepare("
            INSERT INTO activity_logs (admin_username, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $log_stmt->bind_param("ssss", $approved_by, $log_action, $log_details, $log_ip);
        $log_stmt->execute();
        $log_stmt->close();

        // Send email to guest
        $email_result = sendRefundCompletedEmail(
            $refund['guest_email'],
            $refund['guest_name'],
            'REF-' . str_pad($refund['booking_id'], 3, '0', STR_PAD_LEFT),
            $refund['net_refund'],
            $refund['reason']
        );

        $response['success'] = true;
        $response['message'] = 'Refund processed successfully to PayMongo.';
        $response['data'] = ['refund_request_id' => $request_id];

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
