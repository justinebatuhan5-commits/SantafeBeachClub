<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/helpers/private_storage_helper.php';
require_once __DIR__ . '/../backend/libs/phpqrcode/phpqrcode.php';
require_once __DIR__ . '/../backend/services/booking_service.php';
require_once __DIR__ . '/../backend/services/paymongo.php';
require_once __DIR__ . '/../backend/services/paymongo_confirmation.php';

function booking_public_frontend_url(): string
{
    $configured = rtrim((string)(getenv('PUBLIC_APP_URL') ?: ''), '/');
    if ($configured === '' || filter_var($configured, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('PUBLIC_APP_URL is not configured correctly.');
    }
    $host = strtolower((string)parse_url($configured, PHP_URL_HOST));
    $scheme = strtolower((string)parse_url($configured, PHP_URL_SCHEME));
    if ($scheme !== 'https' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
        throw new RuntimeException('PUBLIC_APP_URL must use HTTPS.');
    }
    return str_ends_with($configured, '/frontend') ? $configured : $configured . '/frontend';
}

/** Remove a locally-created booking when PayMongo never created a checkout. */
function cleanup_failed_paymongo_booking(mysqli $conn, int $bookingId, string $cancellationToken): bool
{
    if ($bookingId <= 0 || !preg_match('/\A[a-f0-9]{32}\z/', $cancellationToken)) {
        return false;
    }

    $conn->begin_transaction();
    try {
        $lock = $conn->prepare(
            "SELECT id FROM bookings
             WHERE id = ? AND cancellation_token = ? AND status = 'Pending Payment'
             FOR UPDATE"
        );
        $lock->bind_param('is', $bookingId, $cancellationToken);
        $lock->execute();
        $ownedPendingBooking = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$ownedPendingBooking) {
            $conn->rollback();
            return false;
        }

        $payments = $conn->prepare(
            "DELETE FROM payments WHERE booking_id = ? AND status = 'pending'"
        );
        $payments->bind_param('i', $bookingId);
        $payments->execute();
        $payments->close();

        $booking = $conn->prepare(
            "DELETE FROM bookings
             WHERE id = ? AND cancellation_token = ? AND status = 'Pending Payment'"
        );
        $booking->bind_param('is', $bookingId, $cancellationToken);
        $booking->execute();
        $deleted = $booking->affected_rows === 1;
        $booking->close();

        if (!$deleted) {
            throw new RuntimeException('The failed PayMongo booking was not deleted.');
        }
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $rollbackError) { /* already closed */ }
        error_log('Failed PayMongo booking cleanup error: ' . $e->getMessage());
        return false;
    }
}

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowed_room_types = ['beachview_duplex', 'seaview_duplex', 'beach_villa', 'standard_room', 'standard_king'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && (isset($_GET['rebook']) && $_GET['rebook'] === '1')) {
    $rebook_session_fields = [
        'guest_title' => 'title',
        'guest_first_name' => 'first_name',
        'guest_last_name' => 'last_name',
        'guest_email' => 'email',
        'guest_phone' => 'phone',
        'guest_country' => 'country',
        'guest_comments' => 'comments',
    ];

    foreach ($rebook_session_fields as $session_key => $request_key) {
        if (isset($_GET[$request_key])) {
            $_SESSION[$session_key] = trim((string)$_GET[$request_key]);
        }
    }
}

$step = isset($_REQUEST['step']) ? (int)$_REQUEST['step'] : 1;

// Grab values from homepage search or session/request
$checkin = isset($_REQUEST['checkin']) ? $_REQUEST['checkin'] : (isset($_SESSION['book_checkin']) ? $_SESSION['book_checkin'] : date('Y-m-d'));
$checkout = isset($_REQUEST['checkout']) ? $_REQUEST['checkout'] : (isset($_SESSION['book_checkout']) ? $_SESSION['book_checkout'] : date('Y-m-d', strtotime('+1 day')));
$guests = isset($_REQUEST['guests']) ? (int)$_REQUEST['guests'] : (isset($_SESSION['book_guests']) ? (int)$_SESSION['book_guests'] : 2);
$room_type = isset($_REQUEST['room_type']) ? $_REQUEST['room_type'] : (isset($_SESSION['book_room_type']) ? $_SESSION['book_room_type'] : 'beachview_duplex');
if (!in_array($room_type, $allowed_room_types, true)) {
    $room_type = 'standard_room';
}

// Capture extra adults/children selected on rooms.php and persist across steps.
// extra_adults comes as a URL param on first load; afterwards it lives in session.
if (isset($_REQUEST['extra_adults'])) {
    $_SESSION['book_extra_adults']   = max(0, (int)$_REQUEST['extra_adults']);
    $_SESSION['book_extra_children'] = isset($_REQUEST['extra_children']) ? max(0, (int)$_REQUEST['extra_children']) : 0;
}
$extra_adults   = isset($_SESSION['book_extra_adults'])   ? (int)$_SESSION['book_extra_adults']   : 0;
$extra_children = isset($_SESSION['book_extra_children']) ? (int)$_SESSION['book_extra_children'] : 0;
// The nightly rate includes the room's base occupancy. A smaller search count
// must not reduce that included occupancy; selected extras are added afterward.
// Example: Standard Room searched for 1 adult => 2 included adults; selecting
// 1 extra adult => 3 total adults (not 2).
$_base_capacity = ['beachview_duplex' => 2, 'seaview_duplex' => 2, 'beach_villa' => 4, 'standard_king' => 4, 'standard_room' => 2];
$included_guests = max($guests, $_base_capacity[$room_type] ?? 2);
$guests_total = $included_guests + $extra_adults + $extra_children;

// Store in session to persist across steps
$_SESSION['book_checkin']   = $checkin;
$_SESSION['book_checkout']  = $checkout;
$_SESSION['book_guests']    = $guests;
$_SESSION['book_room_type'] = $room_type;

$error = '';
$success = false;
$booking_id = 0;

// Reject malformed or impossible calendar dates before pricing constructs
// DateTime objects. Reset to a safe range so the page can render the error.
if (!isValidDateRange((string)$checkin, (string)$checkout)) {
    $error = 'Please select valid check-in and check-out dates. Check-out must be later than check-in.';
    $checkin = date('Y-m-d');
    $checkout = date('Y-m-d', strtotime('+1 day'));
    $_SESSION['book_checkin'] = $checkin;
    $_SESSION['book_checkout'] = $checkout;
}

// Handle cancellation from PayMongo checkout screen
if (isset($_GET['cancelled']) && $_GET['cancelled'] == 1) {
    $paymentCancelToken = trim((string)($_GET['cancel_token'] ?? ''));
    if (preg_match('/\A[a-f0-9]{32}\z/', $paymentCancelToken)) {
        // The random booking token is the authority to cancel this pending
        // checkout. A public numeric booking ID must never authorize deletion.
        $bkCheck = $conn->prepare(
            "SELECT id FROM bookings
             WHERE cancellation_token = ? AND status = 'Pending Payment'
             LIMIT 1"
        );
        $bkCheck->bind_param('s', $paymentCancelToken);
        $bkCheck->execute();
        $bookingToCancel = $bkCheck->get_result()->fetch_assoc();
        $bkCheck->close();

        if ($bookingToCancel) {
            $cbId = (int)$bookingToCancel['id'];
            $conn->begin_transaction();
            try {
                $deletePayments = $conn->prepare(
                    "DELETE FROM payments WHERE booking_id = ? AND status = 'pending'"
                );
                $deletePayments->bind_param('i', $cbId);
                $deletePayments->execute();
                $deletePayments->close();

                $deleteBooking = $conn->prepare(
                    "DELETE FROM bookings
                     WHERE id = ? AND cancellation_token = ? AND status = 'Pending Payment'"
                );
                $deleteBooking->bind_param('is', $cbId, $paymentCancelToken);
                $deleteBooking->execute();
                $bookingDeleted = $deleteBooking->affected_rows === 1;
                $deleteBooking->close();

                if (!$bookingDeleted) {
                    throw new RuntimeException('Pending booking was not cancelled.');
                }
                $conn->commit();

                $qr_f = __DIR__ . '/assets/qrcodes/qr_booking_' . $cbId . '.png';
                if (is_file($qr_f)) { @unlink($qr_f); }
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Pending PayMongo cancellation cleanup failed: ' . $e->getMessage());
            }
        }
    }
    $error = 'Payment was cancelled or not completed. Please try again or select another payment method.';
    $step = 3;
}

// Restore success state on GET redirect to prevent form resubmission and double notifications
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_SESSION['booking_success'])) {
    $success = true;
    $step = 4;
    $booking_id = $_SESSION['booking_success']['booking_id'];
    $booking_ref = $_SESSION['booking_success']['booking_ref'];
    $guest_name = $_SESSION['booking_success']['guest_name'];
    $guest_email = $_SESSION['booking_success']['guest_email'];
    $checkin = $_SESSION['booking_success']['checkin'];
    $checkout = $_SESSION['booking_success']['checkout'];
    $accommodation_name = $_SESSION['booking_success']['accommodation'];
    $nights = $_SESSION['booking_success']['nights'];
    $total_amount = $_SESSION['booking_success']['total_amount'] ?? 0;
    $deposit_amount = $_SESSION['booking_success']['deposit_amount'];
    $remaining_balance = $_SESSION['booking_success']['remaining_balance'] ?? 0;
    $payment_method = $_SESSION['booking_success']['payment_method'];
    $checkin_token = $_SESSION['booking_success']['checkin_token'];
    $cancel_token = $_SESSION['booking_success']['cancel_token'];
    $guests = $_SESSION['booking_success']['guests'] ?? $guests;
    $booking_eta = $_SESSION['booking_success']['eta'] ?? '14:00';
    
    $base_url = booking_public_frontend_url();
    $cancel_url = rtrim($base_url, '/') . '/cancel_booking?token=' . $cancel_token;
}

require_once __DIR__ . '/../backend/helpers/room_status_helper.php';
require_once __DIR__ . '/../backend/services/mailer.php';

// Room mapping
$room_names = [
    'beachview_duplex' => 'BEACHVIEW DUPLEX',
    'seaview_duplex'   => 'SEAVIEW DUPLEX',
    'beach_villa'      => 'BEACH VILLA',
    'standard_room'    => 'STANDARD ROOM',
    'standard_king'    => 'STANDARD FAMILY ROOM'
];
$accommodation_name = isset($room_names[$room_type]) ? $room_names[$room_type] : 'STANDARD ROOM';

// Handle Promo Code submission via session or request
if (isset($_POST['apply_promo'])) {
    $_SESSION['applied_promo_code'] = strtoupper(trim($_POST['promo_code'] ?? ''));
} elseif (isset($_POST['remove_promo'])) {
    unset($_SESSION['applied_promo_code']);
}
$applied_promo = $_SESSION['applied_promo_code'] ?? ($_REQUEST['promo_code'] ?? null);

// Fetch Dynamic Stay Pricing. $extra_adults is passed as the override so the
// pricing function charges exactly the extras selected on rooms.php.
$pricing = calculateStayPricing($conn, $room_type, $checkin, $checkout, $applied_promo, $guests_total, $extra_adults);
$room_price = $pricing['base_price_per_night'];
$nights = $pricing['nights'];
$subtotal_amount = $pricing['subtotal'];
$discount_amount = $pricing['discount_amount'];
$total_amount = $pricing['total_amount'];
$deposit_amount = $pricing['deposit_amount'];
$promo_error = $pricing['promo_error'];
$applied_promo_code = $pricing['promo_code'];

if ($promo_error && isset($_POST['apply_promo'])) {
    $error = $promo_error;
    unset($_SESSION['applied_promo_code']);
}

// Fetch GCash Settings
$gcash_number = '0950 522 3146';
$gcash_name = 'Justine B';
if (isset($conn)) {
    $g_res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('gcash_number', 'gcash_name')");
    if ($g_res) {
        while ($r = $g_res->fetch_assoc()) {
            if ($r['setting_key'] === 'gcash_number' && !empty($r['setting_value'])) $gcash_number = $r['setting_value'];
            if ($r['setting_key'] === 'gcash_name' && !empty($r['setting_value'])) $gcash_name = $r['setting_value'];
        }
    }
}

$breakfast_included_types = [
    'beachview_duplex' => true,
    'seaview_duplex'   => true,
    'beach_villa'      => true,
];
$has_breakfast = !empty($breakfast_included_types[$room_type]);

$room_type_id = 0;
$room_type_stmt = $conn->prepare("SELECT id FROM room_types WHERE name = ? LIMIT 1");
$room_type_stmt->bind_param("s", $room_type);
$room_type_stmt->execute();
$room_type_result = $room_type_stmt->get_result();
if ($room_type_result && $room_type_result->num_rows > 0) {
    $room_type_row = $room_type_result->fetch_assoc();
    $room_type_id = (int) $room_type_row['id'];
}
$room_type_stmt->close();

