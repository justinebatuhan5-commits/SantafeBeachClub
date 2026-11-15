<?php

/**
 * Payments page controller: POST mutations, billing rows, action history, and verified breakdown map.
 */

function sfbc_payments_record_action(mysqli $conn, int $paymentId, string $action, string $details = ''): void
{
    $performedBy = $_SESSION['admin_username'] ?? 'System';
    $stmt = $conn->prepare("INSERT INTO payment_action_history (payment_id, action, performed_by, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $paymentId, $action, $performedBy, $details);
    $stmt->execute();
    $stmt->close();
}

function sfbc_payments_handle_post(mysqli $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
        return;
    }

    require_csrf_token();

    // Discount payments must pass the approval, amount, hold and audit checks.
    if (isset($_POST['payment_id']) && in_array($_POST['action'] ?? '', ['process_payment', 'verify_payment', 'reject_payment'], true)) {
        $discountPayment = pm_query($conn, 'SELECT booking_id FROM discount_payments WHERE payment_id=?', [(int)$_POST['payment_id']])->get_result()->fetch_assoc();
        if ($discountPayment) {
            header('Location: discount_requests.php?booking=' . (int)$discountPayment['booking_id']);
            exit;
        }
    }

    $action = $_POST['action'];

    if ($action === 'process_payment' || $action === 'verify_payment') {
        $pay_id = intval($_POST['payment_id'] ?? 0);
        $method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Front Desk Cash';

        $stmt = $conn->prepare("UPDATE payments SET status = 'verified', payment_method = ? WHERE id = ?");
        $stmt->bind_param("si", $method, $pay_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE bookings SET status = 'Confirmed' WHERE id = (SELECT booking_id FROM payments WHERE id = ?)");
        $stmt->bind_param("i", $pay_id);
        $stmt->execute();
        $stmt->close();

        sfbc_payments_record_action($conn, $pay_id, 'verified', 'Payment verified using ' . $method . '.');

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
                $b_ref = 'REF-' . str_pad((string)$verified_booking_id, 3, '0', STR_PAD_LEFT);
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

                $confirmation_result = sendBookingConfirmationEmail(
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

        if (isset($confirmation_result) && !$confirmation_result['success']) {
            header('Location: payments?success=1&email_error=' . urlencode('Payment verified, but confirmation could not be sent. Use Resend Confirmation to retry.'));
            exit;
        }
        header("Location: payments?success=1");
        exit;
    }

    if ($action === 'send_confirmation_email') {
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("SELECT guest_name, guest_email, accommodation_name, check_in, check_out, cancellation_token, checkin_token FROM bookings WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            header("Location: payments?email_error=Booking%20not%20found");
            exit;
        }

        $booking_ref = 'REF-' . str_pad((string)$booking_id, 3, '0', STR_PAD_LEFT);
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
            $checkin_url,
            true // Staff explicitly selected Resend Confirmation.
        );
        $query_key = $send_result['success'] ? 'email_sent=1' : 'email_error=' . urlencode($send_result['error'] ?? 'Email sending failed');
        header("Location: payments?{$query_key}");
        exit;
    }

    if ($action === 'reject_payment') {
        $pay_id = intval($_POST['payment_id'] ?? 0);
        $reject_reason = trim($_POST['reject_reason'] ?? '');

        $stmt = $conn->prepare("UPDATE payments SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $pay_id);
        $stmt->execute();
        $stmt->close();

        $reason_log = $reject_reason !== '' ? $reject_reason : 'No reason provided';
        sfbc_payments_record_action($conn, $pay_id, 'rejected', 'Payment rejected. Reason: ' . $reason_log);

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
        $stmt->close();

        if ($rej_booking && !empty($rej_booking['guest_email'])) {
            $rej_ref = 'REF-' . str_pad((string)(int)$rej_booking['id'], 3, '0', STR_PAD_LEFT);
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
    }

    if ($action === 'refund_payment') {
        $pay_id = intval($_POST['payment_id'] ?? 0);
        $refund_reason = trim($_POST['refund_reason'] ?? 'No reason provided');

        $stmt = $conn->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?");
        $stmt->bind_param("i", $pay_id);
        $stmt->execute();
        $stmt->close();

        sfbc_payments_record_action($conn, $pay_id, 'refunded', 'Refund reason: ' . $refund_reason);

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

            $admin_user = $_SESSION['admin_username'] ?? 'system';
            $detail = "Refunded PHP " . number_format((float)$prow['amount'], 2) . " for payment ID {$pay_id} (Guest: {$prow['guest_name']}). Reason: {$refund_reason}";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $logstmt = $conn->prepare("INSERT INTO activity_logs (admin_username, action, details, ip_address) VALUES (?, 'Payment Refunded', ?, ?)");
            $logstmt->bind_param("sss", $admin_user, $detail, $ip);
            $logstmt->execute();
            $logstmt->close();

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

function sfbc_handle_payments_page(mysqli $conn): array
{
    sfbc_payments_handle_post($conn);

    $payment_history = [];
    $history_query = $conn->query("SELECT payment_id, action, performed_by, details, performed_at FROM payment_action_history ORDER BY performed_at DESC, id DESC");
    if ($history_query) {
        while ($history_row = $history_query->fetch_assoc()) {
            $payment_history[(int)$history_row['payment_id']][] = $history_row;
        }
    }

    $breakdown_map = [];
    $bk_res = $conn->query("SELECT id as pay_id, booking_id, amount, amount_tendered, change_amount, payment_method, status FROM payments WHERE status = 'verified' ORDER BY id ASC");
    if ($bk_res) {
        while ($row = $bk_res->fetch_assoc()) {
            $bid = $row['booking_id'];
            if (!isset($breakdown_map[$bid])) {
                $breakdown_map[$bid] = [];
            }
            $breakdown_map[$bid][] = [
                'pay_id' => $row['pay_id'],
                'amount' => floatval($row['amount']),
                'tendered' => floatval($row['amount_tendered'] ?? $row['amount']),
                'change' => floatval($row['change_amount'] ?? 0),
                'method' => $row['payment_method'],
            ];
        }
    }

    $payments = [];
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
            p.purpose as payment_purpose,
            p.receipt_url,
            p.paid_at,
            b.accommodation_name,
            b.check_in,
            b.check_out,
            b.guests_count,
            b.extra_adults,
            b.extra_children,
            b.discount_amount,
            d.category as discount_category,
            d.total_centavos as discounted_total_centavos,
            DATEDIFF(b.check_out, b.check_in) as nights,
            r.price_per_night
        FROM payments p
        LEFT JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN booking_discounts d ON d.booking_id = b.id
        ORDER BY p.id DESC
    ");

    if ($payments_query) {
        while ($row = $payments_query->fetch_assoc()) {
            $payIdInt = (int)$row['pay_id'];
            $methodRaw = $row['payment_method'] ?: 'Pay at Check-in';
            $txnRaw = $row['transaction_id'] ?: ('TXN-' . $payIdInt);
            $rawAmount = (float)$row['amount'];
            $statusDisplay = strtolower((string)$row['payment_status']);
            $purpose = strtolower((string)($row['payment_purpose'] ?? 'deposit'));
            $purposeLabel = match ($purpose) {
                'balance' => 'Remaining Balance',
                'full' => 'Full Payment',
                'additional' => 'Additional Charge',
                default => '50% Deposit',
            };
            $statusClass = match ($statusDisplay) {
                'verified', 'paid' => 'status-paid',
                'rejected' => 'status-rejected',
                'refunded' => 'status-refunded',
                default => 'status-pending',
            };

            $receiptUrl = (string)($row['receipt_url'] ?? '');
            $receiptAvailable = false;
            if ($receiptUrl !== '') {
                $receiptAvailable = true;
                if (preg_match('~^(?:storage|uploads)/receipts/([^/]+)$~', $receiptUrl, $receiptMatch)) {
                    try {
                        $receiptAvailable = private_storage_file('receipts/' . $receiptMatch[1]) !== null;
                    } catch (Throwable $e) {
                        $receiptAvailable = false;
                    }
                }
            }

            $nights = max(1, intval($row['nights'] ?? 1));
            $pricePerNight = floatval($row['price_per_night'] ?? 0);
            $discountAmount = max(0, (float)($row['discount_amount'] ?? 0));
            $discountedTotal = isset($row['discounted_total_centavos']) ? ((float)$row['discounted_total_centavos'] / 100) : null;
            $bookingTotal = $discountedTotal !== null
                ? $discountedTotal
                : (($pricePerNight > 0) ? max(0, ($pricePerNight * $nights) - $discountAmount) : $rawAmount);
            if ($bookingTotal < $rawAmount) {
                $bookingTotal = $rawAmount;
            }

            $payments[] = [
                'pay_id' => $payIdInt,
                'invoice_code' => 'INV-100' . $payIdInt,
                'booking_id' => (int)($row['booking_id'] ?? 0),
                'guest_name' => (string)$row['guest_name'],
                'room_name' => (string)($row['accommodation_name'] ?: 'Standard Room'),
                'method' => (string)$methodRaw,
                'txn' => (string)$txnRaw,
                'is_gcash_ref' => ($methodRaw === 'GCash QR' || $methodRaw === 'GCash (Manual QR)') && !str_starts_with($txnRaw, 'TXN-'),
                'amount_raw' => $rawAmount,
                'amount_formatted' => number_format($rawAmount, 2),
                'status_display' => $statusDisplay,
                'status_class' => $statusClass,
                'purpose_label' => $purposeLabel,
                'purpose_badge_class' => $purpose === 'balance' ? ' balance' : '',
                'receipt_url' => $receiptUrl,
                'receipt_available' => $receiptAvailable,
                'history' => $payment_history[$payIdInt] ?? [],
                'rcpt_num' => 'RCPT-' . str_pad((string)$payIdInt, 6, '0', STR_PAD_LEFT),
                'booking_total' => $bookingTotal,
                'guest_count' => max(0, (int)($row['guests_count'] ?? 0)),
                'extra_adults' => max(0, (int)($row['extra_adults'] ?? 0)),
                'extra_children' => max(0, (int)($row['extra_children'] ?? 0)),
                'discount_category' => in_array($row['discount_category'] ?? '', ['senior', 'pwd'], true) ? $row['discount_category'] : '',
                'discount_amount' => $discountAmount,
            ];
        }
    }

    $sendBookingId = 0;
    $sendBooking = null;
    if (isset($_GET['send_booking_id']) && (int)$_GET['send_booking_id'] > 0) {
        $sendBookingId = (int)$_GET['send_booking_id'];
        $send_stmt = $conn->prepare("SELECT guest_name, guest_email FROM bookings WHERE id = ? LIMIT 1");
        $send_stmt->bind_param("i", $sendBookingId);
        $send_stmt->execute();
        $sendBooking = $send_stmt->get_result()->fetch_assoc() ?: null;
        $send_stmt->close();
    }

    $frontendRoot = dirname(__DIR__, 2) . '/frontend';

    return [
        'csrfToken' => get_csrf_token(),
        'receptionistName' => $_SESSION['admin_username'] ?? 'Administrator',
        'payments' => $payments,
        'breakdownMap' => $breakdown_map,
        'sendBookingId' => $sendBookingId,
        'sendBooking' => $sendBooking,
        'flash' => [
            'success' => isset($_GET['success']),
            'email_sent' => isset($_GET['email_sent']),
            'email_error' => $_GET['email_error'] ?? null,
            'rejected' => isset($_GET['rejected']),
            'refunded' => isset($_GET['refunded']),
        ],
        'adminCssVersion' => file_exists($frontendRoot . '/assets/css/admin.css') ? filemtime($frontendRoot . '/assets/css/admin.css') : 1,
        'dashboardCssVersion' => file_exists($frontendRoot . '/assets/css/dashboard.css') ? filemtime($frontendRoot . '/assets/css/dashboard.css') : 1,
        'cssVersion' => file_exists($frontendRoot . '/assets/css/pages/payments.css') ? filemtime($frontendRoot . '/assets/css/pages/payments.css') : 1,
        'jsVersion' => file_exists($frontendRoot . '/assets/js/pages/payments.js') ? filemtime($frontendRoot . '/assets/js/pages/payments.js') : 1,
    ];
}
