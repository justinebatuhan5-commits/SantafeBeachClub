<?php
/**
 * Controller for frontend/refund_request_form.php
 */

function sfbc_handle_refund_request_page(mysqli $conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $success_message = '';
    $error_message = '';
    $submitted_ref = '';
    $submitted_email = '';
    $booking = null;

    $schema_name = $conn->query('SELECT DATABASE()')->fetch_row()[0];
    $preference_check = $conn->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name='refund_requests' AND column_name='preferred_refund_method'");
    $preference_check->bind_param('s', $schema_name);
    $preference_check->execute();
    $refund_preference_ready = ((int)$preference_check->get_result()->fetch_row()[0] === 1);
    $preference_check->close();
    $refund_methods = sf_refund_method_settings($conn);

    // Get booking from token (same as cancel_booking.php)
    $token = trim($_REQUEST['token'] ?? '');

    if ($token) {
        $stmt = $conn->prepare("
            SELECT b.id, b.guest_name, b.guest_email, b.accommodation_name,
                   b.check_in, b.check_out, b.status, b.cancellation_token,
                   p.id as payment_id, p.amount, p.payment_method
            FROM bookings b
            LEFT JOIN payments p ON p.id = (
                SELECT p2.id FROM payments p2
                WHERE p2.booking_id = b.id AND p2.status = 'verified'
                ORDER BY p2.paid_at DESC, p2.id DESC LIMIT 1
            )
            WHERE b.cancellation_token = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            $error_message = 'Invalid or expired booking link. Please access this page using the link provided in your booking confirmation email.';
        } elseif ($booking['status'] === 'Checked In' || $booking['status'] === 'Checked Out') {
            $error_message = 'Cannot request a refund after check-in has commenced.';
        }
    }

    $requestPaymentMethod = (string)($booking['payment_method'] ?? '');
    $originalUsesPayMongo = stripos($requestPaymentMethod, 'PayMongo') !== false;
    $originalRefundAvailable = !$originalUsesPayMongo || $refund_methods['paymongo'];
    $manualGcashAllowed = !$originalUsesPayMongo || stripos($requestPaymentMethod, 'GCash') !== false;
    $manualRefundAvailable = $refund_preference_ready && $refund_methods['manual_gcash'] && $manualGcashAllowed;
    $hasAvailableRefundMethod = $originalRefundAvailable || $manualRefundAvailable;

    // Handle refund request submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_refund_request']) && $booking && !$error_message) {
        $reason = trim($_POST['reason'] ?? '');
        $preferred_method = in_array(($_POST['preferred_refund_method'] ?? ''), ['original_source', 'manual_gcash'], true)
            ? $_POST['preferred_refund_method'] : 'original_source';
        $requested_by = $booking['guest_name'];

        if (empty($reason)) {
            $error_message = 'Please provide a reason for your refund request.';
        } elseif ($preferred_method === 'original_source' && !$originalRefundAvailable) {
            $error_message = 'PayMongo refunds are temporarily unavailable. Please select an available refund method.';
        } elseif ($preferred_method === 'manual_gcash' && !$manualRefundAvailable) {
            $error_message = 'Manual GCash refunds are temporarily unavailable for this payment.';
        } elseif (!$hasAvailableRefundMethod) {
            $error_message = 'Refund requests are temporarily unavailable because all eligible refund methods are disabled.';
        } else {
            try {
                // Check for existing pending request
                $check_stmt = $conn->prepare("
                    SELECT id, status FROM refund_requests
                    WHERE booking_id = ? AND status IN ('requested','approved','awaiting_guest_details','details_submitted','processing')
                    LIMIT 1
                ");
                $check_stmt->bind_param("i", $booking['id']);
                $check_stmt->execute();
                $existing = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();

                if ($existing) {
                    $error_message = 'A refund request is already pending for this booking (Status: ' . htmlspecialchars($existing['status']) . ')';
                } else {
                    // Calculate refund based on cancellation policy
                    $policy = sf_get_cancellation_policy($booking['check_in'], $booking['check_out']);
                    $refund_percentage = $policy['refund_percentage'] ?? 0;
                    $total_amount = (float)($booking['amount'] ?? 0);

                    if ($total_amount <= 0) {
                        throw new Exception('No payment found for this booking.');
                    }

                    $refund_amount = ($total_amount * $refund_percentage) / 100;
                    $refund_fee    = $total_amount - $refund_amount;
                    $net_refund    = $refund_amount;
                    if ($net_refund < 0.01) {
                        throw new Exception('This booking is outside the refundable cancellation window, so no refund request can be submitted.');
                    }
                    $payment_id    = (int)($booking['payment_id'] ?? 0);
                    $request_type  = 'guest';

                    // Insert refund request
                    if ($refund_preference_ready) {
                        $req_stmt = $conn->prepare("INSERT INTO refund_requests (booking_id,payment_id,requested_by,request_type,status,reason,refund_amount,refund_fee,net_refund,preferred_refund_method) VALUES (?,?,?,?,'requested',?,?,?,?,?)");
                        $req_stmt->bind_param("iisssddds", $booking['id'], $payment_id, $requested_by, $request_type, $reason, $refund_amount, $refund_fee, $net_refund, $preferred_method);
                    } else {
                        $req_stmt = $conn->prepare("INSERT INTO refund_requests (booking_id,payment_id,requested_by,request_type,status,reason,refund_amount,refund_fee,net_refund) VALUES (?,?,?,?,'requested',?,?,?,?)");
                        $req_stmt->bind_param("iisssddd", $booking['id'], $payment_id, $requested_by, $request_type, $reason, $refund_amount, $refund_fee, $net_refund);
                    }
                    $req_stmt->execute();
                    $refund_request_id = $conn->insert_id;
                    $req_stmt->close();

                    // Update booking with refund tracking
                    $upd = $conn->prepare("UPDATE bookings SET refund_request_id = ?, refund_status = 'requested' WHERE id = ?");
                    $upd->bind_param("ii", $refund_request_id, $booking['id']);
                    $upd->execute();
                    $upd->close();

                    // Notify admin via notifications table
                    $notif_title = 'Refund Request Submitted';
                    $notif_msg   = "{$booking['guest_name']} requested a refund for booking REF-" .
                                   str_pad($booking['id'], 3, '0', STR_PAD_LEFT) .
                                   ". Amount: ₱" . number_format($refund_amount, 2) .
                                   " (Policy: {$policy['policy_name']})";
                    $notif_type  = 'alert';
                    $notif_stmt  = $conn->prepare("INSERT INTO notifications (title,message,type,booking_id) VALUES (?,?,?,?)");
                    $notif_stmt->bind_param("sssi", $notif_title, $notif_msg, $notif_type, $booking['id']);
                    $notif_stmt->execute();
                    $notif_stmt->close();

                    // Send confirmation email to guest
                    if (!empty($booking['guest_email'])) {
                        @sendRefundRequestConfirmationEmail(
                            $booking['guest_email'],
                            $booking['guest_name'],
                            'REF-' . str_pad($booking['id'], 3, '0', STR_PAD_LEFT),
                            $net_refund,
                            $reason
                        );
                    }

                    $success_message = 'Your refund request has been submitted! Our management team will review it and notify you within 24 hours.';
                    $submitted_ref   = 'REF-' . str_pad($booking['id'], 3, '0', STR_PAD_LEFT);
                    $submitted_email = $booking['guest_email'];
                    $booking = null; // clear form
                }
            } catch (Exception $e) {
                $error_message = 'An error occurred: ' . $e->getMessage();
            }
        }
    }

    return compact(
        'success_message',
        'error_message',
        'submitted_ref',
        'submitted_email',
        'booking',
        'refund_preference_ready',
        'refund_methods',
        'token',
        'originalRefundAvailable',
        'manualGcashAllowed',
        'manualRefundAvailable',
        'hasAvailableRefundMethod'
    );
}
