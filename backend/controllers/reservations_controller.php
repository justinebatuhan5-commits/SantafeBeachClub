<?php
/**
 * Reception reservations directory controller.
 */

/**
 * Fetches all bookings for the reception reservations table.
 */
function sfbc_handle_reservations_page(mysqli $conn): array
{
    $bookings = [];
    $result = $conn->query("SELECT id, guest_name, guest_email, guest_type, accommodation_name, check_in, check_out, status FROM bookings ORDER BY id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
    }

    return [
        'bookings' => $bookings,
    ];
}
