<?php
// Run: php backend/scripts/test_inquiry_guard.php (no application database required).
require_once __DIR__ . '/../helpers/inquiry_guard.php';
require_once __DIR__ . '/../helpers/recaptcha_helper.php';
$now = 10000;
$tokens = ['valid' => $now - 10, 'fast' => $now - 1, 'expired' => $now - 7201];
$valid = ['guest_name' => 'Guest', 'guest_email' => 'guest@example.com', 'subject' => 'Room availability', 'message' => 'Do you have a room available next weekend?', 'website' => '', 'inquiry_token' => 'valid'];
$cases = [
    'valid guest' => [$valid, true],
    'honeypot' => [array_replace($valid, ['website' => 'https://spam.example']), false],
    'missing token' => [array_replace($valid, ['inquiry_token' => '']), false],
    'replayed token' => [array_replace($valid, ['inquiry_token' => 'used']), false],
    'too fast' => [array_replace($valid, ['inquiry_token' => 'fast']), false],
    'expired' => [array_replace($valid, ['inquiry_token' => 'expired']), false],
    'invalid email' => [array_replace($valid, ['guest_email' => 'bad']), false],
    'oversized message' => [array_replace($valid, ['message' => str_repeat('x', 5001)]), false],
    'empty message' => [array_replace($valid, ['message' => '  ']), false],
];
foreach (array_keys($valid) as $field) {
    $cases['array ' . $field] = [array_replace($valid, [$field => ['unexpected']]), false];
}
foreach ($cases as $name => [$input, $expected]) {
    if ((inquiry_validate($input, $tokens, $now) === '') !== $expected) {
        throw new RuntimeException('FAILED: ' . $name);
    }
}
if (recaptcha_verify('', 'guest_inquiry', true)['success']) {
    throw new RuntimeException('Missing CAPTCHA must be rejected');
}
echo count($cases) . " validation cases and missing CAPTCHA check passed.\n";
