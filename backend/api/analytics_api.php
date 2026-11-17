<?php
/**
 * analytics_api.php — Native PHP Analytics & Executive KPI API
 * Replaces external Flask dependency so the dashboard loads instantly from MySQL directly.
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/api_auth_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../helpers/room_status_helper.php';

require_api_auth($conn, 'admin');
RateLimiter::enforce($conn, 'analytics_api', 120, 60);

$action = $_GET['action'] ?? '';

if (!function_exists('sf_analytics_percent_change')) {
    function sf_analytics_percent_change(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.00001) {
            return abs($current) < 0.00001 ? 0.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}

if (!function_exists('sf_analytics_column_exists')) {
    function sf_analytics_column_exists(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = strtolower($table . '.' . $column);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeCol = $conn->real_escape_string($column);
        try {
            $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
            $exists = ($res && $res->num_rows > 0);
        } catch (Throwable $e) {
            $exists = false;
        }
        $cache[$key] = $exists;
        return $exists;
    }
}

if (!function_exists('sf_analytics_table_exists')) {
    function sf_analytics_table_exists(mysqli $conn, string $table): bool
    {
        static $cache = [];
        $key = strtolower($table);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $safeTable = $conn->real_escape_string($table);
        try {
            $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
            $exists = ($res && $res->num_rows > 0);
        } catch (Throwable $e) {
            $exists = false;
        }
        $cache[$key] = $exists;
        return $exists;
    }
}

if (!function_exists('sf_analytics_allocate_percentages')) {
    /**
     * Allocate part-of-whole percentages using Largest Remainder so displayed shares sum to exactly 100%.
     *
     * @param array<int, int|float> $counts
     * @return array<int, float>
     */
    function sf_analytics_allocate_percentages(array $counts, int $decimals = 1): array
    {
        $safeCounts = array_map(static fn($v): float => max(0.0, (float)$v), array_values($counts));
        $total = array_sum($safeCounts);
        if ($total <= 0.0) {
            return array_fill(0, count($safeCounts), 0.0);
        }

        $factor = 10 ** $decimals;
        $targetUnits = 100 * $factor;
        $items = [];
        $sumFloored = 0;
        foreach ($safeCounts as $idx => $count) {
            $exact = ($count * $targetUnits) / $total;
            $floored = (int)floor($exact + 1e-9);
            $sumFloored += $floored;
            $items[] = [
                'idx' => $idx,
                'count' => $count,
                'floored' => $floored,
                'remainder' => max(0.0, $exact - $floored),
            ];
        }

        $remaining = $targetUnits - $sumFloored;
        if ($remaining > 0) {
            $candidates = array_values(array_filter($items, static fn(array $item): bool => $item['count'] > 0));
            usort($candidates, static function (array $a, array $b): int {
                $cmp = $b['remainder'] <=> $a['remainder'];
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = $b['count'] <=> $a['count'];
                return $cmp !== 0 ? $cmp : ($a['idx'] <=> $b['idx']);
            });
            foreach ($candidates as $cand) {
                if ($remaining <= 0) {
                    break;
                }
                $items[$cand['idx']]['floored']++;
                $remaining--;
            }
        } elseif ($remaining < 0) {
            $candidates = array_values(array_filter($items, static fn(array $item): bool => $item['floored'] > 0));
            usort($candidates, static function (array $a, array $b): int {
                $cmp = $a['remainder'] <=> $b['remainder'];
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = $a['count'] <=> $b['count'];
                return $cmp !== 0 ? $cmp : ($b['idx'] <=> $a['idx']);
            });
            foreach ($candidates as $cand) {
                if ($remaining >= 0) {
                    break;
                }
                $items[$cand['idx']]['floored']--;
                $remaining++;
            }
        }

        return array_map(static fn(array $item): float => round($item['floored'] / $factor, $decimals), $items);
    }
}

if (!function_exists('sf_analytics_parse_date_range')) {
    /**
     * Parse optional analytics date range parameters (`range`, `start_date`, `end_date`).
     * Defaults to all-time totals with a 30-day period-over-period trend window when unfiltered.
     *
     * @param array<string, mixed> $params
     * @return array{
     *   is_filtered: bool,
     *   range_key: string,
     *   label: string,
     *   start_date: string,
     *   end_date: string,
     *   start_ts: string,
     *   end_exclusive_ts: string,
     *   period_days: int,
     *   prev_start_date: string,
     *   prev_end_date: string,
     *   prev_start_ts: string,
     *   prev_end_exclusive_ts: string
     * }
     */
    function sf_analytics_parse_date_range(array $params): array
    {
        $rawRange = strtolower(trim((string)($params['range'] ?? '')));
        $rawStart = trim((string)($params['start_date'] ?? ''));
        $rawEnd   = trim((string)($params['end_date'] ?? ''));

        $today = new DateTimeImmutable('today');
        $validateDate = static function (string $val): ?DateTimeImmutable {
            if ($val === '') {
                return null;
            }
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $val);
            $errs = DateTimeImmutable::getLastErrors();
            if (!$dt || ($errs !== false && ($errs['warning_count'] > 0 || $errs['error_count'] > 0)) || $dt->format('Y-m-d') !== $val) {
                return null;
            }
            return $dt;
        };

        $startDt = $validateDate($rawStart);
        $endDt   = $validateDate($rawEnd);
        $isFiltered = false;
        $rangeKey = 'all';
        $label = 'All Time';

        if ($startDt && $endDt && $startDt <= $endDt && $rawRange !== 'all') {
            $isFiltered = true;
            $rangeKey = $rawRange !== '' ? $rawRange : 'custom';
            $label = $startDt->format('M j, Y') . ' – ' . $endDt->format('M j, Y');
        } elseif ($rawRange === '7d') {
            $isFiltered = true;
            $rangeKey = '7d';
            $endDt = $today;
            $startDt = $today->modify('-6 days');
            $label = 'Last 7 Days';
        } elseif ($rawRange === '30d') {
            $isFiltered = true;
            $rangeKey = '30d';
            $endDt = $today;
            $startDt = $today->modify('-29 days');
            $label = 'Last 30 Days';
        } elseif ($rawRange === 'month') {
            $isFiltered = true;
            $rangeKey = 'month';
            $startDt = $today->modify('first day of this month');
            $endDt = $today;
            $label = 'This Month (' . $today->format('M Y') . ')';
        } elseif ($rawRange === '90d') {
            $isFiltered = true;
            $rangeKey = '90d';
            $endDt = $today;
            $startDt = $today->modify('-89 days');
            $label = 'Last 90 Days';
        } else {
            // Unfiltered ("All Time"): use latest 30 days vs preceding 30 days as the benchmark window
            $endDt = $today;
            $startDt = $today->modify('-29 days');
        }

        $periodDays = max(1, (int)$startDt->diff($endDt)->days + 1);
        $endExclusiveDt = $endDt->modify('+1 day');
        $prevEndExclusiveDt = $startDt;
        $prevStartDt = $startDt->modify('-' . $periodDays . ' days');
        $prevEndDt = $prevEndExclusiveDt->modify('-1 day');

        return [
            'is_filtered'           => $isFiltered,
            'range_key'             => $rangeKey,
            'label'                 => $label,
            'start_date'            => $startDt->format('Y-m-d'),
            'end_date'              => $endDt->format('Y-m-d'),
            'start_ts'              => $startDt->format('Y-m-d') . ' 00:00:00',
            'end_exclusive_ts'      => $endExclusiveDt->format('Y-m-d') . ' 00:00:00',
            'period_days'           => $periodDays,
            'prev_start_date'       => $prevStartDt->format('Y-m-d'),
            'prev_end_date'         => $prevEndDt->format('Y-m-d'),
            'prev_start_ts'         => $prevStartDt->format('Y-m-d') . ' 00:00:00',
            'prev_end_exclusive_ts' => $prevEndExclusiveDt->format('Y-m-d') . ' 00:00:00',
        ];
    }
}

$dateRange = sf_analytics_parse_date_range($_GET);

// Fallback path matching
if (empty($action) && isset($_SERVER['PATH_INFO'])) {
    $action = trim($_SERVER['PATH_INFO'], '/');
}

