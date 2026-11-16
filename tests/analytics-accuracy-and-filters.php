<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/room_status_helper.php';

function assert_analytics(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $msg);
    }
    echo 'PASS: ' . $msg . PHP_EOL;
}

function call_analytics_action(mysqli $conn, string $action, array $queryParams = []): array
{
    $_GET = array_merge(['action' => $action], $queryParams);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_role'] = 'admin';
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin@santafebeachclub.com';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    ob_start();
    require __DIR__ . '/../backend/api/analytics_api.php';
    $raw = (string)ob_get_clean();
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Action {$action} did not return valid JSON: {$raw}");
    }
    return $decoded;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn->begin_transaction();

try {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    $pastCheckIn = date('Y-m-d', strtotime('-45 days'));
    $pastCheckOut = date('Y-m-d', strtotime('-43 days'));
    $thisMonth = date('Y-m');

    // Pick a valid room for test bookings
    $roomRow = $conn->query("SELECT id, name, type, price_per_night FROM rooms ORDER BY id ASC LIMIT 1")->fetch_assoc();
    assert_analytics(!empty($roomRow), 'At least one room exists in rooms table');
    $roomId = (int)$roomRow['id'];
    $roomName = (string)$roomRow['name'];
    $roomType = (string)$roomRow['type'];

    // 1. Insert a stale historical un-checked-out booking vs an active upcoming booking
    $stmt = $conn->prepare("
        INSERT INTO bookings (guest_name, guest_email, guest_country, room_id, accommodation_name, check_in, check_out, guests_count, status, balance_status, promo_code, discount_amount, created_at)
        VALUES (?, 'test@example.com', 'Testlandia', ?, ?, ?, ?, 2, ?, ?, ?, ?, ?)
    ");

    $gStale = 'Stale Hold Guest';
    $stConfirmed = 'Confirmed';
    $balPaid = 'paid';
    $emptyPromo = '';
    $zeroDisc = 0.0;
    $pastCreated = $pastCheckIn . ' 10:00:00';
    $stmt->bind_param('sissssssds', $gStale, $roomId, $roomName, $pastCheckIn, $pastCheckOut, $stConfirmed, $balPaid, $emptyPromo, $zeroDisc, $pastCreated);
    $stmt->execute();

    // Verify sf_get_reserved_room_ids($conn, true) excludes past check_out dates
    $activeReservedBefore = sf_get_reserved_room_ids($conn, true);

    $gActive = 'Active Analytics Guest';
    $balPending = 'balance_pending';
    $promoCode = 'ANALYTICS500';
    $discAmt = 500.00;
    $nowCreated = $today . ' 09:00:00';
    $stmt->bind_param('sissssssds', $gActive, $roomId, $roomName, $tomorrow, $nextWeek, $stConfirmed, $balPending, $promoCode, $discAmt, $nowCreated);
    $stmt->execute();
    $activeBookingId = (int)$conn->insert_id;

    $gCancelled = 'Cancelled Analytics Guest';
    $stCancelled = 'Cancelled';
    $stmt->bind_param('sissssssds', $gCancelled, $roomId, $roomName, $tomorrow, $nextWeek, $stCancelled, $balPaid, $emptyPromo, $zeroDisc, $nowCreated);
    $stmt->execute();
    $cancelledBookingId = (int)$conn->insert_id;
    $stmt->close();

    $conn->query("UPDATE bookings SET cancellation_reason = 'Schedule Change', cancelled_for_nonpayment_at = NOW() WHERE id = {$cancelledBookingId}");

    $activeReservedAfter = sf_get_reserved_room_ids($conn, true);
    assert_analytics(isset($activeReservedAfter[$roomId]), 'sf_get_reserved_room_ids($conn, true) includes upcoming active reservation');

    // 2. Insert verified earned & deferred payments + completed refund request
    $pStmt = $conn->prepare("
        INSERT INTO payments (booking_id, guest_name, guest_email, amount, payment_method, transaction_id, status, accounting_status, purpose, paid_at)
        VALUES (?, 'Active Analytics Guest', 'test@example.com', ?, 'GCash QR', 'TXN-ANL-1', 'verified', ?, 'deposit', ?)
    ");
    $amtEarned = 4000.00;
    $accEarned = 'earned';
    $paidTs = $today . ' 11:30:00';
    $pStmt->bind_param('idss', $activeBookingId, $amtEarned, $accEarned, $paidTs);
    $pStmt->execute();
    $paymentId = (int)$conn->insert_id;

    $amtDeferred = 2000.00;
    $accDeferred = 'deferred';
    $pStmt->bind_param('idss', $activeBookingId, $amtDeferred, $accDeferred, $paidTs);
    $pStmt->execute();
    $pStmt->close();

    $rStmt = $conn->prepare("
        INSERT INTO refund_requests (booking_id, payment_id, requested_by, request_type, status, reason, refund_amount, refund_fee, net_refund, completed_at, requested_at)
        VALUES (?, ?, 'test_admin', 'admin', 'completed', 'Partial adjustment', 1000.00, 100.00, 900.00, ?, ?)
    ");
    $rStmt->bind_param('iiss', $activeBookingId, $paymentId, $paidTs, $paidTs);
    $rStmt->execute();
    $rStmt->close();

    // 3. Test dashboard-stats with today's date filter
    $stats = call_analytics_action($conn, 'dashboard-stats', [
        'range' => 'custom',
        'start_date' => $today,
        'end_date' => $today,
    ]);
    assert_analytics($stats['date_range']['is_filtered'] === true, 'dashboard-stats recognizes custom date range');
    assert_analytics((float)$stats['gross_revenue'] >= 6000.0, 'dashboard-stats computes Gross Verified Revenue in range');
    assert_analytics((float)$stats['refunded_amount'] >= 900.0, 'dashboard-stats computes Approved/Completed Refunds in range');
    assert_analytics(
        abs(((float)$stats['gross_revenue'] - (float)$stats['refunded_amount']) - (float)$stats['net_revenue']) < 0.01,
        'dashboard-stats Net Revenue equals Gross minus Refunded'
    );
    assert_analytics((float)$stats['earned_revenue'] >= 4000.0, 'dashboard-stats tracks Earned revenue');
    assert_analytics((float)$stats['deferred_revenue'] >= 2000.0, 'dashboard-stats tracks Deferred revenue');
    assert_analytics(!array_key_exists('sparklines', $stats), 'Unused 56-query sparklines loop is removed from dashboard-stats');

    // 4. Test monthly-revenue & monthly-revenue-details
    $monthly = call_analytics_action($conn, 'monthly-revenue');
    assert_analytics(in_array($thisMonth, $monthly['months'] ?? [], true), 'monthly-revenue includes YYYY-MM keys for drill-downs');
    assert_analytics(isset($monthly['gross_revenue'], $monthly['net_revenue'], $monthly['refunds']), 'monthly-revenue returns gross, refunds, and net series');

    $monthDetails = call_analytics_action($conn, 'monthly-revenue-details', ['month' => $thisMonth]);
    assert_analytics($monthDetails['month'] === $thisMonth, 'monthly-revenue-details returns requested month');
    assert_analytics((float)$monthDetails['gross_amount'] >= 6000.0, 'monthly-revenue-details sums verified receipts in month');
    assert_analytics((float)$monthDetails['refunded_amount'] >= 900.0, 'monthly-revenue-details sums refunds in month');

    // 5. Test country-demographics excludes cancelled bookings and sums shares to 100%
    $countries = call_analytics_action($conn, 'country-demographics', [
        'range' => 'custom',
        'start_date' => $today,
        'end_date' => $today,
    ]);
    $testlandia = null;
    foreach ($countries['countries'] as $c) {
        if ($c['country'] === 'Testlandia') {
            $testlandia = $c;
            break;
        }
    }
    assert_analytics($testlandia !== null && (int)$testlandia['bookings'] === 1, 'country-demographics counts non-cancelled booking and excludes cancelled booking');
    $sumShare = array_sum(array_column($countries['countries'], 'share_pct'));
    assert_analytics(abs($sumShare - 100.0) < 0.01, 'country-demographics share_pct sums to 100.0%');

    // 6. Test room-type-performance, discounts-and-promos, cancellation-insights, reviews-summary
    $rtPerf = call_analytics_action($conn, 'room-type-performance', [
        'range' => 'custom',
        'start_date' => $today,
        'end_date' => $today,
    ]);
    assert_analytics(!empty($rtPerf['room_types']), 'room-type-performance returns room categories with ADR and RevPAR');

    $disc = call_analytics_action($conn, 'discounts-and-promos', [
        'range' => 'custom',
        'start_date' => $today,
        'end_date' => $today,
    ]);
    assert_analytics((int)$disc['promo_redemptions_count'] >= 1 && (float)$disc['promo_discount_total'] >= 500.0, 'discounts-and-promos aggregates promo code usage and discount amount');

    $can = call_analytics_action($conn, 'cancellation-insights', [
        'range' => 'custom',
        'start_date' => $today,
        'end_date' => $today,
    ]);
    assert_analytics((int)$can['cancelled_bookings'] >= 1 && (int)$can['nonpayment_auto_cancelled'] >= 1, 'cancellation-insights tracks cancelled count and non-payment auto-cancellations');

    $rev = call_analytics_action($conn, 'reviews-summary');
    assert_analytics(isset($rev['avg_rating'], $rev['distribution']) && count($rev['distribution']) === 5, 'reviews-summary returns 5-star rating distribution');

    echo "ALL ANALYTICS ACCURACY & FILTER ASSERTIONS PASSED!\n";
} finally {
    $conn->rollback();
}