$available_rooms = null;
if ($room_type_id > 0) {
    try {
        $pdo = getPdoConnection();
        $available_rooms = getAvailableRooms($pdo, $room_type_id, $checkin, $checkout);
    } catch (Throwable $e) {
        $available_rooms = null;
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    // Applying or removing a coupon must refresh Step 1 in place. These buttons
    // share the form with the hidden go_step_2 action, so exclude promo submits
    // from the step-navigation handler.
    if (isset($_POST['action']) && !isset($_POST['apply_promo']) && !isset($_POST['remove_promo'])) {
        if ($_POST['action'] === 'go_step_2') {
            header('Location: book?step=2');
            exit;
        } elseif ($_POST['action'] === 'go_step_3') {
            // Save guest details
            $_SESSION['guest_title'] = $_POST['title'] ?? 'Mr.';
            $_SESSION['guest_first_name'] = ucwords(strtolower(trim($_POST['first_name'] ?? '')));
            $_SESSION['guest_last_name'] = ucwords(strtolower(trim($_POST['last_name'] ?? '')));
            $_SESSION['guest_email'] = trim($_POST['email'] ?? '');
            $_SESSION['guest_phone_code'] = $_POST['phone_code'] ?? '+63';
            $_SESSION['guest_phone'] = $_POST['phone'] ?? '';
            $_SESSION['guest_country'] = $_POST['country'] ?? '';
            $_SESSION['guest_comments'] = $_POST['comments'] ?? '';
            $_SESSION['guest_eta'] = $_POST['eta'] ?? '14:00';
            
            if (empty($_SESSION['guest_first_name']) || empty($_SESSION['guest_last_name']) || empty($_SESSION['guest_email'])) {
                $error = "Please fill out required fields.";
                $step = 2;
            } else {
                header('Location: book?step=3');
                exit;
            }
        } elseif ($_POST['action'] === 'request_discount' || ($_POST['action'] === 'confirm_booking' && in_array($_POST['discount_category'] ?? '', ['senior', 'pwd'], true))) {
            try {
                if (empty($_POST['discount_consent'])) throw new RuntimeException('Please consent to ID verification and accept the booking policies.');
                if (empty($_SESSION['discount_request_nonce']) || !hash_equals($_SESSION['discount_request_nonce'], (string)($_POST['discount_request_nonce'] ?? ''))) throw new RuntimeException('This form was already submitted or has expired. Please refresh.');
                require_once __DIR__ . '/../backend/helpers/rate_limiter.php';
                RateLimiter::enforce($conn, 'discount_request', 5, 3600);
                $request = discount_create($conn, [
                    'category' => $_POST['discount_category'] ?? '',
                    'eligible_guest' => trim($_POST['eligible_guest'] ?? ''),
                    'name' => trim(($_SESSION['guest_first_name'] ?? '') . ' ' . ($_SESSION['guest_last_name'] ?? '')),
                    'email' => $_SESSION['guest_email'] ?? '', 'phone' => $_SESSION['guest_phone'] ?? '',
                    'eta' => $_SESSION['guest_eta'] ?? '14:00', 'room_type' => $room_type,
                    'checkin' => $checkin, 'checkout' => $checkout, 'guests' => $guests_total,
                    'extra_adults' => $extra_adults, 'extra_children' => $extra_children,
                    // Subtotal is calculated server-side before promo codes. Discounts never stack.
                    'original' => (int)round($pricing['subtotal'] * 100),
                ], $_FILES['discount_id'] ?? []);
                $_SESSION['discount_tokens'][(int)$request['booking_id']] = $request['token'];
                unset($_SESSION['discount_request_nonce']);
                unset($_SESSION['applied_promo_code']);
                $emailSent = discount_email($conn, (int)$request['booking_id']);
                $_SESSION['discount_notice'] = $emailSent ? 'Request submitted. Check your email for your private booking link.' : 'Request submitted. Email could not be sent; please save this private booking link.';
                header('Location: discount_booking.php?booking=' . (int)$request['booking_id']);
                exit;
            } catch (Throwable $e) {
                $error = $e instanceof mysqli_sql_exception ? 'Could not save your request. Please try again.' : $e->getMessage();
                $step = 3;
            }
        } elseif ($_POST['action'] === 'confirm_booking') {
            $bookingTransactionOpen = false;
            $paymongoSessionCreated = false;
            $storedReceiptPath = null;
            // Check required fields again just in case
            $guest_name = ($_SESSION['guest_first_name'] ?? '') . ' ' . ($_SESSION['guest_last_name'] ?? '');
            $guest_email = $_SESSION['guest_email'] ?? '';
            // Auto-detect guest tier based on previous confirmed bookings
            $prev_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE guest_email = ? AND status NOT IN ('Cancelled','Pending Payment')");
            $prev_stmt->bind_param("s", $guest_email);
            $prev_stmt->execute();
            $prev_count = (int)$prev_stmt->get_result()->fetch_assoc()['cnt'];
            $prev_stmt->close();
            if ($prev_count === 0)      $guest_type = 'First Visit';
            elseif ($prev_count === 1)  $guest_type = 'Returning Guest';
            else                        $guest_type = 'VIP Member';

            if ($room_type_id > 0) {
                
                try {
                    $pdo = getPdoConnection();
                    $currentAvailability = getAvailableRooms($pdo, $room_type_id, $checkin, $checkout);
                    if ($currentAvailability <= 0) {
                        $error = 'Sorry, this room type is sold out for the selected dates.';
                        $step = 3;
                    }
                } catch (Throwable $e) {
                    error_log('Booking availability check failed: ' . $e->getMessage());
                    $error = 'We could not confirm availability right now. Please try again.';
                    $step = 3;
                }
            }
            
            if (empty($error)) {
                // Find a room that is not under maintenance and has no overlapping active booking.
                $conn->begin_transaction();
                $bookingTransactionOpen = true;
                pm_query($conn, "SELECT id FROM rooms WHERE type=? AND status<>'maintenance' ORDER BY id FOR UPDATE", [$room_type])->get_result()->fetch_all();
                $room_id = null;
                $db_acc_name = $accommodation_name;
                $room_stmt = $conn->prepare(
                    "SELECT r.id, r.name
                     FROM rooms r
                     WHERE r.type = ?
                       AND r.status <> 'maintenance'
                       AND NOT EXISTS (
                           SELECT 1
                           FROM bookings b
                           WHERE b.room_id = r.id
                             AND b.status IN ('Confirmed', 'Checked In', 'Pending', 'Pending Payment')
                             AND b.check_in < ?
                             AND b.check_out > ?
                       )
                     ORDER BY CASE WHEN r.status = 'ready' THEN 0 ELSE 1 END, r.room_number ASC
                     LIMIT 1"
                );
                $room_stmt->bind_param("sss", $room_type, $checkout, $checkin);
                $room_stmt->execute();
                $room_query = $room_stmt->get_result();
                if ($room_query && $room_query->num_rows > 0) {
                    $room = $room_query->fetch_assoc();
                    $room_id = (int)$room['id'];
                    $db_acc_name = $room['name'];
                }
                $room_stmt->close();

                if ($room_id === null) {
                    $error = 'No available room matches your selected dates. Please choose different dates or room type.';
                    $step = 3;
                }
            }
            
            $checkin_token = bin2hex(random_bytes(16));
            $cancel_token = bin2hex(random_bytes(16));
            $payment_method = $_POST['payment_method'] ?? 'Bank Deposit';
            $bank_reference = isset($_POST['bank_reference']) ? trim($_POST['bank_reference']) : '';
            $skip_insert = false;

            if ($payment_method === 'Bank Deposit' && empty($bank_reference)) {
                $error = 'Please enter your Bank transfer reference number to continue.';
                $step = 3;
                $skip_insert = true;
            }

            $payment_ref = 'TXN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            if ($payment_method === 'Bank Deposit') {
                $payment_ref = $bank_reference;
            }

            $receipt_url = null;

            // Handle receipt upload for Bank Deposit
            if ($payment_method === 'Bank Deposit' && empty($error)) {
                require_once __DIR__ . '/../backend/helpers/file_scanner.php';
                $file_key = 'bank_receipt';
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                    // Store privately; database paths remain logical receipt identifiers.
                    $tmpFile  = $_FILES[$file_key]['tmp_name'];
                    $origName = basename($_FILES[$file_key]['name']);
                    $file_ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $allowed_exts  = ['jpg', 'jpeg', 'png', 'pdf'];
                    $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
                    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                    $realMime = finfo_file($finfo, $tmpFile);
                    finfo_close($finfo);
                    $max_size = 2 * 1024 * 1024; // 2MB limit
                    if ($_FILES[$file_key]['size'] > $max_size) {
                        $error = 'File size exceeds 2MB limit. Please upload an image under 2MB.';
                        $step  = 3; $skip_insert = true;
                    } elseif (!in_array($file_ext, $allowed_exts, true) || !in_array($realMime, $allowed_mimes, true)) {
                        $error = 'Invalid receipt file type. Only JPG, PNG, and PDF are allowed.';
                        $step  = 3; $skip_insert = true;
                    } else {
                        // Malware / dangerous content scan
                        $scanResult = scan_uploaded_file($tmpFile, $file_ext);
                        if (!$scanResult['safe']) {
                            $error = 'File rejected: ' . ($scanResult['reason'] ?? 'unsafe content detected');
                            $step  = 3; $skip_insert = true;
                        } else {
                            $new_filename = 'rcpt_' . bin2hex(random_bytes(16)) . '.' . $file_ext;
                            if (store_private_receipt($tmpFile, $new_filename)) {
                                $receipt_url = 'storage/receipts/' . $new_filename;
                                $storedReceiptPath = private_storage_file('receipts/' . $new_filename);
                            } else {
                                $error = 'Failed to upload receipt image. Please try again.';
                                $step  = 3; $skip_insert = true;
                            }
                        }
                    }
                } else {
                    $error = 'Please upload a screenshot or image of your bank receipt.';
                    $step  = 3; $skip_insert = true;
                }
            }

            // Handle receipt upload for GCash QR
            if ($payment_method === 'GCash QR' && empty($error)) {
                require_once __DIR__ . '/../backend/helpers/file_scanner.php';
                $file_key = 'gcash_receipt';
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                    // Store privately; database paths remain logical receipt identifiers.
                    $tmpFile  = $_FILES[$file_key]['tmp_name'];
                    $origName = basename($_FILES[$file_key]['name']);
                    $file_ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $allowed_exts  = ['jpg', 'jpeg', 'png'];
                    $allowed_mimes = ['image/jpeg', 'image/png'];
                    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                    $realMime = finfo_file($finfo, $tmpFile);
                    finfo_close($finfo);
                    $max_size = 2 * 1024 * 1024; // 2MB limit
                    if ($_FILES[$file_key]['size'] > $max_size) {
                        $error = 'File size exceeds 2MB limit. Please upload an image under 2MB.';
                        $step  = 3; $skip_insert = true;
                    } elseif (!in_array($file_ext, $allowed_exts, true) || !in_array($realMime, $allowed_mimes, true)) {
                        $error = 'Invalid file type. Please upload a JPG or PNG screenshot of your GCash payment.';
                        $step  = 3; $skip_insert = true;
                    } else {
                        // Malware / dangerous content scan
                        $scanResult = scan_uploaded_file($tmpFile, $file_ext);
                        if (!$scanResult['safe']) {
                            $error = 'File rejected: ' . ($scanResult['reason'] ?? 'unsafe content detected');
                            $step  = 3; $skip_insert = true;
                        } else {
                            $new_filename = 'gcash_rcpt_' . bin2hex(random_bytes(16)) . '.' . $file_ext;
                            if (store_private_receipt($tmpFile, $new_filename)) {
                                $receipt_url = 'storage/receipts/' . $new_filename;
                                $storedReceiptPath = private_storage_file('receipts/' . $new_filename);
                            } else {
                                $error = 'Failed to upload GCash receipt. Please try again.';
                                $step  = 3; $skip_insert = true;
                            }
                        }
                    }
                } else {
                    $error = 'Please upload a screenshot of your GCash payment receipt.';
                    $step  = 3; $skip_insert = true;
                }
            }

            if (!$skip_insert && empty($error)) {
                try {
                    $guest_phone           = $_SESSION['guest_phone'] ?? '';
                    $guest_country         = $_SESSION['guest_country'] ?? '';
                    $guest_special_requests = $_SESSION['guest_comments'] ?? '';
                    $eta                   = $_SESSION['guest_eta'] ?? '14:00';

                    $room_value = $room_id === null ? "NULL" : (string)$room_id;
                    $sql = "INSERT INTO bookings (guest_name, guest_email, guest_type, check_in, check_out, guests_count, extra_adults, extra_children, room_id, accommodation_name, eta, status, checkin_token, cancellation_token, payment_method, guest_phone, guest_country, guest_special_requests, promo_code, discount_amount)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, " . $room_value . ", ?, ?, 'Pending Payment', ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssiiisssssssssd", $guest_name, $guest_email, $guest_type, $checkin, $checkout, $guests_total, $extra_adults, $extra_children, $db_acc_name, $eta, $checkin_token, $cancel_token, $payment_method, $guest_phone, $guest_country, $guest_special_requests, $applied_promo_code, $discount_amount);

                    if ($stmt->execute()) {
                        $booking_id  = $stmt->insert_id;
                        $booking_ref = 'REF-' . str_pad($booking_id, 3, '0', STR_PAD_LEFT);
                        $pay_status  = 'pending';

                        $stmt_pay = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, receipt_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_pay->bind_param("issdssss", $booking_id, $guest_name, $guest_email, $deposit_amount, $payment_method, $payment_ref, $pay_status, $receipt_url);
                        $stmt_pay->execute();
                        $checkoutPaymentId = (int)$stmt_pay->insert_id;
                        $conn->commit();
                        $bookingTransactionOpen = false;

                        // QR Code for check-in
                        $base_url   = booking_public_frontend_url();
                        $checkin_url = rtrim($base_url, '/') . '/checkin?ref=' . $booking_ref . '&token=' . $checkin_token;
                        $cancel_url  = rtrim($base_url, '/') . '/cancel_booking?token=' . $cancel_token;
                        $qr_dir  = __DIR__ . '/assets/qrcodes/';
                        if (!is_dir($qr_dir)) { mkdir($qr_dir, 0777, true); }
                        $qr_file = $qr_dir . 'qr_booking_' . $booking_id . '.png';
                        QRcode::png($checkin_url, $qr_file, QR_ECLEVEL_H, 6, 4);

                        // If PayMongo Online Checkout is selected, create session and redirect
                        if ($payment_method === 'PayMongo Online') {
                            $successUrl = rtrim($base_url, '/') . '/payment_success.php?booking_id=' . $booking_id . '&session_id={CHECKOUT_SESSION_ID}';
                            $cancelPaymentUrl = rtrim($base_url, '/') . '/book.php?step=3&cancelled=1&cancel_token=' . rawurlencode($cancel_token);
                            $desc = '50% Booking Deposit for ' . $db_acc_name . ' (' . $booking_ref . ')';

                            $paymongoRes = PayMongoService::createCheckoutSession(
                                $deposit_amount,
                                $desc,
                                $booking_id,
                                $guest_name,
                                $guest_email,
                                $guest_phone,
                                $successUrl,
                                $cancelPaymentUrl
                            );

                            if (!empty($paymongoRes['success']) && !empty($paymongoRes['checkout_url'])) {
                                $paymongoSessionCreated = true;
                                $sessId = $paymongoRes['session_id'] ?? '';
                                pm_store_checkout($conn, $sessId, $checkoutPaymentId, (int)$booking_id, (float)$deposit_amount);
                                pm_query($conn, "UPDATE payments SET transaction_id = ? WHERE id = ? AND status = 'pending'", [$sessId, $checkoutPaymentId]);
                                unset($_SESSION['applied_promo_code']);
                                @sendBookingReceivedEmail(
                                    $guest_email, $guest_name, $booking_ref, $db_acc_name,
                                    $checkin, $checkout, $total_amount, $cancel_url
                                );
                                header('Location: ' . $paymongoRes['checkout_url']);
                                exit;
                            } else {
                                $pmErr = $paymongoRes['error'] ?? 'PayMongo service error';
                                error_log('PayMongo checkout creation failed: ' . $pmErr);
                                $error = 'Online payment could not be started. Please try again.';
                                $step = 3;

                                // PayMongo did not create a checkout, so atomically remove
                                // the local pending payment and its exact token-bound booking.
                                $cleaned = cleanup_failed_paymongo_booking(
                                    $conn,
                                    (int)$booking_id,
                                    $cancel_token
                                );
                                if (!$cleaned) {
                                    error_log('Failed PayMongo setup left booking REF-' . (int)$booking_id . ' for manual review.');
                                }
                                if ($cleaned && !empty($qr_file) && is_file($qr_file)) {
                                    @unlink($qr_file);
                                }
                            }
                        }

                        if (empty($error)) {
                            // Auto-dispatch booking confirmation / payment pending receipt email
                            if (!empty($guest_email)) {
                                @sendBookingReceivedEmail(
                                    $guest_email,
                                    $guest_name,
                                    $booking_ref,
                                    $db_acc_name,
                                    $checkin,
                                    $checkout,
                                    $total_amount,
                                    $cancel_url,
                                    $checkin_url
                                );
                            }

                            // Dashboard notification
                            $notif_title   = 'New Guest Reservation';
                            $notif_type    = 'info';
                            $notif_message = htmlspecialchars($guest_name) . ' booked ' . htmlspecialchars($db_acc_name) . ' (' . $booking_ref . '). ETA: ' . htmlspecialchars($eta) . ' (' . $guests_total . ' guests)';
                            if ($payment_method === 'GCash QR') {
                                $notif_title   = 'GCash Payment Pending Verification';
                                $notif_message = htmlspecialchars($guest_name) . ' paid via GCash for ' . htmlspecialchars($db_acc_name) . ' (' . $booking_ref . '). Please verify the receipt.';
                                $notif_type    = 'warning';
                            }
                            $stmt_notif = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
                            $stmt_notif->bind_param("sssi", $notif_title, $notif_message, $notif_type, $booking_id);
                            $stmt_notif->execute();

                            // PRG: store success data in session
                            $_SESSION['booking_success'] = [
                                'booking_id'        => $booking_id,
                                'booking_ref'       => $booking_ref,
                                'guest_name'        => $guest_name,
                                'guest_email'       => $guest_email,
                                'checkin'           => $checkin,
                                'checkout'          => $checkout,
                                'accommodation'     => $db_acc_name,
                                'nights'            => $nights,
                                'total_amount'      => $total_amount,
                                'deposit_amount'    => $deposit_amount,
                                'remaining_balance' => $total_amount - $deposit_amount,
                                'discount_amount'   => $discount_amount,
                                'promo_code'        => $applied_promo_code,
                                'payment_method'    => $payment_method,
                                'checkin_token'     => $checkin_token,
                                'cancel_token'      => $cancel_token,
                                'guests'            => $guests_total,
                                'eta'               => $eta,
                            ];

                            unset($_SESSION['applied_promo_code']);

                            header('Location: book?success=1&ref=' . urlencode($booking_ref) . '&room_type=' . urlencode($room_type) . '&checkin=' . urlencode($checkin) . '&checkout=' . urlencode($checkout));
                            exit;
                        }
                    } else {
                        $error = 'Error saving your booking. Please try again.';
                    }
                } catch (Throwable $e) {
                    // If the provider failed before returning a checkout session,
                    // clean up the already-committed local booking as well.
                    if (($payment_method ?? '') === 'PayMongo Online'
                        && !$paymongoSessionCreated
                        && !$bookingTransactionOpen
                        && !empty($booking_id)
                        && !empty($cancel_token)) {
                        $cleaned = cleanup_failed_paymongo_booking($conn, (int)$booking_id, $cancel_token);
                        if ($cleaned && !empty($qr_file) && is_file($qr_file)) @unlink($qr_file);
                    }
                    error_log('Booking save failed: ' . $e->getMessage());
                    $error = 'Error saving your booking. Please try again.';
                }
            }
            if ($bookingTransactionOpen) $conn->rollback();
            if (!empty($storedReceiptPath) && is_file($storedReceiptPath)) {
                if (!@unlink($storedReceiptPath)) {
                    error_log('Could not remove orphaned receipt after booking failure.');
                }
            }
        }
    }
}

