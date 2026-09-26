<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function check_dashboard_checkout(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$root = dirname(__DIR__);
$receptionDashboard = file_get_contents($root . '/frontend/dashboard.php');
$analyticsApi = file_get_contents($root . '/backend/api/analytics_api.php');
$expected = "status = 'Checked In' AND DATE(check_out) = CURDATE()";

check_dashboard_checkout(
    str_contains($receptionDashboard, "DATE(check_out) = CURDATE() AND status = 'Checked In'"),
    'Reception dashboard counts only checked-in guests due to leave today'
);
check_dashboard_checkout(
    str_contains($analyticsApi, $expected),
    'Admin dashboard uses the same current-departure definition'
);
check_dashboard_checkout(
    !str_contains($receptionDashboard, "DATE(check_out) = CURDATE() AND status != 'Cancelled'"),
    'Future, pending, and already completed reservations are excluded from Today’s Check-outs'
);
