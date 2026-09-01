<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/mailer.php';

function recordPaymentAction(mysqli $conn, int $paymentId, string $action, string $details = ''): void
{
    $performedBy = $_SESSION['admin_username'] ?? 'System';
    $stmt = $conn->prepare("INSERT INTO payment_action_history (payment_id, action, performed_by, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $paymentId, $action, $performedBy, $details);
    $stmt->execute();
    $stmt->close();
}

// Handle payment processing action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();

    if ($_POST['action'] === 'process_payment' || $_POST['action'] === 'verify_payment') {
        $pay_id = intval($_POST['payment_id']);
        $method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Front Desk Cash';
        
        $stmt = $conn->prepare("UPDATE payments SET status = 'verified', payment_method = ? WHERE id = ?");
        $stmt->bind_param("si", $method, $pay_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("UPDATE bookings SET status = 'Confirmed' WHERE id = (SELECT booking_id FROM payments WHERE id = ?)");
        $stmt->bind_param("i", $pay_id);
        $stmt->execute();

        recordPaymentAction($conn, $pay_id, 'verified', 'Payment verified using ' . $method . '.');

        $booking_id_stmt = $conn->prepare("SELECT booking_id FROM payments WHERE id = ?");
        $booking_id_stmt->bind_param("i", $pay_id);
        $booking_id_stmt->execute();
        $booking_id_result = $booking_id_stmt->get_result()->fetch_assoc();
        $booking_id_stmt->close();
        $verified_booking_id = (int)($booking_id_result['booking_id'] ?? 0);

        // Automatically dispatch confirmation email with check-in QR code pass
        if ($verified_booking_id > 0) {
            $stmt = $conn->prepare("SELECT guest_name, guest_email, accommodation_name, check_in, check_out, cancellation_token, checkin_token FROM bookings WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $verified_booking_id);
            $stmt->execute();
            $b_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($b_data && !empty($b_data['guest_email'])) {
                $b_ref = 'REF-' . str_pad($verified_booking_id, 3, '0', STR_PAD_LEFT);
                $b_base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
                $b_cancellation_token = $b_data['cancellation_token'];
                if (empty($b_cancellation_token)) {
                    $b_cancellation_token = bin2hex(random_bytes(16));
                    $t_stmt = $conn->prepare("UPDATE bookings SET cancellation_token = ? WHERE id = ?");
                    $t_stmt->bind_param("si", $b_cancellation_token, $verified_booking_id);
                    $t_stmt->execute();
                    $t_stmt->close();
                }

                $b_cancellation_url = rtrim($b_base_url, '/') . '/cancel_booking?token=' . urlencode($b_cancellation_token);
                $b_checkin_url = rtrim($b_base_url, '/') . '/checkin?ref=' . urlencode($b_ref) . '&token=' . urlencode($b_data['checkin_token'] ?? '');
                
                $amt_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS amount FROM payments WHERE booking_id = ? AND status = 'verified'");
                $amt_stmt->bind_param("i", $verified_booking_id);
                $amt_stmt->execute();
                $b_amount = (float)($amt_stmt->get_result()->fetch_assoc()['amount'] ?? 0);
                $amt_stmt->close();

                @sendBookingConfirmationEmail(
                    $b_data['guest_email'],
                    $b_data['guest_name'],
                    $b_ref,
                    $b_data['accommodation_name'],
                    $b_data['check_in'],
                    $b_data['check_out'],
                    $b_amount,
                    $b_cancellation_url,
                    $b_checkin_url
                );
            }
        }
        
        header("Location: payments?success=1&send_booking_id=" . $verified_booking_id);
        exit;
    } elseif ($_POST['action'] === 'send_confirmation_email') {
        $booking_id = intval($_POST['booking_id']);
        $stmt = $conn->prepare("SELECT guest_name, guest_email, accommodation_name, check_in, check_out, cancellation_token, checkin_token FROM bookings WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            header("Location: payments?email_error=Booking%20not%20found");
            exit;
        }

        $booking_ref = 'REF-' . str_pad($booking_id, 3, '0', STR_PAD_LEFT);
        $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        $cancellation_token = $booking['cancellation_token'];
        if (empty($cancellation_token)) {
            $cancellation_token = bin2hex(random_bytes(16));
            $token_stmt = $conn->prepare("UPDATE bookings SET cancellation_token = ? WHERE id = ?");
            $token_stmt->bind_param("si", $cancellation_token, $booking_id);
            $token_stmt->execute();
            $token_stmt->close();
        }

        $cancellation_url = rtrim($base_url, '/') . '/cancel_booking?token=' . urlencode($cancellation_token);
        $checkin_url = rtrim($base_url, '/') . '/checkin?ref=' . urlencode($booking_ref) . '&token=' . urlencode($booking['checkin_token'] ?? '');
        $amount_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS amount FROM payments WHERE booking_id = ? AND status = 'verified'");
        $amount_stmt->bind_param("i", $booking_id);
        $amount_stmt->execute();
        $amount = (float)($amount_stmt->get_result()->fetch_assoc()['amount'] ?? 0);
        $amount_stmt->close();

        if (empty($booking['guest_email'])) {
            header("Location: payments?email_error=Guest%20has%20no%20email%20address");
            exit;
        }

        $send_result = sendBookingConfirmationEmail(
            $booking['guest_email'],
            $booking['guest_name'],
            $booking_ref,
            $booking['accommodation_name'],
            $booking['check_in'],
            $booking['check_out'],
            $amount,
            $cancellation_url,
            $checkin_url
        );
        $query_key = $send_result['success'] ? 'email_sent=1' : 'email_error=' . urlencode($send_result['error'] ?? 'Email sending failed');
        header("Location: payments?{$query_key}");
        exit;
    } elseif ($_POST['action'] === 'reject_payment') {
        $pay_id       = intval($_POST['payment_id']);
        $reject_reason = trim($_POST['reject_reason'] ?? '');

        $stmt = $conn->prepare("UPDATE payments SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $pay_id);
        $stmt->execute();

        $reason_log = $reject_reason !== '' ? $reject_reason : 'No reason provided';
        recordPaymentAction($conn, $pay_id, 'rejected', 'Payment rejected. Reason: ' . $reason_log);

        // Fetch booking details before cancelling, so we have guest info for the email
        $rej_stmt = $conn->prepare(
            "SELECT b.id, b.guest_name, b.guest_email, b.accommodation_name, b.check_in, b.check_out
             FROM payments p
             JOIN bookings b ON b.id = p.booking_id
             WHERE p.id = ? LIMIT 1"
        );
        $rej_stmt->bind_param("i", $pay_id);
        $rej_stmt->execute();
        $rej_booking = $rej_stmt->get_result()->fetch_assoc();
        $rej_stmt->close();

        $stmt = $conn->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = (SELECT booking_id FROM payments WHERE id = ?)");
        $stmt->bind_param("i", $pay_id);
        $stmt->execute();

        // Send rejection email to guest with reason (non-fatal — failure doesn't block redirect)
        if ($rej_booking && !empty($rej_booking['guest_email'])) {
            $rej_ref = 'REF-' . str_pad((int)$rej_booking['id'], 3, '0', STR_PAD_LEFT);
            sendPaymentRejectedEmail(
                $rej_booking['guest_email'],
                $rej_booking['guest_name'],
                $rej_ref,
                $rej_booking['accommodation_name'],
                $rej_booking['check_in'],
                $rej_booking['check_out'],
                $reject_reason
            );
        }

        header("Location: payments?rejected=1");
        exit;
    } elseif ($_POST['action'] === 'refund_payment') {
        $pay_id = intval($_POST['payment_id']);
        $refund_reason = trim($_POST['refund_reason'] ?? 'No reason provided');

        $stmt = $conn->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?");
        $stmt->bind_param("i", $pay_id);
        $stmt->execute();

        recordPaymentAction($conn, $pay_id, 'refunded', 'Refund reason: ' . $refund_reason);

        // Get booking ID to update booking status and log
        $bstmt = $conn->prepare("SELECT booking_id, guest_name, amount FROM payments WHERE id = ?");
        $bstmt->bind_param("i", $pay_id);
        $bstmt->execute();
        $prow = $bstmt->get_result()->fetch_assoc();
        $bstmt->close();

        if ($prow) {
            $stmt2 = $conn->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ?");
            $stmt2->bind_param("i", $prow['booking_id']);
            $stmt2->execute();
            $stmt2->close();

            // Log in activity_logs
            $admin_user = $_SESSION['admin_username'] ?? 'system';
            $detail = "Refunded PHP " . number_format($prow['amount'], 2) . " for payment ID {$pay_id} (Guest: {$prow['guest_name']}). Reason: {$refund_reason}";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $logstmt = $conn->prepare("INSERT INTO activity_logs (admin_username, action, details, ip_address) VALUES (?, 'Payment Refunded', ?, ?)");
            $logstmt->bind_param("sss", $admin_user, $detail, $ip);
            $logstmt->execute();
            $logstmt->close();

            // Add notification
            $notif_title = 'Payment Refunded';
            $notif_msg = "Payment INV-100{$pay_id} for guest {$prow['guest_name']} has been marked as refunded. Reason: {$refund_reason}";
            $notif_type = 'warning';
            $nstmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
            $nstmt->bind_param("sssi", $notif_title, $notif_msg, $notif_type, $prow['booking_id']);
            $nstmt->execute();
            $nstmt->close();
        }

        header("Location: payments?refunded=1");
        exit;
    }
}

// Fetch all payment records joined with bookings and rooms
$payments_query = $conn->query("
    SELECT 
        p.id as pay_id,
        p.booking_id,
        COALESCE(NULLIF(p.guest_name, ''), b.guest_name, 'Unknown Guest') as guest_name,
        p.guest_email,
        p.amount,
        p.amount_tendered,
        p.change_amount,
        p.payment_method,
        p.transaction_id,
        p.status as payment_status,
        p.receipt_url,
        p.paid_at,
        b.accommodation_name,
        b.check_in,
        b.check_out,
        DATEDIFF(b.check_out, b.check_in) as nights,
        r.price_per_night
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    LEFT JOIN rooms r ON b.room_id = r.id
    ORDER BY p.id DESC
");

$payment_history = [];
$history_query = $conn->query("SELECT payment_id, action, performed_by, details, performed_at FROM payment_action_history ORDER BY performed_at DESC, id DESC");
if ($history_query) {
    while ($history_row = $history_query->fetch_assoc()) {
        $payment_history[(int)$history_row['payment_id']][] = $history_row;
    }
}

// Pre-fetch all verified payments to group them by booking_id for the receipt breakdown
$breakdown_map = [];
$bk_res = $conn->query("SELECT id as pay_id, booking_id, amount, amount_tendered, change_amount, payment_method, status FROM payments WHERE status = 'verified' ORDER BY id ASC");
if ($bk_res) {
    while($row = $bk_res->fetch_assoc()) {
        $bid = $row['booking_id'];
        if (!isset($breakdown_map[$bid])) $breakdown_map[$bid] = [];
        $breakdown_map[$bid][] = [
            'pay_id' => $row['pay_id'],
            'amount' => floatval($row['amount']),
            'tendered' => floatval($row['amount_tendered'] ?? $row['amount']),
            'change' => floatval($row['change_amount'] ?? 0),
            'method' => $row['payment_method']
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Payment Processing — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=4">
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .reservations-table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }
        .reservations-table th, .reservations-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .reservations-table th {
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        .btn-pay {
            background-color: #2E7D32;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-pay:hover {
            background-color: #1B5E20;
        }
        .btn-receipt {
            background-color: transparent;
            color: #666;
            border: 1px solid #ccc;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-receipt:hover {
            background-color: #f5f5f5;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        .status-paid { background: #E8F5E9; color: #2E7D32; }
        .status-pending { background: #FFF3E0; color: #E65100; }
        .status-rejected { background: #FFEBEE; color: #C62828; }
        .status-refunded { background: #F3E5F5; color: #6A1B9A; }
        .alert-success {
            background-color: #E8F5E9;
            color: #2E7D32;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* Modals & Overlays */
        .gcash-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 99999;
            align-items: center;
            justify-content: center;
        }
        .gcash-overlay.active { display: flex !important; }
        .gcash-modal {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            max-width: 440px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
            animation: popIn 0.2s ease;
        }
        @keyframes popIn {
            from { transform: scale(0.92); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
    </style>
</head>
<body>

    <?php $active_page = 'payments'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <!-- Main Dashboard Panel -->
    <main class="main-content">
        <!-- Top Bar (shared component, same as Dashboard) -->
        <?php
        $page_title = 'Payment Processing';
        $page_subtitle = 'Manage bills and transactions';
        $header_extra_html = '
            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" placeholder="Search invoice..." class="search-input" id="paymentSearch">
            </div>
            <div class="filter-wrapper" style="margin-left: 12px; display: flex; align-items: center;">
                <select id="paymentStatusFilter" style="padding: 8px 12px; border-radius: 8px; border: 1px solid #D1D5DB; outline: none; font-size: 14px; font-family: inherit; color: #374151; background-color: #fff;">
                    <option value="">All Statuses</option>
                    <option value="verified">Verified</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <section class="dashboard-grid" style="grid-template-columns: 1fr;">
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">
                    ✅ Payment verified successfully! Booking has been confirmed.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['email_sent'])): ?>
                <div class="alert-success">
                    ✅ Confirmation email sent to the guest successfully.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['email_error'])): ?>
                <div class="alert-success" style="background-color:#FFEBEE; color:#C62828;">
                    ❌ Confirmation email was not sent: <?php echo htmlspecialchars($_GET['email_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['rejected'])): ?>
                <div class="alert-success" style="background-color:#FFEBEE; color:#C62828;">
                    ❌ Payment rejected. Booking has been cancelled.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['refunded'])): ?>
                <div class="alert-success" style="background-color:#F3E5F5; color:#6A1B9A;">
                    💜 Payment marked as refunded. Booking has been cancelled and activity logged.
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>Outstanding &amp; Settled Bills</h2>
                </div>

                <div class="table-responsive">
                <table class="reservations-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Guest Name</th>
                            <th>Accommodation</th>
                            <th>Payment Channel</th>
                            <th>Total Amount</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Payment History</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($payments_query && $payments_query->num_rows > 0) {
                            while ($row = $payments_query->fetch_assoc()) {
                                $pay_id = htmlspecialchars($row['pay_id']);
                                $name = htmlspecialchars($row['guest_name']);
                                $room = htmlspecialchars($row['accommodation_name'] ?: 'Standard Room');
                                $method = htmlspecialchars($row['payment_method'] ?: 'Pay at Check-in');
                                $txn = htmlspecialchars($row['transaction_id'] ?: ('TXN-' . $pay_id));
                                $amount = number_format($row['amount'], 2);
                                $raw_amount = $row['amount'];
                                $pay_status_display = strtolower($row['payment_status']);
                                $receipt_url = $row['receipt_url'];

                                $pay_class = 'status-pending';
                                if ($pay_status_display === 'verified' || $pay_status_display === 'paid') $pay_class = 'status-paid';
                                if ($pay_status_display === 'rejected') $pay_class = 'status-rejected';
                                if ($pay_status_display === 'refunded') $pay_class = 'status-refunded';

                                echo "<tr>";
                                echo "<td><strong>INV-100{$pay_id}</strong></td>";
                                echo "<td>{$name}</td>";
                                echo "<td>{$room}</td>";
                                echo "<td>
                                        <div style='font-weight:600; font-size:13px;'>{$method}</div>
                                        <div style='font-size:11px; color:#888;'>{$txn}</div>
                                      </td>";
                                echo "<td><strong>PHP {$amount}</strong></td>";
                                
                                if (!empty($receipt_url)) {
                                    $safe_url = htmlspecialchars($receipt_url);
                                    echo "<td><a href='javascript:void(0)' onclick='openProofImageModal(\"{$safe_url}\")' style='color:#007AFF; font-size:12px; font-weight:600; text-decoration:underline;'>View Receipt</a></td>";
                                } else {
                                    echo "<td><span style='color:#999; font-size:12px;'>No Receipt</span></td>";
                                }
                                
                                echo "<td><span class='status-badge {$pay_class}'>".ucfirst($pay_status_display)."</span></td>";
                                echo "<td style='font-size:12px; color:#64748B; min-width:170px;'>";
                                if (!empty($payment_history[(int)$row['pay_id']])) {
                                    foreach ($payment_history[(int)$row['pay_id']] as $history) {
                                        $history_action = ucfirst(htmlspecialchars($history['action']));
                                        $history_by = htmlspecialchars($history['performed_by']);
                                        $history_time = date('M j, Y g:i A', strtotime($history['performed_at']));
                                        echo "<div style='margin-bottom:5px;'><strong style='color:#334155;'>{$history_action}</strong><br><span>by {$history_by} · {$history_time}</span></div>";
                                    }
                                } else {
                                    echo "<span style='color:#94A3B8;'>No actions recorded</span>";
                                }
                                echo "</td>";
                                echo "<td>";
                                
                                if ($pay_status_display === 'pending') {
                                    $csrf = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
                                    echo "
                                    <div style='display:flex; gap:6px; align-items:center; flex-wrap:wrap;'>
                                        <form method='POST' action='payments' style='margin:0; display:flex; gap:6px; align-items:center;'>
                                            <input type='hidden' name='csrf_token' value='{$csrf}'>
                                            <input type='hidden' name='action' value='verify_payment'>
                                            <input type='hidden' name='payment_id' value='{$pay_id}'>
                                            <select name='payment_method' style='padding:6px; font-size:12px; border:1px solid #ccc; border-radius:4px; background:white;'>
                                                 <option value='Front Desk Cash' ".($method=='Front Desk Cash'?'selected':'').">Cash</option>
                                                 <option value='PayMongo (Card)' ".(stripos($method, 'Card')!==false || stripos($method, 'Visa')!==false?'selected':'').">PayMongo (Visa / Card)</option>
                                                 <option value='PayMongo (GCash)' ".(stripos($method, 'PayMongo')!==false && stripos($method, 'GCash')!==false?'selected':'').">PayMongo (GCash)</option>
                                                 <option value='PayMongo (Maya)' ".(stripos($method, 'Maya')!==false?'selected':'').">PayMongo (Maya)</option>
                                                 <option value='GCash QR' ".($method=='GCash' || $method=='GCash QR'?'selected':'').">GCash (Manual QR)</option>
                                                 <option value='Bank Deposit' ".($method=='Bank Deposit'?'selected':'').">Bank Deposit</option>
                                                 <option value='Front Desk Card' ".($method=='Front Desk Card'?'selected':'').">POS Card</option>
                                             </select>
                                            <button type='submit' class='btn-pay' style='padding:6px 12px; font-size:12px;'>Verify</button>
                                        </form>
                                        <button type='button' class='btn-receipt' style='padding:6px 12px; font-size:12px; color:#d32f2f; border-color:#d32f2f;' onclick='openRejectModal({$pay_id}, \"{$name}\", \"INV-100{$pay_id}\")'>Reject</button>
                                    </div>";

                                } elseif ($pay_status_display === 'verified' || $pay_status_display === 'paid') {
                                    $rcpt_num = 'RCPT-' . str_pad($pay_id, 6, '0', STR_PAD_LEFT);
                                    $nights = max(1, intval($row['nights'] ?? 1));
                                    $price_per_night = floatval($row['price_per_night'] ?? 0);
                                    $b_total = ($price_per_night > 0) ? ($price_per_night * $nights) : $raw_amount;
                                    if ($b_total < $raw_amount) $b_total = $raw_amount;
                                    $b_paid = $raw_amount;
                                    $bid = $row['booking_id'];
                                    echo "<div style='display:flex; gap:6px; align-items:center; flex-wrap:wrap;'>";
                                    echo "<button class='btn-receipt' style='padding:6px 12px; font-size:12px;' onclick='openReceiptModal({$bid}, \"{$rcpt_num}\", \"INV-100{$pay_id}\", \"" . addslashes($name) . "\", \"" . addslashes($room) . "\", {$b_total})'>Print Receipt</button>";
                                    echo "<a href='payments?send_booking_id={$bid}' class='btn-pay' style='padding:6px 12px; font-size:12px; text-decoration:none;'>Send Confirmation</a>";
                                    echo "<button class='btn-receipt' style='padding:6px 12px; font-size:12px; color:#6A1B9A; border-color:#6A1B9A;' onclick='openRefundModal({$pay_id}, \"{$name}\", \"PHP {$amount}\")'>Refund</button>";
                                    echo "</div>";
                                } elseif ($pay_status_display === 'refunded') {
                                    echo "<span style='font-size:12px; color:#6A1B9A; font-weight:600;'>💜 Refunded</span>";
                                } else {
                                    echo "<span style='font-size:12px; color:#888;'>Rejected</span>";
                                }
                                
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9' style='text-align: center; color: #888; padding: 20px;'>No payment records found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                </div>
            </div>
        </section>
    </main>

    <?php if (isset($_GET['send_booking_id']) && (int)$_GET['send_booking_id'] > 0): ?>
        <?php
            $send_booking_id = (int)$_GET['send_booking_id'];
            $send_stmt = $conn->prepare("SELECT guest_name, guest_email FROM bookings WHERE id = ? LIMIT 1");
            $send_stmt->bind_param("i", $send_booking_id);
            $send_stmt->execute();
            $send_booking = $send_stmt->get_result()->fetch_assoc();
            $send_stmt->close();
        ?>
        <?php if ($send_booking): ?>
            <div class="gcash-overlay active" id="sendConfirmationOverlay" style="background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 99999;">
                <div style="background: #ffffff; border-radius: 20px; max-width: 450px; width: calc(100% - 32px); padding: 32px 28px 26px; text-align: center; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.25), 0 0 1px rgba(15, 23, 42, 0.1); position: relative; animation: popIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
                    
                    <!-- Close button -->
                    <a href="payments" style="position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border-radius: 50%; background: #F1F5F9; color: #64748B; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 18px; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#E2E8F0'; this.style.color='#0F172A';" onmouseout="this.style.background='#F1F5F9'; this.style.color='#64748B';">&times;</a>

                    <!-- Icon with glow -->
                    <div style="width: 64px; height: 64px; border-radius: 20px; background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 1.5px solid #BFDBFE; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.25);">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                    </div>

                    <h3 style="margin: 0 0 8px; font-size: 20px; font-weight: 800; color: #0F172A; letter-spacing: -0.01em;">Send Confirmation Email?</h3>
                    <p style="margin: 0 0 20px; font-size: 13.5px; color: #64748B; line-height: 1.55;">Payment is verified! Dispatch the booking voucher and check-in QR pass directly to the guest.</p>

                    <!-- Guest Email Box -->
                    <div style="background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 14px; padding: 14px 16px; margin-bottom: 24px; text-align: left; display: flex; align-items: center; gap: 12px;">
                        <div style="width: 38px; height: 38px; border-radius: 10px; background: #0F172A; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0;">
                            <?php echo strtoupper(substr($send_booking['guest_name'] ?: 'G', 0, 1)); ?>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                <strong style="font-size: 13.5px; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($send_booking['guest_name'] ?: 'Guest'); ?></strong>
                                <span style="background: #DCFCE7; color: #15803D; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Verified</span>
                            </div>
                            <div style="font-size: 12.5px; color: #475569; word-break: break-all; font-weight: 500;">
                                <?php echo htmlspecialchars($send_booking['guest_email'] ?: 'No email address on file'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <a href="payments" style="flex: 1; padding: 12px 0; background: #F1F5F9; color: #475569; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='#E2E8F0'; this.style.color='#0F172A';" onmouseout="this.style.background='#F1F5F9'; this.style.color='#475569';">
                            Not Now
                        </a>
                        <?php if (!empty($send_booking['guest_email'])): ?>
                            <form method="POST" action="payments" style="flex: 1.3; margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="send_confirmation_email">
                                <input type="hidden" name="booking_id" value="<?php echo $send_booking_id; ?>">
                                <button type="submit" style="width: 100%; padding: 12px 0; background: linear-gradient(135deg, #15803D, #166534); color: #ffffff; border: none; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 14px rgba(22, 101, 52, 0.35); display: flex; align-items: center; justify-content: center; gap: 6px; transition: transform 0.15s ease, box-shadow 0.15s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(22, 101, 52, 0.45)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(22, 101, 52, 0.35)';">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                                    Send Email
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Proof of Payment Image Lightbox Modal -->
    <div class="gcash-overlay" id="proofOverlay">
        <div class="gcash-modal" style="max-width:500px; padding:20px; text-align:center;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h3 style="font-size:16px; font-weight:700; margin:0;">Guest Payment Proof</h3>
                <button type="button" onclick="closeProofModal()" style="background:none; border:none; font-size:22px; color:#888; cursor:pointer;">&times;</button>
            </div>
            <div style="max-height:70vh; overflow-y:auto; border-radius:8px; background:#f5f5f5; padding:8px;">
                <img id="proofModalImg" src="" alt="Proof of Payment" style="max-width:100%; border-radius:6px; display:block; margin:0 auto;">
            </div>
            <button type="button" class="btn-receipt" onclick="closeProofModal()" style="margin-top:14px; width:100%; padding:9px;">Close</button>
        </div>
    </div>

    <!-- Refund Confirmation Modal -->
    <div class="gcash-overlay" id="refundOverlay">
        <div class="gcash-modal" style="max-width:480px; text-align:left; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,0.25); border:1px solid rgba(0,0,0,0.06); padding:24px 28px; position:relative; overflow:hidden;">
            <!-- Top Accent Bar -->
            <div style="position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #7C3AED, #9333EA);"></div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="assets/logo.png" alt="Santa Fe Beach Club" style="height:38px; width:auto; border-radius:8px; object-fit:contain;" onerror="this.src='assets/logo.jpg';">
                    <div>
                        <h3 style="font-size:17px; font-weight:800; margin:0; color:#1E293B; letter-spacing:-0.3px; display:flex; align-items:center; gap:6px;">
                            <span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:#F3E8FF; color:#7C3AED; font-size:12px;">↩</span>
                            Issue Refund
                        </h3>
                        <div style="font-size:12px; color:#64748B; margin-top:2px;">Santa Fe Beach Club • Billing Management</div>
                    </div>
                </div>
                <button type="button" onclick="closeRefundModal()" style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:50%; font-size:18px; color:#64748B; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s;" onmouseover="this.style.background='#E2E8F0'; this.style.color='#0F172A';" onmouseout="this.style.background='#F1F5F9'; this.style.color='#64748B';">&times;</button>
            </div>

            <div id="refundModalInfo" style="background:linear-gradient(135deg, #FAF5FF, #F3E8FF); border:1px solid #E9D5FF; border-radius:12px; padding:14px 16px; margin-bottom:18px; font-size:13.5px; color:#581C87; line-height:1.6;"></div>

            <form method="POST" action="payments" id="refundForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="refund_payment">
                <input type="hidden" name="payment_id" id="refundPaymentId">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.6px;">Reason for Refund <span style="color:#DC2626;">*</span></label>
                    <select name="refund_reason" id="refundReasonSelect" style="width:100%; padding:11px 14px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; font-family:inherit; color:#1E293B; background:#F8FAFC; cursor:pointer;" onchange="toggleCustomReason(this.value)">
                        <option value="">— Select a reason —</option>
                        <option value="Guest requested cancellation">Guest requested cancellation</option>
                        <option value="Booking error / duplicate">Booking error / duplicate</option>
                        <option value="No show — policy refund">No show — policy refund</option>
                        <option value="Overcharge correction">Overcharge correction</option>
                        <option value="Other">Other (specify below)</option>
                    </select>
                </div>
                <div id="customReasonWrap" style="display:none; margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.6px;">Custom Reason</label>
                    <input type="text" id="customReasonInput" placeholder="Describe the reason..." style="width:100%; padding:11px 14px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; font-family:inherit; box-sizing:border-box; background:#fff;">
                </div>
                <div style="background:#FFF7ED; border:1px solid #FED7AA; border-radius:12px; padding:12px 14px; margin-bottom:20px; font-size:12.5px; color:#9A3412; display:flex; gap:8px; align-items:flex-start;">
                    <span style="font-size:16px; line-height:1;">⚠️</span>
                    <div>This will mark the payment as <strong>Refunded</strong> and cancel the associated booking. This action cannot be undone.</div>
                </div>
                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="closeRefundModal()" style="flex:1; padding:12px; background:#F1F5F9; color:#475569; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#E2E8F0'; this.style.color='#0F172A';" onmouseout="this.style.background='#F1F5F9'; this.style.color='#475569';">Cancel</button>
                    <button type="button" onclick="submitRefund()" style="flex:1.4; padding:12px; background:linear-gradient(135deg, #7C3AED, #6D28D9); color:#fff; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(124,58,237,0.35); transition:all 0.15s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(124,58,237,0.45)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(124,58,237,0.35)';">Confirm Refund</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Payment Modal -->
    <div class="gcash-overlay" id="rejectOverlay">
        <div class="gcash-modal" style="max-width:480px; text-align:left; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,0.25); border:1px solid rgba(0,0,0,0.06); padding:24px 28px; position:relative; overflow:hidden;">
            <!-- Top Accent Bar -->
            <div style="position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg, #DC2626, #EF4444);"></div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="assets/logo.png" alt="Santa Fe Beach Club" style="height:38px; width:auto; border-radius:8px; object-fit:contain;" onerror="this.src='assets/logo.jpg';">
                    <div>
                        <h3 style="font-size:17px; font-weight:800; margin:0; color:#1E293B; letter-spacing:-0.3px; display:flex; align-items:center; gap:6px;">
                            <span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:#FEE2E2; color:#DC2626; font-size:12px;">✕</span>
                            Reject Payment
                        </h3>
                        <div style="font-size:12px; color:#64748B; margin-top:2px;">Santa Fe Beach Club • Payment Desk</div>
                    </div>
                </div>
                <button type="button" onclick="closeRejectModal()" style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:50%; font-size:18px; color:#64748B; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s;" onmouseover="this.style.background='#E2E8F0'; this.style.color='#0F172A';" onmouseout="this.style.background='#F1F5F9'; this.style.color='#64748B';">&times;</button>
            </div>

            <div id="rejectModalInfo" style="background:linear-gradient(135deg, #FEF2F2, #FFF5F5); border:1px solid #FECACA; border-radius:12px; padding:14px 16px; margin-bottom:18px; font-size:13.5px; color:#991B1B; line-height:1.6;"></div>

            <form method="POST" action="payments" id="rejectForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="reject_payment">
                <input type="hidden" name="payment_id" id="rejectPaymentId">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.6px;">Reason for Rejection <span style="color:#DC2626;">*</span></label>
                    <div style="position:relative;">
                        <select name="reject_reason" id="rejectReasonSelect" style="width:100%; padding:11px 14px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; font-family:inherit; color:#1E293B; background:#F8FAFC; cursor:pointer; transition:border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='#DC2626'; this.style.background='#fff';" onblur="this.style.borderColor='#E2E8F0'; this.style.background='#F8FAFC';" onchange="toggleRejectCustomReason(this.value)">
                            <option value="">— Select a reason —</option>
                            <option value="Invalid or unclear proof of payment">Invalid or unclear proof of payment</option>
                            <option value="Payment amount does not match the booking total">Payment amount does not match the booking total</option>
                            <option value="Duplicate payment submission">Duplicate payment submission</option>
                            <option value="Expired payment / payment deadline passed">Expired payment / payment deadline passed</option>
                            <option value="Fraudulent or suspicious transaction">Fraudulent or suspicious transaction</option>
                            <option value="Wrong payment account / reference number">Wrong payment account / reference number</option>
                            <option value="Booking no longer available">Booking no longer available</option>
                            <option value="Other">Other (specify below)</option>
                        </select>
                    </div>
                </div>
                <div id="rejectCustomReasonWrap" style="display:none; margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.6px;">Custom Reason</label>
                    <input type="text" id="rejectCustomReasonInput" placeholder="Describe the reason..." style="width:100%; padding:11px 14px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; font-family:inherit; box-sizing:border-box; background:#fff;">
                </div>
                <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:12px; padding:12px 14px; margin-bottom:20px; font-size:12.5px; color:#92400E; display:flex; gap:8px; align-items:flex-start;">
                    <span style="font-size:16px; line-height:1;">⚠️</span>
                    <div>The guest will receive an email notification with the rejection reason. This will <strong>cancel the booking</strong> and cannot be undone.</div>
                </div>
                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="closeRejectModal()" style="flex:1; padding:12px; background:#F1F5F9; color:#475569; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#E2E8F0'; this.style.color='#0F172A';" onmouseout="this.style.background='#F1F5F9'; this.style.color='#475569';">Cancel</button>
                    <button type="button" onclick="submitReject()" style="flex:1.4; padding:12px; background:linear-gradient(135deg, #DC2626, #B91C1C); color:#fff; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(220,38,38,0.35); transition:all 0.15s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(220,38,38,0.45)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(220,38,38,0.35)';">Confirm Reject &amp; Notify Guest</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Receipt Preview Modal -->
    <div class="gcash-overlay" id="receiptOverlay">
        <div style="background:#ffffff; border-radius:16px; width:92%; max-width:440px; box-shadow:0 12px 48px rgba(0,0,0,0.25); display:flex; flex-direction:column; max-height:90vh; overflow:hidden; animation:popIn 0.2s ease;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #f0f0f0; background:#FAFAFA;">
                <span style="font-weight:700; font-size:15px; color:#1F2937;">Official Receipt Preview</span>
                <button type="button" onclick="closeReceiptModal()" style="background:none; border:none; font-size:24px; color:#9CA3AF; cursor:pointer; line-height:1;">&times;</button>
            </div>
            <div id="receiptPreview" style="background:#fff; color:#000; font-family:'Courier New',Courier,monospace; padding:20px 24px; font-size:13px; line-height:1.6; text-align:center; overflow-y:auto; flex:1;">
                <!-- Filled by JS -->
            </div>
            <div style="padding:14px 20px; display:flex; gap:12px; justify-content:flex-end; background:#FAFAFA; border-top:1px solid #f0f0f0;">
                <button type="button" onclick="closeReceiptModal()" style="background:#fff; border:1px solid #D1D5DB; color:#374151; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Close</button>
                <button type="button" onclick="doPrintReceipt()" style="background:#7C533C; border:none; color:#fff; padding:9px 24px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(124,83,60,0.3);">🖨️ Print Receipt</button>
            </div>
        </div>
    </div>

    <script>
        const PAYMENTS_BREAKDOWN = <?php echo json_encode($breakdown_map); ?>;
        // ── Proof Image Modal ──
        function openProofImageModal(url) {
            const img = document.getElementById('proofModalImg');
            
            // Format URL safely whether accessed via /frontend/ or clean root path
            let finalUrl = url;
            if (!url.startsWith('http') && !url.startsWith('/')) {
                if (window.location.pathname.includes('/frontend/')) {
                    finalUrl = url;
                } else {
                    finalUrl = 'frontend/' + url;
                }
            }
            
            img.src = finalUrl;
            img.onerror = function() {
                // If clean URL failed, try fallback path
                if (!this.src.includes('frontend/')) {
                    this.src = 'frontend/' + url;
                }
            };

            const ov = document.getElementById('proofOverlay');
            ov.classList.add('active');
            ov.style.display = 'flex';
        }
        function closeProofModal() {
            const ov = document.getElementById('proofOverlay');
            ov.classList.remove('active');
            ov.style.display = 'none';
        }
        document.getElementById('proofOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeProofModal();
        });

        // ── Official Receipt Modal ──
        const RECEPTIONIST_NAME = <?php echo json_encode($_SESSION['admin_username'] ?? 'Administrator'); ?>;
        let _receiptData = {};

        function openReceiptModal(bid, rcpt, inv, guest, room, totalCost) {
            const payments = PAYMENTS_BREAKDOWN[bid] || [];
            const totalPaid = payments.reduce((sum, p) => sum + p.amount, 0);
            _receiptData = { bid, rcpt, inv, guest, room, totalCost: parseFloat(totalCost) || 0, payments, totalPaid };
            renderReceipt();
            const ov = document.getElementById('receiptOverlay');
            ov.classList.add('active');
            ov.style.display = 'flex';
        }

        function closeReceiptModal() {
            const ov = document.getElementById('receiptOverlay');
            ov.classList.remove('active');
            ov.style.display = 'none';
        }

        document.getElementById('receiptOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeReceiptModal();
        });

        function renderReceipt() {
            const d = _receiptData;
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' });
            const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            const sep = '<div style="color:#000; letter-spacing:2px;">--------------------------------------</div>';
            const fmt = (v) => '₱ ' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const balance = Math.max(0, d.totalCost - d.totalPaid);

            let breakdownHtml = `<div style="margin:12px 0 4px; font-size:13px; font-weight:800; letter-spacing:1px; text-align:center;">PAYMENT BREAKDOWN</div>`;
            if (d.payments.length === 0) {
                breakdownHtml += `<div style="text-align:center; font-style:italic;">No payments recorded</div>`;
            } else {
                d.payments.forEach((p, i) => {
                    breakdownHtml += `<div style="text-align:left; margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between;"><span>${i+1}. ${p.method}</span><strong>${fmt(p.amount)}</strong></div>`;
                    if (p.method === 'Front Desk Cash' && p.tendered > p.amount) {
                        breakdownHtml += `<div style="display:flex; justify-content:space-between; color:#666; font-size:11px; padding-left:12px;"><span>Amount Tendered:</span><span>${fmt(p.tendered)}</span></div>`;
                        breakdownHtml += `<div style="display:flex; justify-content:space-between; color:#666; font-size:11px; padding-left:12px;"><span>Change:</span><span>${fmt(p.change)}</span></div>`;
                    }
                    breakdownHtml += `</div>`;
                });
            }

            document.getElementById('receiptPreview').innerHTML = `
                <div style="font-size:16px; font-weight:800; letter-spacing:1px; margin-top:8px;">SANTA FE BEACH CLUB</div>
                <div style="font-size:12px; color:#666;">Bantayan Island, Cebu</div>
                <div style="font-size:12px; color:#666; margin-bottom:8px;">Official Payment Receipt</div>
                ${sep}
                <div style="text-align:left; margin:8px 0;">
                    <div style="display:flex; justify-content:space-between;"><span>Receipt #:</span><strong>${d.rcpt}</strong></div>
                    <div style="display:flex; justify-content:space-between;"><span>Invoice #:</span><strong>${d.inv}</strong></div>
                    <div style="display:flex; justify-content:space-between;"><span>Date & Time:</span><strong>${dateStr}, ${timeStr}</strong></div>
                </div>
                ${sep}
                <div style="text-align:left; margin:8px 0;">
                    <div style="display:flex; justify-content:space-between;"><span>Guest Name:</span><strong>${d.guest}</strong></div>
                    <div style="display:flex; justify-content:space-between;"><span>Accommodation:</span><strong>${d.room}</strong></div>
                </div>
                ${sep}
                <div style="text-align:left; margin:8px 0;">
                    <div style="display:flex; justify-content:space-between; color:#666;"><span>Total Booking Cost:</span><strong>${fmt(d.totalCost)}</strong></div>
                    ${balance > 0.01 ? `<div style="display:flex; justify-content:space-between; color:#d32f2f; margin-top:4px;"><span>Remaining Balance:</span><strong>${fmt(balance)}</strong></div>` : ''}
                </div>
                ${sep}
                ${breakdownHtml}
                ${sep}
                <div style="margin:12px 0 4px; font-size:15px; font-weight:800; letter-spacing:1px;">TOTAL PAID</div>
                <div style="font-size:22px; font-weight:900; letter-spacing:1px; margin-bottom:8px; color:#7C533C;">${fmt(d.totalPaid)}</div>
                ${sep}
                <div style="text-align:left; margin:8px 0; font-weight:700;">
                    ${balance > 0.01 ? `<div style="display:flex; justify-content:space-between; color:#d32f2f;"><span>Status:</span><strong>PARTIAL / BALANCE DUE</strong></div>` : `<div style="display:flex; justify-content:space-between; color:#2E7D32;"><span>Status:</span><strong>PAID IN FULL</strong></div>`}
                </div>
                ${sep}
                <div style="text-align:left; margin:8px 0;">
                    <div style="display:flex; justify-content:space-between;"><span>Staff:</span><strong>${RECEPTIONIST_NAME}</strong></div>
                </div>
                ${sep}
                <div style="color:#666; font-size:12px; margin-top:10px;">Thank you for staying with us!<br>Have a safe trip!</div>
                <div style="color:#888; font-size:11px; margin-top:8px;">This is an official receipt.</div>
            `;
        }

        function doPrintReceipt() {
            const d = _receiptData;
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' });
            const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            const fmt = (v) => '₱ ' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const balance = Math.max(0, d.totalCost - d.totalPaid);
            const sep = '--------------------------------------';

            let breakdownHtml = `<div class="center total-label">PAYMENT BREAKDOWN</div>`;
            if (d.payments.length === 0) {
                breakdownHtml += `<div class="center" style="font-style:italic; margin-bottom:6px;">No payments recorded</div>`;
            } else {
                d.payments.forEach((p, i) => {
                    breakdownHtml += `<div class="row"><span>${i+1}. ${p.method}</span><strong>${fmt(p.amount)}</strong></div>`;
                    if (p.method === 'Front Desk Cash' && p.tendered > p.amount) {
                        breakdownHtml += `<div class="row" style="color:#666; font-size:11px; padding-left:12px;"><span>Amount Tendered:</span><strong>${fmt(p.tendered)}</strong></div>`;
                        breakdownHtml += `<div class="row" style="color:#666; font-size:11px; padding-left:12px; margin-bottom:4px;"><span>Change:</span><strong>${fmt(p.change)}</strong></div>`;
                    }
                });
            }

            const printWin = window.open('', '', 'width=420,height=700');
            printWin.document.write(`<html>
            <head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
                <title>Receipt - ${d.rcpt}</title>
                <style>
                    @page { size: 80mm auto; margin: 0; }
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: 'Courier New', Courier, monospace; background: #fff; color: #000; padding: 16px 8px; font-size: 13px; line-height: 1.6; width: 80mm; }
                    @media print { body { width: 80mm; padding: 8px 0; } }
                    .center { text-align: center; }
                    .sep { color: #000; letter-spacing: 2px; margin: 6px 0; text-align: center; }
                    .row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2px; }
                    .brand { font-size: 16px; font-weight: 800; letter-spacing: 1px; }
                    .total-label { font-size: 15px; font-weight: 800; letter-spacing: 1px; margin-top: 10px; margin-bottom: 6px;}
                    .total-amount { font-size: 20px; font-weight: 900; letter-spacing: 1px; margin-bottom: 6px; }
                </style>
            </head>
            <body>
                <div class="center">
                    <div class="brand">SANTA FE BEACH CLUB</div>
                    <div class="subtitle">Official Payment Receipt</div>
                </div>
                <div class="sep">${sep}</div>
                <div class="row"><span>Receipt #:</span><strong>${d.rcpt}</strong></div>
                <div class="row"><span>Date & Time:</span><strong>${dateStr}, ${timeStr}</strong></div>
                <div class="sep">${sep}</div>
                <div class="row"><span>Guest Name:</span><strong>${d.guest}</strong></div>
                <div class="row"><span>Accommodation:</span><strong>${d.room}</strong></div>
                <div class="sep">${sep}</div>
                <div class="row"><span>Total Cost:</span><strong>${fmt(d.totalCost)}</strong></div>
                ${balance > 0.01 ? `<div class="row" style="color:#d32f2f;"><span>Remaining Balance:</span><strong>${fmt(balance)}</strong></div>` : ''}
                <div class="sep">${sep}</div>
                ${breakdownHtml}
                <div class="sep">${sep}</div>
                <div class="center">
                    <div class="total-label">TOTAL PAID</div>
                    <div class="total-amount">${fmt(d.totalPaid)}</div>
                </div>
                <div class="sep">${sep}</div>
                <div class="row"><span>Status:</span><strong>${balance > 0.01 ? 'PARTIAL / BALANCE DUE' : 'PAID IN FULL'}</strong></div>
                <div class="sep">${sep}</div>
                <div class="row"><span>Staff:</span><strong>${RECEPTIONIST_NAME}</strong></div>
                <script>window.onload = function() { window.print(); }<\/script>
            </body>
            </html>`);
            printWin.document.close();
        }
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById("paymentSearch");
            const statusFilter = document.getElementById("paymentStatusFilter");
            const tableRows = document.querySelectorAll(".reservations-table tbody tr");

            function filterTable() {
                const searchVal = searchInput.value.toLowerCase();
                const statusVal = statusFilter.value.toLowerCase();

                tableRows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    const statusBadge = row.querySelector(".status-badge");
                    const rowStatus = statusBadge ? statusBadge.innerText.toLowerCase() : "";

                    const matchesSearch = text.includes(searchVal);
                    const matchesStatus = statusVal === "" || rowStatus.includes(statusVal);

                    if (matchesSearch && matchesStatus) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                });
            }

            if(searchInput) searchInput.addEventListener("input", filterTable);
            if(statusFilter) statusFilter.addEventListener("change", filterTable);
        });

        function openRefundModal(payId, guestName, amount) {
            document.getElementById("refundPaymentId").value = payId;
            document.getElementById("refundModalInfo").innerHTML =
                "<strong>Guest:</strong> " + guestName + "<br>" +
                "<strong>Amount:</strong> " + amount + "<br>" +
                "<strong>Invoice:</strong> INV-100" + payId;
            document.getElementById("refundReasonSelect").value = "";
            document.getElementById("customReasonWrap").style.display = "none";
            document.getElementById("customReasonInput").value = "";
            document.getElementById("refundOverlay").classList.add("active");
        }

        function closeRefundModal() {
            document.getElementById("refundOverlay").classList.remove("active");
        }

        function toggleCustomReason(val) {
            document.getElementById("customReasonWrap").style.display = (val === "Other") ? "block" : "none";
        }

        function submitRefund() {
            const reasonSelect = document.getElementById("refundReasonSelect");
            const customInput = document.getElementById("customReasonInput");
            let reason = reasonSelect.value;

            if (!reason) {
                alert("Please select a reason for the refund.");
                return;
            }
            if (reason === "Other") {
                reason = customInput.value.trim();
                if (!reason) {
                    alert("Please describe the reason for the refund.");
                    return;
                }
                // Set the select value to the custom reason so it gets submitted
                const hiddenReason = document.createElement("input");
                hiddenReason.type = "hidden";
                hiddenReason.name = "refund_reason";
                hiddenReason.value = reason;
                document.getElementById("refundForm").appendChild(hiddenReason);
                reasonSelect.name = ""; // disable original select
            }

            document.getElementById("refundForm").submit();
        }

        // Close modals on overlay click
        document.getElementById("refundOverlay").addEventListener("click", function(e) {
            if (e.target === this) closeRefundModal();
        });

        // ── Reject Modal ──────────────────────────────────────────────────────────
        function openRejectModal(payId, guestName, invoiceNum) {
            document.getElementById("rejectPaymentId").value = payId;
            document.getElementById("rejectModalInfo").innerHTML =
                "<strong>Guest:</strong> " + guestName + "<br>" +
                "<strong>Invoice:</strong> " + invoiceNum;
            document.getElementById("rejectReasonSelect").value = "";
            document.getElementById("rejectCustomReasonWrap").style.display = "none";
            document.getElementById("rejectCustomReasonInput").value = "";
            document.getElementById("rejectOverlay").classList.add("active");
        }

        function closeRejectModal() {
            document.getElementById("rejectOverlay").classList.remove("active");
        }

        function toggleRejectCustomReason(val) {
            document.getElementById("rejectCustomReasonWrap").style.display = (val === "Other") ? "block" : "none";
        }

        function submitReject() {
            const reasonSelect = document.getElementById("rejectReasonSelect");
            const customInput  = document.getElementById("rejectCustomReasonInput");
            let reason = reasonSelect.value;

            if (!reason) {
                alert("Please select a reason for the rejection.");
                return;
            }
            if (reason === "Other") {
                reason = customInput.value.trim();
                if (!reason) {
                    alert("Please describe the reason for the rejection.");
                    return;
                }
                // Inject as hidden field so the select doesn't conflict
                const hiddenReason = document.createElement("input");
                hiddenReason.type  = "hidden";
                hiddenReason.name  = "reject_reason";
                hiddenReason.value = reason;
                document.getElementById("rejectForm").appendChild(hiddenReason);
                reasonSelect.name = ""; // disable original select
            }

            document.getElementById("rejectForm").submit();
        }

        document.getElementById("rejectOverlay").addEventListener("click", function(e) {
            if (e.target === this) closeRejectModal();
        });
    </script>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
