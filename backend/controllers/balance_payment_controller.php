<?php
/**
 * Controller for frontend/balance_payment.php
 */

function sfbc_handle_balance_payment_page(mysqli $conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Cache-Control: no-store');

    $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
    $error = '';
    $booking = null;
    if (preg_match('/\A[a-f0-9]{32}\z/', $token)) {
        $stmt = $conn->prepare("SELECT id FROM bookings WHERE cancellation_token=? AND status IN ('Pending','Confirmed') LIMIT 1");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            sf_initialize_balance_deadline($conn, (int)$row['id']);
            $booking = sf_refresh_balance_status($conn, (int)$row['id']);
        }
    }
    if (!$booking) {
        http_response_code(404);
        $error = 'This balance-payment link is invalid or the booking is no longer active.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking) {
        require_csrf_token();
        if ($booking['balance_due'] <= 0.009) {
            $error = 'This booking is already paid in full.';
        } elseif ($booking['balance_status']==='cancellation_review') {
            $error = 'This booking requires front-desk review. Please contact Santa Fe Beach Club.';
        } else {
            $conn->begin_transaction();
            try {
                $locked = sf_booking_financials($conn, (int)$booking['id'], true);
                $pending = pm_query($conn, "SELECT p.id,c.checkout_url FROM payments p LEFT JOIN paymongo_checkouts c ON c.payment_id=p.id WHERE p.booking_id=? AND p.purpose='balance' AND p.status='pending' ORDER BY p.id DESC LIMIT 1", [(int)$booking['id']])->get_result()->fetch_assoc();
                if ($pending && !empty($pending['checkout_url'])) {
                    $conn->commit();
                    header('Location: ' . $pending['checkout_url']);
                    exit;
                }
                if ($pending) {
                    throw new RuntimeException('A balance checkout is already active. Please contact the front desk if it cannot be opened.');
                }
                $amount = (float)$locked['balance_due'];
                if ($amount < 1) {
                    throw new RuntimeException('No payable balance remains.');
                }
                pm_query($conn, "INSERT INTO payments (booking_id,guest_name,guest_email,amount,payment_method,status,purpose) VALUES (?,?,?,?, 'PayMongo Online','pending','balance')", [(int)$booking['id'], $booking['guest_name'], $booking['guest_email'], $amount]);
                $paymentId = (int)$conn->insert_id;
                $base = sf_balance_public_url();
                $return = $base . '/payment_success.php?booking_id=' . (int)$booking['id'];
                $cancel = $base . '/balance_payment.php?token=' . urlencode($token);
                $result = PayMongoService::createCheckoutSession($amount, 'Remaining balance for REF-' . str_pad((string)$booking['id'], 3, '0', STR_PAD_LEFT), (int)$booking['id'], $booking['guest_name'], $booking['guest_email'], $booking['guest_phone'] ?? '', $return, $cancel, 'Remaining Booking Balance — Santa Fe Beach Club');
                if (!$result['success']) {
                    throw new RuntimeException($result['error'] ?? 'Could not create PayMongo checkout.');
                }
                pm_store_checkout($conn, (string)$result['session_id'], $paymentId, (int)$booking['id'], $amount);
                pm_query($conn, 'UPDATE paymongo_checkouts SET checkout_url=? WHERE session_id=?', [(string)$result['checkout_url'], (string)$result['session_id']]);
                pm_query($conn, "UPDATE payments SET transaction_id=? WHERE id=? AND status='pending'", [(string)$result['session_id'], $paymentId]);
                $conn->commit();
                header('Location: ' . $result['checkout_url']);
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }

    $deadline = $booking && $booking['balance_due_at'] ? date('M j, Y g:i A', strtotime($booking['balance_due_at'])) : '—';

    return compact('token', 'error', 'booking', 'deadline');
}
