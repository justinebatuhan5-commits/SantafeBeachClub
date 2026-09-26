<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function check_checkin_route(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$root = dirname(__DIR__);
$sidebar = file_get_contents($root . '/frontend/partials/_sidebar.php');
$header = file_get_contents($root . '/frontend/partials/_page_header.php');
$legacy = file_get_contents($root . '/frontend/checkin.php');
$admin = file_get_contents($root . '/frontend/admin_checkin.php');

check_checkin_route(str_contains($admin, "['admin', 'receptionist']"), 'Shared admin check-in page permits receptionists');
check_checkin_route(substr_count($sidebar, 'href="admin_checkin"') >= 2, 'Admin and receptionist sidebar links use the shared check-in page');
check_checkin_route(str_contains($sidebar, "const CHECKIN_URL = 'admin_checkin';"), 'QR check-in opens the shared check-in page');
check_checkin_route(str_contains($header, "var checkinPage = 'admin_checkin';"), 'Check-in notifications open the shared check-in page');
check_checkin_route(str_contains($legacy, "header('Location: admin_checkin'"), 'Old receptionist check-in links redirect safely to the shared page');
