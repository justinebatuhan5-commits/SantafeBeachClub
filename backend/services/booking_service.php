<?php

require_once __DIR__ . '/../config/db_pdo.php';

function isValidDateRange(string $checkIn, string $checkOut): bool
{
    $start = DateTimeImmutable::createFromFormat('Y-m-d', $checkIn);
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $checkOut);

    if (!$start || !$end) {
        return false;
    }

    return $start < $end;
}

function getAvailableRooms(PDO $pdo, int $roomTypeId, string $checkIn, string $checkOut): int
{
    if (!isValidDateRange($checkIn, $checkOut)) {
        return 0;
    }

    $roomTypeStmt = $pdo->prepare('SELECT total_rooms FROM room_types WHERE id = :id');
    $roomTypeStmt->execute([':id' => $roomTypeId]);
    $roomType = $roomTypeStmt->fetch();

    if (!$roomType) {
        return 0;
    }

    $overlapStmt = $pdo->prepare(
        "SELECT COUNT(*) AS overlap_count
         FROM bookings b
         LEFT JOIN rooms r ON r.id = b.room_id
         WHERE (
             b.room_type_id = :room_type_id
             OR (b.room_id IS NOT NULL AND r.type = (SELECT rt.name FROM room_types rt WHERE rt.id = :room_type_id LIMIT 1))
             OR (
                 b.room_type_id IS NULL
                 AND LOWER(COALESCE(b.accommodation_name, '')) LIKE CONCAT('%', LOWER((SELECT rt.name FROM room_types rt WHERE rt.id = :room_type_id LIMIT 1)), '%')
             )
         )
           AND LOWER(COALESCE(b.status, '')) NOT IN ('cancelled', 'canceled', 'checked out')
           AND LOWER(COALESCE(b.status, '')) IN ('confirmed', 'checked in', 'pending payment', 'pending')
           AND b.check_in < :requested_checkout
           AND b.check_out > :requested_checkin"
    );

    // Overlap rule:
    // A booking blocks inventory if its date range intersects the requested range.
    // That happens when:
    //   existing check-in is before the requested check-out
    //   AND existing check-out is after the requested check-in
    // This excludes back-to-back stays from being counted as conflicts.
    $overlapStmt->execute([
        ':room_type_id' => $roomTypeId,
        ':requested_checkout' => $checkOut,
        ':requested_checkin' => $checkIn,
    ]);

    $overlapCount = (int) $overlapStmt->fetchColumn();
    $available = (int) $roomType['total_rooms'] - $overlapCount;

    return max(0, $available);
}

