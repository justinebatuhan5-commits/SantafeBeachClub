<?php
/**
 * booking_controller.php — Public guest reservation flow controller for frontend/book.php.
 */

require_once dirname(__DIR__) . '/helpers/private_storage_helper.php';
require_once dirname(__DIR__) . '/helpers/sms_helper.php';
require_once dirname(__DIR__) . '/helpers/room_status_helper.php';
require_once dirname(__DIR__) . '/helpers/country_codes.php';
require_once dirname(__DIR__) . '/libs/phpqrcode/phpqrcode.php';
require_once dirname(__DIR__) . '/services/booking_service.php';
require_once dirname(__DIR__) . '/services/paymongo.php';
require_once dirname(__DIR__) . '/services/paymongo_confirmation.php';
require_once dirname(__DIR__) . '/services/mailer.php';

if (!function_exists('booking_public_frontend_url')) {
    function booking_public_frontend_url(): string
    {
        $configured = rtrim((string) (getenv('PUBLIC_APP_URL') ?: ''), '/');
        if ($configured === '' || filter_var($configured, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('PUBLIC_APP_URL is not configured correctly.');
        }
        $host   = strtolower((string) parse_url($configured, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($configured, PHP_URL_SCHEME));
        if ($scheme !== 'https' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
            throw new RuntimeException('PUBLIC_APP_URL must use HTTPS.');
        }
        return str_ends_with($configured, '/frontend') ? $configured : $configured . '/frontend';
    }
}

if (!function_exists('cleanup_failed_paymongo_booking')) {
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
}

if (!function_exists('_editExtraLabel')) {
    function _editExtraLabel(int $n, int $inc, int $rate): string
    {
        $extra = max(0, $n - $inc);
        return $extra > 0 ? ' (+&#8369;' . number_format($extra * $rate, 0) . ')' : ' (Included)';
    }
}

if (!function_exists('sfbc_get_booking_countries')) {
    function sfbc_get_booking_countries(): array
    {
        return [
            // Priority (pinned at top)
            'Philippines',
            // Rest of Asia
            'Bangladesh', 'Bhutan', 'Brunei', 'Cambodia', 'China', 'Hong Kong',
            'India', 'Indonesia', 'Japan', 'Kazakhstan', 'Kyrgyzstan', 'Laos',
            'Macau', 'Malaysia', 'Maldives', 'Mongolia', 'Myanmar', 'Nepal',
            'North Korea', 'Pakistan', 'Singapore', 'South Korea', 'Sri Lanka',
            'Taiwan', 'Tajikistan', 'Thailand', 'Timor-Leste', 'Turkmenistan',
            'Uzbekistan', 'Vietnam',
            // Middle East
            'Bahrain', 'Iran', 'Iraq', 'Israel', 'Jordan', 'Kuwait', 'Lebanon',
            'Oman', 'Palestine', 'Qatar', 'Saudi Arabia', 'Syria',
            'United Arab Emirates', 'Yemen',
            // Europe
            'Albania', 'Andorra', 'Armenia', 'Austria', 'Azerbaijan', 'Belarus',
            'Belgium', 'Bosnia and Herzegovina', 'Bulgaria', 'Croatia', 'Cyprus',
            'Czech Republic', 'Denmark', 'Estonia', 'Finland', 'France',
            'Georgia', 'Germany', 'Greece', 'Hungary', 'Iceland', 'Ireland',
            'Italy', 'Kosovo', 'Latvia', 'Liechtenstein', 'Lithuania',
            'Luxembourg', 'Malta', 'Moldova', 'Monaco', 'Montenegro',
            'Netherlands', 'North Macedonia', 'Norway', 'Poland', 'Portugal',
            'Romania', 'Russia', 'San Marino', 'Serbia', 'Slovakia', 'Slovenia',
            'Spain', 'Sweden', 'Switzerland', 'Turkey', 'Ukraine',
            'United Kingdom', 'Vatican City',
            // Americas
            'Antigua and Barbuda', 'Argentina', 'Bahamas', 'Barbados', 'Belize',
            'Bolivia', 'Brazil', 'Canada', 'Chile', 'Colombia', 'Costa Rica',
            'Cuba', 'Dominica', 'Dominican Republic', 'Ecuador', 'El Salvador',
            'Grenada', 'Guatemala', 'Guyana', 'Haiti', 'Honduras', 'Jamaica',
            'Mexico', 'Nicaragua', 'Panama', 'Paraguay', 'Peru',
            'Saint Kitts and Nevis', 'Saint Lucia',
            'Saint Vincent and the Grenadines', 'Suriname',
            'Trinidad and Tobago', 'United States', 'Uruguay', 'Venezuela',
            // Africa
            'Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi',
            'Cabo Verde', 'Cameroon', 'Central African Republic', 'Chad',
            'Comoros', 'DR Congo', 'Republic of the Congo',
            'Djibouti', 'Egypt', 'Equatorial Guinea', 'Eritrea', 'Eswatini',
            'Ethiopia', 'Gabon', 'Gambia', 'Ghana', 'Guinea', 'Guinea-Bissau',
            'Ivory Coast', 'Kenya', 'Lesotho', 'Liberia', 'Libya', 'Madagascar',
            'Malawi', 'Mali', 'Mauritania', 'Mauritius', 'Morocco', 'Mozambique',
            'Namibia', 'Niger', 'Nigeria', 'Rwanda', 'São Tomé and Príncipe',
            'Senegal', 'Seychelles', 'Sierra Leone', 'Somalia', 'South Africa',
            'South Sudan', 'Sudan', 'Tanzania', 'Togo', 'Tunisia', 'Uganda',
            'Zambia', 'Zimbabwe',
            // Oceania
            'Australia', 'Fiji', 'Kiribati', 'Marshall Islands', 'Micronesia',
            'Nauru', 'New Zealand', 'Palau', 'Papua New Guinea', 'Samoa',
            'Solomon Islands', 'Tonga', 'Tuvalu', 'Vanuatu',
        ];
    }
}

if (!function_exists('sfbc_handle_book_page')) {
    function sfbc_handle_book_page(mysqli $conn): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $frontendRoot = dirname(__DIR__, 2) . '/frontend';
        $allowed_room_types = ['beachview_duplex', 'seaview_duplex', 'beach_villa', 'standard_room', 'standard_king'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && (isset($_GET['rebook']) && $_GET['rebook'] === '1')) {
            $rebook_session_fields = [
                'guest_first_name' => 'first_name',
                'guest_last_name'  => 'last_name',
                'guest_email'      => 'email',
                'guest_phone'      => 'phone',
                'guest_country'    => 'country',
                'guest_comments'   => 'comments',
            ];

            foreach ($rebook_session_fields as $session_key => $request_key) {
                if (isset($_GET[$request_key])) {
                    $_SESSION[$session_key] = trim((string) $_GET[$request_key]);
                }
            }
        }

        $step = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 1;

        // Grab values from homepage search, chatbot pre-filled link, or session/request
        $checkin   = $_REQUEST['checkin'] ?? ($_REQUEST['check_in'] ?? ($_SESSION['book_checkin'] ?? date('Y-m-d')));
        $checkout  = $_REQUEST['checkout'] ?? ($_REQUEST['check_out'] ?? ($_SESSION['book_checkout'] ?? date('Y-m-d', strtotime('+1 day'))));
        $guests    = isset($_REQUEST['guests']) ? (int) $_REQUEST['guests'] : (isset($_SESSION['book_guests']) ? (int) $_SESSION['book_guests'] : 2);
        $room_type = $_REQUEST['room_type'] ?? ($_REQUEST['room'] ?? ($_SESSION['book_room_type'] ?? 'beachview_duplex'));
        if (!in_array($room_type, $allowed_room_types, true)) {
            $room_type = 'standard_room';
        }

        // Capture extra adults/children selected on rooms.php and persist across steps.
        if (isset($_REQUEST['extra_adults'])) {
            $_SESSION['book_extra_adults']   = max(0, (int) $_REQUEST['extra_adults']);
            $_SESSION['book_extra_children'] = isset($_REQUEST['extra_children']) ? max(0, (int) $_REQUEST['extra_children']) : 0;
        }
        $extra_adults   = isset($_SESSION['book_extra_adults']) ? (int) $_SESSION['book_extra_adults'] : 0;
        $extra_children = isset($_SESSION['book_extra_children']) ? (int) $_SESSION['book_extra_children'] : 0;

        $_base_capacity  = ['beachview_duplex' => 2, 'seaview_duplex' => 2, 'beach_villa' => 4, 'standard_king' => 4, 'standard_room' => 2];
        $included_guests = max($guests, $_base_capacity[$room_type] ?? 2);
        $guests_total    = $included_guests + $extra_adults + $extra_children;

        // Store in session to persist across steps
        $_SESSION['book_checkin']   = $checkin;
        $_SESSION['book_checkout']  = $checkout;
        $_SESSION['book_guests']    = $guests;
        $_SESSION['book_room_type'] = $room_type;

        $error              = '';
        $success            = false;
        $booking_id         = 0;
        $booking_ref        = '';
        $guest_name         = '';
        $guest_email        = '';
        $remaining_balance  = 0;
        $payment_method     = '';
        $checkin_token      = '';
        $cancel_token       = '';
        $cancel_url         = '';
        $booking_eta        = '14:00';

        // Reject malformed or impossible calendar dates before pricing constructs
        // DateTime objects. Reset to a safe range so the page can render the error.
        if (!isValidDateRange((string)$checkin, (string)$checkout)) {
            $error    = 'Please select valid check-in and check-out dates. Check-out must be later than check-in.';
            $checkin  = date('Y-m-d');
            $checkout = date('Y-m-d', strtotime('+1 day'));
            $_SESSION['book_checkin']  = $checkin;
            $_SESSION['book_checkout'] = $checkout;
        }

        // Handle cancellation from PayMongo checkout screen
        if (isset($_GET['cancelled']) && $_GET['cancelled'] == 1) {
            $paymentCancelToken = trim((string) ($_GET['cancel_token'] ?? ''));
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
                    $cbId = (int) $bookingToCancel['id'];
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

                        $qr_f = $frontendRoot . '/assets/qrcodes/qr_booking_' . $cbId . '.png';
                        if (is_file($qr_f)) { @unlink($qr_f); }
                    } catch (Throwable $e) {
                        $conn->rollback();
                        error_log('Pending PayMongo cancellation cleanup failed: ' . $e->getMessage());
                    }
                }
            }
            $error = 'Payment was cancelled or not completed. Please try again or select another payment method.';
            $step  = 3;
        }

        // Room mapping
        $room_names = [
            'beachview_duplex' => 'BEACHVIEW DUPLEX',
            'seaview_duplex'   => 'SEAVIEW DUPLEX',
            'beach_villa'      => 'BEACH VILLA',
            'standard_room'    => 'STANDARD ROOM',
            'standard_king'    => 'STANDARD FAMILY ROOM',
        ];
        $accommodation_name = $room_names[$room_type] ?? 'STANDARD ROOM';

        // Handle Promo Code submission via session or request
        if (isset($_POST['apply_promo'])) {
            $_SESSION['applied_promo_code'] = strtoupper(trim($_POST['promo_code'] ?? ''));
        } elseif (isset($_POST['remove_promo'])) {
            unset($_SESSION['applied_promo_code']);
        }
        $applied_promo = $_SESSION['applied_promo_code'] ?? ($_REQUEST['promo_code'] ?? null);

        // Fetch Dynamic Stay Pricing
        $pricing            = calculateStayPricing($conn, $room_type, $checkin, $checkout, $applied_promo, $guests_total, $extra_adults);
        $room_price         = $pricing['base_price_per_night'];
        $nights             = $pricing['nights'];
        $subtotal_amount    = $pricing['subtotal'];
        $discount_amount    = $pricing['discount_amount'];
        $total_amount       = $pricing['total_amount'];
        $deposit_amount     = $pricing['deposit_amount'];
        $promo_error        = $pricing['promo_error'];
        $applied_promo_code = $pricing['promo_code'];

        if ($promo_error && isset($_POST['apply_promo'])) {
            $error = $promo_error;
            unset($_SESSION['applied_promo_code']);
        }

        // Restore success state on GET redirect to prevent form resubmission and double notifications
        if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_SESSION['booking_success'])) {
            $success            = true;
            $step               = 4;
            $booking_id         = $_SESSION['booking_success']['booking_id'];
            $booking_ref        = $_SESSION['booking_success']['booking_ref'];
            $guest_name         = $_SESSION['booking_success']['guest_name'];
            $guest_email        = $_SESSION['booking_success']['guest_email'];
            $checkin            = $_SESSION['booking_success']['checkin'];
            $checkout           = $_SESSION['booking_success']['checkout'];
            $accommodation_name = $_SESSION['booking_success']['accommodation'];
            $nights             = $_SESSION['booking_success']['nights'];
            $total_amount       = $_SESSION['booking_success']['total_amount'] ?? 0;
            $deposit_amount     = $_SESSION['booking_success']['deposit_amount'];
            $remaining_balance  = $_SESSION['booking_success']['remaining_balance'] ?? 0;
            $payment_method     = $_SESSION['booking_success']['payment_method'];
            $checkin_token      = $_SESSION['booking_success']['checkin_token'];
            $cancel_token       = $_SESSION['booking_success']['cancel_token'];
            $guests             = $_SESSION['booking_success']['guests'] ?? $guests;
            $booking_eta        = $_SESSION['booking_success']['eta'] ?? '14:00';

            $base_url   = booking_public_frontend_url();
            $cancel_url = rtrim($base_url, '/') . '/cancel_booking?token=' . $cancel_token;
        }

        // Fetch GCash Settings
        $gcash_number = '0950 522 3146';
        $gcash_name   = 'Justine B';
        $g_res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('gcash_number', 'gcash_name')");
        if ($g_res) {
            while ($r = $g_res->fetch_assoc()) {
                if ($r['setting_key'] === 'gcash_number' && !empty($r['setting_value'])) {
                    $gcash_number = $r['setting_value'];
                }
                if ($r['setting_key'] === 'gcash_name' && !empty($r['setting_value'])) {
                    $gcash_name = $r['setting_value'];
                }
            }
        }

        $breakfast_included_types = [
            'beachview_duplex' => true,
            'seaview_duplex'   => true,
            'beach_villa'      => true,
        ];
        $has_breakfast = !empty($breakfast_included_types[$room_type]);

        $room_type_id   = 0;
        $room_type_stmt = $conn->prepare("SELECT id FROM room_types WHERE name = ? LIMIT 1");
        $room_type_stmt->bind_param("s", $room_type);
        $room_type_stmt->execute();
        $room_type_result = $room_type_stmt->get_result();
        if ($room_type_result && $room_type_result->num_rows > 0) {
            $room_type_row = $room_type_result->fetch_assoc();
            $room_type_id  = (int) $room_type_row['id'];
        }
        $room_type_stmt->close();

        $available_rooms = null;
        if ($room_type_id > 0) {
            try {
                $pdo             = getPdoConnection();
                $available_rooms = getAvailableRooms($pdo, $room_type_id, $checkin, $checkout);
            } catch (Throwable $e) {
                $available_rooms = null;
            }
        }

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_csrf_token();
            if (isset($_POST['action']) && !isset($_POST['apply_promo']) && !isset($_POST['remove_promo'])) {
                if ($_POST['action'] === 'go_step_2') {
                    header('Location: book?step=2');
                    exit;
                } elseif ($_POST['action'] === 'go_step_3') {
                    $_SESSION['guest_first_name'] = ucwords(strtolower(trim($_POST['first_name'] ?? '')));
                    $_SESSION['guest_last_name']  = ucwords(strtolower(trim($_POST['last_name'] ?? '')));
                    $_SESSION['guest_email']      = trim($_POST['email'] ?? '');
                    $_SESSION['guest_phone_code'] = $_POST['phone_code'] ?? '+63';
                    $_SESSION['guest_phone']      = $_POST['phone'] ?? '';
                    $_SESSION['guest_country']    = $_POST['country'] ?? '';
                    $_SESSION['guest_comments']   = $_POST['comments'] ?? '';
                    $_SESSION['guest_eta']        = $_POST['eta'] ?? '14:00';

                    if (empty($_SESSION['guest_first_name']) || empty($_SESSION['guest_last_name']) || empty($_SESSION['guest_email'])) {
                        $error = "Please fill out required fields.";
                        $step  = 2;
                    } else {
                        header('Location: book?step=3');
                        exit;
                    }
                } elseif ($_POST['action'] === 'request_discount' || ($_POST['action'] === 'confirm_booking' && in_array($_POST['discount_category'] ?? '', ['senior', 'pwd'], true))) {
                    try {
                        if (empty($_POST['discount_consent'])) {
                            throw new RuntimeException('Please consent to ID verification and accept the booking policies.');
                        }
                        if (empty($_SESSION['discount_request_nonce']) || !hash_equals($_SESSION['discount_request_nonce'], (string) ($_POST['discount_request_nonce'] ?? ''))) {
                            throw new RuntimeException('This form was already submitted or has expired. Please refresh.');
                        }
                        require_once dirname(__DIR__) . '/helpers/rate_limiter.php';
                        RateLimiter::enforce($conn, 'discount_request', 5, 3600);
                        $request = discount_create($conn, [
                            'category'       => $_POST['discount_category'] ?? '',
                            'eligible_guest' => trim($_POST['eligible_guest'] ?? ''),
                            'name'           => trim(($_SESSION['guest_first_name'] ?? '') . ' ' . ($_SESSION['guest_last_name'] ?? '')),
                            'email'          => $_SESSION['guest_email'] ?? '',
                            'phone'          => $_SESSION['guest_phone'] ?? '',
                            'eta'            => $_SESSION['guest_eta'] ?? '14:00',
                            'room_type'      => $room_type,
                            'checkin'        => $checkin,
                            'checkout'       => $checkout,
                            'guests'         => $guests_total,
                            'extra_adults'   => $extra_adults,
                            'extra_children' => $extra_children,
                            // Subtotal is calculated server-side before promo codes. Discounts never stack.
                            'original'       => (int) round($pricing['subtotal'] * 100),
                        ], $_FILES['discount_id'] ?? []);
                        $_SESSION['discount_tokens'][(int) $request['booking_id']] = $request['token'];
                        unset($_SESSION['discount_request_nonce']);
                        unset($_SESSION['applied_promo_code']);
                        $emailSent = discount_email($conn, (int) $request['booking_id']);
                        $_SESSION['discount_notice'] = $emailSent
                            ? 'Request submitted. Check your email for your private booking link.'
                            : 'Request submitted. Email could not be sent; please save this private booking link.';
                        header('Location: discount_booking.php?booking=' . (int) $request['booking_id']);
                        exit;
                    } catch (Throwable $e) {
                        $error = $e instanceof mysqli_sql_exception ? 'Could not save your request. Please try again.' : $e->getMessage();
                        $step  = 3;
                    }
                } elseif ($_POST['action'] === 'confirm_booking') {
                    $bookingTransactionOpen = false;
                    $paymongoSessionCreated = false;
                    $storedReceiptPath      = null;

                    $guest_name  = ($_SESSION['guest_first_name'] ?? '') . ' ' . ($_SESSION['guest_last_name'] ?? '');
                    $guest_email = $_SESSION['guest_email'] ?? '';

                    $prev_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE guest_email = ? AND status NOT IN ('Cancelled','Pending Payment')");
                    $prev_stmt->bind_param("s", $guest_email);
                    $prev_stmt->execute();
                    $prev_count = (int) $prev_stmt->get_result()->fetch_assoc()['cnt'];
                    $prev_stmt->close();
                    if ($prev_count === 0) {
                        $guest_type = 'First Visit';
                    } elseif ($prev_count === 1) {
                        $guest_type = 'Returning Guest';
                    } else {
                        $guest_type = 'VIP Member';
                    }

                    if ($room_type_id > 0) {
                        try {
                            $pdo                 = getPdoConnection();
                            $currentAvailability = getAvailableRooms($pdo, $room_type_id, $checkin, $checkout);
                            if ($currentAvailability <= 0) {
                                $error = 'Sorry, this room type is sold out for the selected dates.';
                                $step  = 3;
                            }
                        } catch (Throwable $e) {
                            error_log('Booking availability check failed: ' . $e->getMessage());
                            $error = 'We could not confirm availability right now. Please try again.';
                            $step  = 3;
                        }
                    }

                    $room_id     = null;
                    $db_acc_name = $accommodation_name;
                    if (empty($error)) {
                        $conn->begin_transaction();
                        $bookingTransactionOpen = true;
                        pm_query($conn, "SELECT id FROM rooms WHERE type=? AND status<>'maintenance' ORDER BY id FOR UPDATE", [$room_type])->get_result()->fetch_all();

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
                            $room        = $room_query->fetch_assoc();
                            $room_id     = (int) $room['id'];
                            $db_acc_name = $room['name'];
                        }
                        $room_stmt->close();

                        if ($room_id === null) {
                            $error = 'No available room matches your selected dates. Please choose different dates or room type.';
                            $step  = 3;
                        }
                    }

                    $checkin_token   = bin2hex(random_bytes(16));
                    $cancel_token    = bin2hex(random_bytes(16));
                    $payment_method  = $_POST['payment_method'] ?? 'Bank Deposit';
                    $bank_reference  = isset($_POST['bank_reference']) ? trim($_POST['bank_reference']) : '';
                    $gcash_reference = isset($_POST['gcash_reference']) ? trim($_POST['gcash_reference']) : '';
                    $skip_insert     = false;

                    if ($payment_method === 'Bank Deposit' && empty($bank_reference)) {
                        $error       = 'Please enter your Bank transfer reference number to continue.';
                        $step        = 3;
                        $skip_insert = true;
                    } elseif ($payment_method === 'GCash QR' && empty($gcash_reference)) {
                        $error       = 'Please enter your GCash reference number to continue.';
                        $step        = 3;
                        $skip_insert = true;
                    }

                    $payment_ref = 'TXN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                    if ($payment_method === 'Bank Deposit') {
                        $payment_ref = $bank_reference;
                    } elseif ($payment_method === 'GCash QR') {
                        $payment_ref = $gcash_reference;
                    }

                    $receipt_url = null;

                    // Handle receipt upload for Bank Deposit
                    if ($payment_method === 'Bank Deposit' && empty($error)) {
                        require_once dirname(__DIR__) . '/helpers/file_scanner.php';
                        $file_key = 'bank_receipt';
                        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                            $tmpFile       = $_FILES[$file_key]['tmp_name'];
                            $origName      = basename($_FILES[$file_key]['name']);
                            $file_ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                            $allowed_exts  = ['jpg', 'jpeg', 'png', 'pdf'];
                            $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
                            $finfo         = finfo_open(FILEINFO_MIME_TYPE);
                            $realMime      = finfo_file($finfo, $tmpFile);
                            finfo_close($finfo);
                            $max_size = 2 * 1024 * 1024;
                            if ($_FILES[$file_key]['size'] > $max_size) {
                                $error = 'File size exceeds 2MB limit. Please upload an image under 2MB.';
                                $step  = 3; $skip_insert = true;
                            } elseif (!in_array($file_ext, $allowed_exts, true) || !in_array($realMime, $allowed_mimes, true)) {
                                $error = 'Invalid receipt file type. Only JPG, PNG, and PDF are allowed.';
                                $step  = 3; $skip_insert = true;
                            } else {
                                $scanResult = scan_uploaded_file($tmpFile, $file_ext);
                                if (!$scanResult['safe']) {
                                    $error = 'File rejected: ' . ($scanResult['reason'] ?? 'unsafe content detected');
                                    $step  = 3; $skip_insert = true;
                                } else {
                                    $new_filename = 'rcpt_' . bin2hex(random_bytes(16)) . '.' . $file_ext;
                                    if (store_private_receipt($tmpFile, $new_filename)) {
                                        $receipt_url       = 'storage/receipts/' . $new_filename;
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
                        require_once dirname(__DIR__) . '/helpers/file_scanner.php';
                        $file_key = 'gcash_receipt';
                        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                            $tmpFile       = $_FILES[$file_key]['tmp_name'];
                            $origName      = basename($_FILES[$file_key]['name']);
                            $file_ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                            $allowed_exts  = ['jpg', 'jpeg', 'png'];
                            $allowed_mimes = ['image/jpeg', 'image/png'];
                            $finfo         = finfo_open(FILEINFO_MIME_TYPE);
                            $realMime      = finfo_file($finfo, $tmpFile);
                            finfo_close($finfo);
                            $max_size = 2 * 1024 * 1024;
                            if ($_FILES[$file_key]['size'] > $max_size) {
                                $error = 'File size exceeds 2MB limit. Please upload an image under 2MB.';
                                $step  = 3; $skip_insert = true;
                            } elseif (!in_array($file_ext, $allowed_exts, true) || !in_array($realMime, $allowed_mimes, true)) {
                                $error = 'Invalid file type. Please upload a JPG or PNG screenshot of your GCash payment.';
                                $step  = 3; $skip_insert = true;
                            } else {
                                $scanResult = scan_uploaded_file($tmpFile, $file_ext);
                                if (!$scanResult['safe']) {
                                    $error = 'File rejected: ' . ($scanResult['reason'] ?? 'unsafe content detected');
                                    $step  = 3; $skip_insert = true;
                                } else {
                                    $new_filename = 'gcash_rcpt_' . bin2hex(random_bytes(16)) . '.' . $file_ext;
                                    if (store_private_receipt($tmpFile, $new_filename)) {
                                        $receipt_url       = 'storage/receipts/' . $new_filename;
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
                            $guest_phone_raw        = (string) ($_SESSION['guest_phone'] ?? '');
                            $guest_phone            = sf_normalize_e164($guest_phone_raw, (string) ($_SESSION['guest_phone_code'] ?? '+63')) ?? $guest_phone_raw;
                            $guest_country          = $_SESSION['guest_country'] ?? '';
                            $guest_special_requests = $_SESSION['guest_comments'] ?? '';
                            $eta                    = $_SESSION['guest_eta'] ?? '14:00';

                            $room_value = $room_id === null ? "NULL" : (string) $room_id;
                            $sql = "INSERT INTO bookings (guest_name, guest_email, guest_type, check_in, check_out, guests_count, extra_adults, extra_children, room_id, accommodation_name, eta, status, checkin_token, cancellation_token, payment_method, guest_phone, guest_country, guest_special_requests, promo_code, discount_amount)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, " . $room_value . ", ?, ?, 'Pending Payment', ?, ?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("sssssiiisssssssssd", $guest_name, $guest_email, $guest_type, $checkin, $checkout, $guests_total, $extra_adults, $extra_children, $db_acc_name, $eta, $checkin_token, $cancel_token, $payment_method, $guest_phone, $guest_country, $guest_special_requests, $applied_promo_code, $discount_amount);

                            if ($stmt->execute()) {
                                $booking_id  = $stmt->insert_id;
                                $booking_ref = 'REF-' . str_pad((string) $booking_id, 3, '0', STR_PAD_LEFT);
                                $pay_status  = 'pending';

                                $stmt_pay = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, receipt_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt_pay->bind_param("issdssss", $booking_id, $guest_name, $guest_email, $deposit_amount, $payment_method, $payment_ref, $pay_status, $receipt_url);
                                $stmt_pay->execute();
                                $checkoutPaymentId = (int) $stmt_pay->insert_id;
                                $conn->commit();
                                $bookingTransactionOpen = false;

                                // QR Code for check-in
                                $base_url    = booking_public_frontend_url();
                                $checkin_url = rtrim($base_url, '/') . '/checkin?ref=' . $booking_ref . '&token=' . $checkin_token;
                                $cancel_url  = rtrim($base_url, '/') . '/cancel_booking?token=' . $cancel_token;
                                $qr_dir      = $frontendRoot . '/assets/qrcodes/';
                                if (!is_dir($qr_dir)) { mkdir($qr_dir, 0777, true); }
                                $qr_file     = $qr_dir . 'qr_booking_' . $booking_id . '.png';
                                QRcode::png($checkin_url, $qr_file, QR_ECLEVEL_H, 6, 4);

                                if ($payment_method === 'PayMongo Online') {
                                    // PayMongo redirects to success_url verbatim; the return page resolves the session from our server-side binding.
                                    $successUrl       = rtrim($base_url, '/') . '/payment_success.php?booking_id=' . $booking_id;
                                    $cancelPaymentUrl = rtrim($base_url, '/') . '/book.php?step=3&cancelled=1&cancel_token=' . rawurlencode($cancel_token);
                                    $desc             = '50% Booking Deposit for ' . $db_acc_name . ' (' . $booking_ref . ')';

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
                                        $sessId                 = $paymongoRes['session_id'] ?? '';
                                        pm_store_checkout($conn, $sessId, $checkoutPaymentId, (int) $booking_id, (float) $deposit_amount);
                                        pm_query($conn, "UPDATE payments SET transaction_id = ? WHERE id = ? AND status = 'pending'", [$sessId, $checkoutPaymentId]);
                                        unset($_SESSION['applied_promo_code']);
                                        @sendBookingReceivedEmail(
                                            $guest_email,
                                            $guest_name,
                                            $booking_ref,
                                            $db_acc_name,
                                            $checkin,
                                            $checkout,
                                            $total_amount,
                                            $cancel_url
                                        );
                                        header('Location: ' . $paymongoRes['checkout_url']);
                                        exit;
                                    } else {
                                        $pmErr = $paymongoRes['error'] ?? 'PayMongo service error';
                                        error_log('PayMongo checkout creation failed: ' . $pmErr);
                                        $error = 'Online payment could not be started. Please try again.';
                                        $step  = 3;

                                        // PayMongo did not create a checkout, so atomically remove
                                        // the local pending payment and its exact token-bound booking.
                                        $cleaned = cleanup_failed_paymongo_booking(
                                            $conn,
                                            (int) $booking_id,
                                            $cancel_token
                                        );
                                        if (!$cleaned) {
                                            error_log('Failed PayMongo setup left booking REF-' . (int) $booking_id . ' for manual review.');
                                        }
                                        if ($cleaned && !empty($qr_file) && is_file($qr_file)) {
                                            @unlink($qr_file);
                                        }
                                    }
                                }

                                if (empty($error)) {
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
                            if (($payment_method ?? '') === 'PayMongo Online'
                                && !$paymongoSessionCreated
                                && !$bookingTransactionOpen
                                && !empty($booking_id)
                                && !empty($cancel_token)) {
                                $cleaned = cleanup_failed_paymongo_booking($conn, (int) $booking_id, $cancel_token);
                                if ($cleaned && !empty($qr_file) && is_file($qr_file)) {
                                    @unlink($qr_file);
                                }
                            }
                            error_log('Booking save failed: ' . $e->getMessage());
                            $error = 'Error saving your booking. Please try again.';
                        }
                    }

                    if ($bookingTransactionOpen) {
                        $conn->rollback();
                    }
                    if (!empty($storedReceiptPath) && is_file($storedReceiptPath)) {
                        if (!@unlink($storedReceiptPath)) {
                            error_log('Could not remove orphaned receipt after booking failure.');
                        }
                    }
                }
            }
        }

        // Formatting helpers
        $checkin_fmt  = date('D, d M Y', strtotime($checkin));
        $checkout_fmt = date('D, d M Y', strtotime($checkout));
        $full_name    = trim(($_SESSION['guest_first_name'] ?? '') . ' ' . ($_SESSION['guest_last_name'] ?? ''));
        if ($step === 3 && empty($_SESSION['discount_request_nonce'])) {
            $_SESSION['discount_request_nonce'] = bin2hex(random_bytes(24));
        }

        $bookingScriptDirectory   = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $bookingApplicationPath   = rtrim(preg_replace('~/frontend$~', '', $bookingScriptDirectory), '/');

        return [
            'step'                   => $step,
            'success'                => $success,
            'error'                  => $error,
            'booking_id'             => $booking_id,
            'booking_ref'            => $booking_ref,
            'guest_name'             => $guest_name,
            'guest_email'            => $guest_email,
            'checkin'                => $checkin,
            'checkout'               => $checkout,
            'checkin_fmt'            => $checkin_fmt,
            'checkout_fmt'           => $checkout_fmt,
            'accommodation_name'     => $accommodation_name,
            'room_type'              => $room_type,
            'nights'                 => $nights,
            'guests'                 => $guests,
            'guests_total'           => $guests_total,
            'extra_adults'           => $extra_adults,
            'extra_children'         => $extra_children,
            'pricing'                => $pricing,
            'total_amount'           => $total_amount,
            'deposit_amount'         => $deposit_amount,
            'remaining_balance'      => $remaining_balance,
            'discount_amount'        => $discount_amount,
            'applied_promo_code'     => $applied_promo_code,
            'payment_method'         => $payment_method,
            'checkin_token'          => $checkin_token,
            'cancel_token'           => $cancel_token,
            'cancel_url'             => $cancel_url,
            'booking_eta'            => $booking_eta,
            'gcash_number'           => $gcash_number,
            'gcash_name'             => $gcash_name,
            'has_breakfast'          => $has_breakfast,
            'available_rooms'        => $available_rooms,
            'full_name'              => $full_name,
            'bookingApplicationPath' => $bookingApplicationPath,
        ];
    }
}