// Formatting helpers
$checkin_fmt = date('D, d M Y', strtotime($checkin));
$checkout_fmt = date('D, d M Y', strtotime($checkout));
$full_name = trim(($_SESSION['guest_first_name'] ?? '') . ' ' . ($_SESSION['guest_last_name'] ?? ''));
if ($step === 3 && empty($_SESSION['discount_request_nonce'])) $_SESSION['discount_request_nonce'] = bin2hex(random_bytes(24));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Reservation - Santa Fe Beach Club</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="assets/css/book.css?v=<?= time(); ?>">
    <script src="assets/js/security.js?v=<?= time(); ?>" defer></script>
<script src="assets/js/site-dialog.js?v=1"></script>
</head>
<body>

    <!-- Header -->
    <header class="main-header">
        <div class="brand-logo" style="display:flex; align-items:center;">
            <a href="index" class="logo-link"><img src="assets/logo.jpg" alt="Santa Fe Beach Club" style="height:40px; width:40px; border-radius:50%; object-fit:cover;"></a>
        </div>
        <nav class="nav-menu">
            <ul>
                <li><a href="index#about" style="color:#111; font-weight:600;">About Us</a></li>
            </ul>
        </nav>
    </header>

    <?php if ($step === 4 && $success): ?>
    <!-- SUCCESS SCREEN -->
    <div class="booking-page-container">
        <div class="booking-card success-box">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h2>Booking Confirmed!</h2>
            <p style="color: #666; margin-bottom: 30px;">Thank you, <?php echo htmlspecialchars($guest_name); ?>. Your reservation has been received!<br><br>
            <strong style="color:#d32f2f;">Important:</strong> Your booking is currently <strong>Pending Payment Verification</strong>. Once our staff verifies your payment, your booking will be Confirmed and your room will be officially reserved.</p>
            
            <div class="ticket-pass">
                <div class="ticket-header">
                    <div class="ticket-brand">Santa Fe Beach Club</div>
                    <div class="ticket-header-actions">
                        <div class="ticket-ref">REF-<?php echo str_pad($booking_id, 3, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
                <div class="ticket-grid">
                    <div>
                        <div style="margin-bottom: 15px;">
                            <span style="font-size: 11px; color: #888; text-transform: uppercase;">Room</span><br>
                            <strong><?php echo htmlspecialchars($accommodation_name); ?></strong>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <span style="font-size: 11px; color: #888; text-transform: uppercase;">Check-in / Check-out</span><br>
                            <strong><?php echo $checkin_fmt; ?> - <?php echo $checkout_fmt; ?></strong><br>
                            <small style="color:#555;">Estimated Arrival: <strong><?php echo htmlspecialchars($booking_eta); ?></strong></small>
                        </div>
                        <div>
                            <span style="font-size: 11px; color: #888; text-transform: uppercase;">Guests</span><br>
                            <strong><?php echo $guests_total; ?> Adult<?php echo $guests_total > 1 ? 's' : ''; ?></strong>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 15px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom: 5px; font-size: 13px;">
                        <span>Total Stay Cost:</span>
                        <strong>₱ <?php echo number_format($total_amount, 2); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom: 5px; font-size: 13px;">
                        <span>Deposit (Pending Verification):</span>
                        <strong style="color: #0284c7;">₱ <?php echo number_format($deposit_amount, 2); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-top: 10px; font-size: 15px; font-weight: 700; color: #b45309; background: #fffbeb; padding: 10px; border-radius: 6px;">
                        <span>Balance due at Front Desk:</span>
                        <span>₱ <?php echo number_format($remaining_balance, 2); ?></span>
                    </div>
                </div>
                
                <div style="margin-top:15px; padding:12px 14px; border:1px solid #f0e3d8; border-radius:8px; background:#fffaf6; font-size:12px; color:#6b4a35;">
                    Need to cancel later? Save this secure link:
                    <div style="margin-top:6px; word-break: break-all;">
                        <a href="<?php echo htmlspecialchars($cancel_url); ?>" style="color:#8B5E3C; font-weight:600;"><?php echo htmlspecialchars($cancel_url); ?></a>
                    </div>
                </div>
            </div>
            <div class="booking-success-actions">
                <a href="invoice.php?ref=<?php echo urlencode($booking_ref); ?>&token=<?php echo urlencode($checkin_token); ?>"
                   target="_blank" 
                   class="btn-home" style="background:#7C533C; color:#fff; margin-right:10px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px; vertical-align:middle;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print / PDF Invoice
                </a>
                <a href="assets/qrcodes/qr_booking_<?php echo $booking_id; ?>.png" 
                   download="SantaFe_Pass_<?php echo htmlspecialchars($booking_ref); ?>.png"
                   class="btn-home" style="background:#0F172A; color:#fff; margin-right:10px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px; vertical-align:middle;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Save QR Pass (PNG)
                </a>
                <a href="my_booking" class="btn-home" style="margin-right:10px;">View My Booking</a>
            </div>
        </div>
    </div>
    <?php else: ?>

    <div class="bk-layout">
        
        <!-- Top Progress Bar -->
        <div class="bk-progress">
            <div class="bk-step <?php echo $step >= 1 ? 'bk-step--active' : ''; ?> <?php echo $step > 1 ? 'bk-step--done' : ''; ?>">
                <div class="bk-step-num">
                    <?php if($step > 1): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><?php else: ?>1<?php endif; ?>
                </div>
                <div class="bk-step-label">CONFIRMATION & EXTRAS</div>
            </div>
            <div class="bk-line"></div>
            <div class="bk-step <?php echo $step >= 2 ? 'bk-step--active' : ''; ?> <?php echo $step > 2 ? 'bk-step--done' : ''; ?>">
                <div class="bk-step-num">
                    <?php if($step > 2): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><?php else: ?>2<?php endif; ?>
                </div>
                <div class="bk-step-label">GUEST DETAILS</div>
            </div>
            <div class="bk-line"></div>
            <div class="bk-step <?php echo $step >= 3 ? 'bk-step--active' : ''; ?>">
                <div class="bk-step-num">3</div>
                <div class="bk-step-label">PAYMENT</div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bk-error-banner">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="bk-columns">
            
            <!-- LEFT MAIN CONTENT -->
            <div class="bk-main">
                <form method="POST" action="book" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>

                    <?php if ($step === 1): ?>
                    <!-- STEP 1: CONFIRMATION & EXTRAS -->
                    <div class="bk-card">
                        <h2 class="bk-card-title">Room</h2>
                        <div class="bk-room-box">
                            <div class="bk-room-left">
                                <h3 class="bk-room-name">1 × <?php echo htmlspecialchars($accommodation_name); ?></h3>
                                <div class="bk-room-badge" style="background:<?php echo ($available_rooms === 0) ? '#FEE2E2' : (($available_rooms !== null && $available_rooms <= 3) ? '#FFF7ED' : '#ECFDF5'); ?>; color:<?php echo ($available_rooms === 0) ? '#B91C1C' : (($available_rooms !== null && $available_rooms <= 3) ? '#B45309' : '#15803D'); ?>;">
                                    <?php if ($available_rooms === null): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                                        Checking availability...
                                    <?php elseif ($available_rooms === 0): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                        Sold out for selected dates
                                    <?php elseif ($available_rooms <= 3): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                                        Only <?php echo $available_rooms; ?> room<?php echo $available_rooms > 1 ? 's' : ''; ?> left!
                                    <?php else: ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        Available (<?php echo $available_rooms; ?> rooms left)
                                    <?php endif; ?>
                                </div>
                                <ul class="bk-room-perks">
                                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Flexible cancellation <span class="info-icon">i</span></li>
                                    <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> <?php echo $has_breakfast ? 'Breakfast included' : 'No breakfast included'; ?></li>
                                </ul>
                                <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
                                    <button type="button" id="openAvailabilityCalBtn" style="background:#FFF; border:1.5px solid #84563C; color:#84563C; border-radius:8px; padding:7px 14px; font-size:12.5px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s;" onmouseover="this.style.background='#84563C'; this.style.color='#FFF';" onmouseout="this.style.background='#FFF'; this.style.color='#84563C';">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        Change Dates & Check Availability
                                    </button>
                                </div>
                            </div>
                            <div class="bk-room-right">
                                <button type="button" class="bk-edit-icon" id="editRoomBtn" aria-label="Edit room" title="Change Room or Guests">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <div class="bk-room-price">₱ <?php echo number_format($total_amount, 2); ?></div>
                                <div class="bk-room-subtext">
                                    <span class="stay-facts" aria-label="Stay details">
                                        <span><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                                        <span>1 room</span>
                                        <span><?php echo $guests_total; ?> adult<?php echo $guests_total > 1 ? 's' : ''; ?></span>
                                    </span>
                                    <?php if (($pricing['extra_person_total'] ?? 0) > 0): ?>
                                        <span style="color:#0284C7; font-weight:600;">(includes <?php echo $pricing['extra_adults']; ?> extra adult<?php echo $pricing['extra_adults']>1?'s':''; ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($discount_amount > 0): ?>
                                    <div style="font-size:11px; color:#15803D; font-weight:700; margin-top:4px;">Coupon applied: -₱<?php echo number_format($discount_amount, 2); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- PROMO CODE INPUT BOX -->
                    <div class="bk-card" style="padding:16px 20px;">
                        <h3 style="font-size:14px; font-weight:700; margin-bottom:8px; color:var(--text-main); display:flex; align-items:center; gap:8px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#7C533C" stroke-width="2.2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            Have a Promo / Coupon Code?
                        </h3>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <input type="text" name="promo_code" value="<?php echo htmlspecialchars($applied_promo_code ?? ''); ?>" placeholder="Enter code (e.g. SUMMER2026)" style="flex:1; padding:10px 14px; border:1px solid #CBD5E1; border-radius:8px; font-size:13px; text-transform:uppercase;">
                            <button type="submit" name="apply_promo" value="1" class="btn-secondary" style="padding:10px 16px; font-size:12px; font-weight:700; cursor:pointer; background:#0F172A; color:#FFF; border-radius:8px; border:none;">Apply</button>
                            <?php if ($applied_promo_code): ?>
                                <button type="submit" name="remove_promo" value="1" class="btn-secondary" style="padding:10px 12px; font-size:12px; cursor:pointer; background:#FEE2E2; color:#B91C1C; border-radius:8px; border:none;">Remove</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($applied_promo_code && $discount_amount > 0): ?>
                            <div style="margin-top:8px; font-size:12px; color:#15803D; font-weight:600;">
                                ✓ Code <strong><?php echo htmlspecialchars($applied_promo_code); ?></strong> applied! You save ₱<?php echo number_format($discount_amount, 2); ?>.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bk-actions">
                        <a href="rooms" class="btn-bk-back">← Back</a>
                        <input type="hidden" name="action" value="go_step_2">
                        <button type="submit" class="btn-bk-next" <?php echo ($available_rooms === 0) ? 'disabled' : ''; ?>>Next &rarr;</button>
                    </div>


                    <?php elseif ($step === 2): ?>
                    <!-- STEP 2: GUEST DETAILS -->
                    <div class="bk-card">
                        <h2 class="bk-card-title">Guest Details</h2>
                        
                        <div class="bk-form-grid">
                            <div class="bk-form-group">
                                <label>First name <span class="req">*</span></label>
                                <div class="bk-input-combo">
                                    <select name="title" class="bk-select-honorific" style="width:66px; min-width:66px; max-width:66px; flex:0 0 66px;" aria-label="Honorific Title">
                                        <option value="Mr." <?php echo (($_SESSION['guest_title'] ?? 'Mr.') === 'Mr.') ? 'selected' : ''; ?>>Mr.</option>
                                        <option value="Ms." <?php echo (($_SESSION['guest_title'] ?? '') === 'Ms.') ? 'selected' : ''; ?>>Ms.</option>
                                        <option value="Mrs." <?php echo (($_SESSION['guest_title'] ?? '') === 'Mrs.') ? 'selected' : ''; ?>>Mrs.</option>
                                        <option value="Dr." <?php echo (($_SESSION['guest_title'] ?? '') === 'Dr.') ? 'selected' : ''; ?>>Dr.</option>
                                    </select>
                                    <input type="text" name="first_name" placeholder="First name" required data-validate="name" data-label="First name" style="text-transform: capitalize;" value="<?php echo htmlspecialchars($_SESSION['guest_first_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="bk-form-group">
                                <label>Last name <span class="req">*</span></label>
                                <input type="text" name="last_name" placeholder="Last name" required data-validate="name" data-label="Last name" style="text-transform: capitalize;" value="<?php echo htmlspecialchars($_SESSION['guest_last_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="bk-form-group">
                                <label>Email <span class="req">*</span></label>
                                <input type="email" name="email" placeholder="Email" required data-validate="email" data-label="Email address" value="<?php echo htmlspecialchars($_SESSION['guest_email'] ?? ''); ?>">
                            </div>
                            <div class="bk-form-group">
                                <label>Retype email <span class="req">*</span></label>
                                <input type="email" name="email_confirm" placeholder="Retype email" required data-validate="email" data-label="Email confirmation" value="<?php echo htmlspecialchars($_SESSION['guest_email'] ?? ''); ?>">
                            </div>
                            
                            <div class="bk-form-group">
                                <label>Contact phone <span class="req">*</span></label>
                                <div class="bk-input-combo">
                                    <select name="phone_code" class="bk-select-phone" aria-label="Country Code">
                                        <?php
                                        $phone_codes = [
                                            ['code' => '+63', 'flag' => '🇵🇭', 'name' => 'PH (+63)'],
                                            ['code' => '+1',  'flag' => '🇺🇸', 'name' => 'US/CA (+1)'],
                                            ['code' => '+61', 'flag' => '🇦🇺', 'name' => 'AU (+61)'],
                                            ['code' => '+44', 'flag' => '🇬🇧', 'name' => 'UK (+44)'],
                                            ['code' => '+81', 'flag' => '🇯🇵', 'name' => 'JP (+81)'],
                                            ['code' => '+82', 'flag' => '🇰🇷', 'name' => 'KR (+82)'],
                                            ['code' => '+86', 'flag' => '🇨🇳', 'name' => 'CN (+86)'],
                                            ['code' => '+852','flag' => '🇭🇰', 'name' => 'HK (+852)'],
                                            ['code' => '+886','flag' => '🇹🇼', 'name' => 'TW (+886)'],
                                            ['code' => '+65', 'flag' => '🇸🇬', 'name' => 'SG (+65)'],
                                            ['code' => '+60', 'flag' => '🇲🇾', 'name' => 'MY (+60)'],
                                            ['code' => '+62', 'flag' => '🇮🇩', 'name' => 'ID (+62)'],
                                            ['code' => '+66', 'flag' => '🇹🇭', 'name' => 'TH (+66)'],
                                            ['code' => '+84', 'flag' => '🇻🇳', 'name' => 'VN (+84)'],
                                            ['code' => '+91', 'flag' => '🇮🇳', 'name' => 'IN (+91)'],
                                            ['code' => '+971','flag' => '🇦🇪', 'name' => 'AE (+971)'],
                                            ['code' => '+966','flag' => '🇸🇦', 'name' => 'SA (+966)'],
                                            ['code' => '+974','flag' => '🇶🇦', 'name' => 'QA (+974)'],
                                            ['code' => '+49', 'flag' => '🇩🇪', 'name' => 'DE (+49)'],
                                            ['code' => '+33', 'flag' => '🇫🇷', 'name' => 'FR (+33)'],
                                            ['code' => '+39', 'flag' => '🇮🇹', 'name' => 'IT (+39)'],
                                            ['code' => '+34', 'flag' => '🇪🇸', 'name' => 'ES (+34)'],
                                            ['code' => '+41', 'flag' => '🇨🇭', 'name' => 'CH (+41)'],
                                            ['code' => '+31', 'flag' => '🇳🇱', 'name' => 'NL (+31)'],
                                            ['code' => '+64', 'flag' => '🇳🇿', 'name' => 'NZ (+64)'],
                                            ['code' => '+7',  'flag' => '🇷🇺', 'name' => 'RU (+7)'],
                                            ['code' => '+55', 'flag' => '🇧🇷', 'name' => 'BR (+55)'],
                                            ['code' => '+52', 'flag' => '🇲🇽', 'name' => 'MX (+52)'],
                                            ['code' => '+27', 'flag' => '🇿🇦', 'name' => 'ZA (+27)'],
                                        ];
                                        $selected_phone_code = $_SESSION['guest_phone_code'] ?? '+63';
                                        foreach ($phone_codes as $item):
                                            $sel = ($selected_phone_code === $item['code']) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($item['code']) . "\" {$sel}>" . $item['flag'] . " " . htmlspecialchars($item['code']) . "</option>\n";
                                        endforeach;
                                        ?>
                                    </select>
                                    <input type="tel" name="phone" placeholder="09171234567" required data-validate="phone" data-label="Phone number" value="<?php echo htmlspecialchars($_SESSION['guest_phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="bk-form-group">
                                <label>Country <span class="req">*</span></label>
                                <select name="country" required data-label="Country">
                                    <option value="">--Select--</option>
                                    <?php
                                    $countries = [
                                        // ── Priority (pinned at top) ──
                                        'Philippines',
                                        // ── Rest of Asia ──
                                        'Bangladesh','Bhutan','Brunei','Cambodia','China','Hong Kong',
                                        'India','Indonesia','Japan','Kazakhstan','Kyrgyzstan','Laos',
                                        'Macau','Malaysia','Maldives','Mongolia','Myanmar','Nepal',
                                        'North Korea','Pakistan','Singapore','South Korea','Sri Lanka',
                                        'Taiwan','Tajikistan','Thailand','Timor-Leste','Turkmenistan',
                                        'Uzbekistan','Vietnam',
                                        // ── Middle East ──
                                        'Bahrain','Iran','Iraq','Israel','Jordan','Kuwait','Lebanon',
                                        'Oman','Palestine','Qatar','Saudi Arabia','Syria',
                                        'United Arab Emirates','Yemen',
                                        // ── Europe ──
                                        'Albania','Andorra','Armenia','Austria','Azerbaijan','Belarus',
                                        'Belgium','Bosnia and Herzegovina','Bulgaria','Croatia','Cyprus',
                                        'Czech Republic','Denmark','Estonia','Finland','France',
                                        'Georgia','Germany','Greece','Hungary','Iceland','Ireland',
                                        'Italy','Kosovo','Latvia','Liechtenstein','Lithuania',
                                        'Luxembourg','Malta','Moldova','Monaco','Montenegro',
                                        'Netherlands','North Macedonia','Norway','Poland','Portugal',
                                        'Romania','Russia','San Marino','Serbia','Slovakia','Slovenia',
                                        'Spain','Sweden','Switzerland','Turkey','Ukraine',
                                        'United Kingdom','Vatican City',
                                        // ── Americas ──
                                        'Antigua and Barbuda','Argentina','Bahamas','Barbados','Belize',
                                        'Bolivia','Brazil','Canada','Chile','Colombia','Costa Rica',
                                        'Cuba','Dominica','Dominican Republic','Ecuador','El Salvador',
                                        'Grenada','Guatemala','Guyana','Haiti','Honduras','Jamaica',
                                        'Mexico','Nicaragua','Panama','Paraguay','Peru',
                                        'Saint Kitts and Nevis','Saint Lucia',
                                        'Saint Vincent and the Grenadines','Suriname',
                                        'Trinidad and Tobago','United States','Uruguay','Venezuela',
                                        // ── Africa ──
                                        'Algeria','Angola','Benin','Botswana','Burkina Faso','Burundi',
                                        'Cabo Verde','Cameroon','Central African Republic','Chad',
                                        'Comoros','DR Congo','Republic of the Congo',
                                        'Djibouti','Egypt','Equatorial Guinea','Eritrea','Eswatini',
                                        'Ethiopia','Gabon','Gambia','Ghana','Guinea','Guinea-Bissau',
                                        'Ivory Coast','Kenya','Lesotho','Liberia','Libya','Madagascar',
                                        'Malawi','Mali','Mauritania','Mauritius','Morocco','Mozambique',
                                        'Namibia','Niger','Nigeria','Rwanda','São Tomé and Príncipe',
                                        'Senegal','Seychelles','Sierra Leone','Somalia','South Africa',
                                        'South Sudan','Sudan','Tanzania','Togo','Tunisia','Uganda',
                                        'Zambia','Zimbabwe',
                                        // ── Oceania ──
                                        'Australia','Fiji','Kiribati','Marshall Islands','Micronesia',
                                        'Nauru','New Zealand','Palau','Papua New Guinea','Samoa',
                                        'Solomon Islands','Tonga','Tuvalu','Vanuatu',
                                    ];
                                    $selected_country = $_SESSION['guest_country'] ?? '';
                                    foreach ($countries as $country):
                                        $sel = ($selected_country === $country) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($country) . "\" {$sel}>" . htmlspecialchars($country) . "</option>\n";
                                    endforeach;
                                    ?>
                                </select>
                            </div>

                            <div class="bk-form-group">
                                <label>Estimated Arrival Time (ETA) <span class="req">*</span></label>
                                <select name="eta" required data-label="Estimated Arrival Time">
                                    <option value="14:00" <?php echo (($_SESSION['guest_eta'] ?? '14:00') === '14:00') ? 'selected' : ''; ?>>02:00 PM (Standard Check-in)</option>
                                    <option value="15:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '15:00') ? 'selected' : ''; ?>>03:00 PM</option>
                                    <option value="16:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '16:00') ? 'selected' : ''; ?>>04:00 PM</option>
                                    <option value="17:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '17:00') ? 'selected' : ''; ?>>05:00 PM</option>
                                    <option value="18:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '18:00') ? 'selected' : ''; ?>>06:00 PM</option>
                                    <option value="20:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '20:00') ? 'selected' : ''; ?>>08:00 PM (Late Arrival)</option>
                                    <option value="22:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '22:00') ? 'selected' : ''; ?>>10:00 PM+ (Late Night Arrival)</option>
                                </select>
                            </div>
                            
                            <div class="bk-form-group" style="grid-column: 1 / -1;">
                                <label>Additional comments</label>
                                <textarea name="comments" placeholder="Additional comments" rows="3"><?php echo htmlspecialchars($_SESSION['guest_comments'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="bk-actions">
                        <a href="book?step=1" class="btn-bk-back">← Back</a>
                        <input type="hidden" name="action" value="go_step_3">
                        <button type="submit" class="btn-bk-next">Next &rarr;</button>
                    </div>


                    <?php elseif ($step === 3): ?>
                    <div class="bk-card bk-discount-card">
                        <h2 class="bk-card-title">Senior Citizen / PWD discount</h2>
                        <input type="hidden" name="discount_request_nonce" value="<?php echo htmlspecialchars($_SESSION['discount_request_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <label for="discountCategory">Booking type</label>
                        <select name="discount_category" id="discountCategory" class="bk-input">
                            <option value="regular">Regular booking</option>
                            <option value="senior">Senior Citizen — request 20% discount</option>
                            <option value="pwd">PWD — request 20% discount</option>
                        </select>
                        <div id="discountFields" hidden>
                            <p>20% applies to the entire room booking after staff verifies eligibility. Promo codes cannot be combined. Estimated total: <strong>₱ <?php echo number_format(round($pricing['subtotal'] * .8, 2), 2); ?></strong>. No payment is collected now.</p>
                            <p>Your room is held for up to <?php echo max(1, min(72, (int)(getenv('DISCOUNT_HOLD_HOURS') ?: 24))); ?> hours, or until arrival time if earlier. Staff approval and a verified 50% deposit are required to confirm your booking.</p>
                            <label for="eligibleGuest">Full name of eligible staying guest</label>
                            <input class="bk-input" id="eligibleGuest" name="eligible_guest" maxlength="150" disabled>
                            <label for="discountId">Valid Senior Citizen / PWD ID (JPG or PNG, maximum 2 MB)</label>
                            <details class="bk-id-guide" id="discountIdGuide">
                                <summary>View sample <span id="sampleIdCategory">Senior Citizen</span> ID</summary>
                                <img id="sampleIdImage" class="bk-id-sample-image" src="assets/images/senior-id-sample.png" alt="Senior Citizen ID illustration with fictional name, photo placeholder and ID number. SAMPLE — NOT VALID." width="1536" height="1024" decoding="async">
                                <p>Actual ID designs may vary by issuing city or municipality. This illustration only shows what should be readable.</p>
                            </details>
                            <p id="discountIdHelp" class="bk-id-help">Upload a clear photo of your own valid ID. Keep your name, photo, and ID number readable, and include all four corners without glare. Do not upload this sample.</p>
                            <input id="discountId" type="file" name="discount_id" accept="image/jpeg,image/png" aria-describedby="discountIdHelp" disabled>
                            <label><input type="checkbox" name="discount_consent" value="1" id="discountConsent" disabled> I agree to the booking policies and authorize staff to review this ID for discount verification. ID images are privately stored and deleted after the configured retention period (<?php echo max(1, min(365, (int)(getenv('DISCOUNT_ID_RETENTION_DAYS') ?: 30))); ?> days after review, or submission if unreviewed).</label>
                        </div>
                    </div>
                    <fieldset id="regularPaymentFields" style="border:0;padding:0;margin:0;min-width:0;">
                    <!-- STEP 3: PAYMENT -->
                    <div class="bk-card">
                        <div class="bk-card-header-flex">
                            <h2 class="bk-card-title" style="margin:0;">Pay 50% Deposit With</h2>
                            <div class="bk-pay-logos">
                                <span style="font-size:10.5px; font-weight:800; display:flex; align-items:center; color:#059669; background:#ECFDF5; padding:3px 8px; border-radius:6px; letter-spacing:0.5px;">
                                    ⚡ AUTO CONFIRMED
                                </span>
                            </div>
                        </div>

                        <!-- 1. INSTANT ONLINE PAYMONGO (AUTO CONFIRMED) -->
                        <div class="bk-pay-option bk-pay-option--active" id="opt-paymongo">
                            <label class="bk-pay-radio" style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                <div style="display:flex; align-items:center;">
                                    <input type="radio" name="payment_method" value="PayMongo Online" checked>
                                    <span style="font-size:16px; margin-right:8px;">⚡</span>
                                    <div>
                                        <strong style="font-size:14.5px; color:#0F172A;">Instant Online (GCash / Maya / Cards / QR Ph)</strong>
                                        <div style="font-size:12px; color:#64748B; margin-top:2px;">Fastest confirmation • Powered by PayMongo</div>
                                    </div>
                                </div>
                                <span style="background:#DCFCE7; color:#15803D; font-size:10px; font-weight:800; padding:3px 8px; border-radius:6px; text-transform:uppercase; letter-spacing:0.5px; flex-shrink:0;">
                                    AUTO CONFIRMED
                                </span>
                            </label>
                            <div id="paymongo-form" class="bk-card-form" style="padding-top:14px;">
                                <div style="background:#F0FDF4; border:1.5px solid #BBF7D0; border-radius:12px; padding:16px 18px; margin-bottom:12px;">
                                    <div style="font-size:13.5px; color:#166534; font-weight:600; line-height:1.5;">
                                        👉 Upon clicking <strong>"Confirm & Pay ₱ <?php echo number_format($deposit_amount, 2); ?>"</strong>, you will be redirected to the secure PayMongo checkout screen.
                                    </div>
                                    <div style="font-size:12.5px; color:#15803D; margin-top:8px; display:flex; align-items:center; gap:6px;">
                                        <span>💳 Visa / Mastercard</span> &bull; <span>📱 GCash</span> &bull; <span>💚 Maya</span> &bull; <span>📲 QR Ph</span>
                                    </div>
                                </div>
                                <p style="font-size:12px; color:#059669; font-weight:600; margin:0;">
                                    ✓ No need to upload receipt. Your booking is verified instantly upon payment.
                                </p>
                            </div>
                        </div>

                        <!-- 2. BANK TRANSFER (MANUAL VERIFICATION) -->
                        <div class="bk-pay-option" id="opt-bank">
                            <label class="bk-pay-radio">
                                <input type="radio" name="payment_method" value="Bank Deposit">
                                <span style="font-size:11px; font-weight:700; display:inline-flex; align-items:center; margin-right:6px; color:#555; letter-spacing:0.5px;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px;"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                                    BANK TRANSFER
                                </span>
                                <strong style="font-size:14.5px; color:#0F172A;">Bank Deposit (Manual)</strong>
                            </label>
                            <div id="bank-form" class="bk-card-form" style="display:none; padding-top: 16px;">
                                <p style="font-size:13.5px; color:#475569; margin:0 0 16px 0; line-height:1.5;">Please transfer the 50% deposit amount (<strong>₱ <?php echo number_format($deposit_amount, 2); ?></strong>) to our BDO Account.</p>
                                <div style="background:#F0F8FF; border-radius:12px; padding:16px 20px; margin-bottom:20px; border:1px solid #E0F2FE;">
                                    <div style="font-size:13px; color:#64748B; margin-bottom:6px;">Bank Name: <strong style="color:#0F172A;">BDO (Banco de Oro)</strong></div>
                                    <div style="font-size:13px; color:#64748B; margin-bottom:6px;">Account Name: <strong style="color:#0F172A;">Santa Fe Beach Club</strong></div>
                                    <div style="font-size:13px; color:#64748B;">Account Number: <strong style="color:#0F172A; letter-spacing:0.5px;">0012 3456 7890</strong></div>
                                </div>
                                <div class="bk-form-group" style="width:100%; text-align:left; margin-bottom:18px;">
                                    <label style="font-size:11px; font-weight:800; color:#1E293B; margin-bottom:8px; display:block; text-transform:uppercase; letter-spacing:0.5px;">Bank Reference / Transaction Number <span class="req" style="color:#EF4444;">*</span></label>
                                    <input type="text" name="bank_reference" id="bankReferenceInput" placeholder="Enter reference number" value="<?php echo htmlspecialchars($_POST['bank_reference'] ?? ''); ?>" style="width:100%; padding:13px 16px; border:1.5px solid #E0F2FE; border-radius:10px; font-size:14px; outline:none; background:#F8FAFC; color:#1E293B; box-sizing:border-box; transition:border-color 0.2s, background 0.2s;" onfocus="this.style.borderColor='#007AFF'; this.style.background='#FFFFFF';" onblur="this.style.borderColor='#E0F2FE'; this.style.background='#F8FAFC';">
                                    <div id="bankRefError" style="display:none; color:#B91C1C; font-size:12px; margin-top:6px; font-weight:500;">Please enter the reference number.</div>
                                </div>
                                <div class="bk-form-group" style="width:100%; text-align:left; margin-bottom:16px;">
                                    <label style="font-size:11px; font-weight:800; color:#1E293B; margin-bottom:8px; display:block; text-transform:uppercase; letter-spacing:0.5px;">Upload Payment Receipt (JPG, PNG, PDF &mdash; Max 2MB) <span class="req" style="color:#EF4444;">*</span></label>
                                    <input type="file" name="bank_receipt" id="bankReceiptInput" accept="image/jpeg,image/png,image/webp,application/pdf" data-max-size="2" data-label="Bank receipt" style="width:100%; padding:12px 16px; border:1.5px dashed #BAE6FD; border-radius:10px; font-size:14px; outline:none; background:#F8FAFC; color:#64748B; box-sizing:border-box;" onchange="validateReceiptFile(this, ['jpg','jpeg','png','pdf'], 'bankReceiptError')">
                                    <div id="bankReceiptError" style="display:none; color:#B91C1C; font-size:12px; margin-top:6px; font-weight:500; background:#FEF2F2; border:1px solid #FECACA; border-radius:6px; padding:8px 12px; align-items:center; gap:6px;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        <span>Invalid file. Please upload an actual JPG, PNG, or PDF file — renaming other file types (e.g. .exe renamed to .png) is not allowed.</span>
                                    </div>
                                </div>
                                <p style="font-size:12px; color:#94A3B8; text-align:center; margin:16px 0 0 0;">Your booking remains pending until our front desk verifies the payment.</p>
                            </div>
                        </div>

                        <!-- 3. GCASH QR CODE UPLOAD (MANUAL VERIFICATION) -->
                        <div class="bk-pay-option" id="opt-online">
                            <label class="bk-pay-radio">
                                <input type="radio" name="payment_method" value="GCash QR">
                                <img src="assets/images/gcash_logo.png?v=<?php echo time(); ?>" alt="GCash" style="width:24px; height:24px; border-radius:6px; margin-right:8px; object-fit:contain; vertical-align:middle; display:inline-block;">
                                <strong>GCash QR Screenshot (Manual)</strong>
                            </label>
                            <div id="online-form" class="bk-card-form" style="display:none; padding-top:20px; flex-direction:column; align-items:center;">
                                <div style="display:flex; flex-direction:column; align-items:center; width:100%; max-width:400px; background:#ffffff; border:1px solid #E5E7EB; border-radius:16px; padding:24px; box-shadow:0 8px 30px rgba(0,0,0,0.04);">
                                    <div style="font-size:15px; color:#374151; font-weight:700; text-align:center; margin-bottom:4px; display:flex; align-items:center; justify-content:center; gap:8px;">
                                        <img src="assets/images/gcash_logo.png?v=<?php echo time(); ?>" alt="GCash" style="width:22px; height:22px; border-radius:4px; vertical-align:middle; object-fit:contain;">
                                        Scan to Pay via <span style="color:#005CE6;">GCash</span>
                                    </div>
                                    <div style="font-size:13px; color:#64748B; text-align:center; margin-bottom:16px;">
                                        Amount: <strong style="color:#0F172A;">₱ <?php echo number_format($deposit_amount, 2); ?></strong>
                                    </div>
                                    <div style="background:#fff; border:2px solid #E2E8F0; border-radius:12px; padding:12px; margin-bottom:16px;">
                                        <img src="assets/images/gcash_qr.png?v=<?php echo time(); ?>"
                                             onerror="this.style.display='none'; document.getElementById('gcash-qr-placeholder').style.display='flex';"
                                             alt="GCash / InstaPay QR Code"
                                             style="width:180px; height:180px; object-fit:contain; display:block;">
                                        <div id="gcash-qr-placeholder" style="display:none; width:180px; height:180px; background:#F3F4F6; border-radius:8px; align-items:center; justify-content:center; flex-direction:column; gap:8px;">
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.2">
                                                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                                                <rect x="14" y="14" width="2" height="2"/><rect x="18" y="14" width="3" height="2"/>
                                                <rect x="14" y="18" width="2" height="3"/><rect x="19" y="19" width="2" height="2"/>
                                            </svg>
                                            <span style="font-size:11px; color:#9CA3AF; font-weight:600; text-align:center;">GCash QR placeholder<br><em>Upload your QR to assets/images/gcash_qr.png</em></span>
                                        </div>
                                    </div>
                                    <div style="background:#F0F7FF; border:1.5px solid #BAE6FD; border-radius:12px; padding:12px 16px; margin-bottom:16px; text-align:center; width:100%; box-sizing:border-box;">
                                        <div style="font-size:11px; color:#0369A1; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">
                                            GCash Account Details
                                        </div>
                                        <div style="font-size:16px; font-weight:800; color:#005CE6; display:flex; align-items:center; justify-content:center; gap:6px;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> <?php echo htmlspecialchars($gcash_number); ?>
                                        </div>
                                        <div style="font-size:12px; color:#475569; margin-top:3px;">
                                            Account Name: <strong style="color:#0F172A;"><?php echo htmlspecialchars($gcash_name); ?></strong>
                                        </div>
                                    </div>

                                    <div style="font-size:12px; color:#64748B; text-align:center; margin-bottom:16px; line-height:1.8;">
                                        1. Open your <strong>GCash app</strong><br>
                                        2. Tap <strong>Pay QR</strong> and scan the code above<br>
                                        3. Enter <strong>₱ <?php echo number_format($deposit_amount, 2); ?></strong> and complete payment<br>
                                        4. Take a <strong>screenshot</strong> of the receipt and upload below
                                    </div>
                                    <div style="width:100%; text-align:left;">
                                        <label style="font-size:12px; font-weight:600; color:#334155; margin-bottom:8px; display:block;">Upload GCash Receipt Screenshot (JPG or PNG &mdash; Max 2MB) <span style="color:#EF4444;">*</span></label>
                                        <input type="file" name="gcash_receipt" id="gcashReceiptInput" accept="image/jpeg,image/png" data-max-size="2" data-label="GCash receipt" style="width:100%; padding:12px 16px; border:2px dashed #007AFF; border-radius:10px; font-size:14px; outline:none; background:#F0F7FF; box-sizing:border-box;" onchange="validateReceiptFile(this, ['jpg','jpeg','png'], 'gcashReceiptError')">
                                        <div id="gcashReceiptError" style="display:none; color:#B91C1C; font-size:12px; margin-top:6px; font-weight:500; background:#FEF2F2; border:1px solid #FECACA; border-radius:6px; padding:8px 12px; align-items:center; gap:6px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                            <span>Invalid file. Please upload an actual JPG or PNG screenshot — renaming other file types is not allowed.</span>
                                        </div>
                                    </div>
                                    <p style="font-size:11px; color:#94A3B8; text-align:center; margin-top:12px; margin-bottom:0;">Our staff will verify your GCash receipt and confirm your booking.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    </fieldset>
                    <!-- REMOVED PAYMENT CURRENCY BLOCK SINCE NO CARDS ARE CHARGED ONLINE -->

                    <div class="bk-card" id="regularPaymentSchedule">
                        <h2 class="bk-card-title">Payment schedule</h2>
                        <div class="bk-schedule-row">
                            <span>Deposit due now</span>
                            <span class="bk-amt">₱ <?php echo number_format($deposit_amount, 2); ?></span>
                        </div>
                        <div class="bk-schedule-row bk-schedule-row--due">
                            <div>
                                Due on <?php echo date('d M Y', strtotime($checkin . ' -1 day')); ?><br>
                                <small style="color:#888;">Amount</small><br>
                                <small style="color:#888;">Payment convenience fee</small>
                            </div>
                            <div style="text-align: right;">
                                <span class="bk-amt">₱ <?php echo number_format($total_amount - $deposit_amount, 2); ?></span><br>
                                <small style="color:#888;">₱ <?php echo number_format($total_amount - $deposit_amount, 2); ?></small><br>
                                <small style="color:#888;">₱ 0.00</small>
                            </div>
                        </div>
                    </div>

                    <div class="bk-card">
                        <h2 class="bk-card-title">Booking Policies</h2>
                        <p class="bk-policy-intro">Our booking includes items with different booking policies.</p>
                        <div class="bk-policy-box">
                            <strong><?php echo htmlspecialchars($accommodation_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p>Cancellation: If cancelled, modified or in case of no-show, the first night will be charged.</p>
                            <p>Payment: 50% of the booking item amount deposit will be charged. Balance due 1 day before arrival.</p>
                            <p>Other policies: - Guests availing of a Senior Citizen or PWD discount must provide a valid ID for verification. Please message the hotel’s official Facebook page <a href="https://www.facebook.com/SantaFeBeachClub" target="_blank" rel="noopener noreferrer">https://www.facebook.com/SantaFeBeachClub</a> or email <a href="mailto:santafebeachclub@gmail.com">santafebeachclub@gmail.com</a> to submit your ID. Once approved, the hotel will send a payment link with the discounted amount. Only one discount may be applied per booking.</p>
                            - 1,000+ 150/pax for excess of 10pax<br>
                            - 1,000 50 to 10 pax<br>
                            - 500 1 to 5 pax<br><br>
                            ☐ Check out time is 11:00 AM<br>
                            ☐ Check in time is 1:30 PM<br><br>
                            ☐ Restaurant time:<br>
                            ☐ Breakfast 7AM to 1:30 PM
                        </div>
                        <label class="bk-checkbox-label">
                            <input type="checkbox" required>
                            Please check this box to indicate that you have read and agree to the <a href="terms" target="_blank" rel="noopener noreferrer">Booking Policies, Terms of Service, and Privacy Policy</a>.
                        </label>
                    </div>

                    <div class="bk-actions" style="justify-content: flex-start; gap: 15px;">
                        <a href="book?step=2" class="btn-bk-back">← Back</a>
                        <input type="hidden" name="action" value="confirm_booking">
                        <button type="submit" class="btn-bk-next" style="width: auto; padding: 14px 40px; margin-left: 0;">Confirm and book</button>
                    </div>

                    <?php endif; ?>

                </form>
            </div>

            <!-- RIGHT SIDEBAR (SUMMARY) -->
            <aside class="bk-sidebar">
                <!-- Dark branded header -->
                <div class="bk-sidebar-header">
                    <h3 class="bk-sb-title">Booking Summary</h3>
                    <?php if ($full_name): ?>
                    <div style="color:rgba(255,255,255,0.65); font-size:13px; margin-top:4px;"><?php echo htmlspecialchars($full_name); ?></div>
                    <?php endif; ?>
                </div>

                <div class="bk-sb-inner">
                    <div class="bk-sb-dates">
                        <div class="bk-sb-dcol">
                            <div class="bk-sb-dmain"><?php echo $checkin_fmt; ?></div>
                            <div class="bk-sb-dsub">Check-in · 13:30</div>
                        </div>
                        <div class="bk-sb-darrow">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </div>
                        <div class="bk-sb-dcol">
                            <div class="bk-sb-dmain"><?php echo $checkout_fmt; ?></div>
                            <div class="bk-sb-dsub">Check-out · 11:00</div>
                        </div>
                    </div>

                    <div class="bk-sb-meta">
                        <span class="stay-facts" aria-label="Stay details">
                            <span><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                            <span>1 room</span>
                            <span><?php echo $guests_total; ?> adult<?php echo $guests_total > 1 ? 's' : ''; ?></span>
                        </span>
                    </div>

                    <div class="bk-sb-room-row">
                        <span>Base Rate (<?php echo $nights; ?> night<?php echo $nights>1?'s':''; ?>)</span>
                        <span>₱ <?php echo number_format($pricing['base_price_per_night'] * $nights, 2); ?></span>
                    </div>

                    <?php if (($pricing['extra_person_total'] ?? 0) > 0): ?>
                    <div class="bk-sb-room-row" style="color:#0284C7; font-size:12.5px;">
                        <span>Extra Adult Fee (<?php echo $pricing['extra_adults']; ?> × ₱<?php echo number_format($pricing['extra_rate_per_adult'], 0); ?>)</span>
                        <span>+₱ <?php echo number_format($pricing['extra_person_total'], 2); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($pricing['weekend_surcharge_total'] > 0): ?>
                    <div class="bk-sb-room-row" style="color:#B45309; font-size:12.5px;">
                        <span>Weekend Rate Adjustment</span>
                        <span>+₱ <?php echo number_format($pricing['weekend_surcharge_total'], 2); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($pricing['seasonal_adjustment_total'] > 0): ?>
                    <div class="bk-sb-room-row" style="color:#B45309; font-size:12.5px;">
                        <span>Seasonal Rate Adjustment</span>
                        <span>+₱ <?php echo number_format($pricing['seasonal_adjustment_total'], 2); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($discount_amount > 0): ?>
                    <div class="bk-sb-room-row" style="color:#15803D; font-weight:700; font-size:12.5px;">
                        <span>Promo (<?php echo htmlspecialchars($applied_promo_code); ?>)</span>
                        <span>-₱ <?php echo number_format($discount_amount, 2); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="bk-sb-room-detail">
                        <strong>1 × <?php echo htmlspecialchars($accommodation_name); ?></strong>
                        <?php echo $guests_total; ?> adult<?php echo $guests_total>1?'s':''; ?>
                        <?php if ($has_breakfast): ?><br><span style="color:var(--green); font-size:12px; font-weight:600;">✓ Breakfast included</span><?php endif; ?>
                    </div>

                    <div class="bk-sb-total-row" id="regularSummaryTotal">
                        <span>Total Stay Cost</span>
                        <span>₱ <?php echo number_format($total_amount, 2); ?></span>
                    </div>

                    <div class="bk-sb-room-row" id="regularSummaryDeposit" style="margin-top:10px; font-weight:700; color:#0284C7;">
                        <span>50% Deposit Due:</span>
                        <span>₱ <?php echo number_format($deposit_amount, 2); ?></span>
                    </div>

                    <div class="bk-sb-tax-note">Price includes all taxes and applicable fees</div>
                    <div id="discountSummary" hidden><p><strong>Estimated total after 20% discount: ₱ <?php echo number_format(round($pricing['subtotal'] * .8, 2), 2); ?></strong></p><p>No payment due while awaiting ID verification. Any promo code shown above will not apply.</p></div>
                </div>
            </aside>
            
        </div>
    </div>
    
    <?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
function bkSelectPayment(method) {
    var paymongoForm = document.getElementById('paymongo-form');
    var paymongoOpt  = document.getElementById('opt-paymongo');
    var bankForm     = document.getElementById('bank-form');
    var bankOpt      = document.getElementById('opt-bank');
    var gcashForm    = document.getElementById('online-form');
    var gcashOpt     = document.getElementById('opt-online');

    // Reset all
    if (paymongoForm) {
        paymongoForm.style.display = 'none';
        paymongoForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = true; });
    }
    if (paymongoOpt)  paymongoOpt.classList.remove('bk-pay-option--active');
    if (bankForm) {
        bankForm.style.display = 'none';
        bankForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = true; });
    }
    if (bankOpt)      bankOpt.classList.remove('bk-pay-option--active');
    if (gcashForm) {
        gcashForm.style.display = 'none';
        gcashForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = true; });
    }
    if (gcashOpt)     gcashOpt.classList.remove('bk-pay-option--active');

    if (method === 'PayMongo Online') {
        if (paymongoForm) {
            paymongoForm.style.display = 'block';
            paymongoForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = false; });
        }
        if (paymongoOpt)  paymongoOpt.classList.add('bk-pay-option--active');
    } else if (method === 'Bank Deposit') {
        if (bankForm) {
            bankForm.style.display = 'block';
            bankForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = false; });
        }
        if (bankOpt)   bankOpt.classList.add('bk-pay-option--active');
    } else if (method === 'GCash QR' || method === 'Online Payment') {
        if (gcashForm) {
            gcashForm.style.display = 'flex';
            gcashForm.querySelectorAll('input, select, textarea').forEach(function(el) { el.disabled = false; });
        }
        if (gcashOpt)  gcashOpt.classList.add('bk-pay-option--active');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var category = document.getElementById('discountCategory');
    if (category) {
        var updateDiscount = function() {
            var requested = category.value !== 'regular';
            var sampleCategory = category.value === 'pwd' ? 'PWD' : 'Senior Citizen';
            document.getElementById('sampleIdCategory').textContent = sampleCategory;
            var sampleImage = document.getElementById('sampleIdImage');
            sampleImage.src = category.value === 'pwd' ? 'assets/images/pwd-id-sample.png' : 'assets/images/senior-id-sample.png';
            sampleImage.alt = sampleCategory + ' ID illustration with fictional name, photo placeholder and ID number. SAMPLE — NOT VALID.';
            document.getElementById('discountIdGuide').open = requested;
            document.getElementById('discountFields').hidden = !requested;
            var payments = document.getElementById('regularPaymentFields');
            payments.disabled = requested;
            payments.hidden = requested;
            document.getElementById('regularPaymentSchedule').hidden = requested;
            document.getElementById('regularSummaryTotal').hidden = requested;
            document.getElementById('regularSummaryDeposit').hidden = requested;
            document.getElementById('regularSummaryTotal').style.display = requested ? 'none' : '';
            document.getElementById('regularSummaryDeposit').style.display = requested ? 'none' : '';
            document.getElementById('discountSummary').hidden = !requested;
            ['eligibleGuest', 'discountId', 'discountConsent'].forEach(function(id) {
                var input = document.getElementById(id);
                input.disabled = !requested;
                input.required = requested;
            });
            var form = category.closest('form');
            form.querySelector('input[name="action"]').value = requested ? 'request_discount' : 'confirm_booking';
            form.querySelector('button[type="submit"]').textContent = requested ? 'Submit booking & discount request' : 'Confirm and book';
        };
        category.addEventListener('change', updateDiscount);
        updateDiscount();
    }
    var radios = document.querySelectorAll('input[name="payment_method"]');
    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                bkSelectPayment(this.value);
            }
        });
    });

    var options = document.querySelectorAll('.bk-pay-option');
    options.forEach(function(opt) {
        opt.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.closest('.bk-card-form')) {
                return;
            }
            var radio = opt.querySelector('input[name="payment_method"]');
            if (radio) {
                radio.checked = true;
                bkSelectPayment(radio.value);
            }
        });
    });
    
    // Initialize the correct state on load
    var checkedRadio = document.querySelector('input[name="payment_method"]:checked');
    if (checkedRadio) {
        bkSelectPayment(checkedRadio.value);
    }
});

