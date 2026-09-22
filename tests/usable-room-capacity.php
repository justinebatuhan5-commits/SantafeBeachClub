<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../backend/services/booking_service.php');
if ($source === false) throw new RuntimeException('Unable to read booking service.');

foreach ([
    "SELECT COUNT(*) FROM rooms WHERE type = :type AND status <> 'maintenance'",
    '$usableCapacity - $overlapCount',
    "r.status <> 'maintenance'",
    '$usableCapacity = count($lockedUsableRooms)',
] as $fragment) {
    if (!str_contains($source, $fragment)) {
        throw new RuntimeException('Missing usable-capacity safeguard: ' . $fragment);
    }
}
if (str_contains($source, "(int) \$roomType['total_rooms'] - \$overlapCount")) {
    throw new RuntimeException('Availability still relies on catalog total_rooms.');
}

echo "Availability capacity is derived from non-maintenance physical rooms.\n";
