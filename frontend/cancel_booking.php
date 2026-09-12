<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = trim($_REQUEST['token'] ?? '');
$error = '';
$success_message = '';
$email_notice = '';
$booking = null;
$is_past_deadline = false;
$deadline_str = '';
$hours_remaining = 0;

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
        $booking_ref = 'REF-' . str_pad((int)$booking['id'], 3, '0', STR_PAD_LEFT);

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
                $checkin_fmt = date('M j, Y', strtotime($booking['check_in']));
                $checkout_fmt = date('M j, Y', strtotime($booking['check_out']));

                // 1. Insert alert notification for Admin & Reception
                $notif_title = 'Guest Cancelled Booking';
                $notif_message = $booking['guest_name'] . ' cancelled ' . $booking_ref . ' (' . $booking['accommodation_name'] . ', ' . $checkin_fmt . ' to ' . $checkout_fmt . '). Reason: ' . $final_reason;
                $notif_type = 'alert';
                $notif_stmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
                $notif_stmt->bind_param("sssi", $notif_title, $notif_message, $notif_type, $booking_id);
                $notif_stmt->execute();
                $notif_stmt->close();

                // 2. Insert into Activity Audit Log
                $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $log_action = 'Booking Cancelled (Guest)';
                $log_details = $booking_ref . ' (' . $booking['guest_name'] . ') cancelled for ' . $booking['accommodation_name'] . ' (' . $checkin_fmt . '). Reason: ' . $final_reason;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking - Santa Fe Beach Club</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #84563C;
            --primary-dark: #68412B;
            --primary-light: #FBF7F4;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border: #E2E8F0;
            --card-bg: #FFFFFF;
            --bg: #F8FAFC;
            --radius: 16px;
            --danger: #DC2626;
            --danger-bg: #FEF2F2;
            --danger-border: #FECACA;
            --success: #059669;
            --success-bg: #ECFDF5;
            --warning: #D97706;
            --warning-bg: #FFFBEB;
            --warning-border: #FDE68A;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 30px 16px;
        }

        .cancel-wrap {
            max-width: 680px;
            margin: 0 auto;
            width: 100%;
        }

        .cancel-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .cancel-head {
            padding: 30px 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            position: relative;
        }

        .cancel-head h1 {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .cancel-head p {
            margin: 6px 0 0;
            opacity: 0.9;
            font-size: 13.5px;
        }

        .cancel-body {
            padding: 32px;
        }

        .notice {
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .notice.error {
            background: var(--danger-bg);
            color: #991B1B;
            border: 1px solid var(--danger-border);
        }

        .notice.warning {
            background: var(--warning-bg);
            color: #92400E;
            border: 1px solid var(--warning-border);
        }

        .notice.success {
            background: var(--success-bg);
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .policy-badge-box {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .policy-badge-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: rgba(132, 86, 60, 0.12);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }

        .policy-badge-text h4 {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
        }

        .policy-badge-text p {
            margin: 3px 0 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin: 20px 0 24px;
        }

        .detail {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
        }

        .detail .label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 4px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .detail .value {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Form elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .form-label span.req {
            color: var(--danger);
        }

        .reason-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 14px;
        }

        .reason-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.15s;
            font-size: 13.5px;
            color: var(--text-main);
        }

        .reason-item:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .reason-item input[type="radio"] {
            margin: 0;
            accent-color: var(--primary);
            width: 16px;
            height: 16px;
        }

        .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 13px;
            color: var(--text-main);
            resize: vertical;
            min-height: 70px;
            background: #FFFFFF;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(132, 86, 60, 0.12);
        }

        .btn-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            font-family: inherit;
            border: 0;
            cursor: pointer;
            transition: all 0.18s;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: #B91C1C;
        }

        .btn-secondary {
            background: #F1F5F9;
            color: #475569;
        }
        .btn-secondary:hover {
            background: #E2E8F0;
        }

        .contact-box {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 12px;
            padding: 20px;
            margin-top: 18px;
        }
        .contact-box h4 {
            margin: 0 0 8px;
            font-size: 14px;
            font-weight: 700;
            color: #92400E;
        }
        .contact-box p {
            margin: 0 0 14px;
            font-size: 13px;
            color: #78350F;
            line-height: 1.5;
        }
        .contact-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #FFFFFF;
            border: 1px solid #FCD34D;
            border-radius: 8px;
            color: #92400E;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="cancel-wrap">
        <div class="cancel-card">
            <div class="cancel-head">
                <h1>Reservation Cancellation</h1>
                <p>Santa Fe Beach Club Self-Service Guest Portal</p>
            </div>
            <div class="cancel-body">

                <?php if ($success_message): ?>
                    <!-- Success View -->
                    <div class="notice success">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <div>
                            <strong><?php echo htmlspecialchars($success_message); ?></strong>
                            <?php if ($email_notice): ?>
                                <div style="margin-top:4px; font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($email_notice); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($booking): ?>
                        <div class="details-grid">
                            <div class="detail"><div class="label">Booking Ref</div><div class="value"><?php echo htmlspecialchars($booking_ref); ?></div></div>
                            <div class="detail"><div class="label">Guest</div><div class="value"><?php echo htmlspecialchars($booking['guest_name']); ?></div></div>
                            <div class="detail"><div class="label">Room</div><div class="value"><?php echo htmlspecialchars($booking['accommodation_name']); ?></div></div>
                            <div class="detail"><div class="label">Status</div><div class="value" style="color:var(--danger);">Cancelled</div></div>
                        </div>

                        <?php if (!empty($booking['cancellation_reason'])): ?>
                            <div style="background:#F8FAFC; border:1px solid var(--border); border-radius:10px; padding:12px 16px; margin-bottom:20px; font-size:13px;">
                                <strong style="color:var(--text-muted); text-transform:uppercase; font-size:11px; display:block; margin-bottom:3px;">Reason Provided</strong>
                                <span><?php echo htmlspecialchars($booking['cancellation_reason']); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p style="font-size:13.5px; color:var(--text-muted); line-height:1.5;">
                        We are sorry to see you cancel your stay. If your schedule clears or you wish to plan another beach getaway, we would be delighted to host you.
                    </p>

                    <div class="btn-row">
                        <a href="book.php" class="btn btn-secondary" style="background:var(--primary); color:white;">Book Another Date</a>
                        <a href="index.php" class="btn btn-secondary">Return to Home</a>
                    </div>

                <?php elseif ($error && !$booking): ?>
                    <!-- Invalid Link Error -->
                    <div class="notice error">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div>
                            <strong>Unable to process cancellation</strong>
                            <div style="margin-top:4px; font-size:13px;"><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    </div>
                    <div class="btn-row">
                        <a href="index.php" class="btn btn-secondary">Return to Home</a>
                    </div>

                <?php elseif ($booking): ?>

                    <!-- Active Booking Details -->
                    <div class="details-grid">
                        <div class="detail"><div class="label">Booking Ref</div><div class="value"><?php echo htmlspecialchars($booking_ref); ?></div></div>
                        <div class="detail"><div class="label">Guest Name</div><div class="value"><?php echo htmlspecialchars($booking['guest_name']); ?></div></div>
                        <div class="detail"><div class="label">Room</div><div class="value"><?php echo htmlspecialchars($booking['accommodation_name']); ?></div></div>
                        <div class="detail"><div class="label">Stay Dates</div><div class="value"><?php echo htmlspecialchars($checkin_fmt); ?> &rarr; <?php echo htmlspecialchars($checkout_fmt); ?></div></div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <!-- Booking Already Cancelled / Checked In Error -->
                        <div class="notice error">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                        <div class="btn-row">
                            <a href="index.php" class="btn btn-secondary">Return to Home</a>
                        </div>

                    <?php elseif ($is_past_deadline): ?>
                        <!-- ⚠️ Past Deadline View -->
                        <div class="notice warning">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div>
                                <strong style="font-size:15px;">Online Cancellation Deadline Has Passed</strong>
                                <p style="margin:6px 0 0; font-size:13px; line-height:1.5;">
                                    Self-service online cancellation for this <?php echo (int)$policy_nights; ?>-night stay closed on <strong><?php echo htmlspecialchars($deadline_str); ?></strong> (<?php echo htmlspecialchars($policy_window_label); ?> prior to arrival).
                                </p>
                            </div>
                        </div>

                        <div class="contact-box">
                            <h4>Need to cancel or reschedule your arrival?</h4>
                            <p>
                                Because your arrival date is coming up soon, please contact our Front Desk team directly. We are happy to assist you with special requests or date changes.
                            </p>
                            <div class="contact-links">
                                <a href="tel:+639505223146" class="contact-btn">📞 Call Front Desk (+63 950 522 3146)</a>
                                <a href="mailto:Justinebatuhan017@gmail.com?subject=Cancellation%20Request%20<?php echo urlencode($booking_ref); ?>" class="contact-btn">📧 Email Front Desk</a>
                            </div>
                        </div>

                        <div class="btn-row" style="margin-top:22px;">
                            <a href="index.php" class="btn btn-secondary">Return to Home</a>
                        </div>

                    <?php else: ?>
                        <!-- ✅ Within Cancellation Window: Form -->
                        <div class="policy-badge-box">
                            <div class="policy-badge-icon">⏰</div>
                            <div class="policy-badge-text">
                                <h4>Free Cancellation Open (<?php echo $hours_remaining >= 24 ? $days_remaining . ' days left' : $hours_remaining . ' hrs left'; ?>)</h4>
                                <p>Self-service cancellation for your <?php echo (int)$policy_nights; ?>-night stay closes on <strong><?php echo htmlspecialchars($deadline_str); ?></strong> (<?php echo htmlspecialchars($policy_window_label); ?> before check-in).</p>
                            </div>
                        </div>

                        <?php if (!empty($form_error)): ?>
                            <div class="notice error">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <div><?php echo htmlspecialchars($form_error); ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="cancel_booking.php" id="cancelForm">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="confirm_cancel" value="1">

                            <div class="form-group">
                                <label class="form-label">Please select your reason for cancellation <span class="req">*</span></label>
                                <div class="reason-options">
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Change of travel plans" required>
                                        <span>Change of travel plans / scheduling conflict</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Personal or medical emergency">
                                        <span>Personal or medical emergency</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Transportation / flight issues">
                                        <span>Transportation, ferry, or flight disruption</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Found alternative accommodation">
                                        <span>Found alternative accommodation</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Booked wrong dates by mistake">
                                        <span>Booked wrong dates / accidental reservation</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Other" id="reasonOtherRadio">
                                        <span>Other reason</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Additional details / note <span style="font-weight:400; color:var(--text-muted);">(Optional)</span></label>
                                <textarea name="custom_details" class="form-textarea" placeholder="Help us improve our service with any extra details or feedback..."></textarea>
                            </div>

                            <p style="font-size:12.5px; color:var(--text-muted); margin:18px 0; line-height:1.5; display:flex; align-items:center; gap:6px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span><strong>Note:</strong> Once confirmed, this reservation will be released immediately and a cancellation confirmation will be emailed to your inbox.</span>
                            </p>

                            <div class="btn-row">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this reservation? This cannot be undone.');">
                                    Confirm Cancellation
                                </button>
                                <a href="index.php" class="btn btn-secondary">Keep Reservation</a>
                            </div>
                        </form>

                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