// Require reference / receipt before letting the guest submit
(function () {
    var bookingForm = document.querySelector('form[action="book"]');
    var actionField = bookingForm ? bookingForm.querySelector('input[name="action"][value="confirm_booking"]') : null;
    if (!bookingForm || !actionField) return;

    bookingForm.addEventListener('submit', function (e) {
        if (document.getElementById('discountCategory') && document.getElementById('discountCategory').value !== 'regular') return;
        var selectedMethod = bookingForm.querySelector('input[name="payment_method"]:checked');
        if (!selectedMethod) return;

        if (selectedMethod.value === 'Bank Deposit') {
            var bankRefInput = document.getElementById('bankReferenceInput');
            var bankRefError = document.getElementById('bankRefError');
            var bankReceiptInput = document.getElementById('bankReceiptInput');

            if (bankRefInput && bankRefInput.value.trim() === '') {
                e.preventDefault();
                if (bankRefError) bankRefError.style.display = 'block';
                bankRefInput.style.borderColor = '#E65100';
                bankRefInput.focus();
                bankRefInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (bankReceiptInput && bankReceiptInput.files.length === 0) {
                e.preventDefault();
                SiteDialog.alert('Please upload a screenshot or image of your bank receipt.');
                bankReceiptInput.focus();
                bankReceiptInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
        } else if (selectedMethod.value === 'GCash QR') {
            var gcashReceiptInput = document.getElementById('gcashReceiptInput');
            if (gcashReceiptInput && gcashReceiptInput.files.length === 0) {
                e.preventDefault();
                SiteDialog.alert('Please upload a screenshot of your GCash payment receipt.');
                gcashReceiptInput.focus();
                gcashReceiptInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
        }
    });
    
    var bankRefInput = document.getElementById('bankReferenceInput');
    if (bankRefInput) {
        bankRefInput.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                document.getElementById('bankRefError').style.display = 'none';
                this.style.borderColor = '';
            }
        });
    }
})();

