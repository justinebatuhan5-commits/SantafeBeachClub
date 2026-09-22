<?php

require_once __DIR__ . '/../services/booking_service.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $action = $_GET['action'] ?? 'check_availability';

    if ($action === 'validate_promo') {
        require_once __DIR__ . '/../config/db.php';
        require_once __DIR__ . '/../helpers/room_status_helper.php';

        $roomType = $_GET['room_type'] ?? 'beachview_duplex';
        $checkIn = $_GET['check_in'] ?? date('Y-m-d');
        $checkOut = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day'));
        $promoCode = trim($_GET['code'] ?? '');

        $calc = calculateStayPricing($conn, $roomType, $checkIn, $checkOut, $promoCode);
        echo json_encode([
            'success' => empty($calc['promo_error']),
            'error' => $calc['promo_error'],
            'pricing' => $calc,
        ]);
        exit;
    }

    if ($action === 'get_month_matrix') {
        require_once __DIR__ . '/../config/db.php';

        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
        $roomType = $_GET['room_type'] ?? 'any';

        $startOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $daysInMonth = (int)date('t', strtotime($startOfMonth));
        $endOfMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // Get total physical rooms for this filter
        $roomWhere = "status <> 'maintenance'";
        if ($roomType !== 'any') {
            $roomWhere .= " AND type = '" . $conn->real_escape_string($roomType) . "'";
        }
        $totalRoomCap = (int)($conn->query("SELECT COUNT(*) as cnt FROM rooms WHERE {$roomWhere}")->fetch_assoc()['cnt'] ?? 0);

        // Fetch overlapping active bookings
        $bWhere = "status NOT IN ('Cancelled', 'Checked Out') AND check_in <= '{$endOfMonth}' AND check_out >= '{$startOfMonth}'";
        if ($roomType !== 'any') {
            $bWhere .= " AND (room_id IN (SELECT id FROM rooms WHERE type = '" . $conn->real_escape_string($roomType) . "') OR LOWER(accommodation_name) LIKE '%" . $conn->real_escape_string($roomType) . "%')";
        }
        $bRes = $conn->query("SELECT check_in, check_out FROM bookings WHERE {$bWhere}");
        $activeBookings = $bRes ? $bRes->fetch_all(MYSQLI_ASSOC) : [];

        $daysData = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $occupiedCount = 0;
            foreach ($activeBookings as $b) {
                if ($dateStr >= $b['check_in'] && $dateStr < $b['check_out']) {
                    $occupiedCount++;
                }
            }
            $avail = max(0, $totalRoomCap - $occupiedCount);
            $status = 'available';
            if ($avail === 0) {
                $status = 'sold_out';
            } elseif ($avail <= 2) {
                $status = 'low_stock';
            }

            $daysData[$dateStr] = [
                'date' => $dateStr,
                'day' => $d,
                'available' => $avail,
                'total' => $totalRoomCap,
                'status' => $status,
                'is_past' => ($dateStr < date('Y-m-d')),
            ];
        }

        echo json_encode([
            'success' => true,
            'year' => $year,
            'month' => $month,
            'total_rooms' => $totalRoomCap,
            'days' => $daysData,
        ]);
        exit;
    }

    if ($action === 'export_report_csv') {
        require_once __DIR__ . '/../config/db.php';
        require_once __DIR__ . '/../helpers/admin_auth_check.php';

        $type = $_GET['type'] ?? 'bookings';
        $filename = "santafe_{$type}_report_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');

        if ($type === 'payments') {
            fputcsv($out, ['Payment ID', 'Booking Ref', 'Guest Name', 'Email', 'Amount (PHP)', 'Payment Method', 'Transaction Ref', 'Status', 'Date Paid']);
            $res = $conn->query("
                SELECT p.id, CONCAT('REF-', LPAD(p.booking_id, 3, '0')) AS booking_ref, p.guest_name, p.guest_email, p.amount, p.payment_method, p.transaction_id, p.status, p.paid_at 
                FROM payments p 
                ORDER BY p.id DESC
            ");
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, $row);
            }
        } else {
            fputcsv($out, ['Booking ID', 'Booking Ref', 'Guest Name', 'Guest Type', 'Email', 'Phone', 'Room / Accommodation', 'Check-in', 'Check-out', 'Guests', 'Status', 'Promo Code', 'Discount (PHP)', 'Booked On']);
            $res = $conn->query("
                SELECT b.id, CONCAT('REF-', LPAD(b.id, 3, '0')) AS booking_ref, b.guest_name, b.guest_type, b.guest_email, b.guest_phone, b.accommodation_name, b.check_in, b.check_out, b.guests_count, b.status, b.promo_code, b.discount_amount, b.created_at 
                FROM bookings b 
                ORDER BY b.id DESC
            ");
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, $row);
            }
        }

        fclose($out);
        exit;
    }

    $roomTypeId = isset($_GET['room_type_id']) ? (int) $_GET['room_type_id'] : 0;
    $checkIn = $_GET['check_in'] ?? '';
    $checkOut = $_GET['check_out'] ?? '';

    if ($roomTypeId <= 0 || $checkIn === '' || $checkOut === '') {
        http_response_code(400);
        echo json_encode(['error' => 'room_type_id, check_in, and check_out are required.']);
        exit;
    }

    if (!isValidDateRange($checkIn, $checkOut)) {
        http_response_code(400);
        echo json_encode(['error' => 'check_out must be later than check_in.']);
        exit;
    }

    $pdo = getPdoConnection();
    $available = getAvailableRooms($pdo, $roomTypeId, $checkIn, $checkOut);

    echo json_encode([
        'available' => $available,
        'low_stock' => $available >= 1 && $available <= 3,
        'sold_out' => $available === 0,
    ]);
} catch (Throwable $e) {
    error_log('Availability API failure: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Availability could not be checked right now. Please try again.']);
}
