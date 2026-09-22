<?php
declare(strict_types=1);

$api = file_get_contents(__DIR__ . '/../backend/api/refund_request_api.php');
$ui = file_get_contents(__DIR__ . '/../frontend/admin_refunds.php');
if ($api === false || $ui === false) throw new RuntimeException('Unable to read refund files.');

foreach (["\$action === 'cancel_approval'", "WHERE r.id = ? AND r.status = 'approved'", "SET status='rejected'", "refund_status='rejected'", 'Refund Approval Cancelled'] as $fragment) {
    if (!str_contains($api, $fragment)) throw new RuntimeException('Missing cancellation safeguard: ' . $fragment);
}
if (!str_contains($ui, 'Cancel Refund Approval') || !str_contains($ui, 'cancelRefundApproval(')) {
    throw new RuntimeException('Approved refund requests do not expose the cancellation control.');
}

echo "Approved refunds can be cancelled without changing the payment or booking status.\n";