async function downloadBookingPdf() {
    var ticket = document.querySelector('.ticket-pass');
    var button = document.querySelector('.btn-pdf--header');

    if (!ticket) {
        SiteDialog.alert('Unable to find the booking ticket.');
        return;
    }

    if (!window.html2canvas || !window.jspdf || !window.jspdf.jsPDF) {
        SiteDialog.alert('PDF tools are not ready yet. Please try again in a moment.');
        return;
    }

    var hiddenEls = ticket.querySelectorAll('.pdf-hide');
    var hiddenState = [];
    hiddenEls.forEach(function (el) {
        hiddenState.push({ el: el, display: el.style.display });
        el.style.display = 'none';
    });

    if (button) {
        button.disabled = true;
        button.textContent = 'Downloading...';
    }

    try {
        var canvas = await html2canvas(ticket, {
            scale: 2,
            backgroundColor: '#FFFDF9',
            useCORS: true,
            scrollY: -window.scrollY
        });

        var imgData = canvas.toDataURL('image/png');
        var pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
        var pageWidth = pdf.internal.pageSize.getWidth();
        var pageHeight = pdf.internal.pageSize.getHeight();
        var margin = 10;
        var maxWidth = pageWidth - (margin * 2);
        var maxHeight = pageHeight - (margin * 2);
        var scale = Math.min(maxWidth / canvas.width, maxHeight / canvas.height);
        var finalWidth = canvas.width * scale;
        var finalHeight = canvas.height * scale;
        var x = (pageWidth - finalWidth) / 2;
        var y = margin;

        pdf.addImage(imgData, 'PNG', x, y, finalWidth, finalHeight);
        pdf.save('booking-confirmation-REF-<?php echo str_pad($booking_id, 3, '0', STR_PAD_LEFT); ?>.pdf');
    } catch (error) {
        console.error(error);
        SiteDialog.alert('Unable to download the PDF. Please try again.');
    } finally {
        hiddenState.forEach(function (item) {
            item.el.style.display = item.display;
        });
        if (button) {
            button.disabled = false;
            button.textContent = 'Download PDF';
        }
    }
}

