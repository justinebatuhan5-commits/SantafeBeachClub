<?php
/**
 * Legacy POST compatibility handler for frontend/checkin.php.
 *
 * Reception and admin GET traffic redirects to admin_checkin.
 */

/**
 * Handles legacy POST check-in actions if submitted to frontend/checkin.php.
 */
function sfbc_handle_legacy_checkin_post(mysqli $conn): void
{
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'checkin') {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        $serverFinancials = sf_booking_financials($conn, $bookingId);
        if (!$serverFinancials || !in_array($serverFinancials['status'], ['Pending', 'Confirmed'], true)) {
            http_response_code(409);
            exit('Booking is not eligible for check-in.');
        }

        $serverBalanceDue = (float)$serverFinancials['balance_due'];
        $discountCheckin = pm_query($conn, 'SELECT * FROM booking_discounts WHERE booking_id=?', [$bookingId])->get_result()->fetch_assoc();
        if ($discountCheckin && ($discountCheckin['state'] !== 'Confirmed' || discount_due($conn, $discountCheckin) > 0)) {
            header('Location: discount_requests.php?booking=' . $bookingId);
            exit;
        }
        if ($discountCheckin) {
            $_POST['collect_payment'] = '0';
        }
        if (!$discountCheckin && $serverBalanceDue > 0.009 && ($_POST['collect_payment'] ?? '') !== '1') {
            http_response_code(409);
            exit('The remaining balance must be paid before check-in.');
        }

        $printParams = '';
        if (isset($_POST['collect_payment']) && $_POST['collect_payment'] === '1') {
            $paymentMethod = $_POST['payment_method'] ?? 'Front Desk Cash';
            $balanceAmount = $serverBalanceDue;
            $amountTendered = isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '' ? floatval($_POST['amount_tendered']) : $balanceAmount;
            $changeAmount = isset($_POST['change_amount']) && $_POST['change_amount'] !== '' ? floatval($_POST['change_amount']) : 0;
            $refNo = trim($_POST['reference_number'] ?? '');

            if ($balanceAmount > 0) {
                $gStmt = $conn->prepare("SELECT guest_name, guest_email FROM bookings WHERE id = ?");
                $gStmt->bind_param("i", $bookingId);
                $gStmt->execute();
                $gRes = $gStmt->get_result()->fetch_assoc();
                $gStmt->close();

                if ($gRes) {
                    $guestName = $gRes['guest_name'];
                    $guestEmail = $gRes['guest_email'];
                    $txnId = (!empty($refNo) && $paymentMethod !== 'Front Desk Cash') ? $refNo : 'TXN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

                    $pStmt = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, accounting_status, amount_tendered, change_amount, purpose) VALUES (?, ?, ?, ?, ?, ?, 'verified', 'deferred', ?, ?, 'balance')");
                    $pStmt->bind_param("issdssdd", $bookingId, $guestName, $guestEmail, $balanceAmount, $paymentMethod, $txnId, $amountTendered, $changeAmount);
                    $pStmt->execute();
                    $pStmt->close();

                    $printParams = "&print_rcpt=1&bid=" . urlencode((string)$bookingId) . "&txn=" . urlencode($txnId) . "&method=" . urlencode($paymentMethod) . "&amount=" . urlencode((string)$balanceAmount) . "&tendered=" . urlencode((string)$amountTendered) . "&change=" . urlencode((string)$changeAmount);

                    $notifTitle = 'Balance Payment Collected';
                    $notifType = 'info';
                    $notifMessage = 'Collected remaining balance of ₱' . number_format($balanceAmount, 2) . ' via ' . $paymentMethod . ' for guest ' . htmlspecialchars($guestName) . ' (REF-' . str_pad((string)$bookingId, 3, '0', STR_PAD_LEFT) . ') at check-in.';

                    $nStmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
                    $nStmt->bind_param("sssi", $notifTitle, $notifMessage, $notifType, $bookingId);
                    $nStmt->execute();
                    $nStmt->close();
                }
            }
        }

        $stmt = $conn->prepare("UPDATE bookings SET status = 'Checked In' WHERE id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $stmt->close();
        sf_refresh_balance_status($conn, $bookingId);

        $roomStmt = $conn->prepare("SELECT room_id FROM bookings WHERE id = ?");
        $roomStmt->bind_param("i", $bookingId);
        $roomStmt->execute();
        $roomQuery = $roomStmt->get_result();
        if ($roomQuery && $roomQuery->num_rows > 0) {
            $roomId = (int)$roomQuery->fetch_assoc()['room_id'];
            if ($roomId > 0) {
                $uStmt = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $uStmt->bind_param("i", $roomId);
                $uStmt->execute();
                $uStmt->close();
            }
        }
        $roomStmt->close();

        $adminUser = $_SESSION['admin_username'] ?? 'System';
        log_activity($conn, $adminUser, 'Check-in Guest', 'Checked in booking ID #' . $bookingId);

        if (isset($_POST['format']) && $_POST['format'] === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        header("Location: admin_checkin?success=1" . $printParams);
        exit;
    }

    if ($action === 'walkin_checkin') {
        $guestName = ucwords(strtolower(trim($_POST['guest_name'] ?? '')));
        $guestEmail = trim($_POST['guest_email'] ?? '');
        $guestPhone = trim($_POST['guest_phone'] ?? '');
        $guestType = trim($_POST['guest_type'] ?? 'Walk-in');
        $roomId = intval($_POST['room_id'] ?? 0);
        $checkIn = trim($_POST['check_in'] ?? date('Y-m-d'));
        $checkOut = trim($_POST['check_out'] ?? date('Y-m-d', strtotime('+1 day')));
        $guestsCount = max(1, intval($_POST['guests_count'] ?? 1));
        $paymentMethod = trim($_POST['payment_method'] ?? 'Front Desk Cash');
        $amountTendered = isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '' ? floatval($_POST['amount_tendered']) : 0;
        $refNo = trim($_POST['reference_number'] ?? '');

        if (!empty($guestName) && $roomId > 0) {
            $rStmt = $conn->prepare("SELECT r.*, rt.id as room_type_id FROM rooms r LEFT JOIN room_types rt ON r.type = rt.name WHERE r.id = ?");
            $rStmt->bind_param("i", $roomId);
            $rStmt->execute();
            $roomInfo = $rStmt->get_result()->fetch_assoc();
            $rStmt->close();

            if ($roomInfo) {
                $nights = max(1, (int)ceil((strtotime($checkOut) - strtotime($checkIn)) / 86400));
                $pricePerNight = floatval($roomInfo['price_per_night']);
                $totalCost = $nights * $pricePerNight;
                $accommodationName = $roomInfo['name'];
                $roomTypeId = $roomInfo['room_type_id'] ?? null;

                $changeAmount = max(0, $amountTendered - $totalCost);
                if ($paymentMethod !== 'Front Desk Cash') {
                    $amountTendered = $totalCost;
                    $changeAmount = 0;
                }

                $checkinToken = bin2hex(random_bytes(16));
                $cancellationToken = bin2hex(random_bytes(16));
                $eta = date('H:i');

                $bStmt = $conn->prepare("
                    INSERT INTO bookings (guest_name, guest_email, guest_phone, guest_type, room_type_id, room_id, accommodation_name, check_in, check_out, guests_count, eta, status, payment_method, checkin_token, cancellation_token)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Checked In', ?, ?, ?)
                ");
                $bStmt->bind_param(
                    "ssssiisssissss",
                    $guestName,
                    $guestEmail,
                    $guestPhone,
                    $guestType,
                    $roomTypeId,
                    $roomId,
                    $accommodationName,
                    $checkIn,
                    $checkOut,
                    $guestsCount,
                    $eta,
                    $paymentMethod,
                    $checkinToken,
                    $cancellationToken
                );
                $bStmt->execute();
                $bookingId = $conn->insert_id;
                $bStmt->close();

                $uStmt = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $uStmt->bind_param("i", $roomId);
                $uStmt->execute();
                $uStmt->close();

                $txnId = (!empty($refNo) && $paymentMethod !== 'Front Desk Cash') ? $refNo : 'TXN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $pStmt = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, accounting_status, amount_tendered, change_amount) VALUES (?, ?, ?, ?, ?, ?, 'verified', 'deferred', ?, ?)");
                $pStmt->bind_param("issdssdd", $bookingId, $guestName, $guestEmail, $totalCost, $paymentMethod, $txnId, $amountTendered, $changeAmount);
                $pStmt->execute();
                $pStmt->close();

                $notifTitle = 'New Walk-in Check-in';
                $notifType = 'success';
                $notifMessage = 'Walk-in guest ' . htmlspecialchars($guestName) . ' checked into ' . htmlspecialchars($accommodationName) . ' for ' . $nights . ' night(s). ₱' . number_format($totalCost, 2) . ' collected via ' . $paymentMethod . '.';
                $nStmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id) VALUES (?, ?, ?, ?)");
                $nStmt->bind_param("sssi", $notifTitle, $notifMessage, $notifType, $bookingId);
                $nStmt->execute();
                $nStmt->close();

                $adminUser = $_SESSION['admin_username'] ?? 'Reception';
                log_activity($conn, $adminUser, 'Walk-in Check-in', 'Checked in walk-in guest ' . $guestName . ' into ' . $accommodationName . ' (Booking #' . $bookingId . ')');

                $printParams = "&print_rcpt=1&bid=" . urlencode((string)$bookingId) . "&txn=" . urlencode($txnId) . "&method=" . urlencode($paymentMethod) . "&amount=" . urlencode((string)$totalCost) . "&tendered=" . urlencode((string)$amountTendered) . "&change=" . urlencode((string)$changeAmount);

                header("Location: admin_checkin?success=walkin" . $printParams);
                exit;
            }
        }
    }
}
