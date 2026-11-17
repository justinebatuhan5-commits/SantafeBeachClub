<?php
/**
 * Controller for frontend/admin_checkin.php.
 */

function sfbc_handle_admin_checkin_page(mysqli $conn): array
{
    @sf_send_prearrival_checkin_reminders($conn, 2);
    @sf_process_balance_deadlines($conn);

    // Handle POST request to check-in
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
        $booking_id = intval($_POST['booking_id']);
        $serverFinancials = sf_booking_financials($conn, $booking_id);
        if (!$serverFinancials || !in_array($serverFinancials['status'], ['Pending', 'Confirmed'], true)) {
            http_response_code(409);
            exit('Booking is not eligible for check-in.');
        }
        $serverBalanceDue = (float)$serverFinancials['balance_due'];
        if ($serverBalanceDue > 0.009 && ($_POST['collect_payment'] ?? '') !== '1') {
            http_response_code(409);
            exit('The remaining balance must be paid before check-in.');
        }
        $discountCheckin = pm_query($conn, 'SELECT * FROM booking_discounts WHERE booking_id=?', [$booking_id])->get_result()->fetch_assoc();
        if ($discountCheckin && $discountCheckin['state'] !== 'Confirmed') {
            header('Location: discount_requests.php?booking=' . $booking_id);
            exit;
        }
        $print_params = '';

        // Check if there is a payment to record
        if (isset($_POST['collect_payment']) && $_POST['collect_payment'] === '1') {
            $payment_method = $_POST['payment_method'] ?? 'Front Desk Cash';
            $balance_amount = $discountCheckin
                ? discount_due($conn, $discountCheckin) / 100
                : $serverBalanceDue;

            $amount_tendered = isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '' ? floatval($_POST['amount_tendered']) : $balance_amount;
            $change_amount = isset($_POST['change_amount']) && $_POST['change_amount'] !== '' ? floatval($_POST['change_amount']) : 0;
            $ref_no = trim($_POST['reference_number'] ?? '');

            if ($balance_amount > 0) {
                $g_stmt = $conn->prepare("SELECT guest_name, guest_email FROM bookings WHERE id = ?");
                $g_stmt->bind_param("i", $booking_id);
                $g_stmt->execute();
                $g_res = $g_stmt->get_result()->fetch_assoc();
                $g_stmt->close();

                if ($g_res) {
                    $guest_name = $g_res['guest_name'];
                    $guest_email = $g_res['guest_email'];
                    $txn_id = (!empty($ref_no) && $payment_method !== 'Front Desk Cash') ? $ref_no : 'TXN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

                    $p_stmt = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, accounting_status, amount_tendered, change_amount, purpose) VALUES (?, ?, ?, ?, ?, ?, 'verified', 'deferred', ?, ?, 'balance')");
                    $p_stmt->bind_param("issdssdd", $booking_id, $guest_name, $guest_email, $balance_amount, $payment_method, $txn_id, $amount_tendered, $change_amount);
                    $p_stmt->execute();
                    $payment_id = (int)$conn->insert_id;
                    $p_stmt->close();

                    if ($discountCheckin) {
                        pm_query($conn, 'INSERT INTO discount_payments (payment_id, booking_id, purpose, reference_key) VALUES (?, ?, \'balance\', NULL)', [$payment_id, $booking_id]);
                        discount_audit($conn, $booking_id, $_SESSION['admin_username'] ?? 'Reception', 'Balance paid at check-in', $payment_method . ' · ' . $txn_id);
                    }

                    $print_params = "&print_rcpt=1&bid=" . urlencode($booking_id) . "&txn=" . urlencode($txn_id) . "&method=" . urlencode($payment_method) . "&amount=" . urlencode($balance_amount) . "&tendered=" . urlencode($amount_tendered) . "&change=" . urlencode($change_amount);

                    $notif_title = 'Balance Payment Collected';
                    $notif_type = 'info';
                    $notif_message = 'Collected remaining balance of ₱' . number_format($balance_amount, 2) . ' via ' . $payment_method . ' for guest ' . htmlspecialchars($guest_name) . ' (REF-' . str_pad($booking_id, 3, '0', STR_PAD_LEFT) . ') at check-in.';

                    $n_stmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
                    $n_stmt->bind_param("sssi", $notif_title, $notif_message, $notif_type, $booking_id);
                    $n_stmt->execute();
                    $n_stmt->close();
                }
            }
        }

        $stmt = $conn->prepare("UPDATE bookings SET status = 'Checked In' WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();
        sf_refresh_balance_status($conn, $booking_id);

        $room_query = $conn->query("SELECT room_id FROM bookings WHERE id = $booking_id");
        if ($room_query && $room_query->num_rows > 0) {
            $room_id = $room_query->fetch_assoc()['room_id'];
            if ($room_id) {
                $conn->query("UPDATE rooms SET status = 'occupied' WHERE id = $room_id");
            }
        }

        $admin_user = $_SESSION['admin_username'] ?? 'System';
        log_activity($conn, $admin_user, 'Check-in Guest', 'Checked in booking ID #' . $booking_id);

        if (isset($_POST['format']) && $_POST['format'] === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        header("Location: admin_checkin?success=1" . $print_params);
        exit;
    }

    // Handle POST request for Walk-in / Express Check-in
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'walkin_checkin') {
        $guest_name = trim($_POST['guest_name'] ?? '');
        $guest_email = trim($_POST['guest_email'] ?? '');
        $guest_phone = trim($_POST['guest_phone'] ?? '');
        $guest_country = trim($_POST['guest_country'] ?? 'Philippines');
        if ($guest_country === '') {
            $guest_country = 'Philippines';
        }
        $guest_country = substr($guest_country, 0, 100);
        $guest_type = trim($_POST['guest_type'] ?? 'Walk-in');
        $room_id = intval($_POST['room_id'] ?? 0);
        $check_in = trim($_POST['check_in'] ?? date('Y-m-d'));
        $check_out = trim($_POST['check_out'] ?? date('Y-m-d', strtotime('+1 day')));
        $guests_count = max(1, intval($_POST['guests_count'] ?? 1));
        $payment_method = trim($_POST['payment_method'] ?? 'Front Desk Cash');
        $amount_tendered = isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '' ? floatval($_POST['amount_tendered']) : 0;
        $ref_no = trim($_POST['reference_number'] ?? '');

        if (!empty($guest_name) && $room_id > 0) {
            $r_stmt = $conn->prepare("SELECT r.*, rt.id as room_type_id FROM rooms r LEFT JOIN room_types rt ON r.type = rt.name WHERE r.id = ?");
            $r_stmt->bind_param("i", $room_id);
            $r_stmt->execute();
            $room_info = $r_stmt->get_result()->fetch_assoc();
            $r_stmt->close();

            if ($room_info) {
                $nights = max(1, (int)ceil((strtotime($check_out) - strtotime($check_in)) / 86400));
                $price_per_night = floatval($room_info['price_per_night']);
                $total_cost = $nights * $price_per_night;
                $accommodation_name = $room_info['name'];
                $room_type_id = $room_info['room_type_id'] ?? null;

                $change_amount = max(0, $amount_tendered - $total_cost);
                if ($payment_method !== 'Front Desk Cash') {
                    $amount_tendered = $total_cost;
                    $change_amount = 0;
                }

                $checkin_token = bin2hex(random_bytes(16));
                $cancellation_token = bin2hex(random_bytes(16));
                $eta = date('H:i');

                $b_stmt = $conn->prepare("
                    INSERT INTO bookings (guest_name, guest_email, guest_phone, guest_country, guest_type, room_type_id, room_id, accommodation_name, check_in, check_out, guests_count, eta, status, payment_method, checkin_token, cancellation_token)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Checked In', ?, ?, ?)
                ");
                $b_stmt->bind_param(
                    "sssssiisssissss",
                    $guest_name,
                    $guest_email,
                    $guest_phone,
                    $guest_country,
                    $guest_type,
                    $room_type_id,
                    $room_id,
                    $accommodation_name,
                    $check_in,
                    $check_out,
                    $guests_count,
                    $eta,
                    $payment_method,
                    $checkin_token,
                    $cancellation_token
                );
                $b_stmt->execute();
                $booking_id = $conn->insert_id;
                $b_stmt->close();

                $u_stmt = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $u_stmt->bind_param("i", $room_id);
                $u_stmt->execute();
                $u_stmt->close();

                $txn_id = (!empty($ref_no) && $payment_method !== 'Front Desk Cash') ? $ref_no : 'TXN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $p_stmt = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, accounting_status, amount_tendered, change_amount) VALUES (?, ?, ?, ?, ?, ?, 'verified', 'deferred', ?, ?)");
                $p_stmt->bind_param("issdssdd", $booking_id, $guest_name, $guest_email, $total_cost, $payment_method, $txn_id, $amount_tendered, $change_amount);
                $p_stmt->execute();
                $p_stmt->close();

                $notif_title = 'New Walk-in Check-in';
                $notif_type = 'success';
                $notif_message = 'Walk-in guest ' . htmlspecialchars($guest_name) . ' checked into ' . htmlspecialchars($accommodation_name) . ' for ' . $nights . ' night(s). ₱' . number_format($total_cost, 2) . ' collected via ' . $payment_method . '.';
                $n_stmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
                $n_stmt->bind_param("sssi", $notif_title, $notif_message, $notif_type, $booking_id);
                $n_stmt->execute();
                $n_stmt->close();

                $admin_user = $_SESSION['admin_username'] ?? 'System';
                log_activity($conn, $admin_user, 'Walk-in Check-in', 'Checked in walk-in guest ' . $guest_name . ' into ' . $accommodation_name . ' (Booking #' . $booking_id . ')');

                $print_params = "&print_rcpt=1&bid=" . urlencode($booking_id) . "&txn=" . urlencode($txn_id) . "&method=" . urlencode($payment_method) . "&amount=" . urlencode($total_cost) . "&tendered=" . urlencode($amount_tendered) . "&change=" . urlencode($change_amount);

                header("Location: admin_checkin?success=walkin" . $print_params);
                exit;
            }
        }
    }

    // Handle POST request to mark booking as Cancelled / No-Show
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_noshow') {
        require_csrf_token();
        unset($_SESSION['noshow_refund_handoff']);
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $cancel_reason = trim($_POST['cancel_reason'] ?? 'No-Show / Overdue Check-in');
        if (empty($cancel_reason)) {
            $cancel_reason = 'No-Show / Overdue Check-in';
        }

        if ($booking_id > 0) {
            $b_stmt = $conn->prepare("SELECT guest_name, accommodation_name, room_id, status, check_in, check_out FROM bookings WHERE id = ?");
            $b_stmt->bind_param("i", $booking_id);
            $b_stmt->execute();
            $b_data = $b_stmt->get_result()->fetch_assoc();
            $b_stmt->close();

            if ($b_data && in_array($b_data['status'], ['Pending', 'Confirmed'])) {
                $u_stmt = $conn->prepare("UPDATE bookings SET status = 'Cancelled', cancelled_at = NOW(), cancellation_reason = ?, cancelled_for_nonpayment_at=CASE WHEN balance_status='cancellation_review' THEN NOW() ELSE cancelled_for_nonpayment_at END, balance_status='cancelled' WHERE id = ?");
                $u_stmt->bind_param("si", $cancel_reason, $booking_id);
                $u_stmt->execute();
                $u_stmt->close();

                if (!empty($b_data['room_id'])) {
                    $conn->query("UPDATE rooms SET status = 'ready' WHERE id = " . intval($b_data['room_id']));
                }

                $admin_user = $_SESSION['admin_username'] ?? 'Staff';
                log_activity($conn, $admin_user, 'Mark No-Show / Cancelled', 'Marked booking #' . $booking_id . ' (' . $b_data['guest_name'] . ') as Cancelled (' . $cancel_reason . ')');

                $notif_title = 'Reservation Marked No-Show';
                $notif_message = 'Booking #' . $booking_id . ' (' . $b_data['guest_name'] . ' - ' . $b_data['accommodation_name'] . ') was marked as No-Show / Cancelled by ' . $admin_user . '. Room released.';
                $n_stmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, 'warning', ?)");
                $n_stmt->bind_param("ssi", $notif_title, $notif_message, $booking_id);
                $n_stmt->execute();
                $n_stmt->close();

                $_SESSION['noshow_refund_handoff'] = sf_noshow_refund_handoff(
                    $_SESSION['admin_role'] ?? '',
                    $booking_id,
                    $b_data,
                    $cancel_reason
                );
                header("Location: admin_checkin?cancelled=1&bid=" . urlencode($booking_id));
                exit;
            }
        }
        header("Location: admin_checkin");
        exit;
    }

    $ref = strtoupper(trim((string)($_GET['ref'] ?? '')));
    $token = strtolower(trim((string)($_GET['token'] ?? '')));
    $specific_booking = null;

    if (preg_match('/\AREF-(\d+)\z/', $ref, $ref_match) && preg_match('/\A[a-f0-9]{32}\z/', $token)) {
        $scanned_booking_id = (int)$ref_match[1];
        $stmt = $conn->prepare("
            SELECT 
                b.*,
                DATEDIFF(b.check_out, b.check_in) AS nights,
                COALESCE(r.price_per_night, rt.price, 0) AS price_per_night,
                COALESCE((SELECT d.total_centavos / 100 FROM booking_discounts d WHERE d.booking_id=b.id), DATEDIFF(b.check_out, b.check_in) * COALESCE(r.price_per_night, rt.price, 0)) AS total_cost,
                (SELECT d.category FROM booking_discounts d WHERE d.booking_id=b.id) AS discount_category,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id AND p.status = 'verified') AS amount_paid
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.id
            LEFT JOIN room_types rt ON b.room_type_id = rt.id
            WHERE b.id = ? AND b.checkin_token = ? AND b.status IN ('Pending', 'Confirmed')
        ");
        $stmt->bind_param("is", $scanned_booking_id, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $specific_booking = $result->fetch_assoc();
        }
        $stmt->close();
    }

    $available_rooms_query = $conn->query("
        SELECT r.id, r.room_number, r.name, r.type, r.price_per_night, r.capacity, rt.id AS room_type_id
        FROM rooms r
        LEFT JOIN room_types rt ON r.type = rt.name
        WHERE r.status = 'ready'
          AND r.id NOT IN (
              SELECT b.room_id 
              FROM bookings b 
              WHERE b.room_id IS NOT NULL 
                AND b.status IN ('Checked In', 'Confirmed', 'Pending', 'Pending Payment')
                AND b.check_in <= CURDATE()
                AND b.check_out > CURDATE()
          )
        ORDER BY r.room_number ASC
    ");
    $available_rooms = $available_rooms_query ? $available_rooms_query->fetch_all(MYSQLI_ASSOC) : [];

    $bookings_query = $conn->query("
        SELECT 
            b.id, b.guest_name, b.guest_email, b.guest_type, b.accommodation_name, b.check_in, b.check_out, b.eta, b.status,
            b.balance_status, b.balance_due_at, b.balance_grace_expires_at,
            DATEDIFF(b.check_out, b.check_in) AS nights,
            COALESCE(r.price_per_night, rt.price, 0) AS price_per_night,
            COALESCE((SELECT d.total_centavos / 100 FROM booking_discounts d WHERE d.booking_id=b.id), DATEDIFF(b.check_out, b.check_in) * COALESCE(r.price_per_night, rt.price, 0)) AS total_cost,
            (SELECT d.category FROM booking_discounts d WHERE d.booking_id=b.id) AS discount_category,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id AND p.status = 'verified') AS amount_paid
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN room_types rt ON b.room_type_id = rt.id
        WHERE b.status IN ('Pending', 'Confirmed') 
        ORDER BY b.check_in ASC
    ");
    $pending_bookings = $bookings_query ? $bookings_query->fetch_all(MYSQLI_ASSOC) : [];

    $printReceipt = null;
    if (isset($_GET['print_rcpt']) && $_GET['print_rcpt'] == '1') {
        $bid = $_GET['bid'] ?? '';
        $txn = $_GET['txn'] ?? '';
        $method = $_GET['method'] ?? '';
        $amountDue = (float)($_GET['amount'] ?? 0);
        $tendered = (float)($_GET['tendered'] ?? $amountDue);
        $change = (float)($_GET['change'] ?? 0);

        $pr_stmt = $conn->prepare("SELECT b.guest_name, b.accommodation_name, d.category AS discount_category FROM bookings b LEFT JOIN booking_discounts d ON d.booking_id=b.id WHERE b.id = ?");
        $pr_stmt->bind_param("i", $bid);
        $pr_stmt->execute();
        $pr_res = $pr_stmt->get_result()->fetch_assoc();
        $pr_stmt->close();

        if ($pr_res) {
            $receipt_discount_label = ($pr_res['discount_category'] ?? '') === 'senior'
                ? 'Senior Citizen (20%)'
                : ((($pr_res['discount_category'] ?? '') === 'pwd') ? 'PWD (20%)' : '');
            $printReceipt = [
                'rcpt' => str_pad((string)$bid, 4, '0', STR_PAD_LEFT),
                'dateStr' => date('M d, Y'),
                'timeStr' => date('h:i A'),
                'guestName' => (string)$pr_res['guest_name'],
                'room' => (string)$pr_res['accommodation_name'],
                'discountLabel' => $receipt_discount_label,
                'method' => (string)$method,
                'txn' => (string)$txn,
                'amountDue' => $amountDue,
                'tendered' => $tendered,
                'change' => $change,
                'adminName' => (string)($_SESSION['admin_username'] ?? 'Reception'),
                'favicon32' => 'assets/icons/favicon-circle-32.png?v=3',
                'favicon180' => 'assets/icons/favicon-circle-180.png?v=3',
            ];
        }
    }

    $frontendDir = dirname(__DIR__, 2) . '/frontend';
    $gcashLogoPath = $frontendDir . '/assets/images/gcash_logo.png';
    $gcashQrPath = $frontendDir . '/assets/gcash_qr.png';
    $gcashLogoVersion = file_exists($gcashLogoPath) ? (string)filemtime($gcashLogoPath) : (string)time();
    $gcashQrVersion = file_exists($gcashQrPath) ? (string)filemtime($gcashQrPath) : (string)time();

    return compact(
        'ref',
        'token',
        'specific_booking',
        'available_rooms',
        'pending_bookings',
        'printReceipt',
        'gcashLogoVersion',
        'gcashQrVersion'
    );
}
