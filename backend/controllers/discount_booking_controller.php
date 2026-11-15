<?php
/**
 * Controller for frontend/discount_booking.php
 */

if (!function_exists('dh')) {
    function dh($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function sfbc_handle_discount_booking_page(mysqli $conn): array
{
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');

    if (isset($_GET['token']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'access')) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf_token();
        }
        $token = (string)($_POST['access_token'] ?? $_GET['token'] ?? '');
        $row = preg_match('/^[a-f0-9]{64}$/D', $token) ? pm_query($conn, 'SELECT booking_id FROM booking_discounts WHERE token=?', [$token])->get_result()->fetch_assoc() : null;
        if (!$row) {
            http_response_code(404);
            exit('This private booking link is invalid.');
        }
        $_SESSION['discount_tokens'][(int)$row['booking_id']] = $token;
        header('Location: discount_booking.php?booking=' . (int)$row['booking_id']);
        exit;
    }

    $id = (int)($_GET['booking'] ?? 0);
    if (!$id && $_SERVER['REQUEST_METHOD'] === 'GET') {
        return ['needsAccessBootstrap' => true];
    }

    $token = $_SESSION['discount_tokens'][$id] ?? '';
    $d = pm_query($conn, 'SELECT d.*, b.guest_name,b.guest_email,b.accommodation_name,b.status FROM booking_discounts d JOIN bookings b ON b.id=d.booking_id WHERE d.booking_id=? AND d.token=?', [$id, $token])->get_result()->fetch_assoc();
    if (!$d) {
        http_response_code(403);
        exit('Open the private link sent to your booking email to access this page.');
    }

    $message = $_SESSION['discount_notice'] ?? '';
    unset($_SESSION['discount_notice']);
    $error = '';
    $settings = [];
    $rs = $conn->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('gcash_number','gcash_name','discount_bank_name','discount_bank_account','discount_bank_holder')");
    while ($s = $rs->fetch_assoc()) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
    $gcash = !empty($settings['gcash_number']) && !empty($settings['gcash_name']);
    $bank = !empty($settings['discount_bank_name']) && !empty($settings['discount_bank_account']) && !empty($settings['discount_bank_holder']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
        try {
            $action = $_POST['action'] ?? '';
            if ($action === 'pay') {
                $method = $_POST['method'] ?? '';
                if (($method === 'GCash QR' && !$gcash) || ($method === 'Bank Deposit' && !$bank)) {
                    throw new RuntimeException('This payment method is not configured.');
                }
                $url = discount_start_payment($conn, $id, $method, trim($_POST['reference'] ?? ''), $_FILES['receipt'] ?? null);
                if ($url) {
                    header('Location: ' . $url);
                    exit;
                }
                $message = 'Receipt submitted. Staff will verify the incoming transaction before confirming payment.';
            } elseif ($action === 'refresh_payment') {
                require_once dirname(__DIR__) . '/services/paymongo.php';
                $checkout = pm_query($conn, "SELECT c.session_id FROM paymongo_checkouts c JOIN payments p ON p.id=c.payment_id WHERE c.booking_id=? AND p.status='pending' LIMIT 1", [$id])->get_result()->fetch_assoc();
                if ($checkout) {
                    $result = PayMongoService::retrieveCheckoutSession($checkout['session_id']);
                    if (!empty($result['success']) && !empty($result['is_paid'])) {
                        pm_confirm_checkout($conn, $id, $checkout['session_id'], $result['data']);
                        discount_email($conn, $id);
                        $message = 'Payment verified. Your booking is confirmed.';
                    } else {
                        $message = 'Payment is not confirmed yet. Please check again shortly.';
                    }
                } else {
                    $message = 'Your latest booking status is displayed below.';
                }
            } elseif ($action === 'switch_manual') {
                $conn->begin_transaction();
                try {
                    $pendingPaymongo = pm_query($conn, "SELECT p.id FROM discount_payments x JOIN payments p ON p.id=x.payment_id WHERE x.booking_id=? AND p.status='pending' AND x.checkout_url IS NOT NULL FOR UPDATE", [$id])->get_result()->fetch_assoc();
                    if ($pendingPaymongo) {
                        $pid = (int)$pendingPaymongo['id'];
                        pm_query($conn, "UPDATE payments SET status='cancelled' WHERE id=?", [$pid]);
                        pm_query($conn, "UPDATE discount_payments SET checkout_url=NULL, reference_key=NULL WHERE payment_id=?", [$pid]);
                        discount_audit($conn, $id, 'Guest', 'Switched payment method', 'Switched from PayMongo to manual transfer');
                    }
                    $conn->commit();
                    $message = 'You can now submit your payment via GCash or Bank Transfer.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                }
            } elseif (in_array($action, ['regular', 'cancel', 'resubmit'], true)) {
                $newFile = null;
                if ($action === 'resubmit') {
                    $newFile = discount_upload($_FILES['discount_id'] ?? [], 'discount_ids');
                }
                $conn->begin_transaction();
                try {
                    $locked = discount_locked($conn, $id);
                    if ($action === 'regular') {
                        if ($locked['state'] !== 'Discount declined') {
                            throw new RuntimeException('Regular-price acceptance is not available.');
                        }
                        $amounts = discount_amounts((int)$locked['original_centavos'], false);
                        pm_query($conn, "UPDATE booking_discounts SET state='Awaiting Deposit',total_centavos=?,deposit_centavos=? WHERE booking_id=?", [$amounts['total'], $amounts['deposit'], $id]);
                        pm_query($conn, 'UPDATE bookings SET discount_amount=0,promo_code=NULL WHERE id=?', [$id]);
                    } elseif ($action === 'cancel') {
                        if (!in_array($locked['state'], ['Awaiting ID verification', 'More information needed', 'Discount declined', 'Awaiting Deposit'], true)) {
                            throw new RuntimeException('Contact reception to cancel a paid booking or a payment under review.');
                        }
                        if (pm_query($conn, "SELECT id FROM payments WHERE booking_id=? AND status IN ('pending','verified') LIMIT 1", [$id])->get_result()->fetch_assoc()) {
                            throw new RuntimeException('A payment has already been initiated. Contact reception to cancel.');
                        }
                        pm_query($conn, "UPDATE bookings SET status='Cancelled' WHERE id=?", [$id]);
                        pm_query($conn, "UPDATE booking_discounts SET state='Cancelled' WHERE booking_id=?", [$id]);
                    } else {
                        if ($locked['state'] !== 'More information needed') {
                            throw new RuntimeException('A new ID has not been requested.');
                        }
                        pm_query($conn, "UPDATE booking_discounts SET state='Awaiting ID verification',id_file=?,reviewed_at=NULL,created_at=NOW() WHERE booking_id=?", [$newFile, $id]);
                    }
                    discount_audit($conn, $id, 'Guest', $action);
                    $conn->commit();
                    if ($newFile && $locked['id_file']) {
                        $old = private_storage_file('discount_ids/' . $locked['id_file']);
                        if ($old) {
                            unlink($old);
                        }
                    }
                } catch (Throwable $e) {
                    $conn->rollback();
                    if ($newFile) {
                        @unlink(private_storage_directory('discount_ids') . '/' . $newFile);
                    }
                    throw $e;
                }
                $message = 'Your booking request has been updated.';
            } else {
                throw new RuntimeException('Unknown action.');
            }
            $_SESSION['discount_notice'] = $message;
            header('Location: discount_booking.php?booking=' . $id);
            exit;
        } catch (Throwable $e) {
            $error = $e instanceof mysqli_sql_exception
                ? 'The request could not be completed. A payment reference may already be in use. Contact reception if needed.'
                : $e->getMessage();
        }
    }

    $paid = (int)pm_query($conn, "SELECT COALESCE(ROUND(SUM(amount)*100),0) FROM payments WHERE booking_id=? AND status='verified'", [$id])->get_result()->fetch_row()[0];
    $pending = pm_query($conn, "SELECT p.*,x.checkout_url FROM discount_payments x JOIN payments p ON p.id=x.payment_id WHERE x.booking_id=? AND p.status='pending' ORDER BY p.id DESC LIMIT 1", [$id])->get_result()->fetch_assoc();
    $estimate = in_array($d['state'], ['Awaiting ID verification', 'More information needed'], true);
    $gcashQrPath = dirname(__DIR__, 2) . '/frontend/assets/gcash_qr.png';
    $gcashQrVersion = file_exists($gcashQrPath) ? filemtime($gcashQrPath) : time();

    return [
        'needsAccessBootstrap' => false,
        'id' => $id,
        'd' => $d,
        'message' => $message,
        'error' => $error,
        'settings' => $settings,
        'gcash' => $gcash,
        'bank' => $bank,
        'paid' => $paid,
        'pending' => $pending,
        'estimate' => $estimate,
        'gcashQrVersion' => $gcashQrVersion,
    ];
}
