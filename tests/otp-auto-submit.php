<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function check_otp_auto_submit(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$root = dirname(__DIR__);
$staffOtp = file_get_contents($root . '/frontend/verify_otp.php')
    . "\n" . file_get_contents($root . '/frontend/views/auth/_verify_otp.php')
    . "\n" . file_get_contents($root . '/frontend/assets/js/pages/verify-otp.js');
$guestOtp = file_get_contents($root . '/frontend/my_booking.php')
    . "\n" . file_get_contents($root . '/frontend/assets/js/pages/my-booking.js');
$refundOtp = file_get_contents($root . '/frontend/refund_details.php')
    . "\n" . file_get_contents($root . '/frontend/views/refund_details/_card.php')
    . "\n" . file_get_contents($root . '/frontend/assets/js/pages/refund-details.js');

check_otp_auto_submit(
    str_contains($staffOtp, 'maybeAutoSubmitOtp();')
        && str_contains($staffOtp, 'otpForm.requestSubmit();'),
    'Staff sign-in submits automatically after all six digits are entered'
);
check_otp_auto_submit(
    str_contains($guestOtp, 'maybeAutoSubmitGuestOtp();')
        && str_contains($guestOtp, 'guestOtpForm.requestSubmit();'),
    'Guest booking verification submits automatically after all six digits are entered'
);
check_otp_auto_submit(
    str_contains($refundOtp, "if(e.target.value.length===6)verifyRefundOtp()"),
    'Refund email verification submits automatically after the six-digit code is entered or pasted'
);
check_otp_auto_submit(
    str_contains($staffOtp, 'otpVerificationPending')
        && str_contains($guestOtp, 'guestOtpVerificationPending')
        && str_contains($refundOtp, 'refundOtpVerificationPending'),
    'All OTP screens prevent duplicate verification requests'
);