// ── File Upload Magic Byte Validation ─────────────────────────────────────
// Checks the REAL file signature (first bytes) in the browser before submit.
// This catches renamed files (e.g. virus.exe renamed to photo.png).
const MAGIC_SIGNATURES = {
    jpg:  { offset: 0, bytes: [0xFF, 0xD8, 0xFF] },
    jpeg: { offset: 0, bytes: [0xFF, 0xD8, 0xFF] },
    png:  { offset: 0, bytes: [0x89, 0x50, 0x4E, 0x47] },
    pdf:  { offset: 0, bytes: [0x25, 0x50, 0x44, 0x46] }, // %PDF
};

function validateReceiptFile(input, allowedExts, errorDivId) {
    const errorDiv = document.getElementById(errorDivId);
    const file = input.files[0];

    // Hide any previous error
    errorDiv.style.display = 'none';
    input.style.borderColor = '';

    if (!file) return;

    // Step 1 — Check file size (2MB maximum)
    const MAX_SIZE_BYTES = 2 * 1024 * 1024; // 2MB
    if (file.size > MAX_SIZE_BYTES) {
        const sizeInMb = (file.size / (1024 * 1024)).toFixed(2);
        showFileError(input, errorDiv, 'File is too large (' + sizeInMb + 'MB). Maximum allowed size is 2MB. Please compress or resize your image.');
        return;
    }

    // Step 2 — Check file extension
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExts.includes(ext)) {
        showFileError(input, errorDiv, 'Wrong file type. Only ' + allowedExts.join(', ').toUpperCase() + ' files are accepted.');
        return;
    }

    // Step 3 — Read first 8 bytes and check magic signature
    const reader = new FileReader();
    reader.onload = function(e) {
        const bytes = new Uint8Array(e.target.result);
        const sig = MAGIC_SIGNATURES[ext];

        if (!sig) {
            // No known signature check for this type, allow it
            return;
        }

        const matches = sig.bytes.every((b, i) => bytes[i + sig.offset] === b);

        if (!matches) {
            showFileError(
                input,
                errorDiv,
                '⛔ This file is not a real ' + ext.toUpperCase() + ' file. You cannot upload a renamed file (e.g. an .exe or .zip renamed to .' + ext + '). Please upload a genuine screenshot or photo.'
            );
            input.value = ''; // Clear the input
        }
    };
    reader.readAsArrayBuffer(file.slice(0, 8));
}

