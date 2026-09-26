<?php
declare(strict_types=1);

$api = file_get_contents(__DIR__ . '/../backend/api/refund_request_api.php');
$ui = file_get_contents(__DIR__ . '/../frontend/admin_refunds.php');
if ($api === false || $ui === false) throw new RuntimeException('Unable to read refund files.');

foreach ([
    "require_once __DIR__ . '/../helpers/csrf_helper.php'",
    "(\$_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'",
    'verify_csrf_token()',
        "['submit_request', 'approve', 'reject', 'cancel_approval', 'process_paymongo', 'sync_paymongo', 'send_refund_link', 'update_refund_methods']",
] as $fragment) {
    if (!str_contains($api, $fragment)) {
        throw new RuntimeException('Missing refund request safeguard: ' . $fragment);
    }
}
if (substr_count($ui, "fd.append('csrf_token', refundCsrfToken)") < 4) {
    throw new RuntimeException('Admin refund state changes do not all send a CSRF token.');
}

echo "Every state-changing refund API action requires POST and CSRF validation.\n";
