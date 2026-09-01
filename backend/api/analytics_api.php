<?php
/**
 * analytics_api.php — Native PHP Analytics & Executive KPI API
 * Replaces external Flask dependency so the dashboard loads instantly from MySQL directly.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/api_auth_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../helpers/room_status_helper.php';

require_api_auth($conn, 'admin');
RateLimiter::enforce($conn, 'analytics_api', 120, 60);

$action = $_GET['action'] ?? '';

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
        $reserved_ids   = sf_get_reserved_room_ids($conn);

        $occupied_rooms = count($checked_in_ids);
        $reserved_rooms = count($reserved_ids);

        $occupancy_rate = ($total_rooms > 0) ? round(($occupied_rooms / $total_rooms) * 100, 1) . '%' : '0%';

        // Bookings counters
        $total_bookings_res = $conn->query("SELECT COUNT(*) AS c FROM bookings");
        $total_bookings = (int)($total_bookings_res ? $total_bookings_res->fetch_assoc()['c'] : 0);

        $pending_bookings_res = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status = 'Pending'");
        $pending_bookings = (int)($pending_bookings_res ? $pending_bookings_res->fetch_assoc()['c'] : 0);

        $pending_payments_res = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status = 'pending'");
        $pending_payments = (int)($pending_payments_res ? $pending_payments_res->fetch_assoc()['c'] : 0);

        $checkins_today_res = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status = 'Pending' AND DATE(check_in) = CURDATE()");
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
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dayLabel = date('D', strtotime($date));
            $labels[] = $dayLabel;

            $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS amt FROM payments WHERE status = 'verified' AND DATE(paid_at) = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $amt = (float)($stmt->get_result()->fetch_assoc()['amt'] ?? 0);
            $stmt->close();

            $data[] = $amt;
        }

        echo json_encode([
            'labels' => $labels,
            'data'   => $data,
        ]);
        break;

    // ── 3. Status Breakdown Doughnut Chart ────────────────────────────────────
    case 'status-breakdown':
        $res = $conn->query("SELECT status, COUNT(*) AS count FROM bookings GROUP BY status");
        $breakdown = [
            'Checked In'  => 0,
            'Checked Out' => 0,
            'Pending'     => 0,
            'Cancelled'   => 0,
        ];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $status = $row['status'];
                if (isset($breakdown[$status])) {
                    $breakdown[$status] = (int)$row['count'];
                }
            }
        }
        echo json_encode($breakdown);
        break;

    // ── 4. Room Type Occupancy Bar Chart ──────────────────────────────────────
    case 'room-type-occupancy':
        $labels = [];
        $data = [];

        $rt_res = $conn->query("SELECT name, total_rooms FROM room_types ORDER BY id ASC");
        if ($rt_res) {
            while ($rt = $rt_res->fetch_assoc()) {
                $typeName = $rt['name'];
                $cleanName = ucwords(str_replace('_', ' ', $typeName));
                $totalForType = (int)$rt['total_rooms'];

                // Count occupied rooms of this type
                $occ_stmt = $conn->prepare("
                    SELECT COUNT(*) AS occ
                    FROM bookings b
                    JOIN rooms r ON b.room_id = r.id
                    WHERE b.status = 'Checked In' AND r.type = ?
                ");
                $occ_stmt->bind_param("s", $typeName);
                $occ_stmt->execute();
                $occCount = (int)($occ_stmt->get_result()->fetch_assoc()['occ'] ?? 0);
                $occ_stmt->close();

                $pct = ($totalForType > 0) ? round(($occCount / $totalForType) * 100) : 0;

                $labels[] = $cleanName;
                $data[] = $pct;
            }
        }

        echo json_encode([
            'labels' => $labels,
            'data'   => $data,
        ]);
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

    // ── 8. Dashboard KPI Stats ──────────────────────────────────────────────
    case 'dashboard-stats':
        $rev_res = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM payments WHERE status = 'verified'");
        $total_revenue = (float)($rev_res ? $rev_res->fetch_assoc()['total_revenue'] : 0);

        $bk_res = $conn->query("SELECT COUNT(*) AS total_bookings FROM bookings");
        $total_bookings = (int)($bk_res ? $bk_res->fetch_assoc()['total_bookings'] : 0);

        $pb_res = $conn->query("SELECT COUNT(*) AS pending_bookings FROM bookings WHERE status = 'Pending'");
        $pending_bookings = (int)($pb_res ? $pb_res->fetch_assoc()['pending_bookings'] : 0);

        $g_res = $conn->query("SELECT COALESCE(SUM(guests_count), 0) AS total_guests FROM bookings");
        $total_guests = (int)($g_res ? $g_res->fetch_assoc()['total_guests'] : 0);

        $avg_res = $conn->query("SELECT ROUND(AVG(DATEDIFF(check_out, check_in)), 1) AS avg_stay FROM bookings WHERE status != 'Cancelled'");
        $avg_stay = (float)($avg_res ? ($avg_res->fetch_assoc()['avg_stay'] ?? 0) : 0);

        // Occupancy Rate
        $total_rooms_res = $conn->query("SELECT COUNT(*) AS c FROM rooms");
        $total_rooms = (int)($total_rooms_res ? $total_rooms_res->fetch_assoc()['c'] : 0);
        $checked_in_ids = sf_get_checked_in_room_ids($conn);
        $occupied_rooms = count($checked_in_ids);
        $occupancy_rate = ($total_rooms > 0) ? round(($occupied_rooms / $total_rooms) * 100, 1) : 0.0;

        echo json_encode([
            'total_revenue'    => $total_revenue,
            'total_bookings'   => $total_bookings,
            'pending_bookings' => $pending_bookings,
            'total_guests'     => $total_guests,
            'avg_stay'         => $avg_stay,
            'occupancy_rate'   => $occupancy_rate,
            'occupied_rooms'   => $occupied_rooms,
            'total_rooms'      => $total_rooms,
        ]);
        break;

    // ── 9. Monthly Revenue ───────────────────────────────────────────────────
    case 'monthly-revenue':
        $labels = [];
        $revenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthTime = strtotime("-$i months");
            $m = date('Y-m', $monthTime);
            $label = date('M Y', $monthTime);

            $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS v FROM payments WHERE status = 'verified' AND DATE_FORMAT(paid_at, '%Y-%m') = ?");
            $stmt->bind_param("s", $m);
            $stmt->execute();
            $val = (float)($stmt->get_result()->fetch_assoc()['v'] ?? 0);
            $stmt->close();

            $labels[] = $label;
            $revenue[] = $val;
        }
        echo json_encode([
            'labels'  => $labels,
            'revenue' => $revenue,
        ]);
        break;

    // ── 10. Top Accommodations ───────────────────────────────────────────────
    case 'top-accommodations':
        $res = $conn->query("
            SELECT accommodation_name, COUNT(*) AS cnt, COALESCE(SUM(guests_count), 0) AS guests
            FROM bookings
            GROUP BY accommodation_name
            ORDER BY cnt DESC
            LIMIT 8
        ");
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = [
                    'accommodation_name' => $row['accommodation_name'],
                    'cnt'                => (int)$row['cnt'],
                    'guests'             => (int)$row['guests'],
                ];
            }
        }
        echo json_encode($data);
        break;

    // ── 11. Payment Methods Breakdown ────────────────────────────────────────
    case 'payment-methods':
        $res = $conn->query("
            SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
            FROM payments
            WHERE status = 'verified'
            GROUP BY payment_method
            ORDER BY total DESC
        ");
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
        echo json_encode($data);
        break;

    // ── 12. Accommodation Popularity ─────────────────────────────────────────
    case 'accommodation-popularity':
        $res = $conn->query("
            SELECT accommodation_name, COUNT(id) AS booking_count
            FROM bookings
            GROUP BY accommodation_name
            ORDER BY booking_count DESC
        ");
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = [
                    'accommodation_name' => $row['accommodation_name'],
                    'booking_count'      => (int)$row['booking_count'],
                ];
            }
        }
        echo json_encode($data);
        break;

    // ── 13. Country Demographics & Guest Origins ──────────────────────────────
    case 'country-demographics':
        $sql = "
            SELECT 
                COALESCE(NULLIF(TRIM(b.guest_country), ''), 'Philippines') AS country,
                COUNT(DISTINCT b.id) AS total_bookings,
                COUNT(DISTINCT b.guest_email) AS unique_guests,
                COALESCE(SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END), 0) AS total_revenue
            FROM bookings b
            LEFT JOIN payments p ON b.id = p.booking_id
            GROUP BY country
            ORDER BY total_bookings DESC, total_revenue DESC
        ";
        $res = $conn->query($sql);
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
                    'country'       => $row['country'],
                    'bookings'      => $bCount,
                    'guests'        => (int)$row['unique_guests'],
                    'revenue'       => $rSum,
                ];
            }
        }

        // Calculate percentages
        foreach ($countries as &$c) {
            $c['share_pct'] = $totalBookingsAll > 0 ? round(($c['bookings'] / $totalBookingsAll) * 100, 1) : 0;
        }
        unset($c);

        echo json_encode([
            'countries'      => $countries,
            'total_bookings' => $totalBookingsAll,
            'total_revenue'  => $totalRevenueAll,
            'total_origins'  => count($countries),
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
