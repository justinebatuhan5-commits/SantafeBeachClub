<?php

/**
 * Admin Dashboard controller: loads overdue checkout alerts and asset versions.
 */

function sfbc_handle_admin_dashboard_page(mysqli $conn): array
{
    $admin = $_SESSION['admin_username'] ?? 'Admin';
    $overdueGuestNames = [];
    $overdueNowDisplay = date('M d, Y h:i A');
    $overdueTzName     = 'Asia/Manila';

    try {
        $res = $conn->query(
            "SELECT guest_name FROM bookings
             WHERE status = 'Checked In'
               AND check_out < CURDATE()
             ORDER BY check_out ASC"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $overdueGuestNames[] = (string)$row['guest_name'];
            }
        }
    } catch (Throwable $e) {
        $overdueGuestNames = [];
    }

    $frontendRoot = dirname(__DIR__, 2) . '/frontend';

    return [
        'adminName' => $admin,
        'overdueGuestNames' => $overdueGuestNames,
        'overdueNowDisplay' => $overdueNowDisplay,
        'overdueTzName' => $overdueTzName,
        'adminCssVersion' => file_exists($frontendRoot . '/assets/css/admin.css') ? filemtime($frontendRoot . '/assets/css/admin.css') : 1,
        'adminCalendarCssVersion' => (int)@filemtime($frontendRoot . '/assets/css/admin_calendar.css'),
        'occupancyKpiJsVersion' => (int)@filemtime($frontendRoot . '/assets/js/occupancy_kpi.js'),
        'cssVersion' => file_exists($frontendRoot . '/assets/css/pages/admin-dashboard.css') ? filemtime($frontendRoot . '/assets/css/pages/admin-dashboard.css') : 1,
        'jsVersion' => file_exists($frontendRoot . '/assets/js/pages/admin-dashboard.js') ? filemtime($frontendRoot . '/assets/js/pages/admin-dashboard.js') : 1,
    ];
}
