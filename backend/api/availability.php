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

        // Fetch physical rooms for this filter to compute availability and daily rates
        $roomWhere = "status <> 'maintenance'";
        if ($roomType !== 'any') {
            $roomWhere .= " AND type = '" . $conn->real_escape_string($roomType) . "'";
        }
        $roomsRes = $conn->query("SELECT id, type, price_per_night FROM rooms WHERE {$roomWhere}");
        $matchingRooms = $roomsRes ? $roomsRes->fetch_all(MYSQLI_ASSOC) : [];
        $totalRoomCap = count($matchingRooms);

        $roomIdToType = [];
        $typeCap = [];
        $typeBaseRate = [];
        foreach ($matchingRooms as $rm) {
            $rmId = (int)$rm['id'];
            $rmType = (string)$rm['type'];
            $rmPrice = (float)$rm['price_per_night'];
            $roomIdToType[$rmId] = $rmType;
            $typeCap[$rmType] = ($typeCap[$rmType] ?? 0) + 1;
            if (!isset($typeBaseRate[$rmType]) || $rmPrice < $typeBaseRate[$rmType]) {
                $typeBaseRate[$rmType] = $rmPrice;
            }
        }

        // Map room_type_id -> room type slug if table is available
        $rtIdToName = [];
        try {
            $rtRes = @$conn->query("SELECT id, name FROM room_types");
            if ($rtRes) {
                while ($rtRow = $rtRes->fetch_assoc()) {
                    $rtIdToName[(int)$rtRow['id']] = (string)$rtRow['name'];
                }
            }
        } catch (Throwable $ignore) {
            $rtIdToName = [];
        }

        // Fetch active pricing rules (weekend / date_range) if available
        $activeRules = [];
        try {
            $rulesRes = @$conn->query("SELECT title, rule_type, room_type, days_of_week, start_date, end_date, adjustment_type, adjustment_value FROM pricing_rules WHERE is_active = 1");
            if ($rulesRes) {
                $activeRules = $rulesRes->fetch_all(MYSQLI_ASSOC);
            }
        } catch (Throwable $ignore) {
            $activeRules = [];
        }

        // Fetch overlapping active bookings
        $bWhere = "status NOT IN ('Cancelled', 'Checked Out') AND check_in <= '{$endOfMonth}' AND check_out >= '{$startOfMonth}'";
        if ($roomType !== 'any') {
            $safeTypeEsc = $conn->real_escape_string($roomType);
            $bWhere .= " AND (room_id IN (SELECT id FROM rooms WHERE type = '{$safeTypeEsc}') OR LOWER(accommodation_name) LIKE '%{$safeTypeEsc}%')";
        }
        $bRes = $conn->query("SELECT room_id, room_type_id, accommodation_name, check_in, check_out FROM bookings WHERE {$bWhere}");
        $activeBookings = $bRes ? $bRes->fetch_all(MYSQLI_ASSOC) : [];

        $todayStr = date('Y-m-d');
        $daysData = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $dayOfWeek = (int)date('w', strtotime($dateStr));
            $occupiedCount = 0;
            $typeOccupied = [];

            foreach ($activeBookings as $b) {
                if ($dateStr >= $b['check_in'] && $dateStr < $b['check_out']) {
                    $occupiedCount++;
                    $bRoomId = isset($b['room_id']) ? (int)$b['room_id'] : 0;
                    $bType = null;
                    if ($bRoomId > 0 && isset($roomIdToType[$bRoomId])) {
                        $bType = $roomIdToType[$bRoomId];
                    } elseif (!empty($b['room_type_id']) && isset($rtIdToName[(int)$b['room_type_id']])) {
                        $bType = $rtIdToName[(int)$b['room_type_id']];
                    } else {
                        $accLow = strtolower((string)($b['accommodation_name'] ?? ''));
                        foreach (array_keys($typeCap) as $candidateType) {
                            if ($accLow !== '' && strpos($accLow, str_replace('_', ' ', $candidateType)) !== false) {
                                $bType = $candidateType;
                                break;
                            }
                        }
                    }
                    if ($bType !== null) {
                        $typeOccupied[$bType] = ($typeOccupied[$bType] ?? 0) + 1;
                    }
                }
            }

            $avail = max(0, $totalRoomCap - $occupiedCount);
            $status = 'available';
            if ($avail === 0) {
                $status = 'sold_out';
            } elseif ($avail <= 2) {
                $status = 'low_stock';
            }

            $availRates = [];
            $allRates = [];
            $dayNotes = [];
            foreach ($typeBaseRate as $tKey => $basePrice) {
                $nightRate = $basePrice;
                foreach ($activeRules as $rule) {
                    $ruleTarget = (string)($rule['room_type'] ?? 'all');
                    if ($ruleTarget !== 'all' && $ruleTarget !== $tKey) {
                        continue;
                    }
                    $matchesRule = false;
                    if ($rule['rule_type'] === 'weekend') {
                        $daysList = array_map('intval', explode(',', (string)($rule['days_of_week'] ?? '5,6,0')));
                        $matchesRule = in_array($dayOfWeek, $daysList, true);
                    } elseif ($rule['rule_type'] === 'date_range') {
                        $rStart = (string)($rule['start_date'] ?? '');
                        $rEnd = (string)($rule['end_date'] ?? '');
                        $matchesRule = ($rStart !== '' && $rEnd !== '' && $dateStr >= $rStart && $dateStr <= $rEnd);
                    }
                    if ($matchesRule) {
                        $adj = (float)($rule['adjustment_value'] ?? 0);
                        $diff = (($rule['adjustment_type'] ?? '') === 'percent')
                            ? ($basePrice * ($adj / 100))
                            : $adj;
                        $nightRate += $diff;
                        if (!empty($rule['title'])) {
                            $dayNotes[(string)$rule['title']] = true;
                        }
                    }
                }
                $nightRate = max(0.0, $nightRate);
                $allRates[$tKey] = $nightRate;
                $tAvail = max(0, ($typeCap[$tKey] ?? 0) - ($typeOccupied[$tKey] ?? 0));
                if ($tAvail > 0) {
                    $availRates[$tKey] = $nightRate;
                }
            }

            $dayMinRate = !empty($availRates) ? min($availRates) : (!empty($allRates) ? min($allRates) : 0);
            $dayMaxRate = !empty($availRates) ? max($availRates) : (!empty($allRates) ? max($allRates) : 0);

            $daysData[$dateStr] = [
                'date' => $dateStr,
                'day' => $d,
                'available' => $avail,
                'total' => $totalRoomCap,
                'status' => $status,
                'is_past' => ($dateStr < $todayStr),
                'min_rate' => round($dayMinRate),
                'max_rate' => round($dayMaxRate),
                'has_dynamic_rate' => !empty($dayNotes),
                'rate_note' => !empty($dayNotes) ? implode(', ', array_keys($dayNotes)) : '',
            ];
        }

        echo json_encode([
            'success' => true,
            'year' => $year,
            'month' => $month,
            'room_type' => $roomType,
            'total_rooms' => $totalRoomCap,
            'base_min_rate' => !empty($typeBaseRate) ? round(min($typeBaseRate)) : 0,
            'is_rate_prefix' => ($roomType === 'any' && count($typeBaseRate) > 1),
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
