<?php
declare(strict_types=1);

$availability = file_get_contents(__DIR__ . '/../backend/api/availability.php');
$booking = file_get_contents(__DIR__ . '/../frontend/book.php');
$storage = file_get_contents(__DIR__ . '/../backend/helpers/private_storage_helper.php');
if ($availability === false || $booking === false || $storage === false) throw new RuntimeException('Unable to read implementation files.');

if (str_contains($availability, "json_encode(['error' => \$e->getMessage()])")) throw new RuntimeException('Availability API still exposes exceptions.');
if (str_contains($booking, "'DB Error: ' . \$e->getMessage()") || str_contains($booking, "'Error saving your booking: ' . \$e->getMessage()")) throw new RuntimeException('Booking page still exposes exceptions.');
if (str_contains($booking, "'http://' . \$_SERVER['HTTP_HOST']")) throw new RuntimeException('Booking URLs still trust the Host header.');
foreach (['booking_public_frontend_url()', '$storedReceiptPath', 'unlink($storedReceiptPath)', '$prospective'] as $fragment) {
    if (!str_contains($booking . $storage, $fragment)) throw new RuntimeException('Missing hardening safeguard: ' . $fragment);
}

echo "Public errors, booking URLs, receipt cleanup, and prospective storage validation are hardened.\n";