// $staffStatus must only be supplied by an authenticated, CSRF-protected staff endpoint.
// Public request data never controls the initial booking status.
function createBooking(PDO $pdo, array $data, string $staffStatus = 'Pending'): array
{
    $roomTypeId = (int) ($data['room_type_id'] ?? 0);
    $checkIn = $data['check_in'] ?? '';
    $checkOut = $data['check_out'] ?? '';
    $guestName = trim((string) ($data['guest_name'] ?? ''));
    $guestEmail = trim((string) ($data['guest_email'] ?? ''));
    if (!in_array($staffStatus, ['Pending', 'Checked In'], true)) {
        throw new InvalidArgumentException('Invalid initial booking status.');
    }
    $status = strtolower($staffStatus);

    if ($roomTypeId <= 0 || $checkIn === '' || $checkOut === '' || $guestName === '') {
        throw new InvalidArgumentException('Missing required booking data.');
    }

    if (!isValidDateRange($checkIn, $checkOut)) {
        throw new InvalidArgumentException('check_out must be later than check_in.');
    }

    $pdo->beginTransaction();

    try {
        $lockRoomTypeStmt = $pdo->prepare('SELECT id, total_rooms FROM room_types WHERE id = :id FOR UPDATE');
        $lockRoomTypeStmt->execute([':id' => $roomTypeId]);
        $roomType = $lockRoomTypeStmt->fetch();

        if (!$roomType) {
            throw new RuntimeException('Room type not found.');
        }

        $overlapStmt = $pdo->prepare(
            "SELECT COUNT(*) AS overlap_count
             FROM bookings b
             LEFT JOIN rooms r ON r.id = b.room_id
             WHERE (
                 b.room_type_id = :room_type_id
                 OR (b.room_id IS NOT NULL AND r.type = (SELECT rt.name FROM room_types rt WHERE rt.id = :room_type_id LIMIT 1))
                 OR (
                     b.room_type_id IS NULL
                     AND LOWER(COALESCE(b.accommodation_name, '')) LIKE CONCAT('%', LOWER((SELECT rt.name FROM room_types rt WHERE rt.id = :room_type_id LIMIT 1)), '%')
                 )
             )
               AND LOWER(COALESCE(b.status, '')) NOT IN ('cancelled', 'canceled', 'checked out')
               AND LOWER(COALESCE(b.status, '')) IN ('confirmed', 'checked in', 'pending payment', 'pending')
               AND b.check_in < :requested_checkout
               AND b.check_out > :requested_checkin
             FOR UPDATE"
        );
        $overlapStmt->execute([
            ':room_type_id' => $roomTypeId,
            ':requested_checkout' => $checkOut,
            ':requested_checkin' => $checkIn,
        ]);

        $overlapCount = (int) $overlapStmt->fetchColumn();
        $available = max(0, (int) $roomType['total_rooms'] - $overlapCount);

        if ($available <= 0) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Sold out',
                'available' => 0,
            ];
        }

        $roomTypeNameStmt = $pdo->prepare('SELECT name FROM room_types WHERE id = :id');
        $roomTypeNameStmt->execute([':id' => $roomTypeId]);
        $roomTypeName = (string) $roomTypeNameStmt->fetchColumn();

        // Find an available room that is not under maintenance and has no overlapping booking.
        $roomStmt = $pdo->prepare("
            SELECT r.id, r.name
            FROM rooms r
            WHERE r.type = :type
              AND r.status <> 'maintenance'
              AND NOT EXISTS (
                  SELECT 1
                  FROM bookings b
                  WHERE b.room_id = r.id
                    AND LOWER(b.status) IN ('confirmed', 'checked in', 'pending', 'pending payment')
                    AND b.check_in < :checkout
                    AND b.check_out > :checkin
              )
            ORDER BY CASE WHEN r.status = 'ready' THEN 0 ELSE 1 END, r.room_number ASC
            LIMIT 1
        ");
        $roomStmt->execute([
            ':type' => $roomTypeName,
            ':checkout' => $checkOut,
            ':checkin' => $checkIn
        ]);
        $roomRow = $roomStmt->fetch();
        $roomId = $roomRow ? (int)$roomRow['id'] : null;
        $dbAccName = $roomRow ? $roomRow['name'] : ($roomTypeName !== '' ? $roomTypeName : 'Room Type');

        // Auto-detect guest tier: count prior non-cancelled bookings by email (or name)
        if ($guestEmail !== '') {
            $tierStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guest_email = :e AND status NOT IN ('Cancelled','Pending Payment')");
            $tierStmt->execute([':e' => $guestEmail]);
        } else {
            $tierStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guest_name = :n AND status NOT IN ('Cancelled','Pending Payment')");
            $tierStmt->execute([':n' => $guestName]);
        }
        $tierCount = (int) $tierStmt->fetchColumn();
        if ($tierCount === 0)     $autoGuestType = 'First Visit';
        elseif ($tierCount === 1) $autoGuestType = 'Returning Guest';
        else                      $autoGuestType = 'VIP Member';
        // Allow caller to override the detected type if explicitly provided
        $resolvedGuestType = ($data['guest_type'] ?? '') !== '' ? $data['guest_type'] : $autoGuestType;

        $insertStmt = $pdo->prepare(
            'INSERT INTO bookings (room_type_id, guest_type, check_in, check_out, guests_count, room_id, accommodation_name, eta, status, guest_name, guest_email)
             VALUES (:room_type_id, :guest_type, :check_in, :check_out, :guests_count, :room_id, :accommodation_name, :eta, :status, :guest_name, :guest_email)'
        );
        $insertStmt->execute([
            ':room_type_id' => $roomTypeId,
            ':guest_type' => $resolvedGuestType,
            ':check_in' => $checkIn,
            ':check_out' => $checkOut,
            ':guests_count' => (int) ($data['guests_count'] ?? 1),
            ':room_id' => $roomId,
            ':accommodation_name' => $dbAccName,
            ':eta' => $data['eta'] ?? '14:00',
            ':status' => ($status === 'checked in') ? 'Checked In' : (($status === 'pending') ? 'Pending' : ucwords($status)),
            ':guest_name' => $guestName,
            ':guest_email' => $guestEmail !== '' ? $guestEmail : null,
        ]);

        $bookingId = (int) $pdo->lastInsertId();

        // If status is checked in immediately, update the physical room's status to occupied in the rooms table
        if ($status === 'checked in' && $roomId !== null) {
            $updateRoomStmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = :room_id");
            $updateRoomStmt->execute([':room_id' => $roomId]);
        }

        $pdo->commit();

        return [
            'success' => true,
            'booking_id' => $bookingId,
            'available' => $available - 1,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
