<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/helpers/date_helper.php';
require_once __DIR__ . '/../backend/helpers/country_codes.php';

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            "ASSERTION FAILED: {$message}\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true) . "\n"
        );
        exit(1);
    }
}

$tz = new DateTimeZone('Asia/Manila');
$afternoonNow = new DateTimeImmutable('2026-11-15 17:31:00', $tz);
$lateNightNow = new DateTimeImmutable('2026-11-15 22:15:00', $tz);

// 1. Same-day booking at 17:31 must exclude 14:00, 15:00, 16:00, 17:00
$todayOptions = sf_available_eta_options('2026-11-15', $afternoonNow);
assertSameValue(['18:00', '20:00', '22:00'], array_keys($todayOptions), 'Same-day options after 17:31 exclude past ETAs');
assertSameValue('18:00', sf_default_booking_eta('2026-11-15', $afternoonNow, '14:00'), 'Stale 14:00 ETA resets to earliest remaining slot (18:00)');
assertSameValue('20:00', sf_default_booking_eta('2026-11-15', $afternoonNow, '20:00'), 'Upcoming 20:00 ETA is preserved');
assertSameValue(false, sf_is_valid_booking_eta('14:00', '2026-11-15', $afternoonNow), '14:00 is rejected for same-day booking at 17:31');
assertSameValue(false, sf_is_valid_booking_eta('17:00', '2026-11-15', $afternoonNow), '17:00 is rejected for same-day booking at 17:31');
assertSameValue(true, sf_is_valid_booking_eta('18:00', '2026-11-15', $afternoonNow), '18:00 is allowed for same-day booking at 17:31');
assertSameValue(false, sf_is_valid_booking_eta('23:00', '2026-11-15', $afternoonNow), 'Uncataloged ETA value is rejected');

// 2. Late-night same-day booking at 22:15 preserves only 22:00 (10:00 PM+)
$lateOptions = sf_available_eta_options('2026-11-15', $lateNightNow);
assertSameValue(['22:00'], array_keys($lateOptions), 'Late-night same-day booking preserves 22:00 (10:00 PM+) slot');
assertSameValue('22:00', sf_default_booking_eta('2026-11-15', $lateNightNow, '14:00'), 'Late-night default ETA resolves to 22:00');
assertSameValue(true, sf_is_valid_booking_eta('22:00', '2026-11-15', $lateNightNow), '22:00 remains valid after 22:00');
assertSameValue(false, sf_is_valid_booking_eta('20:00', '2026-11-15', $lateNightNow), '20:00 is rejected at 22:15');

// 3. Future check-in date preserves full catalog and 14:00 default
$tomorrowOptions = sf_available_eta_options('2026-11-16', $afternoonNow);
assertSameValue(['14:00', '15:00', '16:00', '17:00', '18:00', '20:00', '22:00'], array_keys($tomorrowOptions), 'Tomorrow check-in keeps all 7 ETA slots');
assertSameValue('14:00', sf_default_booking_eta('2026-11-16', $afternoonNow), 'Tomorrow check-in defaults to 14:00');
assertSameValue(true, sf_is_valid_booking_eta('14:00', '2026-11-16', $afternoonNow), '14:00 is valid for tomorrow check-in');

// 4. Step 2 view renders filtered ETA options without past options
if (!function_exists('sfbc_get_booking_countries')) {
    function sfbc_get_booking_countries(): array
    {
        return ['Philippines', 'United States'];
    }
}

$renderStep2 = static function (array $eta_options, string $selected_eta, bool $is_same_day_checkin): string {
    $error = '';
    $step = 2;
    $rooms = [];
    $selected_room_id = 0;
    $checkin = '2026-11-15';
    $checkout = '2026-11-16';
    $adults = 2;
    $children = 0;
    ob_start();
    include __DIR__ . '/../frontend/views/book/step2_guest.php';
    return (string) ob_get_clean();
};

$step2Html = $renderStep2(
    $todayOptions,
    sf_default_booking_eta('2026-11-15', $afternoonNow, '14:00'),
    true
);
assertSameValue(false, str_contains($step2Html, 'value="14:00"'), 'Step 2 HTML omits 14:00 option for 17:31 same-day booking');
assertSameValue(false, str_contains($step2Html, 'value="17:00"'), 'Step 2 HTML omits 17:00 option for 17:31 same-day booking');
assertSameValue(true, str_contains($step2Html, 'value="18:00" selected'), 'Step 2 HTML auto-selects 18:00 option');
assertSameValue(true, str_contains($step2Html, 'Same-day check-in: past arrival times for today are hidden.'), 'Step 2 HTML displays same-day ETA helper notice');

// 5. Controller contract checks for server-side enforcement across all Step 2/3 submit paths
$controllerSource = file_get_contents(__DIR__ . '/../backend/controllers/booking_controller.php');
assertSameValue(true, is_string($controllerSource), 'Readable booking_controller.php');
assertSameValue(true, substr_count($controllerSource, 'sf_is_valid_booking_eta(') >= 3, 'Controller validates ETA across go_step_3, request_discount, and confirm_booking');
assertSameValue(true, str_contains($controllerSource, "'eta_options'            => \$eta_options"), 'Controller exposes filtered eta_options to views');
assertSameValue(true, str_contains($controllerSource, "'selected_eta'           => \$selected_eta"), 'Controller exposes selected_eta to views');
assertSameValue(true, str_contains($controllerSource, "'is_same_day_checkin'    => \$is_same_day_checkin"), 'Controller exposes is_same_day_checkin flag to views');

echo "same-day-eta-validation.php: ALL PASSED\n";