function showFileError(input, errorDiv, message) {
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
    input.style.borderColor = '#EF4444';
    input.value = ''; // Clear the invalid file
}
</script>

<!-- ══ Edit Room Modal ══════════════════════════════════════════ -->
<div id="editRoomModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.45); backdrop-filter:blur(3px); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:14px; box-shadow:0 24px 64px rgba(0,0,0,0.18); width:100%; max-width:560px; margin:24px; overflow:hidden; font-family:'Outfit',sans-serif;">

        <!-- Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px 18px; border-bottom:1px solid #F1F5F9;">
            <h3 style="font-size:17px; font-weight:700; color:#1A1A2E; margin:0;">Edit Room</h3>
            <button id="closeEditRoomModal" type="button" style="background:none; border:none; cursor:pointer; color:#6B7280; padding:4px; line-height:0; border-radius:6px; transition:background 0.15s;" onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='none'">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Body -->
        <div style="padding:22px 24px 24px;">

            <!-- Warning notice -->
            <div style="display:flex; gap:12px; background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:14px 16px; margin-bottom:22px; align-items:flex-start;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>
                    <div style="font-size:13px; font-weight:600; color:#92400E;">Room quantity can only be reduced. Guest numbers are flexible.</div>
                    <div style="font-size:12px; color:#B45309; margin-top:3px;">Once decreased, it cannot be increased.</div>
                </div>
            </div>

            <!-- Room Type Selection -->
            <div style="margin-bottom:18px;">
                <label style="font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;">Accommodation Type</label>
                <select id="editRoomType" style="width:100%; padding:12px 16px; border:1.5px solid #E5E7EB; border-radius:10px; font-family:'Outfit',sans-serif; font-size:14px; color:#1A1A2E; background:#fff; outline:none; cursor:pointer;">
                    <option value="beachview_duplex" <?php echo ($room_type === 'beachview_duplex') ? 'selected' : ''; ?>>Beachview Duplex</option>
                    <option value="seaview_duplex" <?php echo ($room_type === 'seaview_duplex') ? 'selected' : ''; ?>>Seaview Duplex</option>
                    <option value="beach_villa" <?php echo ($room_type === 'beach_villa') ? 'selected' : ''; ?>>Beach Villa</option>
                    <option value="standard_king" <?php echo ($room_type === 'standard_king') ? 'selected' : ''; ?>>Standard Family Room</option>
                    <option value="standard_room" <?php echo ($room_type === 'standard_room') ? 'selected' : ''; ?>>Standard Room</option>
                </select>
            </div>

            <!-- Room count -->
            <div style="margin-bottom:18px;">
                <label style="font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;">Room Quantity</label>
                <select id="editRoomCount" style="width:100%; padding:12px 16px; border:1.5px solid #E5E7EB; border-radius:10px; font-family:'Outfit',sans-serif; font-size:14px; color:#1A1A2E; background:#F9FAFB; outline:none; cursor:pointer;">
                    <option value="1" selected>1 room</option>
                </select>
            </div>

<?php
// Per-room included capacity & extra rate for the Edit modal dropdown
$_editIncluded = in_array($room_type, ['beach_villa', 'standard_king']) ? 4 : 2;
$_editRate     = (int)$pricing['extra_rate_per_adult'];
function _editExtraLabel(int $n, int $inc, int $rate): string {
    $extra = max(0, $n - $inc);
    return $extra > 0 ? ' (+&#8369;' . number_format($extra * $rate, 0) . ')' : ' (Included)';
}
?>
            <!-- Guest in Room 1 -->
            <div id="editGuestSection" style="border:1.5px solid #E5E7EB; border-radius:10px; overflow:hidden;">
                <div style="background:#F8FAFC; padding:12px 16px; font-size:13px; font-weight:700; color:#374151; border-bottom:1px solid #E5E7EB;">Guest in Room 1</div>
                <div style="padding:14px 16px; display:flex; gap:12px; align-items:center;">
                    <div style="font-size:13px; font-weight:600; color:#6B7280; flex-shrink:0; width:40px;">Main</div>
                    <select id="editAdults" style="flex:1; padding:10px 14px; border:1.5px solid #E5E7EB; border-radius:8px; font-family:'Outfit',sans-serif; font-size:13px; color:#1A1A2E; background:#fff; outline:none; cursor:pointer; appearance:none; background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%234B5563' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E\"); background-repeat:no-repeat; background-position:calc(100% - 12px) center;">
                        <option value="1" <?php echo ($guests_total == 1) ? 'selected' : ''; ?>>1 adult<?php echo _editExtraLabel(1, $_editIncluded, $_editRate); ?></option>
                        <option value="2" <?php echo ($guests_total == 2) ? 'selected' : ''; ?>>2 adults<?php echo _editExtraLabel(2, $_editIncluded, $_editRate); ?></option>
                        <option value="3" <?php echo ($guests_total == 3) ? 'selected' : ''; ?>>3 adults<?php echo _editExtraLabel(3, $_editIncluded, $_editRate); ?></option>
                        <option value="4" <?php echo ($guests_total == 4) ? 'selected' : ''; ?>>4 adults<?php echo _editExtraLabel(4, $_editIncluded, $_editRate); ?></option>
                        <option value="5" <?php echo ($guests_total >= 5) ? 'selected' : ''; ?>>5 adults<?php echo _editExtraLabel(5, $_editIncluded, $_editRate); ?></option>
                    </select>
                    <select id="editChildren" style="flex:1; padding:10px 14px; border:1.5px solid #E5E7EB; border-radius:8px; font-family:'Outfit',sans-serif; font-size:13px; color:#1A1A2E; background:#fff; outline:none; cursor:pointer; appearance:none; background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%234B5563' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E\"); background-repeat:no-repeat; background-position:calc(100% - 12px) center;">
                        <option value="0" selected>0 children</option>
                        <option value="1">1 child</option>
                        <option value="2">2 children</option>
                    </select>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div style="display:flex; justify-content:flex-end; padding:16px 24px; border-top:1px solid #F1F5F9;">
            <button id="applyEditRoom" type="button" style="padding:11px 28px; background:linear-gradient(135deg,#C8996F,#A67850); color:#fff; border:none; border-radius:9px; font-family:'Outfit',sans-serif; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(200,153,111,0.4); transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
                Close
            </button>
        </div>

    </div>
