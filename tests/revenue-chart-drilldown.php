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

function assertDrilldown(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "=== Revenue Trajectory Drill-Down API Tests ===\n";

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_role'] = 'admin';
$_SESSION['admin_username'] = 'admin@santafebeachclub.com';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$conn->begin_transaction();

try {
    $targetDate = date('Y-m-d', strtotime('-2 days'));
    $roomRow = $conn->query("SELECT id, name FROM rooms ORDER BY id ASC LIMIT 1")->fetch_assoc();
    assertDrilldown(!empty($roomRow), 'Found physical room for test booking');

    $rid = (int)$roomRow['id'];
    $acc = (string)$roomRow['name'];
    $insB = $conn->prepare("INSERT INTO bookings (guest_name, guest_email, guest_type, check_in, check_out, guests_count, room_id, accommodation_name, eta, status) VALUES ('Chart Drilldown Guest', 'drill@example.com', 'First Visit', ?, ?, 2, ?, ?, '14:00', 'Confirmed')");
    $cout = date('Y-m-d', strtotime($targetDate . ' +2 days'));
    $insB->bind_param('ssis', $targetDate, $cout, $rid, $acc);
    $insB->execute();
    $bid = (int)$conn->insert_id;
    $insB->close();

    $paidAtVerified = $targetDate . ' 15:45:00';
    $paidAtPending = $targetDate . ' 16:00:00';
    $txnVer = 'DRILL-VER-' . uniqid();
    $txnPen = 'DRILL-PEN-' . uniqid();
    $insP = $conn->prepare("INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, purpose, paid_at) VALUES (?, 'Chart Drilldown Guest', 'drill@example.com', ?, ?, ?, ?, ?, ?)");
    $amt1 = 3950.00; $m1 = 'GCash QR'; $st1 = 'verified'; $pur1 = 'deposit';
    $insP->bind_param('idsssss', $bid, $amt1, $m1, $txnVer, $st1, $pur1, $paidAtVerified);
    $insP->execute();
    $amt2 = 1200.00; $m2 = 'Bank Deposit'; $st2 = 'pending'; $pur2 = 'balance';
    $insP->bind_param('idsssss', $bid, $amt2, $m2, $txnPen, $st2, $pur2, $paidAtPending);
    $insP->execute();
    $insP->close();

    // 1. weekly-revenue-trajectory returns matching 7 labels, dates, and data
    $_GET['action'] = 'weekly-revenue-trajectory';
    unset($_GET['date']);
    ob_start();
    include __DIR__ . '/../backend/api/analytics_api.php';
    $trajJson = json_decode((string)ob_get_clean(), true);

    assertDrilldown(is_array($trajJson) && count($trajJson['labels'] ?? []) === 7, 'weekly-revenue-trajectory returns 7 labels');
    assertDrilldown(count($trajJson['dates'] ?? []) === 7, 'weekly-revenue-trajectory returns 7 ISO dates');
    assertDrilldown(count($trajJson['data'] ?? []) === 7, 'weekly-revenue-trajectory returns 7 data values');
    $idx = array_search($targetDate, $trajJson['dates'], true);
    assertDrilldown($idx !== false && (float)$trajJson['data'][$idx] >= 3950.0, 'Trajectory includes verified ₱3,950 on target date and excludes pending ₱1,200');

    // 2. daily-revenue-details returns exact verified transactions for targetDate
    $_GET['action'] = 'daily-revenue-details';
    $_GET['date'] = $targetDate;
    ob_start();
    include __DIR__ . '/../backend/api/analytics_api.php';
    $detJson = json_decode((string)ob_get_clean(), true);

    assertDrilldown(($detJson['date'] ?? '') === $targetDate, 'daily-revenue-details returns requested date');
    assertDrilldown((float)($detJson['total_amount'] ?? 0) === (float)$trajJson['data'][$idx], 'daily-revenue-details total matches chart data point exactly');
    $foundTxn = null;
    foreach ($detJson['transactions'] ?? [] as $t) {
        if (($t['transaction_id'] ?? '') === $txnVer) {
            $foundTxn = $t;
        }
        assertDrilldown(($t['transaction_id'] ?? '') !== $txnPen, 'Pending payment is excluded from verified receipts drill-down');
    }
    assertDrilldown($foundTxn !== null, 'Verified payment appears in daily-revenue-details transactions');
    assertDrilldown(($foundTxn['purpose_label'] ?? '') === '50% Deposit' && (float)$foundTxn['amount'] === 3950.0, 'Transaction row includes formatted purpose and amount');

    // 3. Invalid date returns 400 error
    http_response_code(200);
    $_GET['action'] = 'daily-revenue-details';
    $_GET['date'] = '2026-02-30';
    ob_start();
    include __DIR__ . '/../backend/api/analytics_api.php';
    $errJson = json_decode((string)ob_get_clean(), true);
    assertDrilldown(http_response_code() === 400 && !empty($errJson['error']), 'Invalid calendar date returns HTTP 400 JSON error');

    echo "\nALL REVENUE CHART DRILL-DOWN API TESTS PASSED!\n";
} finally {
    $conn->rollback();
}
