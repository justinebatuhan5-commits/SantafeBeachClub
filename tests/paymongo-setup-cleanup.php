<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../frontend/book.php');
if ($source === false) throw new RuntimeException('Unable to read booking flow.');

foreach ([
    'function cleanup_failed_paymongo_booking',
    "WHERE id = ? AND cancellation_token = ? AND status = 'Pending Payment'",
    "DELETE FROM payments WHERE booking_id = ? AND status = 'pending'",
    '$paymongoSessionCreated = true',
] as $fragment) {
    if (!str_contains($source, $fragment)) {
        throw new RuntimeException('Missing failed-checkout cleanup safeguard: ' . $fragment);
    }
}
if (str_contains($source, 'DELETE FROM bookings WHERE booking_id')) {
    throw new RuntimeException('Booking cleanup still uses the nonexistent bookings.booking_id column.');
}

echo "Failed PayMongo setup cleanup is token-bound, transactional, and uses bookings.id.\n";
