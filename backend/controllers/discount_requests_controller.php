<?php

function sfbc_discount_status_class(string $state): string
{
    if (in_array($state, ['Confirmed', 'Verified', 'Approved'], true)) {
        return 'status-confirmed';
    }
    if (in_array($state, ['Cancelled', 'Declined', 'Expired', 'Rejected'], true)) {
        return 'status-cancelled';
    }
    return 'status-pending';
}

/**
 * Handles Senior/PWD discount review actions and loads detail or list data for discount_requests.php.
 */
function sfbc_handle_discount_requests_page(mysqli $conn): array
{
    $actor   = (string)($_SESSION['admin_username'] ?? 'Staff');
    $id      = (int)($_GET['booking'] ?? $_POST['booking_id'] ?? 0);
    $message = (string)($_SESSION['discount_staff_notice'] ?? '');
    unset($_SESSION['discount_staff_notice']);
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
        try {
            $action = (string)($_POST['action'] ?? '');
            if (in_array($action, ['approve', 'decline', 'information'], true)) {
                discount_review($conn, $id, $action, trim((string)($_POST['note'] ?? '')), $actor);
                $sent = discount_email($conn, $id);
                $message = $sent
                    ? 'Decision saved and guest emailed with their private booking/payment link.'
                    : 'Decision saved, but email failed. Copy the private link below or retry email.';
            } elseif ($action === 'email') {
                $message = discount_email($conn, $id) ? 'Guest emailed.' : 'Email failed. Copy the private link below.';
            } elseif ($action === 'checkout') {
                discount_start_payment($conn, $id, 'PayMongo Online');
                $message = discount_email($conn, $id)
                    ? 'PayMongo checkout created; guest emailed.'
                    : 'Checkout created. Email failed; copy the private link below.';
            } elseif (in_array($action, ['verify', 'reject'], true)) {
                if ($action === 'verify' && empty($_POST['verified_transaction'])) {
                    throw new RuntimeException('Confirm that you checked the actual incoming account transaction.');
                }
                discount_manual_review($conn, $id, (int)($_POST['payment_id'] ?? 0), $action === 'verify', $actor, trim((string)($_POST['note'] ?? '')));
                $sent = discount_email($conn, $id);
                $message = 'Payment decision saved.' . ($sent ? ' Guest emailed.' : ' Email failed; use the private link.');
            } else {
                throw new RuntimeException('Unknown action.');
            }
            $_SESSION['discount_staff_notice'] = $message;
            header('Location: discount_requests.php' . ($id ? '?booking=' . $id : ''));
            exit;
        } catch (Throwable $e) {
            $error = $e instanceof mysqli_sql_exception
                ? 'The update could not be saved. Please refresh and retry.'
                : $e->getMessage();
        }
    }

    $detail = $id
        ? pm_query($conn, 'SELECT d.*,b.guest_name,b.guest_email,b.accommodation_name,b.status FROM booking_discounts d JOIN bookings b ON b.id=d.booking_id WHERE d.booking_id=?', [$id])->get_result()->fetch_assoc()
        : null;

    $payments     = [];
    $historyRows  = [];
    $dueAmount    = 0;
    $privateLink  = '';
    $listRows     = [];

    if ($detail) {
        $dueAmount   = discount_due($conn, $detail);
        $privateLink = discount_link($detail);

        $ps = pm_query($conn, 'SELECT p.*,x.purpose,x.checkout_url FROM discount_payments x JOIN payments p ON p.id=x.payment_id WHERE x.booking_id=? ORDER BY p.id DESC', [$id])->get_result();
        while ($row = $ps->fetch_assoc()) {
            $payments[] = $row;
        }

        $historyResult = pm_query($conn, 'SELECT * FROM discount_history WHERE booking_id=? ORDER BY id DESC', [$id])->get_result();
        $historyRows   = $historyResult->fetch_all(MYSQLI_ASSOC);
    } else {
        $rows = $conn->query('SELECT d.*,b.guest_name FROM booking_discounts d JOIN bookings b ON b.id=d.booking_id ORDER BY d.created_at DESC LIMIT 200');
        if ($rows) {
            while ($r = $rows->fetch_assoc()) {
                $listRows[] = $r;
            }
        }
    }

    $frontendDir = __DIR__ . '/../../frontend';

    return [
        'id'           => $id,
        'message'      => $message,
        'error'        => $error,
        'detail'       => $detail,
        'payments'     => $payments,
        'historyRows'  => $historyRows,
        'historyCount' => count($historyRows),
        'dueAmount'    => $dueAmount,
        'privateLink'  => $privateLink,
        'listRows'     => $listRows,
        'csrfField'    => csrf_field(),
        'versions'     => [
            'adminCss'     => filemtime($frontendDir . '/assets/css/admin.css'),
            'discountsCss' => filemtime($frontendDir . '/assets/css/discounts.css'),
            'pageJs'       => file_exists($frontendDir . '/assets/js/pages/discount-requests.js')
                ? filemtime($frontendDir . '/assets/js/pages/discount-requests.js')
                : 1,
        ],
    ];
}
