<?php
/**
 * Controller for frontend/cancel_booking.php
 */

function sfbc_handle_cancel_booking_page(mysqli $conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = trim($_REQUEST['token'] ?? '');
    $error = '';
    $form_error = '';
    $success_message = '';
    $email_notice = '';
    $booking = null;
    $is_past_deadline = false;
    $deadline_str = '';
    $hours_remaining = 0;
    $days_remaining = 0;
    $policy_nights = 1;
    $policy_window_label = '';
    $policy_name = '';

    // Cancellation policy window: 48 hours before check-in (14:00)
    $CANCELLATION_DEADLINE_HOURS = 48;

    if ($token === '') {
        $error = 'Missing cancellation link. Please access this page using the cancellation link provided in your booking confirmation email.';
    } else {
        $stmt = $conn->prepare("SELECT id, guest_name, guest_email, accommodation_name, check_in, check_out, status, cancellation_token, cancellation_reason, cancelled_at FROM bookings WHERE cancellation_token = ? LIMIT 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $booking = $result->fetch_assoc();

            if ($booking['status'] === 'Checked In' || $booking['status'] === 'Checked Out') {
                $error = 'This reservation cannot be cancelled because the stay has already commenced or been completed.';
            } elseif ($booking['status'] === 'Cancelled') {
                $error = 'This booking has already been cancelled' . ($booking['cancelled_at'] ? ' on ' . date('M j, Y g:i A', strtotime($booking['cancelled_at'])) : '') . '.';
                if (!empty($booking['cancellation_reason'])) {
                    $error .= ' (Reason: ' . htmlspecialchars($booking['cancellation_reason']) . ')';
                }
            } else {
                // Dynamic Cancellation Policy based on length of stay
                $policy = sf_get_cancellation_policy($booking['check_in'], $booking['check_out']);
                $is_past_deadline = $policy['is_expired'];
                $deadline_str = $policy['deadline_formatted'];
                $hours_remaining = $policy['hours_left'];
                $days_remaining = $policy['days_left'];
                $policy_nights = $policy['nights'];
                $policy_window_label = $policy['window_label'];
                $policy_name = $policy['policy_name'];
            }
        } else {
            $error = 'We could not find a reservation matching this cancellation link.';
        }
        $stmt->close();
    }

    // Handle cancellation POST submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel']) && $booking && empty($error)) {
        if ($is_past_deadline) {
            $error = 'Self-service cancellation has expired for this reservation because it is within 48 hours of check-in.';
        } else {
            $reason_choice = trim($_POST['reason_choice'] ?? '');
            $custom_details = trim($_POST['custom_details'] ?? '');

            if (empty($reason_choice)) {
                $form_error = 'Please select a reason for cancelling your reservation.';
            } else {
                $final_reason = $reason_choice;
                if ($reason_choice === 'Other' && !empty($custom_details)) {
                    $final_reason = 'Other: ' . $custom_details;
                } elseif (!empty($custom_details)) {
                    $final_reason .= ' (' . $custom_details . ')';
                }

                $booking_ref = 'REF-' . str_pad((int)$booking['id'], 3, '0', STR_PAD_LEFT);
                $booking_id = (int)$booking['id'];

                $cancel_stmt = $conn->prepare("UPDATE bookings SET status = 'Cancelled', cancelled_at = NOW(), cancellation_reason = ? WHERE id = ? AND status IN ('Pending', 'Pending Payment', 'Confirmed')");
                $cancel_stmt->bind_param("si", $final_reason, $booking_id);
                $cancel_stmt->execute();

                if ($cancel_stmt->affected_rows === 1) {
                    $checkin_fmt_log = date('M j, Y', strtotime($booking['check_in']));
                    $checkout_fmt_log = date('M j, Y', strtotime($booking['check_out']));

                    // 1. Insert alert notification for Admin & Reception
                    $notif_title = 'Guest Cancelled Booking';
                    $notif_message = $booking['guest_name'] . ' cancelled ' . $booking_ref . ' (' . $booking['accommodation_name'] . ', ' . $checkin_fmt_log . ' to ' . $checkout_fmt_log . '). Reason: ' . $final_reason;
                    $notif_type = 'alert';
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
                    $notif_stmt->bind_param("sssi", $notif_title, $notif_message, $notif_type, $booking_id);
                    $notif_stmt->execute();
                    $notif_stmt->close();

                    // 2. Insert into Activity Audit Log
                    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $log_action = 'Booking Cancelled (Guest)';
                    $log_details = $booking_ref . ' (' . $booking['guest_name'] . ') cancelled for ' . $booking['accommodation_name'] . ' (' . $checkin_fmt_log . '). Reason: ' . $final_reason;
                    $log_user = 'Guest Self-Service';
                    $log_stmt = $conn->prepare("INSERT INTO activity_logs (admin_username, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $log_stmt->bind_param("ssss", $log_user, $log_action, $log_details, $client_ip);
                    $log_stmt->execute();
                    $log_stmt->close();

                    // 3. Send email to guest
                    if (!empty($booking['guest_email'])) {
                        $email_result = sendBookingCancellationEmail(
                            $booking['guest_email'],
                            $booking['guest_name'],
                            $booking_ref,
                            $booking['accommodation_name'],
                            $booking['check_in'],
                            $booking['check_out'],
                            $final_reason
                        );

                        $email_notice = $email_result['success']
                            ? 'A cancellation confirmation email has been sent to ' . htmlspecialchars($booking['guest_email']) . '.'
                            : 'Your booking has been cancelled, but confirmation email could not be delivered.';
                    }

                    $success_message = 'Your booking has been cancelled successfully.';
                    $booking['status'] = 'Cancelled';
                    $booking['cancellation_reason'] = $final_reason;
                } else {
                    $error = 'This booking could not be cancelled. It may have already been cancelled or updated by staff.';
                }

                $cancel_stmt->close();
            }
        }
    }

    $booking_ref = $booking ? 'REF-' . str_pad((int)$booking['id'], 3, '0', STR_PAD_LEFT) : '';
    $checkin_fmt = $booking ? date('D, d M Y', strtotime($booking['check_in'])) : '';
    $checkout_fmt = $booking ? date('D, d M Y', strtotime($booking['check_out'])) : '';

    return compact(
        'token',
        'error',
        'form_error',
        'success_message',
        'email_notice',
        'booking',
        'is_past_deadline',
        'deadline_str',
        'hours_remaining',
        'days_remaining',
        'policy_nights',
        'policy_window_label',
        'policy_name',
        'booking_ref',
        'checkin_fmt',
        'checkout_fmt',
        'CANCELLATION_DEADLINE_HOURS'
    );
}
