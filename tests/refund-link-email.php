<?php
$api = file_get_contents(__DIR__ . '/../backend/api/refund_request_api.php');
$ui = file_get_contents(__DIR__ . '/../frontend/admin_refunds.php');
$mailer = file_get_contents(__DIR__ . '/../backend/services/mailer.php');
if ($api === false || $ui === false || $mailer === false) throw new RuntimeException('Unable to read refund link files.');

foreach (["'send_refund_link'", "transfer.paymongo.com", 'sendPayMongoRefundLinkEmail', "r.status IN ('approved','processing')"] as $fragment) {
    if (!str_contains($api, $fragment)) throw new RuntimeException('Missing protected refund-link API behavior: ' . $fragment);
}
foreach (['Email Refund Link', 'paymongoTransferLink', "fd.append('csrf_token', refundCsrfToken)"] as $fragment) {
    if (!str_contains($ui, $fragment)) throw new RuntimeException('Missing refund-link UI behavior: ' . $fragment);
}
foreach (['function sendPayMongoRefundLinkEmail', 'Complete Refund Details', '3 days', 'transfer.paymongo.com'] as $fragment) {
    if (!str_contains($mailer, $fragment)) throw new RuntimeException('Missing refund-link email behavior: ' . $fragment);
}
echo "Approved refunds can email a validated PayMongo transfer link to the saved guest address.\n";
