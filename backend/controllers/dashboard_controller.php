<?php
require_once __DIR__ . '/../helpers/room_status_helper.php';

/**
 * Loads live front-desk metrics, expected arrivals, room availability grid,
 * room types, and overdue check-out alerts for the reception dashboard.
 */
function sfbc_handle_dashboard_page(mysqli $conn): array
{
    // Overdue check-out alert
    $overdueGuestNames = [];
    $overdueNowDisplay = date('M d, Y h:i A');
    $overdueTzName     = 'Asia/Manila';
    try {
        $ovRes = $conn->query(
            "SELECT guest_name FROM bookings
             WHERE status = 'Checked In'
               AND check_out < CURDATE()
             ORDER BY check_out ASC"
        );
        if ($ovRes) {
            while ($ovRow = $ovRes->fetch_assoc()) {
                $overdueGuestNames[] = (string)$ovRow['guest_name'];
            }
        }
    } catch (Throwable $ovErr) {
        $overdueGuestNames = [];
    }

    $isAdminUser = (($_SESSION['admin_role'] ?? 'receptionist') === 'admin');
    $checkoutTargetUrl = $isAdminUser ? 'admin_checkout' : 'checkout';

    // Calculate live dashboard metrics
    $checkinsToday = (int)($conn->query(
        "SELECT COUNT(*) as count FROM bookings WHERE DATE(check_in) = CURDATE() AND status != 'Cancelled'"
    )->fetch_assoc()['count'] ?? 0);
    $checkoutsToday = (int)($conn->query(
        "SELECT COUNT(*) as count FROM bookings WHERE DATE(check_out) = CURDATE() AND status = 'Checked In'"
    )->fetch_assoc()['count'] ?? 0);
    $pendingRsvps = (int)($conn->query(
        "SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending'"
    )->fetch_assoc()['count'] ?? 0);
    $totalGuests = (int)($conn->query(
        "SELECT SUM(guests_count) as count FROM bookings WHERE status = 'Checked In'"
    )->fetch_assoc()['count'] ?? 0);

    // Fetch current arrivals list
    $arrivals = [];
    $bookingsQuery = $conn->query("
        SELECT b.id, b.guest_name, b.guest_phone, b.guest_email, b.guest_type, b.accommodation_name, b.eta, b.status, b.room_id, b.check_in, b.check_out, r.room_number
        FROM bookings b
        LEFT JOIN rooms r ON r.id = b.room_id
        WHERE b.status = 'Pending' AND DATE(b.check_in) = CURDATE()
        ORDER BY b.id DESC
    ");
    if ($bookingsQuery && $bookingsQuery->num_rows > 0) {
        while ($row = $bookingsQuery->fetch_assoc()) {
            $arrivals[] = $row;
        }
    }

    $checkedInRoomIds = sf_get_checked_in_room_ids($conn);
    $reservedRoomIds  = sf_get_reserved_room_ids($conn);
    $roomStatusRows   = [];
    $occupiedRooms    = 0;
    $availableRooms   = 0;

    // Fetch full details of each room including who is currently staying or reserved
    $roomsQuery = $conn->query("
        SELECT r.id, r.room_number, r.status, r.type,
               b.id AS booking_id, b.guest_name, b.guest_phone, b.guest_email, b.guest_type, b.check_in, b.check_out, b.status AS booking_status
        FROM rooms r
        LEFT JOIN bookings b ON b.room_id = r.id AND b.status IN ('Checked In', 'Pending', 'Confirmed') AND b.check_in <= CURDATE() AND b.check_out >= CURDATE()
        ORDER BY r.room_number ASC
    ");

    if ($roomsQuery && $roomsQuery->num_rows > 0) {
        while ($room = $roomsQuery->fetch_assoc()) {
            $room['resolved_status'] = sf_resolve_room_display_status($room, $checkedInRoomIds, $reservedRoomIds);
            $roomStatusRows[] = $room;

            if ($room['resolved_status'] === 'occupied') {
                $occupiedRooms++;
            } elseif ($room['resolved_status'] === 'ready') {
                $availableRooms++;
            }
        }
    }

    // Fetch room types for the booking form dropdown
    $roomTypes = [];
    try {
        $rtQuery = $conn->query("SELECT id, name, price AS base_price FROM room_types ORDER BY id ASC");
        if ($rtQuery && $rtQuery->num_rows > 0) {
            while ($row = $rtQuery->fetch_assoc()) {
                $roomTypes[] = $row;
            }
        }
    } catch (Throwable $e) {
        $roomTypes = [];
    }

    if (empty($roomTypes)) {
        $roomTypes = [
            ['id' => 1, 'name' => 'beachview_duplex', 'base_price' => 3500],
            ['id' => 2, 'name' => 'seaview_duplex',   'base_price' => 4200],
            ['id' => 3, 'name' => 'beach_villa',      'base_price' => 5500],
            ['id' => 4, 'name' => 'standard_room',    'base_price' => 2800],
            ['id' => 5, 'name' => 'standard_king',    'base_price' => 3200],
        ];
    }

    $frontendDir = __DIR__ . '/../../frontend';

    return [
        'overdueGuestNames' => $overdueGuestNames,
        'overdueNowDisplay' => $overdueNowDisplay,
        'overdueTzName'     => $overdueTzName,
        'checkoutTargetUrl' => $checkoutTargetUrl,
        'checkinsToday'     => $checkinsToday,
        'checkoutsToday'    => $checkoutsToday,
        'pendingRsvps'      => $pendingRsvps,
        'totalGuests'       => $totalGuests,
        'arrivals'          => $arrivals,
        'roomStatusRows'    => $roomStatusRows,
        'occupiedRooms'     => $occupiedRooms,
        'availableRooms'    => $availableRooms,
        'roomTypes'         => $roomTypes,
        'defaultCheckin'    => date('Y-m-d'),
        'defaultCheckout'   => date('Y-m-d', strtotime('+1 day')),
        'csrfToken'         => get_csrf_token(),
        'versions'          => [
            'adminCss'     => filemtime($frontendDir . '/assets/css/admin.css'),
            'dashboardCss' => filemtime($frontendDir . '/assets/css/dashboard.css'),
            'pageCss'      => file_exists($frontendDir . '/assets/css/pages/dashboard.css')
                ? filemtime($frontendDir . '/assets/css/pages/dashboard.css')
                : 1,
            'pageJs'       => file_exists($frontendDir . '/assets/js/pages/dashboard.js')
                ? filemtime($frontendDir . '/assets/js/pages/dashboard.js')
                : 1,
        ],
    ];
}
