<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/services/booking_service.php';

function date_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

date_check(isValidDateRange('2026-02-28', '2026-03-01'), 'Valid date range was rejected.');
date_check(isValidDateRange('2028-02-29', '2028-03-01'), 'Valid leap day was rejected.');

foreach (['2026-02-29', '2026-02-30', '2026-13-01', '2026-1-01', '2026-01-01 extra', '', 'not-a-date'] as $invalid) {
    date_check(!isValidDateRange($invalid, '2026-12-31'), "Invalid check-in date was accepted: {$invalid}");
    date_check(!isValidDateRange('2026-01-01', $invalid), "Invalid check-out date was accepted: {$invalid}");
}

date_check(!isValidDateRange('2026-05-01', '2026-05-01'), 'Zero-night stay was accepted.');
date_check(!isValidDateRange('2026-05-02', '2026-05-01'), 'Backward date range was accepted.');

echo "Booking dates require exact valid Y-m-d values and an increasing range.\n";

$bookingPage = file_get_contents(__DIR__ . '/../frontend/book.php');
date_check($bookingPage !== false && str_contains($bookingPage, 'if (!isValidDateRange((string)$checkin, (string)$checkout))'), 'Public booking page does not reject invalid dates before pricing.');
