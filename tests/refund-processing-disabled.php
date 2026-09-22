<?php
declare(strict_types=1);

$api = file_get_contents(__DIR__ . '/../backend/api/refund_request_api.php');
$ui = file_get_contents(__DIR__ . '/../frontend/admin_refunds.php');
if ($api === false || $ui === false) throw new RuntimeException('Unable to read refund implementation.');

$start = strpos($api, "elseif (\$action === 'process_paymongo')");
$end = strpos($api, "elseif (\$action === 'list_pending')", $start ?: 0);
if ($start === false || $end === false) throw new RuntimeException('Refund processing branch not found.');
$branch = substr($api, $start, $end - $start);

foreach (["http_response_code(501)", "'success'", "UPDATE refund_requests", "UPDATE payments", "UPDATE bookings", 'sendRefundCompletedEmail'] as $fragment) {
    $shouldExist = $fragment === 'http_response_code(501)';
    if (str_contains($branch, $fragment) !== $shouldExist) {
        throw new RuntimeException('Unsafe or missing disabled-refund behavior: ' . $fragment);
    }
}
if (!str_contains($ui, 'PayMongo Processing Unavailable') || !str_contains($ui, 'disabled title=')) {
    throw new RuntimeException('Admin UI still exposes active refund processing.');
}

echo "PayMongo refund processing is disabled without changing financial records.\n";
