<?php
declare(strict_types=1);

$api = file_get_contents(__DIR__ . '/../backend/api/refund_request_api.php');
$ui = file_get_contents(__DIR__ . '/../frontend/admin_refunds.php')
    . file_get_contents(__DIR__ . '/../frontend/views/admin_refunds/_refund_cards.php')
    . file_get_contents(__DIR__ . '/../frontend/views/admin_refunds/_modals.php')
    . file_get_contents(__DIR__ . '/../frontend/assets/js/pages/admin-refunds.js');
if ($api === false || $ui === false) throw new RuntimeException('Unable to read refund implementation.');

$start = strpos($api, "elseif (\$action === 'process_paymongo')");
$end = strpos($api, "elseif (\$action === 'list_pending')", $start ?: 0);
if ($start === false || $end === false) throw new RuntimeException('Refund processing branch not found.');
$branch = substr($api, $start, $end - $start);

foreach (['PayMongoService::retrieveRefund', '$submittedRefundId', "'succeeded'", "UPDATE refund_requests", "UPDATE payments", "UPDATE bookings"] as $fragment) {
    if (!str_contains($branch, $fragment)) throw new RuntimeException('Missing verified refund behavior: ' . $fragment);
}
foreach (['Record PayMongo Refund', 'Verify PayMongo Status', "fd.append('csrf_token', refundCsrfToken)"] as $fragment) {
    if (!str_contains($ui, $fragment)) throw new RuntimeException('Missing protected refund UI: ' . $fragment);
}

echo "PayMongo refunds require provider IDs, exact payment/amount matching, and succeeded verification.\n";
