<?php
/**
 * Controller for frontend/invoice.php
 */

function sfbc_handle_invoice_page(mysqli $conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $ref = trim($_GET['ref'] ?? '');
    $token = trim($_GET['token'] ?? '');

    $booking_id = 0;
    if (preg_match('/^REF-(\d+)$/i', $ref, $m)) {
        $booking_id = (int)$m[1];
    } elseif (is_numeric($ref)) {
        $booking_id = (int)$ref;
    }

    if ($booking_id <= 0) {
        http_response_code(400);
        die("Invalid booking reference.");
    }

    // Check admin authentication or valid token / session
    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    $isGuest = isset($_SESSION['verified_guest_booking_id']) && (int)$_SESSION['verified_guest_booking_id'] === $booking_id;

    $stmt = $conn->prepare("
        SELECT b.*,
               DATEDIFF(b.check_out, b.check_in)  AS nights_calc,
               COALESCE(r.price_per_night, rt.price, 0) AS nightly_rate,
               d.category AS discount_category,
               d.state AS discount_state,
               d.original_centavos,
               d.total_centavos
        FROM bookings b
        LEFT JOIN rooms r ON r.id = b.room_id
        LEFT JOIN room_types rt ON rt.id = b.room_type_id
            OR (b.room_type_id IS NULL AND rt.name = b.accommodation_name)
        LEFT JOIN booking_discounts d ON d.booking_id = b.id
        WHERE b.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        http_response_code(404);
        die("Booking record not found.");
    }

    // Validate access token if not logged in as admin or session guest
    if (!$isAdmin && !$isGuest) {
        $checkinToken = $booking['checkin_token'] ?? '';
        $cancelToken = $booking['cancellation_token'] ?? '';
        if (empty($token) || (!hash_equals($checkinToken, $token) && !hash_equals($cancelToken, $token))) {
            http_response_code(403);
            die("Unauthorized access to this invoice.");
        }
    }

    // Load verified payment totals and latest payment details separately for TiDB compatibility.
    $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS deposit_paid FROM payments WHERE booking_id = ? AND status = 'verified'");
    $sum_stmt->bind_param("i", $booking_id);
    $sum_stmt->execute();
    $sum_row = $sum_stmt->get_result()->fetch_assoc();
    $sum_stmt->close();

    $pay_stmt = $conn->prepare("SELECT status AS payment_status, payment_method AS payment_method_detail, transaction_id, paid_at FROM payments WHERE booking_id = ? AND status = 'verified' ORDER BY id DESC LIMIT 1");
    $pay_stmt->bind_param("i", $booking_id);
    $pay_stmt->execute();
    $latest_payment = $pay_stmt->get_result()->fetch_assoc();
    $pay_stmt->close();

    $booking['deposit_paid'] = $sum_row['deposit_paid'] ?? 0;
    $booking['payment_status'] = $latest_payment['payment_status'] ?? null;
    $booking['payment_method_detail'] = $latest_payment['payment_method_detail'] ?? null;
    $booking['transaction_id'] = $latest_payment['transaction_id'] ?? null;
    $booking['paid_at'] = $latest_payment['paid_at'] ?? null;

    $booking_ref   = 'REF-' . str_pad($booking['id'], 3, '0', STR_PAD_LEFT);
    $nights        = max(1, (int)($booking['nights_calc'] ?? $booking['nights'] ?? 1));
    $deposit_paid  = (float)($booking['deposit_paid'] ?? 0);
    $discount_amt  = (float)($booking['discount_amount'] ?? 0);
    $extra_adults = max(0, (int)($booking['extra_adults'] ?? 0));
    $extra_children = max(0, (int)($booking['extra_children'] ?? 0));
    $included_guests = max(0, (int)$booking['guests_count'] - $extra_adults - $extra_children);
    $discount_category = strtolower((string)($booking['discount_category'] ?? ''));
    $discount_label = $discount_category === 'senior' ? 'Senior Citizen Discount (20%)'
        : ($discount_category === 'pwd' ? 'PWD Discount (20%)' : 'Promo Discount');
    // Discount requests preserve their authoritative original and approved totals,
    // including any selected extra-person charges.
    if (!empty($booking['discount_category']) && isset($booking['total_centavos'])) {
        $total_amount = max(0, (float)$booking['total_centavos'] / 100);
        $discount_amt = max(0, ((float)$booking['original_centavos'] - (float)$booking['total_centavos']) / 100);
    } else {
        $total_amount = max(0, $nights * (float)$booking['nightly_rate'] - $discount_amt);
    }
    $remaining_balance = max(0, $total_amount - $deposit_paid);

    $checkin_fmt = date('D, F j, Y', strtotime($booking['check_in']));
    $checkout_fmt = date('D, F j, Y', strtotime($booking['check_out']));
    $created_fmt = date('F j, Y g:i A', strtotime($booking['created_at'] ?? 'now'));

    // Check-in QR Code Generation
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . $_SERVER['HTTP_HOST']
              . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $checkin_url = $base_url . '/checkin.php?ref=' . urlencode($booking_ref) . '&token=' . urlencode($booking['checkin_token'] ?? '');

    $qr_dir = dirname(__DIR__, 2) . '/frontend/assets/qrcodes/';
    if (!is_dir($qr_dir)) {
        @mkdir($qr_dir, 0777, true);
    }
    $qr_file = $qr_dir . 'qr_booking_' . $booking['id'] . '.png';
    if (!file_exists($qr_file)) {
        QRcode::png($checkin_url, $qr_file, QR_ECLEVEL_H, 6, 4);
    }
    $qr_src = 'assets/qrcodes/qr_booking_' . $booking['id'] . '.png?v=' . (file_exists($qr_file) ? filemtime($qr_file) : time());

    $autoPrint = isset($_GET['print']) && $_GET['print'] == '1';

    return compact(
        'booking',
        'booking_ref',
        'nights',
        'deposit_paid',
        'discount_amt',
        'extra_adults',
        'extra_children',
        'included_guests',
        'discount_label',
        'total_amount',
        'remaining_balance',
        'checkin_fmt',
        'checkout_fmt',
        'created_fmt',
        'qr_src',
        'autoPrint'
    );
}
