<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/room_status_helper.php';
require_once __DIR__ . '/../backend/services/chatbot_tools.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';

function assertStaffCheck(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "=== Staff Operations Assistant vs System Dashboard Alignment Tests ===\n";

// Clear any leftover transaction and isolate all synthetic test rows in a rolled-back transaction
$conn->begin_transaction();

try {
    $today = sf_staff_current_date($conn);
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
    $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
    $twoDays = date('Y-m-d', strtotime($today . ' +2 days'));

    // Pick 4 distinct ready/Available rooms
    $roomRows = $conn->query("SELECT id, room_number, name, type FROM rooms WHERE LOWER(COALESCE(status, '')) NOT IN ('maintenance', 'out of order') ORDER BY id ASC LIMIT 4")->fetch_all(MYSQLI_ASSOC);
    assertStaffCheck(count($roomRows) === 4, 'Found 4 usable physical rooms for isolated transaction tests');

    // Insert 3 bookings:
    // 1) Overdue Pending arrival overlapping today (check_in = yesterday, check_out = +2d)
    // 2) Confirmed arrival today (check_in = today, check_out = +1d)
    // 3) Checked In guest staying through tomorrow (check_in = yesterday, check_out = +1d)
    $insB = $conn->prepare("INSERT INTO bookings (guest_name, guest_email, guest_type, check_in, check_out, guests_count, room_id, accommodation_name, eta, status) VALUES (?, ?, 'First Visit', ?, ?, ?, ?, ?, '14:00', ?)");
    $g1 = 'Dash Test Pending Overlap'; $e1 = 'd1@example.com'; $cnt1 = 2; $r1 = (int)$roomRows[0]['id']; $a1 = $roomRows[0]['name']; $s1 = 'Pending';
    $insB->bind_param('ssssiiss', $g1, $e1, $yesterday, $twoDays, $cnt1, $r1, $a1, $s1);
    $insB->execute();

    $g2 = 'Dash Test Arrival Today'; $e2 = 'd2@example.com'; $cnt2 = 3; $r2 = (int)$roomRows[1]['id']; $a2 = $roomRows[1]['name']; $s2 = 'Confirmed';
    $insB->bind_param('ssssiiss', $g2, $e2, $today, $tomorrow, $cnt2, $r2, $a2, $s2);
    $insB->execute();

    $g3 = 'Dash Test Checked In'; $e3 = 'd3@example.com'; $cnt3 = 2; $r3 = (int)$roomRows[2]['id']; $a3 = $roomRows[2]['name']; $s3 = 'Checked In';
    $insB->bind_param('ssssiiss', $g3, $e3, $yesterday, $tomorrow, $cnt3, $r3, $a3, $s3);
    $insB->execute();
    $b3Id = (int)$conn->insert_id;
    $insB->close();

    // Mark room 4 in maintenance
    $maintRoomId = (int)$roomRows[3]['id'];
    $conn->query("UPDATE rooms SET status = 'maintenance' WHERE id = {$maintRoomId}");

    // Compute ground-truth System Dashboard metrics directly using dashboard SQL/JS rules
    $totalRooms = (int)$conn->query("SELECT COUNT(*) FROM rooms")->fetch_row()[0];
    $occSql = $conn->prepare("
        SELECT DISTINCT room_id FROM bookings
        WHERE room_id IS NOT NULL
          AND LOWER(COALESCE(status, '')) NOT IN ('cancelled', 'canceled', 'checked out')
          AND DATE(check_in) <= ? AND DATE(check_out) > ?
    ");
    $occSql->bind_param('ss', $today, $today);
    $occSql->execute();
    $dashOccRooms = $occSql->get_result()->num_rows;
    $occSql->close();

    $dashOccPctInt = (int)round(($dashOccRooms / $totalRooms) * 100);
    $dashCheckedInRooms = count(sf_get_checked_in_room_ids($conn));
    $dashCheckedInPct = round(($dashCheckedInRooms / $totalRooms) * 100, 1);

    $arrSql = $conn->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE room_id IS NOT NULL
          AND LOWER(COALESCE(status, '')) NOT IN ('cancelled', 'canceled', 'checked out')
          AND DATE(check_in) = ?
    ");
    $arrSql->bind_param('s', $today);
    $arrSql->execute();
    $dashArrivalsToday = (int)$arrSql->get_result()->fetch_row()[0];
    $arrSql->close();

    $maintRooms = (int)$conn->query("SELECT COUNT(*) FROM rooms WHERE LOWER(COALESCE(status, '')) IN ('maintenance', 'out of order')")->fetch_row()[0];
    $expectedAvailable = $totalRooms - $maintRooms - $dashOccRooms; // roomRows[3] is maintenance and unbooked

    // 1. Test "how many occupied today? how many percent"
    $occRes = sf_staff_assistant_send_message($conn, 'how many occupied today? how many percent', 'admin', 'admin@santafebeachclub.com');
    assertStaffCheck(($occRes['tool_used'] ?? '') === 'occupancy_summary', '"how many occupied today? how many percent" routes to occupancy_summary');
    $occData = sf_staff_tool_occupancy_summary($conn, $today, $tomorrow);
    assertStaffCheck($occData['booked_or_occupied_units'] === $dashOccRooms, "Occupied units ({$occData['booked_or_occupied_units']}) matches dashboard Occupied Today ({$dashOccRooms})");
    assertStaffCheck($occData['dashboard_occupied_today_percent'] === $dashOccPctInt, "Dashboard Occupied Today % ({$occData['dashboard_occupied_today_percent']}%) matches JS round ({$dashOccPctInt}%)");
    assertStaffCheck($occData['checked_in_occupancy_percent'] === $dashCheckedInPct, "Checked-in Occupancy Rate ({$occData['checked_in_occupancy_percent']}%) matches analytics_api ({$dashCheckedInPct}%)");
    assertStaffCheck($occData['available_units'] === $expectedAvailable, "Units Available ({$occData['available_units']}) matches dashboard readyCount ({$expectedAvailable})");
    assertStaffCheck(str_contains($occRes['reply'], "{$dashOccRooms} of {$totalRooms} total rooms"), 'Occupancy reply cites exact occupied/total room count');
    assertStaffCheck(str_contains($occRes['reply'], "{$dashOccPctInt}%"), 'Occupancy reply includes exact dashboard percentage');

    // 2. Test "which rooms are occupied today?" does not get hijacked by rooms_and_promotions
    $whichOcc = sf_staff_assistant_send_message($conn, 'which rooms are occupied today?', 'admin', 'admin@santafebeachclub.com');
    assertStaffCheck(($whichOcc['tool_used'] ?? '') === 'occupancy_summary', '"which rooms are occupied today?" routes to occupancy_summary');

    // 3. Test "arrivals today"
    $arrRes = sf_staff_assistant_send_message($conn, 'arrivals today', 'admin', 'admin@santafebeachclub.com');
    assertStaffCheck(($arrRes['tool_used'] ?? '') === 'today_operations', '"arrivals today" routes to today_operations');
    $opsData = sf_staff_tool_today_operations($conn, $today, 'admin');
    assertStaffCheck($opsData['arrivals_count'] === $dashArrivalsToday, "Arrivals count ({$opsData['arrivals_count']}) matches dashboard Arrivals Today ({$dashArrivalsToday})");
    assertStaffCheck($opsData['overdue_arrivals_count'] >= 1, 'Detects overdue unchecked-in arrival from yesterday');
    assertStaffCheck(str_contains($arrRes['reply'], 'Dash Test Arrival Today'), 'Arrivals reply lists guest arriving today');
    assertStaffCheck(str_contains($arrRes['reply'], "{$dashOccRooms} of {$totalRooms} rooms"), 'Front desk reply includes dashboard Occupied Today snapshot');

    // 4. Test unassigned Beach Villa booking does NOT double-count into 'villa'
    $beforeOcc = sf_staff_tool_occupancy_summary($conn, $today, $tomorrow);
    $insUn = $conn->prepare("INSERT INTO bookings (guest_name, guest_email, guest_type, room_type_id, check_in, check_out, guests_count, room_id, accommodation_name, eta, status) VALUES ('Unassigned Villa Test', 'uv@example.com', 'First Visit', 2, ?, ?, 2, NULL, 'Beach Villa 103', '14:00', 'Pending')");
    $insUn->bind_param('ss', $today, $tomorrow);
    $insUn->execute();
    $insUn->close();
    $afterOcc = sf_staff_tool_occupancy_summary($conn, $today, $tomorrow);
    assertStaffCheck($afterOcc['booked_or_occupied_units'] === $beforeOcc['booked_or_occupied_units'] + 1, 'Unassigned Beach Villa booking increments occupied units by exactly 1 (no beach_villa + villa double-count)');

    // 5. Test Daily Revenue vs Weekly Revenue calendar-day boundary
    $todayMid = $today . ' 00:05:00';
    $yestLate = $yesterday . ' 23:30:00';
    $insP = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, paid_at) VALUES (?, 'Dash Test Checked In', 'd3@example.com', ?, 'GCash QR', ?, 'verified', ?)");
    $amtToday = 2500.00; $txn1 = 'TEST-TODAY-' . uniqid();
    $insP->bind_param('idss', $b3Id, $amtToday, $txn1, $todayMid);
    $insP->execute();
    $amtYest = 4000.00; $txn2 = 'TEST-YEST-' . uniqid();
    $insP->bind_param('idss', $b3Id, $amtYest, $txn2, $yestLate);
    $insP->execute();
    $insP->close();

    $expectedDaily = (float)$conn->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified' AND DATE(paid_at) = CURDATE()")->fetch_row()[0];
    $expectedWeekly = (float)$conn->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetch_row()[0];

    $dailyRevRes = sf_staff_assistant_send_message($conn, 'daily revenue', 'admin', 'admin@santafebeachclub.com');
    assertStaffCheck(($dailyRevRes['tool_used'] ?? '') === 'revenue_summary', '"daily revenue" routes to revenue_summary');
    $dailyRevTool = sf_staff_tool_revenue_summary($conn, 'admin', 1);
    assertStaffCheck(abs($dailyRevTool['verified_revenue_php'] - $expectedDaily) < 0.01, "Daily revenue ({$dailyRevTool['verified_revenue_php']}) matches dashboard CURDATE() revenue ({$expectedDaily})");
    assertStaffCheck(abs($dailyRevTool['weekly_revenue_php'] - $expectedWeekly) < 0.01, "Weekly revenue ({$dailyRevTool['weekly_revenue_php']}) matches dashboard 7-day revenue ({$expectedWeekly})");

    // 6. Test RBAC enforcement for Receptionist on revenue and dashboard summary
    $recRev = sf_staff_assistant_send_message($conn, 'daily revenue', 'receptionist', 'reception@santafebeachclub.com');
    assertStaffCheck(str_contains($recRev['reply'], 'Access Restricted'), 'Receptionist is blocked from revenue summary');
    $recDash = sf_staff_assistant_send_message($conn, 'dashboard summary', 'receptionist', 'reception@santafebeachclub.com');
    assertStaffCheck(($recDash['tool_used'] ?? '') === 'dashboard_summary' && !str_contains($recDash['reply'], 'Daily Revenue'), 'Receptionist dashboard summary excludes financial revenue totals');

    // 7. Test single-date extraction
    $singleDateRes = sf_staff_assistant_send_message($conn, 'arrivals on 2026-12-25', 'admin', 'admin@santafebeachclub.com');
    assertStaffCheck(($singleDateRes['tool_used'] ?? '') === 'today_operations' && str_contains($singleDateRes['reply'], '2026-12-25'), 'Single ISO date "arrivals on 2026-12-25" extracts 2026-12-25');

    // 8. Test percent promo formatting
    $pFrom = $yesterday; $pUntil = $twoDays;
    $conn->query("INSERT INTO promotions (title, code, discount_type, discount_value, valid_from, valid_until, is_active) VALUES ('Dash Percent Promo', 'TEST20', 'percent', 20.00, '{$pFrom}', '{$pUntil}', 1)");
    $promoRes = sf_staff_assistant_send_message($conn, 'rooms and promos', 'admin', 'admin@santafebeachclub.com');
    assertStaffCheck(str_contains($promoRes['reply'], '20% off') && !str_contains($promoRes['reply'], '₱20.00 off'), 'Percent promo renders as "20% off" instead of "₱20.00 off"');

    // 9. Widget safe italic formatting check
    $widgetJs = (string)file_get_contents(__DIR__ . '/../frontend/assets/js/components/staff-assistant-widget.js');
    assertStaffCheck(str_contains($widgetJs, '<em>$2</em>'), 'Staff assistant widget renders *italic* safely without literal asterisks');

    echo "\nALL STAFF ASSISTANT DASHBOARD ALIGNMENT TESTS PASSED!\n";
} finally {
    $conn->rollback();
}