</div>

<!-- ══ Interactive Availability Calendar Modal ═══════════════════ -->
<div id="availabilityCalendarModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:16px; box-shadow:0 24px 64px rgba(0,0,0,0.2); width:100%; max-width:540px; overflow:hidden; font-family:'Outfit',sans-serif; animation:modalPop 0.25s ease-out;">
        
        <!-- Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:18px 22px; background:#FAF6F0; border-bottom:1px solid #EFE4D6;">
            <div>
                <h3 style="font-size:17px; font-weight:700; color:#5A3E2B; margin:0; display:flex; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Live Availability Calendar
                </h3>
                <p style="font-size:12px; color:#84563C; margin:3px 0 0 0;">Select your Check-in and Check-out dates</p>
            </div>
            <button id="closeAvailCalModal" type="button" style="background:none; border:none; cursor:pointer; color:#7C533C; font-size:24px; line-height:1; padding:2px 6px; border-radius:6px;">&times;</button>
        </div>

        <!-- Body -->
        <div style="padding:20px 22px;">
            
            <!-- Month Navigator -->
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <button type="button" id="book-cal-prev" style="background:#F3F4F6; border:none; padding:6px 12px; border-radius:8px; cursor:pointer; font-weight:700; color:#374151;">&larr; Prev</button>
                <div id="book-cal-month-title" style="font-size:15px; font-weight:700; color:#1F2937;">Loading...</div>
                <button type="button" id="book-cal-next" style="background:#F3F4F6; border:none; padding:6px 12px; border-radius:8px; cursor:pointer; font-weight:700; color:#374151;">Next &rarr;</button>
            </div>

            <!-- Legend -->
            <div style="display:flex; justify-content:center; gap:14px; margin-bottom:14px; font-size:11.5px; color:#4B5563;">
                <span style="display:inline-flex; align-items:center; gap:4px;"><span style="width:10px; height:10px; border-radius:3px; background:#ECFDF5; border:1px solid #A7F3D0; display:inline-block;"></span> Available</span>
                <span style="display:inline-flex; align-items:center; gap:4px;"><span style="width:10px; height:10px; border-radius:3px; background:#FFFBEB; border:1px solid #FDE68A; display:inline-block;"></span> Low Stock</span>
                <span style="display:inline-flex; align-items:center; gap:4px;"><span style="width:10px; height:10px; border-radius:3px; background:#FEF2F2; border:1px solid #FECACA; display:inline-block;"></span> Sold Out</span>
            </div>

            <!-- Weekday header -->
            <div style="display:grid; grid-template-columns:repeat(7, 1fr); text-align:center; font-size:11px; font-weight:700; color:#9CA3AF; margin-bottom:8px;">
                <div>SUN</div><div>MON</div><div>TUE</div><div>WED</div><div>THU</div><div>FRI</div><div>SAT</div>
            </div>

            <!-- Calendar Days Grid -->
            <div id="book-cal-grid" style="display:grid; grid-template-columns:repeat(7, 1fr); gap:6px;">
                <div style="grid-column:span 7; text-align:center; padding:30px; color:#9CA3AF; font-size:13px;">Loading availability matrix...</div>
            </div>

            <!-- Selected Dates Indicator -->
            <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:12px 14px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:11px; color:#64748B; text-transform:uppercase; font-weight:700;">Selected Stay</div>
                    <div id="book-cal-selection-text" style="font-size:13.5px; font-weight:700; color:#1E293B; margin-top:2px;">
                        <?php echo date('M j, Y', strtotime($checkin)); ?> &rarr; <?php echo date('M j, Y', strtotime($checkout)); ?> (<?php echo $nights; ?> night<?php echo $nights>1?'s':''; ?>)
                    </div>
                </div>
                <button type="button" id="book-cal-apply-btn" style="background:#84563C; color:#fff; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer;">
                    Confirm Dates
                </button>
            </div>
        </div>

    </div>
</div>

<script>
(function(){
    // ── Edit Room Modal ──────────────────────────────────────────
    var editModal = document.getElementById('editRoomModal');
    var openEditBtn = document.getElementById('editRoomBtn');
    var closeEditBtn = document.getElementById('closeEditRoomModal');
    var applyEditBtn = document.getElementById('applyEditRoom');

    if (openEditBtn) openEditBtn.addEventListener('click', function() {
        editModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });
    if (closeEditBtn) closeEditBtn.addEventListener('click', function() {
        editModal.style.display = 'none';
        document.body.style.overflow = '';
    });
    if (applyEditBtn) applyEditBtn.addEventListener('click', function(){
        var adults   = parseInt(document.getElementById('editAdults').value, 10) || 1;
        var roomType = document.getElementById('editRoomType') ? document.getElementById('editRoomType').value : <?php echo xss_json($room_type); ?>;
        var includedCap = (['beach_villa','standard_king'].indexOf(roomType) !== -1) ? 4 : 2;
        var baseGuests  = Math.min(adults, includedCap);
        var extraAdults = Math.max(0, adults - includedCap);
        var params   = new URLSearchParams(window.location.search);
        params.set('step', '1');
        params.set('guests', baseGuests);
        params.set('extra_adults', extraAdults);
        params.set('checkin',  <?php echo xss_json($checkin); ?>);
        params.set('checkout', <?php echo xss_json($checkout); ?>);
        params.set('room_type', roomType);
        window.location.href = 'book?' + params.toString();
    });

    if (editModal) {
        editModal.addEventListener('click', function(e){
            if (e.target === editModal) { editModal.style.display = 'none'; document.body.style.overflow = ''; }
        });
    }

    // ── Availability Calendar Modal ──────────────────────────────
    var calModal = document.getElementById('availabilityCalendarModal');
    var openCalBtn = document.getElementById('openAvailabilityCalBtn');
    var closeCalBtn = document.getElementById('closeAvailCalModal');
    var prevMonthBtn = document.getElementById('book-cal-prev');
    var nextMonthBtn = document.getElementById('book-cal-next');
    var applyCalBtn = document.getElementById('book-cal-apply-btn');

    var currentYear = <?php echo (int)date('Y', strtotime($checkin)); ?>;
    var currentMonth = <?php echo (int)date('n', strtotime($checkin)); ?>;
    var activeRoomType = <?php echo xss_json($room_type); ?>;

    var selectedCheckIn = <?php echo xss_json($checkin); ?>;
    var selectedCheckOut = <?php echo xss_json($checkout); ?>;
    var pickState = 0; // 0 = ready to pick check-in, 1 = ready to pick check-out

    var monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];

    function openCal() {
        if (!calModal) return;
        calModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        loadCalData(currentYear, currentMonth);
    }
    function closeCal() {
        if (!calModal) return;
        calModal.style.display = 'none';
        document.body.style.overflow = '';
    }

    if (openCalBtn) openCalBtn.addEventListener('click', openCal);
    if (closeCalBtn) closeCalBtn.addEventListener('click', closeCal);
    if (calModal) {
        calModal.addEventListener('click', function(e){
            if (e.target === calModal) closeCal();
        });
    }

    if (prevMonthBtn) prevMonthBtn.addEventListener('click', function(){
        currentMonth--;
        if (currentMonth < 1) { currentMonth = 12; currentYear--; }
        loadCalData(currentYear, currentMonth);
    });
    if (nextMonthBtn) nextMonthBtn.addEventListener('click', function(){
        currentMonth++;
        if (currentMonth > 12) { currentMonth = 1; currentYear++; }
        loadCalData(currentYear, currentMonth);
    });

    function updateSelectionText() {
        var textEl = document.getElementById('book-cal-selection-text');
        if (!textEl) return;
        if (selectedCheckIn && selectedCheckOut) {
            var d1 = new Date(selectedCheckIn);
            var d2 = new Date(selectedCheckOut);
            var diffNights = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
            textEl.textContent = selectedCheckIn + ' → ' + selectedCheckOut + ' (' + diffNights + ' night' + (diffNights > 1 ? 's' : '') + ')';
        } else if (selectedCheckIn) {
            textEl.textContent = selectedCheckIn + ' → Select check-out date';
        }
    }

    function loadCalData(year, month) {
        var titleEl = document.getElementById('book-cal-month-title');
        var grid = document.getElementById('book-cal-grid');
        if (!titleEl || !grid) return;

        titleEl.textContent = monthNames[month - 1] + ' ' + year;
        grid.innerHTML = '<div style="grid-column:span 7; text-align:center; padding:25px; color:#9CA3AF; font-size:13px;">Checking room availability...</div>';

        var applicationPath = <?php
            $bookingScriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $bookingApplicationPath = rtrim(preg_replace('~/frontend$~', '', $bookingScriptDirectory), '/');
            echo json_encode($bookingApplicationPath, JSON_UNESCAPED_SLASHES);
        ?>;
        var availabilityUrl = applicationPath + '/backend/api/availability.php?action=get_month_matrix&year=' + year + '&month=' + month + '&room_type=' + encodeURIComponent(activeRoomType);

        fetch(availabilityUrl, { credentials: 'same-origin' })
            .then(function(res){
                if (!res.ok) throw new Error('Availability API returned HTTP ' + res.status);
                return res.json();
            })
            .then(function(data){
                if (!data || !data.success) {
                    grid.innerHTML = '<div style="grid-column:span 7; text-align:center; color:#EF4444; font-size:13px;">Unable to load matrix.</div>';
                    return;
                }

                grid.innerHTML = '';
                var firstDayIndex = new Date(year, month - 1, 1).getDay();
                for (var pad = 0; pad < firstDayIndex; pad++) {
                    var empty = document.createElement('div');
                    empty.style.cssText = 'height:48px;';
                    grid.appendChild(empty);
                }

                Object.keys(data.days).forEach(function(dateKey){
                    var item = data.days[dateKey];
                    var cell = document.createElement('div');

                    var isSelected = (dateKey === selectedCheckIn || dateKey === selectedCheckOut);
                    var isInRange = (selectedCheckIn && selectedCheckOut && dateKey > selectedCheckIn && dateKey < selectedCheckOut);

                    var bg = '#ECFDF5';
                    var border = '#A7F3D0';
                    var textColor = '#065F46';
                    var badgeText = item.available + ' left';

                    if (item.is_past) {
                        bg = '#F8FAFC'; border = '#E2E8F0'; textColor = '#94A3B8'; badgeText = 'Past';
                    } else if (item.status === 'sold_out') {
                        bg = '#FEF2F2'; border = '#FECACA'; textColor = '#991B1B'; badgeText = 'Sold Out';
                    } else if (item.status === 'low_stock') {
                        bg = '#FFFBEB'; border = '#FDE68A'; textColor = '#92400E'; badgeText = item.available + ' left';
                    }

                    if (isSelected) {
                        bg = '#84563C'; border = '#84563C'; textColor = '#FFFFFF';
                    } else if (isInRange) {
                        bg = '#F5EBE6'; border = '#D7C4B7'; textColor = '#84563C';
                    }

                    cell.style.cssText = 'height:48px; background:' + bg + '; border:1px solid ' + border + '; border-radius:8px; padding:3px; display:flex; flex-direction:column; align-items:center; justify-content:center; font-size:12px; cursor:pointer; transition:all 0.15s; user-select:none;';
                    cell.innerHTML = '<strong style="color:' + (isSelected ? '#FFF' : textColor) + '; font-size:13px;">' + item.day + '</strong>' +
                                     '<span style="font-size:9px; font-weight:700; color:' + (isSelected ? '#FFF' : textColor) + ';">' + badgeText + '</span>';

                    if (!item.is_past && item.status !== 'sold_out') {
                        cell.onmouseover = function() { if (!isSelected) this.style.transform = 'scale(1.06)'; };
                        cell.onmouseout = function() { if (!isSelected) this.style.transform = 'none'; };
                        cell.onclick = function() {
                            if (pickState === 0) {
                                selectedCheckIn = dateKey;
                                selectedCheckOut = '';
                                pickState = 1;
                            } else {
                                if (dateKey <= selectedCheckIn) {
                                    selectedCheckIn = dateKey;
                                    selectedCheckOut = '';
                                    pickState = 1;
                                } else {
                                    selectedCheckOut = dateKey;
                                    pickState = 0;
                                }
                            }
                            updateSelectionText();
                            loadCalData(year, month);
                        };
                    }

                    grid.appendChild(cell);
                });
            })
            .catch(function(){
                grid.innerHTML = '<div style="grid-column:span 7; text-align:center; color:#EF4444; font-size:13px;">Failed to fetch availability.</div>';
            });
    }

    if (applyCalBtn) {
        applyCalBtn.addEventListener('click', function(){
            if (!selectedCheckIn || !selectedCheckOut) {
                SiteDialog.alert('Please select both Check-in and Check-out dates.');
                return;
            }
            var params = new URLSearchParams(window.location.search);
            params.set('step', '1');
            params.set('checkin', selectedCheckIn);
            params.set('checkout', selectedCheckOut);
            params.set('room_type', activeRoomType);
            params.set('guests', '<?php echo (int)$guests; ?>');
            window.location.href = 'book?' + params.toString();
        });
    }

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            if (editModal && editModal.style.display === 'flex') { editModal.style.display = 'none'; document.body.style.overflow = ''; }
            if (calModal && calModal.style.display === 'flex') closeCal();
        }
    });
})();
</script>
</body>
</html>
