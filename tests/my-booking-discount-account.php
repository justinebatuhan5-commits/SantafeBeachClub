<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../frontend/my_booking.php');
if ($source === false) throw new RuntimeException('Unable to read My Booking page.');

if (str_contains($source, "header('Location: discount_booking.php?booking='")) {
    throw new RuntimeException('Senior/PWD bookings are still forcibly redirected out of My Booking.');
}
foreach (['original_centavos', 'total_centavos', 'deposit_centavos', "status='verified'", 'Manage Discount &amp; Payment'] as $fragment) {
    if (!str_contains($source, $fragment)) {
        throw new RuntimeException("Missing discounted-account integration: {$fragment}");
    }
}

echo "Senior/PWD bookings remain in My Booking with authoritative discount totals.\n";
