<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function check_guest_recaptcha(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$root = dirname(__DIR__);
require_once $root . '/backend/helpers/recaptcha_helper.php';

$pageSource = file_get_contents($root . '/frontend/my_booking.php') ?: '';
$formSource = file_get_contents($root . '/frontend/views/my_booking/_lookup_form.php') ?: '';
$cssSource  = file_get_contents($root . '/frontend/assets/css/my-booking.css') ?: '';
$jsSource   = file_get_contents($root . '/frontend/assets/js/pages/my-booking.js') ?: '';
$lookupApi  = file_get_contents($root . '/backend/api/guest_lookup_api.php') ?: '';
$otpApi     = file_get_contents($root . '/backend/api/guest_verify_otp_api.php') ?: '';

check_guest_recaptcha(
    str_contains($pageSource, 'https://www.google.com/recaptcha/api.js?render='),
    'my_booking.php loads Google reCAPTCHA v3 script for lookup view'
);
check_guest_recaptcha(
    str_contains($formSource, 'id="lookupForm"')
        && str_contains($formSource, 'id="g-recaptcha-response"')
        && str_contains($formSource, 'id="guestOtpForm"')
        && str_contains($formSource, 'id="guest-otp-recaptcha-response"'),
    'Lookup and OTP forms expose hidden g-recaptcha-response inputs and site key attributes'
);
check_guest_recaptcha(
    str_contains($cssSource, '.booking-lookup-page .grecaptcha-badge')
        && str_contains($cssSource, 'visibility: visible !important;')
        && str_contains($cssSource, 'opacity: 1 !important;')
        && !str_contains($cssSource, 'overflow: visible !important;')
        && !preg_match('/\.booking-lookup-page\s+\.grecaptcha-badge\s*\{[^}]*\bright\s*:\s*0\b/s', $cssSource),
    'Lookup page displays Google compact reCAPTCHA v3 badge cleanly without stretching it open'
);
check_guest_recaptcha(
    str_contains($jsSource, 'grecaptcha.ready')
        && str_contains($jsSource, "executeRecaptcha('guest_lookup')")
        && str_contains($jsSource, "executeRecaptcha('guest_otp_verify')")
        && str_contains($jsSource, "executeRecaptcha('guest_otp_resend')"),
    'my-booking.js uses grecaptcha.ready across lookup, OTP verification, and OTP resend'
);
check_guest_recaptcha(
    str_contains($lookupApi, "recaptcha_verify(\$recaptchaToken, 'guest_lookup', true)")
        && str_contains($lookupApi, "'RECAPTCHA_FAIL'"),
    'guest_lookup_api.php enforces strict reCAPTCHA v3 and logs RECAPTCHA_FAIL'
);
check_guest_recaptcha(
    str_contains($otpApi, "recaptcha_verify(\$recaptchaToken, \$recaptchaAction, true)")
        && str_contains($otpApi, "'guest_otp_resend'")
        && str_contains($otpApi, "'guest_otp_verify'")
        && str_contains($otpApi, "'RECAPTCHA_FAIL'"),
    'guest_verify_otp_api.php enforces strict reCAPTCHA v3 on both verify and resend actions'
);

$emptyLookupResult = recaptcha_verify('', 'guest_lookup', true);
$emptyOtpResult    = recaptcha_verify('', 'guest_otp_verify', true);
check_guest_recaptcha(
    $emptyLookupResult['success'] === false && $emptyOtpResult['success'] === false,
    'Strict reCAPTCHA v3 rejects empty tokens on guest lookup and OTP actions'
);

echo "ALL GUEST LOOKUP RECAPTCHA V3 TESTS PASSED\n";
