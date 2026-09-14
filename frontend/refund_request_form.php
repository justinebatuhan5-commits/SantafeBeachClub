<?php
/**
 * Guest Refund Request Form
 * Allows guests to submit refund requests for their bookings
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success_message = '';
$error_message = '';
$booking = null;

// Get booking from token (same as cancel_booking.php)
$token = trim($_REQUEST['token'] ?? '');

if ($token) {
    $stmt = $conn->prepare("
        SELECT b.id, b.guest_name, b.guest_email, b.accommodation_name, 
               b.check_in, b.check_out, b.status, b.cancellation_token,
               p.id as payment_id, p.amount
        FROM bookings b
        LEFT JOIN payments p ON b.id = p.booking_id
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

// Handle refund request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_refund_request']) && $booking && !$error_message) {
    $reason = trim($_POST['reason'] ?? '');
    $requested_by = $booking['guest_name'];

    if (empty($reason)) {
        $error_message = 'Please provide a reason for your refund request.';
    } else {
        try {
            // Check for existing pending request
            $check_stmt = $conn->prepare("
                SELECT id, status FROM refund_requests
                WHERE booking_id = ? AND status IN ('requested','approved','processing')
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
                $payment_id    = (int)($booking['payment_id'] ?? 0);
                $request_type  = 'guest';

                // Insert refund request
                $req_stmt = $conn->prepare("
                    INSERT INTO refund_requests
                        (booking_id, payment_id, requested_by, request_type, status, reason,
                         refund_amount, refund_fee, net_refund)
                    VALUES (?, ?, ?, ?, 'requested', ?, ?, ?, ?)
                ");
                $req_stmt->bind_param("iisssddd",
                    $booking['id'], $payment_id, $requested_by,
                    $request_type, $reason,
                    $refund_amount, $refund_fee, $net_refund
                );
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Refund - Santa Fe Beach Club</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #FAF6F0 0%, #F5EBE0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            width: 100%;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #7C533C 0%, #6B472E 100%);
            padding: 32px 28px;
            color: #fff;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .body {
            padding: 28px;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert.success {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            color: #047857;
        }

        .alert.error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-weight: 600;
        }

        .info-value {
            color: #1e293b;
            font-weight: 500;
            text-align: right;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            color: #1e293b;
            resize: vertical;
            min-height: 100px;
            transition: all 0.3s ease;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #7c533c;
            box-shadow: 0 0 0 3px rgba(124, 83, 60, 0.1);
        }

        .char-count {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .policy-info {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #92400e;
            line-height: 1.6;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: #7c533c;
            color: #fff;
        }

        .btn-primary:hover {
            background: #6b472e;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(124, 83, 60, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .success-message {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .success-message h2 {
            font-size: 20px;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .success-message p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .reference-box {
            background: #f1f5f9;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }

        .reference-box .label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .reference-box .value {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            font-family: 'Courier New', monospace;
        }

        @media (max-width: 640px) {
            .header {
                padding: 24px;
            }

            .body {
                padding: 20px;
            }

            .button-group {
                flex-direction: column;
            }

            .info-row {
                flex-direction: column;
                gap: 4px;
            }

            .info-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🔄 Request Refund</h1>
                <p>Submit a refund request for your booking</p>
            </div>

            <div class="body">
                <?php if ($success_message): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
                    <div class="success-message">
                        <div class="success-icon">✓</div>
                        <h2>Request Submitted</h2>
                        <p>Thank you for submitting your refund request. Our management team will review your request and contact you within 24 hours with a decision.</p>
                        <div class="reference-box">
                            <div class="label">Booking Reference</div>
                            <div class="value"><?php echo htmlspecialchars($submitted_ref ?? ''); ?></div>
                        </div>
                        <p style="color: #94a3b8; font-size: 13px;">
                            We've notified our management team. You'll receive a decision within 24 hours.
                        </p>
                        <a href="/" class="btn btn-primary" style="margin-top: 16px;">Back to Home</a>
                    </div>
                <?php elseif ($error_message): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
                    <div style="text-align: center; padding: 20px 0;">
                        <a href="/" class="btn btn-secondary" style="max-width: 200px;">Back to Home</a>
                    </div>
                <?php elseif ($booking): ?>
                    <form method="POST">
                        <!-- Booking Info -->
                        <div class="info-box">
                            <div class="info-row">
                                <span class="info-label">Accommodation</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['accommodation_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Check-in Date</span>
                                <span class="info-value"><?php echo date('M j, Y', strtotime($booking['check_in'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Check-out Date</span>
                                <span class="info-value"><?php echo date('M j, Y', strtotime($booking['check_out'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Booking Reference</span>
                                <span class="info-value" style="font-family: 'Courier New', monospace;">REF-<?php echo str_pad($booking['id'], 3, '0', STR_PAD_LEFT); ?></span>
                            </div>
                        </div>

                        <!-- Policy Info -->
                        <div class="policy-info">
                            <strong>📋 Refund Policy:</strong> Your refund amount is based on our cancellation policy and when the refund is requested relative to your check-in date.
                        </div>

                        <!-- Refund Reason -->
                        <div class="form-group">
                            <label for="reason">Why are you requesting a refund? *</label>
                            <textarea 
                                id="reason" 
                                name="reason" 
                                placeholder="Please tell us the reason for your refund request. This helps us understand your needs and improve our service..."
                                required
                                onkeyup="updateCharCount()"></textarea>
                            <div class="char-count">
                                <span id="charCount">0</span> / 500 characters
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="button-group">
                            <a href="/" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="submit_refund_request" class="btn btn-primary">
                                Submit Request
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert error">
                        <?php echo $error_message ?: 'Unable to load booking information. Please check your booking confirmation email for the correct link.'; ?>
                    </div>
                    <div style="text-align: center; padding: 20px 0;">
                        <a href="/" class="btn btn-secondary" style="max-width: 200px;">Back to Home</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function updateCharCount() {
            const textarea = document.getElementById('reason');
            const count = textarea.value.length;
            const maxCount = 500;
            document.getElementById('charCount').textContent = Math.min(count, maxCount);
            
            if (count > maxCount) {
                textarea.value = textarea.value.substring(0, maxCount);
                document.getElementById('charCount').textContent = maxCount;
            }
        }
    </script>
</body>
</html>