switch ($action) {

    // ── 1. Executive Stats & KPIs ─────────────────────────────────────────────
    case 'executive-stats':
        // Daily revenue (today's verified payments)
        $daily_res = $conn->query("
            SELECT COALESCE(SUM(amount), 0) AS daily_rev
            FROM payments
            WHERE status = 'verified' AND DATE(paid_at) = CURDATE()
        ");
        $daily_revenue = (float)($daily_res ? $daily_res->fetch_assoc()['daily_rev'] : 0);

        // Weekly revenue (last 7 days verified payments)
        $weekly_res = $conn->query("
            SELECT COALESCE(SUM(amount), 0) AS weekly_rev
            FROM payments
            WHERE status = 'verified' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        ");
        $weekly_revenue = (float)($weekly_res ? $weekly_res->fetch_assoc()['weekly_rev'] : 0);

        // Room occupancy
        $total_rooms_res = $conn->query("SELECT COUNT(*) AS c FROM rooms");
        $total_rooms = (int)($total_rooms_res ? $total_rooms_res->fetch_assoc()['c'] : 0);

        $checked_in_ids = sf_get_checked_in_room_ids($conn);
        $reserved_ids   = sf_get_reserved_room_ids($conn, true);

        $occupied_rooms = count($checked_in_ids);
        $reserved_rooms = count($reserved_ids);

        $occupancy_rate = ($total_rooms > 0) ? round(($occupied_rooms / $total_rooms) * 100, 1) . '%' : '0%';

        // Bookings counters
        $total_bookings_res = $conn->query("SELECT COUNT(*) AS c FROM bookings");
        $total_bookings = (int)($total_bookings_res ? $total_bookings_res->fetch_assoc()['c'] : 0);

        $pending_bookings_res = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status IN ('Pending', 'Pending Payment')");
        $pending_bookings = (int)($pending_bookings_res ? $pending_bookings_res->fetch_assoc()['c'] : 0);

        $pending_payments_res = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status = 'pending'");
        $pending_payments = (int)($pending_payments_res ? $pending_payments_res->fetch_assoc()['c'] : 0);

        $checkins_today_res = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status IN ('Pending', 'Pending Payment', 'Confirmed') AND DATE(check_in) = CURDATE()");
        $checkins_today = (int)($checkins_today_res ? $checkins_today_res->fetch_assoc()['c'] : 0);

        $checkouts_today_res = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status = 'Checked In' AND DATE(check_out) = CURDATE()");
        $checkouts_today = (int)($checkouts_today_res ? $checkouts_today_res->fetch_assoc()['c'] : 0);

        echo json_encode([
            'daily_revenue'    => $daily_revenue,
            'weekly_revenue'   => $weekly_revenue,
            'occupancy_rate'   => $occupancy_rate,
            'occupied_rooms'   => $occupied_rooms,
            'total_rooms'      => $total_rooms,
            'total_bookings'   => $total_bookings,
            'pending_bookings' => $pending_bookings,
            'reserved_rooms'   => $reserved_rooms,
            'pending_payments' => $pending_payments,
            'checkins_today'   => $checkins_today,
            'checkouts_today'  => $checkouts_today,
        ]);
        break;

    // ── 2. Weekly Revenue Trajectory Chart ────────────────────────────────────
    case 'weekly-revenue-trajectory':
        $labels = [];
        $dates = [];
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dayLabel = date('D', strtotime($date));
            $labels[] = $dayLabel;
            $dates[] = $date;

            $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS amt FROM payments WHERE status = 'verified' AND DATE(paid_at) = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $amt = (float)($stmt->get_result()->fetch_assoc()['amt'] ?? 0);
            $stmt->close();

            $data[] = $amt;
        }

        echo json_encode([
            'labels' => $labels,
            'dates'  => $dates,
            'data'   => $data,
        ]);
        break;

    // ── 2b. Daily Revenue Details (Chart Drill-Down) ──────────────────────────
    case 'daily-revenue-details':
        $rawDate = trim((string)($_GET['date'] ?? ''));
        $targetDate = $rawDate !== '' ? $rawDate : date('Y-m-d');
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $targetDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (
            !$parsedDate
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $parsedDate->format('Y-m-d') !== $targetDate
        ) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid date parameter. Expected YYYY-MM-DD.']);
            break;
        }

        $hasPurpose = sf_analytics_column_exists($conn, 'payments', 'purpose');
        $purposeExpr = $hasPurpose ? "COALESCE(NULLIF(LOWER(TRIM(p.purpose)), ''), 'deposit')" : "'deposit'";
        $stmt = $conn->prepare("
            SELECT
                p.id,
                p.booking_id,
                COALESCE(NULLIF(TRIM(p.guest_name), ''), b.guest_name, 'Guest') AS guest_name,
                p.amount,
                COALESCE(NULLIF(TRIM(p.payment_method), ''), 'Front Desk Cash') AS payment_method,
                COALESCE(NULLIF(TRIM(p.transaction_id), ''), CONCAT('TXN-', p.id)) AS transaction_id,
                {$purposeExpr} AS purpose,
                p.paid_at,
                COALESCE(b.accommodation_name, '') AS accommodation_name,
                COALESCE(b.status, '') AS booking_status
            FROM payments p
            LEFT JOIN bookings b ON b.id = p.booking_id
            WHERE p.status = 'verified' AND DATE(p.paid_at) = ?
            ORDER BY p.paid_at DESC, p.id DESC
        ");
        $transactions = [];
        $totalAmount = 0.0;
        if ($stmt) {
            $stmt->bind_param('s', $targetDate);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $amt = (float)($row['amount'] ?? 0);
                $totalAmount += $amt;
                $pid = (int)$row['id'];
                $bid = (int)($row['booking_id'] ?? 0);
                $purpose = (string)($row['purpose'] ?? 'deposit');
                $purposeLabel = match ($purpose) {
                    'balance' => 'Remaining Balance',
                    'full' => 'Full Payment',
                    'additional' => 'Additional Charge',
                    default => '50% Deposit',
                };
                $paidAt = (string)($row['paid_at'] ?? '');
                $paidTime = $paidAt !== '' ? date('h:i A', strtotime($paidAt)) : '';
                $transactions[] = [
                    'payment_id'         => $pid,
                    'invoice_code'       => 'INV-100' . $pid,
                    'booking_id'         => $bid,
                    'booking_ref'        => $bid > 0 ? ('REF-' . str_pad((string)$bid, 3, '0', STR_PAD_LEFT)) : '',
                    'guest_name'         => (string)$row['guest_name'],
                    'accommodation_name' => (string)$row['accommodation_name'],
                    'booking_status'     => (string)$row['booking_status'],
                    'amount'             => $amt,
                    'payment_method'     => (string)$row['payment_method'],
                    'transaction_id'     => (string)$row['transaction_id'],
                    'purpose'            => $purpose,
                    'purpose_label'      => $purposeLabel,
                    'paid_at'            => $paidAt,
                    'paid_time_display'  => $paidTime,
                ];
            }
            $stmt->close();
        }

        echo json_encode([
            'date'              => $targetDate,
            'date_display'      => $parsedDate->format('D, M j, Y'),
            'total_amount'      => round($totalAmount, 2),
            'transaction_count' => count($transactions),
            'transactions'      => $transactions,
        ]);
        break;

    // ── 3. Status Breakdown Doughnut Chart ────────────────────────────────────
    case 'status-breakdown':
        if ($dateRange['is_filtered']) {
            $stmt = $conn->prepare("SELECT status, COUNT(*) AS count FROM bookings WHERE created_at >= ? AND created_at < ? GROUP BY status");
            $stmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $stmt = null;
            $res = $conn->query("SELECT status, COUNT(*) AS count FROM bookings GROUP BY status");
        }
        $breakdown = [
            'Confirmed'   => 0,
            'Checked In'  => 0,
            'Checked Out' => 0,
            'Pending'     => 0,
            'Cancelled'   => 0,
        ];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $status = trim((string)$row['status']);
                $cnt = (int)$row['count'];
                $lower = strtolower($status);
                if ($lower === 'pending payment' || $lower === 'pending') {
                    $breakdown['Pending'] += $cnt;
                } elseif ($lower === 'confirmed') {
                    $breakdown['Confirmed'] += $cnt;
                } elseif ($lower === 'checked in') {
                    $breakdown['Checked In'] += $cnt;
                } elseif ($lower === 'checked out') {
                    $breakdown['Checked Out'] += $cnt;
                } elseif ($lower === 'cancelled' || $lower === 'canceled') {
                    $breakdown['Cancelled'] += $cnt;
                } else {
                    $breakdown['Pending'] += $cnt;
                }
            }
        }
        if ($stmt) {
            $stmt->close();
        }
        echo json_encode($breakdown);
        break;

    // ── 5. Recent Reservations Table ─────────────────────────────────────────
    case 'recent-bookings':
        $b_res = $conn->query("
            SELECT id, guest_name, accommodation_name, check_in, status
            FROM bookings
            ORDER BY id DESC
            LIMIT 6
        ");
        $bookings = [];
        if ($b_res) {
            while ($row = $b_res->fetch_assoc()) {
                $bookings[] = $row;
            }
        }
        echo json_encode($bookings);
        break;

    // ── 6. Recent Logs Feed ──────────────────────────────────────────────────
    case 'recent-logs':
        $l_res = $conn->query("
            SELECT admin_username, action, details, created_at
            FROM activity_logs
            ORDER BY id DESC
            LIMIT 6
        ");
        $logs = [];
        if ($l_res) {
            while ($row = $l_res->fetch_assoc()) {
                $logs[] = $row;
            }
        }
        echo json_encode($logs);
        break;

    // ── 7. Daily Summary Widget ──────────────────────────────────────────────
    case 'daily-summary':
        $today = date('Y-m-d');

        $b_today = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?? 0);
        $pay_res = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(amount), 0) AS total FROM payments WHERE status = 'verified' AND DATE(paid_at) = CURDATE()")->fetch_assoc();
        $p_count = (int)($pay_res['c'] ?? 0);
        $p_amt   = (float)($pay_res['total'] ?? 0);

        $cin_today  = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status = 'Checked In' AND DATE(check_in) = CURDATE()")->fetch_assoc()['c'] ?? 0);
        $cout_today = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status = 'Checked Out' AND DATE(check_out) = CURDATE()")->fetch_assoc()['c'] ?? 0);
        $canc_today = (int)($conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status = 'Cancelled' AND DATE(cancelled_at) = CURDATE()")->fetch_assoc()['c'] ?? 0);

        echo json_encode([
            'date'                  => $today,
            'bookings_today'        => $b_today,
            'payments_today_count'  => $p_count,
            'payments_today_amount' => $p_amt,
            'checkins_today'        => $cin_today,
            'checkouts_today'       => $cout_today,
            'cancellations_today'   => $canc_today,
        ]);
        break;

    // ── 8. Dashboard KPI Stats & Financial Health ───────────────────────────
    case 'dashboard-stats':
        $periodValue = static function (mysqli $db, string $sql, string $start, string $end): float {
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                return 0.0;
            }
            $stmt->bind_param('ss', $start, $end);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_row();
            $stmt->close();
            return (float)($row[0] ?? 0);
        };

        $revenueSql  = "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified' AND paid_at >= ? AND paid_at < ?";
        $refundSql   = "SELECT COALESCE(SUM(net_refund), 0) FROM refund_requests WHERE status IN ('approved', 'completed') AND COALESCE(completed_at, approved_at, requested_at) >= ? AND COALESCE(completed_at, approved_at, requested_at) < ?";
        $bookingSql  = "SELECT COUNT(*) FROM bookings WHERE created_at >= ? AND created_at < ?";
        $guestSql    = "SELECT COALESCE(SUM(guests_count), 0) FROM bookings WHERE status != 'Cancelled' AND created_at >= ? AND created_at < ?";
        $occupancySql = "SELECT COALESCE(SUM(GREATEST(0, DATEDIFF(LEAST(check_out, ?), GREATEST(check_in, ?)))), 0) FROM bookings WHERE status != 'Cancelled' AND check_in < ? AND check_out > ?";

        $roomNights = static function (mysqli $db, string $start, string $end) use ($occupancySql): float {
            $stmt = $db->prepare($occupancySql);
            if (!$stmt) {
                return 0.0;
            }
            $stmt->bind_param('ssss', $end, $start, $end, $start);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_row();
            $stmt->close();
            return (float)($row[0] ?? 0);
        };

        $total_rooms_res = $conn->query("SELECT COUNT(*) AS c FROM rooms");
        $total_rooms = (int)($total_rooms_res ? $total_rooms_res->fetch_assoc()['c'] : 0);
        $checked_in_ids = sf_get_checked_in_room_ids($conn);
        $occupied_rooms = count($checked_in_ids);

        if ($dateRange['is_filtered']) {
            $gross_revenue = $periodValue($conn, $revenueSql, $dateRange['start_ts'], $dateRange['end_exclusive_ts']);

            $refStmt = $conn->prepare("
                SELECT
                    COUNT(*) AS refund_count,
                    COALESCE(SUM(net_refund), 0) AS refunded_amount,
                    COALESCE(SUM(refund_fee), 0) AS refund_fees
                FROM refund_requests
                WHERE status IN ('approved', 'completed')
                  AND COALESCE(completed_at, approved_at, requested_at) >= ?
                  AND COALESCE(completed_at, approved_at, requested_at) < ?
            ");
            $refStmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $refStmt->execute();
            $refRow = $refStmt->get_result()->fetch_assoc() ?: [];
            $refStmt->close();

            $accStmt = $conn->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN status = 'verified' AND accounting_status = 'earned' THEN amount ELSE 0 END), 0) AS earned_revenue,
                    COALESCE(SUM(CASE WHEN status = 'verified' AND COALESCE(accounting_status, 'deferred') != 'earned' THEN amount ELSE 0 END), 0) AS deferred_revenue,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_verification_count,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_verification_amount
                FROM payments
                WHERE paid_at >= ? AND paid_at < ?
            ");
            $accStmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $accStmt->execute();
            $accRow = $accStmt->get_result()->fetch_assoc() ?: [];
            $accStmt->close();

            $bkStmt = $conn->prepare("
                SELECT
                    COUNT(*) AS total_bookings,
                    COALESCE(SUM(CASE WHEN status IN ('Pending', 'Pending Payment') THEN 1 ELSE 0 END), 0) AS pending_bookings,
                    COALESCE(SUM(CASE WHEN status != 'Cancelled' THEN guests_count ELSE 0 END), 0) AS total_guests,
                    ROUND(AVG(CASE WHEN status != 'Cancelled' THEN DATEDIFF(check_out, check_in) END), 1) AS avg_stay
                FROM bookings
                WHERE created_at >= ? AND created_at < ?
            ");
            $bkStmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $bkStmt->execute();
            $bkRow = $bkStmt->get_result()->fetch_assoc() ?: [];
            $bkStmt->close();

            $total_bookings   = (int)($bkRow['total_bookings'] ?? 0);
            $pending_bookings = (int)($bkRow['pending_bookings'] ?? 0);
            $total_guests     = (int)($bkRow['total_guests'] ?? 0);
            $avg_stay         = (float)($bkRow['avg_stay'] ?? 0.0);

            $periodCapacity = max(1, $total_rooms * $dateRange['period_days']);
            $soldNights = $roomNights($conn, $dateRange['start_date'], date('Y-m-d', strtotime($dateRange['end_date'] . ' +1 day')));
            $occupancy_rate = ($total_rooms > 0) ? min(100.0, round(($soldNights / $periodCapacity) * 100, 1)) : 0.0;
        } else {
            $rev_res = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM payments WHERE status = 'verified'");
            $gross_revenue = (float)($rev_res ? $rev_res->fetch_assoc()['total_revenue'] : 0);

            $ref_res = $conn->query("
                SELECT
                    COUNT(*) AS refund_count,
                    COALESCE(SUM(net_refund), 0) AS refunded_amount,
                    COALESCE(SUM(refund_fee), 0) AS refund_fees
                FROM refund_requests
                WHERE status IN ('approved', 'completed')
            ");
            $refRow = $ref_res ? ($ref_res->fetch_assoc() ?: []) : [];

            $acc_res = $conn->query("
                SELECT
                    COALESCE(SUM(CASE WHEN status = 'verified' AND accounting_status = 'earned' THEN amount ELSE 0 END), 0) AS earned_revenue,
                    COALESCE(SUM(CASE WHEN status = 'verified' AND COALESCE(accounting_status, 'deferred') != 'earned' THEN amount ELSE 0 END), 0) AS deferred_revenue,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_verification_count,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_verification_amount
                FROM payments
            ");
            $accRow = $acc_res ? ($acc_res->fetch_assoc() ?: []) : [];

            $bk_res = $conn->query("SELECT COUNT(*) AS total_bookings FROM bookings");
            $total_bookings = (int)($bk_res ? $bk_res->fetch_assoc()['total_bookings'] : 0);

            $pb_res = $conn->query("SELECT COUNT(*) AS pending_bookings FROM bookings WHERE status IN ('Pending', 'Pending Payment')");
            $pending_bookings = (int)($pb_res ? $pb_res->fetch_assoc()['pending_bookings'] : 0);

            $g_res = $conn->query("SELECT COALESCE(SUM(guests_count), 0) AS total_guests FROM bookings WHERE status != 'Cancelled'");
            $total_guests = (int)($g_res ? $g_res->fetch_assoc()['total_guests'] : 0);

            $avg_res = $conn->query("SELECT ROUND(AVG(DATEDIFF(check_out, check_in)), 1) AS avg_stay FROM bookings WHERE status != 'Cancelled'");
            $avg_stay = (float)($avg_res ? ($avg_res->fetch_assoc()['avg_stay'] ?? 0) : 0);

            $occupancy_rate = ($total_rooms > 0) ? round(($occupied_rooms / $total_rooms) * 100, 1) : 0.0;
        }

        $refund_count    = (int)($refRow['refund_count'] ?? 0);
        $refunded_amount = round((float)($refRow['refunded_amount'] ?? 0), 2);
        $refund_fees     = round((float)($refRow['refund_fees'] ?? 0), 2);
        $gross_revenue   = round($gross_revenue, 2);
        $net_revenue     = round(max(0.0, $gross_revenue - $refunded_amount), 2);
        $earned_revenue  = round((float)($accRow['earned_revenue'] ?? 0), 2);
        $deferred_revenue = round((float)($accRow['deferred_revenue'] ?? 0), 2);
        $pending_verification_count  = (int)($accRow['pending_verification_count'] ?? 0);
        $pending_verification_amount = round((float)($accRow['pending_verification_amount'] ?? 0), 2);

        // Outstanding guest balances on active stays/confirmed reservations
        $hasDiscountAmt = sf_analytics_column_exists($conn, 'bookings', 'discount_amount');
        $hasBalanceStatus = sf_analytics_column_exists($conn, 'bookings', 'balance_status');
        $discExpr = $hasDiscountAmt ? 'COALESCE(b.discount_amount, 0)' : '0';
        $balWhere = $hasBalanceStatus
            ? "AND COALESCE(b.balance_status, 'deposit_pending') IN ('deposit_pending', 'balance_pending')"
            : '';
        $bal_res = $conn->query("
            SELECT
                COUNT(DISTINCT b.id) AS unpaid_bookings_count,
                COALESCE(SUM(GREATEST(0,
                    (GREATEST(1, DATEDIFF(b.check_out, b.check_in)) * COALESCE(r.price_per_night, rt.price, 2900.00))
                    - {$discExpr}
                    - COALESCE(p.paid_total, 0)
                )), 0) AS unpaid_balance_estimate
            FROM bookings b
            LEFT JOIN rooms r ON r.id = b.room_id
            LEFT JOIN room_types rt ON rt.id = b.room_type_id
            LEFT JOIN (
                SELECT booking_id, SUM(amount) AS paid_total
                FROM payments
                WHERE status = 'verified'
                GROUP BY booking_id
            ) p ON p.booking_id = b.id
            WHERE b.status IN ('Pending', 'Pending Payment', 'Confirmed', 'Checked In')
              AND b.check_out >= CURDATE()
              {$balWhere}
        ");
        $balRow = $bal_res ? ($bal_res->fetch_assoc() ?: []) : [];
        $outstanding_balances_count  = (int)($balRow['unpaid_bookings_count'] ?? 0);
        $outstanding_balances_amount = round((float)($balRow['unpaid_balance_estimate'] ?? 0), 2);

        // Period-over-period comparison
        $current_start  = $dateRange['start_ts'];
        $current_end    = $dateRange['end_exclusive_ts'];
        $previous_start = $dateRange['prev_start_ts'];
        $previous_end   = $dateRange['prev_end_exclusive_ts'];

        $current_revenue  = $periodValue($conn, $revenueSql, $current_start, $current_end);
        $previous_revenue = $periodValue($conn, $revenueSql, $previous_start, $previous_end);
        $current_refunds  = $periodValue($conn, $refundSql, $current_start, $current_end);
        $previous_refunds = $periodValue($conn, $refundSql, $previous_start, $previous_end);
        $current_net_rev  = max(0.0, $current_revenue - $current_refunds);
        $previous_net_rev = max(0.0, $previous_revenue - $previous_refunds);
        $current_bookings = $periodValue($conn, $bookingSql, $current_start, $current_end);
        $previous_bookings = $periodValue($conn, $bookingSql, $previous_start, $previous_end);
        $current_guests   = $periodValue($conn, $guestSql, $current_start, $current_end);
        $previous_guests  = $periodValue($conn, $guestSql, $previous_start, $previous_end);

        $roomCapacity = max(1, $total_rooms * $dateRange['period_days']);
        $currEndDateExcl = substr($current_end, 0, 10);
        $prevEndDateExcl = substr($previous_end, 0, 10);
        $current_period_occupancy  = round(($roomNights($conn, $dateRange['start_date'], $currEndDateExcl) / $roomCapacity) * 100, 1);
        $previous_period_occupancy = round(($roomNights($conn, $dateRange['prev_start_date'], $prevEndDateExcl) / $roomCapacity) * 100, 1);

        $trend = [
            'period_days'      => $dateRange['period_days'],
            'comparison_label' => 'vs previous ' . $dateRange['period_days'] . 'd',
            'revenue_pct'      => sf_analytics_percent_change($current_revenue, $previous_revenue),
            'net_revenue_pct'  => sf_analytics_percent_change($current_net_rev, $previous_net_rev),
            'bookings_pct'     => sf_analytics_percent_change($current_bookings, $previous_bookings),
            'guests_pct'       => sf_analytics_percent_change($current_guests, $previous_guests),
            'occupancy_pct'    => sf_analytics_percent_change($current_period_occupancy, $previous_period_occupancy),
        ];

        echo json_encode([
            'date_range'                  => [
                'is_filtered' => $dateRange['is_filtered'],
                'range_key'   => $dateRange['range_key'],
                'label'       => $dateRange['label'],
                'start_date'  => $dateRange['start_date'],
                'end_date'    => $dateRange['end_date'],
                'period_days' => $dateRange['period_days'],
            ],
            'total_revenue'               => $gross_revenue,
            'gross_revenue'               => $gross_revenue,
            'refunded_amount'             => $refunded_amount,
            'refund_fees'                 => $refund_fees,
            'refund_count'                => $refund_count,
            'net_revenue'                 => $net_revenue,
            'earned_revenue'              => $earned_revenue,
            'deferred_revenue'            => $deferred_revenue,
            'pending_verification_count'  => $pending_verification_count,
            'pending_verification_amount' => $pending_verification_amount,
            'outstanding_balances_count'  => $outstanding_balances_count,
            'outstanding_balances_amount' => $outstanding_balances_amount,
            'total_bookings'              => $total_bookings,
            'pending_bookings'            => $pending_bookings,
            'total_guests'                => $total_guests,
            'avg_stay'                    => $avg_stay,
            'occupancy_rate'              => $occupancy_rate,
            'occupied_rooms'              => $occupied_rooms,
            'total_rooms'                 => $total_rooms,
            'trend'                       => $trend,
        ]);
        break;

    // ── 9. Monthly Revenue (Gross vs Refunded vs Net + Drill-Down Keys) ───────
    case 'monthly-revenue':
        $months = [];
        $labels = [];
        $grossRevenue = [];
        $refunds = [];
        $netRevenue = [];
        $bookingsCount = [];

        $anchorMonth = ($dateRange['is_filtered'] && !empty($dateRange['end_date']))
            ? new DateTimeImmutable(substr($dateRange['end_date'], 0, 7) . '-01')
            : new DateTimeImmutable(date('Y-m-01'));

        $revStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS v FROM payments WHERE status = 'verified' AND paid_at >= ? AND paid_at < ?");
        $refStmt = $conn->prepare("
            SELECT COALESCE(SUM(net_refund), 0) AS r
            FROM refund_requests
            WHERE status IN ('approved', 'completed')
              AND COALESCE(completed_at, approved_at, requested_at) >= ?
              AND COALESCE(completed_at, approved_at, requested_at) < ?
        ");
        $bkStmt = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE status != 'Cancelled' AND created_at >= ? AND created_at < ?");

        for ($i = 5; $i >= 0; $i--) {
            $mStartDt = $anchorMonth->modify("-{$i} months");
            $mEndDt   = $mStartDt->modify('+1 month');
            $mKey     = $mStartDt->format('Y-m');
            $mLabel   = $mStartDt->format('M Y');
            $mStartTs = $mStartDt->format('Y-m-d') . ' 00:00:00';
            $mEndTs   = $mEndDt->format('Y-m-d') . ' 00:00:00';

            $revStmt->bind_param('ss', $mStartTs, $mEndTs);
            $revStmt->execute();
            $gVal = round((float)($revStmt->get_result()->fetch_assoc()['v'] ?? 0), 2);

            $refStmt->bind_param('ss', $mStartTs, $mEndTs);
            $refStmt->execute();
            $rVal = round((float)($refStmt->get_result()->fetch_assoc()['r'] ?? 0), 2);

            $bkStmt->bind_param('ss', $mStartTs, $mEndTs);
            $bkStmt->execute();
            $bCnt = (int)($bkStmt->get_result()->fetch_assoc()['c'] ?? 0);

            $nVal = round(max(0.0, $gVal - $rVal), 2);

            $months[]        = $mKey;
            $labels[]        = $mLabel;
            $grossRevenue[]  = $gVal;
            $refunds[]       = $rVal;
            $netRevenue[]    = $nVal;
            $bookingsCount[] = $bCnt;
        }
        $revStmt->close();
        $refStmt->close();
        $bkStmt->close();

        echo json_encode([
            'months'         => $months,
            'labels'         => $labels,
            'revenue'        => $grossRevenue,
            'gross_revenue'  => $grossRevenue,
            'refunds'        => $refunds,
            'net_revenue'    => $netRevenue,
            'bookings_count' => $bookingsCount,
        ]);
        break;

    // ── 9b. Monthly Revenue Details (Reports Bar Drill-Down Modal) ────────────
    case 'monthly-revenue-details':
        $rawMonth = trim((string)($_GET['month'] ?? ''));
        $targetMonth = $rawMonth !== '' ? $rawMonth : date('Y-m');
        $parsedMonth = DateTimeImmutable::createFromFormat('!Y-m', $targetMonth);
        $monthErrors = DateTimeImmutable::getLastErrors();
        if (
            !$parsedMonth
            || ($monthErrors !== false && ($monthErrors['warning_count'] > 0 || $monthErrors['error_count'] > 0))
            || $parsedMonth->format('Y-m') !== $targetMonth
        ) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid month parameter. Expected YYYY-MM.']);
            break;
        }

        $mStartTs = $parsedMonth->format('Y-m-01') . ' 00:00:00';
        $mEndTs   = $parsedMonth->modify('+1 month')->format('Y-m-01') . ' 00:00:00';

        $hasPurpose = sf_analytics_column_exists($conn, 'payments', 'purpose');
        $hasAccStatus = sf_analytics_column_exists($conn, 'payments', 'accounting_status');
        $purposeExpr = $hasPurpose ? "COALESCE(NULLIF(LOWER(TRIM(p.purpose)), ''), 'deposit')" : "'deposit'";
        $accExpr = $hasAccStatus ? "COALESCE(NULLIF(LOWER(TRIM(p.accounting_status)), ''), 'deferred')" : "'deferred'";

        $stmt = $conn->prepare("
            SELECT
                p.id,
                p.booking_id,
                COALESCE(NULLIF(TRIM(p.guest_name), ''), b.guest_name, 'Guest') AS guest_name,
                p.amount,
                COALESCE(NULLIF(TRIM(p.payment_method), ''), 'Front Desk Cash') AS payment_method,
                COALESCE(NULLIF(TRIM(p.transaction_id), ''), CONCAT('TXN-', p.id)) AS transaction_id,
                {$purposeExpr} AS purpose,
                {$accExpr} AS accounting_status,
                p.paid_at,
                COALESCE(b.accommodation_name, '') AS accommodation_name,
                COALESCE(b.status, '') AS booking_status
            FROM payments p
            LEFT JOIN bookings b ON b.id = p.booking_id
            WHERE p.status = 'verified' AND p.paid_at >= ? AND p.paid_at < ?
            ORDER BY p.paid_at DESC, p.id DESC
        ");
        $transactions = [];
        $grossAmount = 0.0;
        if ($stmt) {
            $stmt->bind_param('ss', $mStartTs, $mEndTs);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $amt = (float)($row['amount'] ?? 0);
                $grossAmount += $amt;
                $pid = (int)$row['id'];
                $bid = (int)($row['booking_id'] ?? 0);
                $purpose = (string)($row['purpose'] ?? 'deposit');
                $purposeLabel = match ($purpose) {
                    'balance' => 'Remaining Balance',
                    'full' => 'Full Payment',
                    'additional' => 'Additional Charge',
                    default => '50% Deposit',
                };
                $paidAt = (string)($row['paid_at'] ?? '');
                $paidDisplay = $paidAt !== '' ? date('M j, Y • h:i A', strtotime($paidAt)) : '';
                $transactions[] = [
                    'payment_id'         => $pid,
                    'invoice_code'       => 'INV-100' . $pid,
                    'booking_id'         => $bid,
                    'booking_ref'        => $bid > 0 ? ('REF-' . str_pad((string)$bid, 3, '0', STR_PAD_LEFT)) : '',
                    'guest_name'         => (string)$row['guest_name'],
                    'accommodation_name' => (string)$row['accommodation_name'],
                    'booking_status'     => (string)$row['booking_status'],
                    'amount'             => $amt,
                    'payment_method'     => (string)$row['payment_method'],
                    'transaction_id'     => (string)$row['transaction_id'],
                    'purpose'            => $purpose,
                    'purpose_label'      => $purposeLabel,
                    'accounting_status'  => (string)$row['accounting_status'],
                    'paid_at'            => $paidAt,
                    'paid_time_display'  => $paidDisplay,
                ];
            }
            $stmt->close();
        }

        $refStmt = $conn->prepare("
            SELECT COALESCE(SUM(net_refund), 0) AS r_amt, COUNT(*) AS r_cnt
            FROM refund_requests
            WHERE status IN ('approved', 'completed')
              AND COALESCE(completed_at, approved_at, requested_at) >= ?
              AND COALESCE(completed_at, approved_at, requested_at) < ?
        ");
        $refundedAmount = 0.0;
        $refundCount = 0;
        if ($refStmt) {
            $refStmt->bind_param('ss', $mStartTs, $mEndTs);
            $refStmt->execute();
            $rRow = $refStmt->get_result()->fetch_assoc() ?: [];
            $refundedAmount = round((float)($rRow['r_amt'] ?? 0), 2);
            $refundCount    = (int)($rRow['r_cnt'] ?? 0);
            $refStmt->close();
        }

        echo json_encode([
            'month'             => $targetMonth,
            'month_display'     => $parsedMonth->format('F Y'),
            'total_amount'      => round($grossAmount, 2),
            'gross_amount'      => round($grossAmount, 2),
            'refunded_amount'   => $refundedAmount,
            'net_amount'        => round(max(0.0, $grossAmount - $refundedAmount), 2),
            'transaction_count' => count($transactions),
            'refund_count'      => $refundCount,
            'transactions'      => $transactions,
        ]);
        break;

    // ── 10. Top Accommodations ───────────────────────────────────────────────
    case 'top-accommodations':
        if ($dateRange['is_filtered']) {
            $stmt = $conn->prepare("
                SELECT
                    b.accommodation_name,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(b.guests_count), 0) AS guests,
                    COALESCE(SUM(GREATEST(1, DATEDIFF(b.check_out, b.check_in))), 0) AS nights_sold,
                    COALESCE(SUM(p.verified_revenue), 0) AS revenue
                FROM bookings b
                LEFT JOIN (
                    SELECT booking_id, SUM(amount) AS verified_revenue
                    FROM payments
                    WHERE status = 'verified'
                    GROUP BY booking_id
                ) p ON p.booking_id = b.id
                WHERE b.status != 'Cancelled'
                  AND b.created_at >= ? AND b.created_at < ?
                GROUP BY b.accommodation_name
                ORDER BY cnt DESC, revenue DESC
                LIMIT 8
            ");
            $stmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $stmt = null;
            $res = $conn->query("
                SELECT
                    b.accommodation_name,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(b.guests_count), 0) AS guests,
                    COALESCE(SUM(GREATEST(1, DATEDIFF(b.check_out, b.check_in))), 0) AS nights_sold,
                    COALESCE(SUM(p.verified_revenue), 0) AS revenue
                FROM bookings b
                LEFT JOIN (
                    SELECT booking_id, SUM(amount) AS verified_revenue
                    FROM payments
                    WHERE status = 'verified'
                    GROUP BY booking_id
                ) p ON p.booking_id = b.id
                WHERE b.status != 'Cancelled'
                GROUP BY b.accommodation_name
                ORDER BY cnt DESC, revenue DESC
                LIMIT 8
            ");
        }
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = [
                    'accommodation_name' => $row['accommodation_name'],
                    'cnt'                => (int)$row['cnt'],
                    'guests'             => (int)$row['guests'],
                    'nights_sold'        => (int)($row['nights_sold'] ?? 0),
                    'revenue'            => round((float)($row['revenue'] ?? 0), 2),
                ];
            }
        }
        if ($stmt) {
            $stmt->close();
        }
        echo json_encode($data);
        break;

    // ── 11. Payment Methods Breakdown ────────────────────────────────────────
    case 'payment-methods':
        if ($dateRange['is_filtered']) {
            $stmt = $conn->prepare("
                SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
                FROM payments
                WHERE status = 'verified' AND paid_at >= ? AND paid_at < ?
                GROUP BY payment_method
                ORDER BY total DESC
            ");
            $stmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $stmt = null;
            $res = $conn->query("
                SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
                FROM payments
                WHERE status = 'verified'
                GROUP BY payment_method
                ORDER BY total DESC
            ");
        }
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = [
                    'payment_method' => $row['payment_method'],
                    'cnt'            => (int)$row['cnt'],
                    'total'          => (float)$row['total'],
                ];
            }
        }
        if ($stmt) {
            $stmt->close();
        }
        echo json_encode($data);
        break;

    // ── 12. Room-Type Performance (ADR, RevPAR, Occupancy & Revenue) ─────────
    case 'room-type-performance':
        $periodDays = $dateRange['period_days'];
        $typesRes = $conn->query("
            SELECT
                r.type AS room_type_code,
                COUNT(DISTINCT CASE WHEN r.status <> 'maintenance' THEN r.id END) AS active_units,
                COUNT(DISTINCT r.id) AS total_units,
                ROUND(AVG(r.price_per_night), 2) AS base_rate
            FROM rooms r
            WHERE r.type IS NOT NULL AND TRIM(r.type) <> ''
            GROUP BY r.type
            ORDER BY base_rate DESC
        ");

        $types = [];
        if ($typesRes) {
            while ($tRow = $typesRes->fetch_assoc()) {
                $code = (string)$tRow['room_type_code'];
                $activeUnits = max(1, (int)($tRow['active_units'] ?? 1));
                $baseRate = (float)($tRow['base_rate'] ?? 0);

                if ($dateRange['is_filtered']) {
                    $bStmt = $conn->prepare("
                        SELECT
                            COUNT(DISTINCT b.id) AS stays_count,
                            COALESCE(SUM(b.guests_count), 0) AS guests_hosted,
                            COALESCE(SUM(GREATEST(1, DATEDIFF(b.check_out, b.check_in))), 0) AS nights_sold,
                            COALESCE(SUM(p.verified_revenue), 0) AS verified_revenue
                        FROM bookings b
                        LEFT JOIN rooms rm ON rm.id = b.room_id
                        LEFT JOIN room_types rt ON rt.id = b.room_type_id
                        LEFT JOIN (
                            SELECT booking_id, SUM(amount) AS verified_revenue
                            FROM payments
                            WHERE status = 'verified'
                            GROUP BY booking_id
                        ) p ON p.booking_id = b.id
                        WHERE b.status != 'Cancelled'
                          AND b.created_at >= ? AND b.created_at < ?
                          AND (rm.type = ? OR rt.name = ?)
                    ");
                    $bStmt->bind_param('ssss', $dateRange['start_ts'], $dateRange['end_exclusive_ts'], $code, $code);
                } else {
                    $bStmt = $conn->prepare("
                        SELECT
                            COUNT(DISTINCT b.id) AS stays_count,
                            COALESCE(SUM(b.guests_count), 0) AS guests_hosted,
                            COALESCE(SUM(GREATEST(1, DATEDIFF(b.check_out, b.check_in))), 0) AS nights_sold,
                            COALESCE(SUM(p.verified_revenue), 0) AS verified_revenue
                        FROM bookings b
                        LEFT JOIN rooms rm ON rm.id = b.room_id
                        LEFT JOIN room_types rt ON rt.id = b.room_type_id
                        LEFT JOIN (
                            SELECT booking_id, SUM(amount) AS verified_revenue
                            FROM payments
                            WHERE status = 'verified'
                            GROUP BY booking_id
                        ) p ON p.booking_id = b.id
                        WHERE b.status != 'Cancelled'
                          AND (rm.type = ? OR rt.name = ?)
                    ");
                    $bStmt->bind_param('ss', $code, $code);
                }
                $bStmt->execute();
                $mRow = $bStmt->get_result()->fetch_assoc() ?: [];
                $bStmt->close();

                $staysCount  = (int)($mRow['stays_count'] ?? 0);
                $guestsHosted = (int)($mRow['guests_hosted'] ?? 0);
                $nightsSold  = (int)($mRow['nights_sold'] ?? 0);
                $verRev      = round((float)($mRow['verified_revenue'] ?? 0), 2);
                $availNights = max(1, $activeUnits * $periodDays);
                $occPct      = min(100.0, round(($nightsSold / $availNights) * 100, 1));
                $adr         = $nightsSold > 0 ? round($verRev / $nightsSold, 2) : $baseRate;
                $revpar      = round($verRev / $availNights, 2);

                $types[] = [
                    'room_type'        => $code,
                    'display_name'     => ucwords(str_replace('_', ' ', $code)),
                    'units'            => (int)($tRow['total_units'] ?? $activeUnits),
                    'active_units'     => (int)($tRow['active_units'] ?? $activeUnits),
                    'base_rate'        => $baseRate,
                    'stays_count'      => $staysCount,
                    'guests_hosted'    => $guestsHosted,
                    'nights_sold'      => $nightsSold,
                    'occupancy_pct'    => $occPct,
                    'adr'              => $adr,
                    'revpar'           => $revpar,
                    'verified_revenue' => $verRev,
                ];
            }
        }

        usort($types, static fn(array $a, array $b): int => ($b['verified_revenue'] <=> $a['verified_revenue']) ?: ($b['stays_count'] <=> $a['stays_count']));
        echo json_encode([
            'period_days' => $periodDays,
            'room_types'  => $types,
        ]);
        break;

    // ── 13. Country Demographics & Guest Origins ──────────────────────────────
    case 'country-demographics':
        if ($dateRange['is_filtered']) {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(NULLIF(TRIM(b.guest_country), ''), 'Unknown / Not provided') AS country,
                    COUNT(DISTINCT b.id) AS total_bookings,
                    COUNT(DISTINCT NULLIF(LOWER(TRIM(b.guest_email)), '')) AS unique_customers,
                    COALESCE(SUM(b.guests_count), 0) AS booked_guests,
                    COALESCE(SUM(p.verified_revenue), 0) AS total_revenue
                FROM bookings b
                LEFT JOIN (
                    SELECT booking_id, SUM(amount) AS verified_revenue
                    FROM payments
                    WHERE status = 'verified'
                    GROUP BY booking_id
                ) p ON b.id = p.booking_id
                WHERE b.status != 'Cancelled'
                  AND b.created_at >= ? AND b.created_at < ?
                GROUP BY COALESCE(NULLIF(TRIM(b.guest_country), ''), 'Unknown / Not provided')
                ORDER BY total_bookings DESC, total_revenue DESC
            ");
            $stmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $stmt = null;
            $res = $conn->query("
                SELECT 
                    COALESCE(NULLIF(TRIM(b.guest_country), ''), 'Unknown / Not provided') AS country,
                    COUNT(DISTINCT b.id) AS total_bookings,
                    COUNT(DISTINCT NULLIF(LOWER(TRIM(b.guest_email)), '')) AS unique_customers,
                    COALESCE(SUM(b.guests_count), 0) AS booked_guests,
                    COALESCE(SUM(p.verified_revenue), 0) AS total_revenue
                FROM bookings b
                LEFT JOIN (
                    SELECT booking_id, SUM(amount) AS verified_revenue
                    FROM payments
                    WHERE status = 'verified'
                    GROUP BY booking_id
                ) p ON b.id = p.booking_id
                WHERE b.status != 'Cancelled'
                GROUP BY COALESCE(NULLIF(TRIM(b.guest_country), ''), 'Unknown / Not provided')
                ORDER BY total_bookings DESC, total_revenue DESC
            ");
        }
        $countries = [];
        $totalBookingsAll = 0;
        $totalRevenueAll = 0;

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $bCount = (int)$row['total_bookings'];
                $rSum   = (float)$row['total_revenue'];
                $totalBookingsAll += $bCount;
                $totalRevenueAll  += $rSum;
                $countries[] = [
                    'country'          => $row['country'],
                    'bookings'         => $bCount,
                    'unique_customers' => (int)$row['unique_customers'],
                    'booked_guests'    => (int)$row['booked_guests'],
                    'revenue'          => $rSum,
                ];
            }
        }
        if ($stmt) {
            $stmt->close();
        }

        // Calculate percentages so all origin shares sum to exactly 100%
        $countryShares = sf_analytics_allocate_percentages(array_column($countries, 'bookings'), 1);
        foreach ($countries as $idx => &$c) {
            $c['share_pct'] = $countryShares[$idx] ?? 0.0;
        }
        unset($c);

        echo json_encode([
            'countries'      => $countries,
            'total_bookings' => $totalBookingsAll,
            'total_revenue'  => $totalRevenueAll,
            'total_origins'  => count($countries),
        ]);
        break;

    // ── 13b. Promotions & Statutory Discounts Impact ──────────────────────────
    case 'discounts-and-promos':
        if ($dateRange['is_filtered']) {
            $promoStmt = $conn->prepare("
                SELECT
                    UPPER(TRIM(b.promo_code)) AS code,
                    COALESCE(MAX(pr.title), UPPER(TRIM(b.promo_code))) AS title,
                    COUNT(*) AS uses_count,
                    COALESCE(SUM(b.discount_amount), 0) AS discount_total
                FROM bookings b
                LEFT JOIN promotions pr ON UPPER(TRIM(pr.code)) = UPPER(TRIM(b.promo_code))
                WHERE b.status != 'Cancelled'
                  AND b.promo_code IS NOT NULL AND TRIM(b.promo_code) <> ''
                  AND b.created_at >= ? AND b.created_at < ?
                GROUP BY UPPER(TRIM(b.promo_code))
                ORDER BY discount_total DESC, uses_count DESC
            ");
            $promoStmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $promoStmt->execute();
            $promoRes = $promoStmt->get_result();

            $statStmt = $conn->prepare("
                SELECT
                    UPPER(TRIM(bd.category)) AS category,
                    COUNT(*) AS total_requests,
                    SUM(CASE WHEN LOWER(bd.state) LIKE '%approved%' OR LOWER(bd.state) LIKE '%verified%' OR LOWER(bd.state) = 'confirmed' THEN 1 ELSE 0 END) AS approved_count,
                    COALESCE(SUM(GREATEST(0, bd.original_centavos - bd.total_centavos)) / 100.0, 0) AS discount_total
                FROM booking_discounts bd
                WHERE bd.created_at >= ? AND bd.created_at < ?
                GROUP BY UPPER(TRIM(bd.category))
                ORDER BY discount_total DESC
            ");
            $statStmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $statStmt->execute();
            $statRes = $statStmt->get_result();
        } else {
            $promoStmt = null;
            $promoRes = $conn->query("
                SELECT
                    UPPER(TRIM(b.promo_code)) AS code,
                    COALESCE(MAX(pr.title), UPPER(TRIM(b.promo_code))) AS title,
                    COUNT(*) AS uses_count,
                    COALESCE(SUM(b.discount_amount), 0) AS discount_total
                FROM bookings b
                LEFT JOIN promotions pr ON UPPER(TRIM(pr.code)) = UPPER(TRIM(b.promo_code))
                WHERE b.status != 'Cancelled'
                  AND b.promo_code IS NOT NULL AND TRIM(b.promo_code) <> ''
                GROUP BY UPPER(TRIM(b.promo_code))
                ORDER BY discount_total DESC, uses_count DESC
            ");

            $statStmt = null;
            $statRes = $conn->query("
                SELECT
                    UPPER(TRIM(bd.category)) AS category,
                    COUNT(*) AS total_requests,
                    SUM(CASE WHEN LOWER(bd.state) LIKE '%approved%' OR LOWER(bd.state) LIKE '%verified%' OR LOWER(bd.state) = 'confirmed' THEN 1 ELSE 0 END) AS approved_count,
                    COALESCE(SUM(GREATEST(0, bd.original_centavos - bd.total_centavos)) / 100.0, 0) AS discount_total
                FROM booking_discounts bd
                GROUP BY UPPER(TRIM(bd.category))
                ORDER BY discount_total DESC
            ");
        }

        $promos = [];
        $totalPromoDiscount = 0.0;
        $totalPromoUses = 0;
        if ($promoRes) {
            while ($row = $promoRes->fetch_assoc()) {
                $amt = round((float)($row['discount_total'] ?? 0), 2);
                $cnt = (int)($row['uses_count'] ?? 0);
                $totalPromoDiscount += $amt;
                $totalPromoUses += $cnt;
                $promos[] = [
                    'code'           => (string)$row['code'],
                    'title'          => (string)$row['title'],
                    'uses_count'     => $cnt,
                    'discount_total' => $amt,
                ];
            }
        }
        if ($promoStmt) {
            $promoStmt->close();
        }

        $statutory = [];
        $totalStatutoryDiscount = 0.0;
        $totalStatutoryCount = 0;
        if ($statRes) {
            while ($row = $statRes->fetch_assoc()) {
                $cat = (string)($row['category'] ?? 'DISC');
                $label = match ($cat) {
                    'SC', 'SENIOR' => 'Senior Citizen (20%)',
                    'PWD' => 'PWD (20%)',
                    default => $cat . ' Discount',
                };
                $amt = round((float)($row['discount_total'] ?? 0), 2);
                $cnt = (int)($row['total_requests'] ?? 0);
                $totalStatutoryDiscount += $amt;
                $totalStatutoryCount += $cnt;
                $statutory[] = [
                    'category'       => $cat,
                    'label'          => $label,
                    'requests_count' => $cnt,
                    'approved_count' => (int)($row['approved_count'] ?? 0),
                    'discount_total' => $amt,
                ];
            }
        }
        if ($statStmt) {
            $statStmt->close();
        }

        $activePromosRes = $conn->query("SELECT COUNT(*) AS c FROM promotions WHERE is_active = 1 AND (valid_until IS NULL OR valid_until >= CURDATE())");
        $activePromosCount = (int)($activePromosRes ? ($activePromosRes->fetch_assoc()['c'] ?? 0) : 0);

        echo json_encode([
            'active_promotions_count'   => $activePromosCount,
            'promo_redemptions_count'   => $totalPromoUses,
            'promo_discount_total'      => round($totalPromoDiscount, 2),
            'statutory_requests_count'  => $totalStatutoryCount,
            'statutory_discount_total'  => round($totalStatutoryDiscount, 2),
            'combined_discount_total'   => round($totalPromoDiscount + $totalStatutoryDiscount, 2),
            'promo_breakdown'           => $promos,
            'statutory_breakdown'       => $statutory,
        ]);
        break;

    // ── 13c. Cancellations & Booking Lead-Time Insights ───────────────────────
    case 'cancellation-insights':
        $hasNonpayCol = sf_analytics_column_exists($conn, 'bookings', 'cancelled_for_nonpayment_at');
        $hasCancelReasonCol = sf_analytics_column_exists($conn, 'bookings', 'cancellation_reason');
        $nonpaySumExpr = $hasNonpayCol ? "COALESCE(SUM(CASE WHEN cancelled_for_nonpayment_at IS NOT NULL THEN 1 ELSE 0 END), 0)" : "0";
        $reasonExpr = $hasCancelReasonCol
            ? ($hasNonpayCol
                ? "COALESCE(NULLIF(TRIM(cancellation_reason), ''), CASE WHEN cancelled_for_nonpayment_at IS NOT NULL THEN 'Unpaid Balance / Expired Hold' ELSE 'Guest / Admin Cancellation (Unspecified)' END)"
                : "COALESCE(NULLIF(TRIM(cancellation_reason), ''), 'Guest / Admin Cancellation (Unspecified)')")
            : "'Guest / Admin Cancellation (Unspecified)'";

        if ($dateRange['is_filtered']) {
            $sumStmt = $conn->prepare("
                SELECT
                    COUNT(*) AS total_bookings,
                    COALESCE(SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_bookings,
                    {$nonpaySumExpr} AS nonpayment_auto_cancelled,
                    ROUND(AVG(GREATEST(0, DATEDIFF(check_in, DATE(created_at)))), 1) AS avg_lead_days
                FROM bookings
                WHERE created_at >= ? AND created_at < ?
            ");
            $sumStmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $sumStmt->execute();
            $sumRow = $sumStmt->get_result()->fetch_assoc() ?: [];
            $sumStmt->close();

            $rsnStmt = $conn->prepare("
                SELECT
                    {$reasonExpr} AS reason,
                    COUNT(*) AS cnt
                FROM bookings
                WHERE status = 'Cancelled'
                  AND created_at >= ? AND created_at < ?
                GROUP BY {$reasonExpr}
                ORDER BY cnt DESC
                LIMIT 6
            ");
            $rsnStmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $rsnStmt->execute();
            $rsnRes = $rsnStmt->get_result();
        } else {
            $sumRes = $conn->query("
                SELECT
                    COUNT(*) AS total_bookings,
                    COALESCE(SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_bookings,
                    {$nonpaySumExpr} AS nonpayment_auto_cancelled,
                    ROUND(AVG(GREATEST(0, DATEDIFF(check_in, DATE(created_at)))), 1) AS avg_lead_days
                FROM bookings
            ");
            $sumRow = $sumRes ? ($sumRes->fetch_assoc() ?: []) : [];

            $rsnStmt = null;
            $rsnRes = $conn->query("
                SELECT
                    {$reasonExpr} AS reason,
                    COUNT(*) AS cnt
                FROM bookings
                WHERE status = 'Cancelled'
                GROUP BY {$reasonExpr}
                ORDER BY cnt DESC
                LIMIT 6
            ");
        }

        $reasons = [];
        if ($rsnRes) {
            while ($row = $rsnRes->fetch_assoc()) {
                $reasons[] = [
                    'reason' => (string)$row['reason'],
                    'count'  => (int)$row['cnt'],
                ];
            }
        }
        if ($rsnStmt) {
            $rsnStmt->close();
        }

        $reasonShares = sf_analytics_allocate_percentages(array_column($reasons, 'count'), 1);
        foreach ($reasons as $idx => &$rItem) {
            $rItem['share_pct'] = $reasonShares[$idx] ?? 0.0;
        }
        unset($rItem);

        $totB = (int)($sumRow['total_bookings'] ?? 0);
        $canB = (int)($sumRow['cancelled_bookings'] ?? 0);
        $canRate = $totB > 0 ? round(($canB / $totB) * 100, 1) : 0.0;

        echo json_encode([
            'total_bookings'            => $totB,
            'cancelled_bookings'        => $canB,
            'cancellation_rate_pct'     => $canRate,
            'nonpayment_auto_cancelled' => (int)($sumRow['nonpayment_auto_cancelled'] ?? 0),
            'avg_lead_time_days'        => (float)($sumRow['avg_lead_days'] ?? 0.0),
            'reasons'                   => $reasons,
        ]);
        break;

    // ── 13d. Guest Reviews & Satisfaction Summary ─────────────────────────────
    case 'reviews-summary':
        if ($dateRange['is_filtered']) {
            $revStmt = $conn->prepare("
                SELECT
                    SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) AS approved_count,
                    SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) AS pending_count,
                    ROUND(AVG(CASE WHEN is_approved = 1 THEN rating END), 2) AS avg_rating,
                    SUM(CASE WHEN is_approved = 1 AND rating = 5 THEN 1 ELSE 0 END) AS r5,
                    SUM(CASE WHEN is_approved = 1 AND rating = 4 THEN 1 ELSE 0 END) AS r4,
                    SUM(CASE WHEN is_approved = 1 AND rating = 3 THEN 1 ELSE 0 END) AS r3,
                    SUM(CASE WHEN is_approved = 1 AND rating = 2 THEN 1 ELSE 0 END) AS r2,
                    SUM(CASE WHEN is_approved = 1 AND rating = 1 THEN 1 ELSE 0 END) AS r1
                FROM reviews
                WHERE created_at >= ? AND created_at < ?
            ");
            $revStmt->bind_param('ss', $dateRange['start_ts'], $dateRange['end_exclusive_ts']);
            $revStmt->execute();
            $revRow = $revStmt->get_result()->fetch_assoc() ?: [];
            $revStmt->close();
        } else {
            $revRes = $conn->query("
                SELECT
                    SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) AS approved_count,
                    SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) AS pending_count,
                    ROUND(AVG(CASE WHEN is_approved = 1 THEN rating END), 2) AS avg_rating,
                    SUM(CASE WHEN is_approved = 1 AND rating = 5 THEN 1 ELSE 0 END) AS r5,
                    SUM(CASE WHEN is_approved = 1 AND rating = 4 THEN 1 ELSE 0 END) AS r4,
                    SUM(CASE WHEN is_approved = 1 AND rating = 3 THEN 1 ELSE 0 END) AS r3,
                    SUM(CASE WHEN is_approved = 1 AND rating = 2 THEN 1 ELSE 0 END) AS r2,
                    SUM(CASE WHEN is_approved = 1 AND rating = 1 THEN 1 ELSE 0 END) AS r1
                FROM reviews
            ");
            $revRow = $revRes ? ($revRes->fetch_assoc() ?: []) : [];
        }

        $starCounts = [
            (int)($revRow['r5'] ?? 0),
            (int)($revRow['r4'] ?? 0),
            (int)($revRow['r3'] ?? 0),
            (int)($revRow['r2'] ?? 0),
            (int)($revRow['r1'] ?? 0),
        ];
        $starShares = sf_analytics_allocate_percentages($starCounts, 1);
        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $idx => $stars) {
            $distribution[] = [
                'stars'     => $stars,
                'count'     => $starCounts[$idx],
                'share_pct' => $starShares[$idx] ?? 0.0,
            ];
        }

        echo json_encode([
            'approved_count' => (int)($revRow['approved_count'] ?? 0),
            'pending_count'  => (int)($revRow['pending_count'] ?? 0),
            'avg_rating'     => (float)($revRow['avg_rating'] ?? 0.0),
            'distribution'   => $distribution,
        ]);
        break;

    // ── 14. Security Threats & Warning Summary (Last 24h) ─────────────────────
    case 'security-threats-summary':
        $res = $conn->query("
            SELECT 
                COUNT(*) AS total_threats,
                SUM(CASE WHEN event_level = 'CRITICAL' THEN 1 ELSE 0 END) AS critical_count,
                SUM(CASE WHEN event_level = 'WARNING' THEN 1 ELSE 0 END) AS warning_count
            FROM security_logs
            WHERE event_level IN ('CRITICAL', 'WARNING')
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats = $res ? $res->fetch_assoc() : [];

        $total_threats  = (int)($stats['total_threats'] ?? 0);
        $critical_count = (int)($stats['critical_count'] ?? 0);
        $warning_count  = (int)($stats['warning_count'] ?? 0);

        $latest_res = $conn->query("
            SELECT event_type, event_level, username, ip_address, description, created_at
            FROM security_logs
            WHERE event_level IN ('CRITICAL', 'WARNING')
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY id DESC
            LIMIT 3
        ");
        $recent_events = [];
        if ($latest_res) {
            while ($row = $latest_res->fetch_assoc()) {
                $recent_events[] = $row;
            }
        }

        echo json_encode([
            'total_threats'  => $total_threats,
            'critical_count' => $critical_count,
            'warning_count'  => $warning_count,
            'recent_events'  => $recent_events,
            'status'         => $critical_count > 0 ? 'critical' : ($warning_count > 0 ? 'warning' : 'secure')
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action endpoint']);
        break;
}
