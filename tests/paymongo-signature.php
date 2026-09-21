<?php
require_once __DIR__ . '/../backend/helpers/paymongo_signature.php';
require_once __DIR__ . '/../backend/services/paymongo.php';

$secret = 'webhook-test-fixture-only';
$body = '{"data":{"attributes":{"type":"payment.paid"}}}';
$timestamp = '1750000000';
$signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
$live = "t=$timestamp,te=,li=$signature";
$test = "t=$timestamp,te=$signature,li=";
$cases = [
    ['valid live signature', $body, $live, $secret, true, true],
    ['valid test signature with empty live field', $body, $test, $secret, false, true],
    ['test signature rejected in live mode', $body, $test, $secret, true, false],
    ['live signature rejected in test mode', $body, $live, $secret, false, false],
    ['missing header', $body, '', $secret, true, false],
    ['missing secret', $body, $live, '', true, false],
    ['blank secret', $body, $live, '  ', true, false],
    ['wrong secret', $body, $live, 'wrong', true, false],
    ['missing timestamp', $body, "li=$signature", $secret, true, false],
    ['missing signature', $body, "t=$timestamp", $secret, true, false],
    ['empty signatures', $body, "t=$timestamp,te=,li=", $secret, true, false],
    ['invalid timestamp', $body, "t=invalid,li=$signature", $secret, true, false],
    ['invalid signature encoding', $body, "t=$timestamp,li=" . str_repeat('z', 64), $secret, true, false],
    ['short signature', $body, "t=$timestamp,li=abcd", $secret, true, false],
    ['duplicate timestamp', $body, "$live,t=$timestamp", $secret, true, false],
    ['duplicate signature', $body, "$live,li=$signature", $secret, true, false],
    ['malformed component', $body, "$live,broken", $secret, true, false],
    ['tampered body', $body . ' ', $live, $secret, true, false],
    ['empty body', '', $live, $secret, true, false],
];
foreach ($cases as [$name, $payload, $header, $key, $mode, $expected]) {
    if (verify_paymongo_signature($payload, $header, $key, $mode) !== $expected) {
        throw new RuntimeException("Failed: $name");
    }
}
$previous = getenv('PAYMONGO_WEBHOOK_SECRET');
try {
    putenv('PAYMONGO_WEBHOOK_SECRET');
    if (PayMongoService::getWebhookSecretKey() !== '') {
        throw new RuntimeException('Missing configuration must not use a hardcoded secret.');
    }
    putenv('PAYMONGO_WEBHOOK_SECRET=' . $secret);
    if (PayMongoService::getWebhookSecretKey() !== $secret) {
        throw new RuntimeException('Configured webhook secret was not used.');
    }
} finally {
    putenv($previous === false ? 'PAYMONGO_WEBHOOK_SECRET' : 'PAYMONGO_WEBHOOK_SECRET=' . $previous);
}
echo count($cases) . " signature cases and secret configuration checks passed.\n";
