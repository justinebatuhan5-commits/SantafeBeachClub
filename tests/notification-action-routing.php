<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function check_notification_route(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$root = dirname(__DIR__);
$header = file_get_contents($root . '/frontend/partials/_page_header.php')
    . file_get_contents($root . '/frontend/assets/js/components/page-header.js');
$payments = file_get_contents($root . '/frontend/payments.php')
    . file_get_contents($root . '/frontend/assets/js/pages/payments.js');

check_notification_route(str_contains($header, 'resolveNotificationAction(data)'), 'Notification actions use centralized contextual routing');
check_notification_route(str_contains($header, "label: 'Review Payment'") && str_contains($header, "url: 'payments'"), 'Payment notifications open Payments');
check_notification_route(str_contains($header, "label: 'View Refund'") && str_contains($header, "url: 'admin_refunds'"), 'Refund notifications open Refund Requests');
check_notification_route(str_contains($header, "label: 'Review Discount'") && str_contains($header, "url: 'discount_requests.php'"), 'Discount notifications open Discount Requests');
check_notification_route(str_contains($header, "label: 'Open Check-in'") && str_contains($header, "label: 'Open Check-out'"), 'Arrival and departure notifications open their operational pages');
check_notification_route(str_contains($header, "label: 'View Rooms'") && str_contains($header, "url: 'accommodations'"), 'Room notifications open Accommodations');
check_notification_route(str_contains($payments, "new URLSearchParams(window.location.search).get('search')"), 'Payments applies the booking reference supplied by a notification');
