<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../backend/helpers/sms_helper.php';

function assertPhoneRule(bool $cond, string $message): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

// 1. Philippine numbers (+63)
assertPhoneRule(sf_validate_booking_phone('09505223146', '+63') === null, 'PH 11-digit 09... passes');
assertPhoneRule(sf_validate_booking_phone('9505223146', '+63') === null, 'PH 10-digit 9... passes');
assertPhoneRule(sf_validate_booking_phone('+639505223146', '+63') === null, 'PH +639... passes');
assertPhoneRule(sf_validate_booking_phone('950522314', '+63') !== null, 'PH 9-digit short number fails');
assertPhoneRule(sf_validate_booking_phone('95052231467', '+63') !== null, 'PH 11-digit without leading 0 fails');

// 2. Kyrgyzstan (+996) — strictly 9 national digits
assertPhoneRule(sf_validate_booking_phone('9505223146', '+996') !== null, 'KG +996 with 10-digit PH number 9505223146 fails');
assertPhoneRule(sf_validate_booking_phone('555123456', '+996') === null, 'KG +996 with 9-digit number passes');
assertPhoneRule(sf_validate_booking_phone('0555123456', '+996') === null, 'KG +996 with trunk 0 + 9-digit number passes');
assertPhoneRule(sf_normalize_e164('0555123456', '+996') === '+996555123456', 'KG number normalizes cleanly to E.164');

// 3. Mismatched explicit + prefix
assertPhoneRule(sf_validate_booking_phone('+639505223146', '+996') !== null, 'Mismatched +63 prefix under +996 fails');

// 4. Other international dial codes
assertPhoneRule(sf_validate_booking_phone('4155550199', '+1') === null, 'US +1 10-digit passes');
assertPhoneRule(sf_validate_booking_phone('12345', '+1') !== null, 'US +1 5-digit fails');
assertPhoneRule(sf_validate_booking_phone('91234567', '+65') === null, 'SG +65 8-digit passes');
assertPhoneRule(sf_validate_booking_phone('912345678', '+65') !== null, 'SG +65 9-digit fails');

echo "ALL PHP PHONE VALIDATION ASSERTIONS PASSED!\n";
