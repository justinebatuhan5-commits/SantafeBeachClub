<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../backend/api/refund_request_api.php');
if ($source === false) {
    throw new RuntimeException('Unable to read refund API.');
}

$required = [
    "refund_admin_authenticated()",
    "refund_request_token()",
    "b.cancellation_token = ?",
    "WHERE booking_id = ? AND status = 'verified'",
];
foreach ($required as $fragment) {
    if (!str_contains($source, $fragment)) {
        throw new RuntimeException("Missing refund authorization safeguard: {$fragment}");
    }
}

if (str_contains($source, "intval(\$_POST['payment_id']")) {
    throw new RuntimeException('Refund API still trusts a client-supplied payment ID.');
}
if (str_contains($source, "trim(\$_POST['requested_by']") || str_contains($source, "trim(\$_POST['request_type']")) {
    throw new RuntimeException('Refund API still trusts client-supplied identity fields.');
}
if (!str_contains($source, '"iisssddd"')) {
    throw new RuntimeException('Refund insert parameter types do not cover all eight bound values.');
}

echo "Refund submission requires admin authentication or a booking token; payment ownership is resolved server-side.\n";
