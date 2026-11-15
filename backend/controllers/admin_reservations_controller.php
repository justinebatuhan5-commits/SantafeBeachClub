<?php
/**
 * Admin reservations register controller.
 *
 * Fetches filtered bookings and summary counts for the admin register view.
 */

/**
 * Loads bookings and status counts for the admin reservations page.
 */
function sfbc_handle_admin_reservations_page(mysqli $conn): array
{
    $allowedStatuses = ['Pending', 'Confirmed', 'Checked In', 'Checked Out', 'Cancelled'];
    $selectedStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
    if (!in_array($selectedStatus, $allowedStatuses, true)) {
        $selectedStatus = '';
    }

    $bookings = [];
    if ($selectedStatus === '') {
        $result = $conn->query("SELECT id, guest_name, guest_email, guest_type, accommodation_name, check_in, check_out, status, cancellation_reason FROM bookings ORDER BY id DESC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
        }
    } elseif ($selectedStatus === 'Pending') {
        $result = $conn->query("SELECT id, guest_name, guest_email, guest_type, accommodation_name, check_in, check_out, status, cancellation_reason FROM bookings WHERE status IN ('Pending', 'Pending Payment') ORDER BY id DESC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
        }
    } else {
        $stmt = $conn->prepare("SELECT id, guest_name, guest_email, guest_type, accommodation_name, check_in, check_out, status, cancellation_reason FROM bookings WHERE status = ? ORDER BY id DESC");
        $stmt->bind_param('s', $selectedStatus);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
        }
        $stmt->close();
    }

    $totalCount     = (int)($conn->query("SELECT COUNT(*) as c FROM bookings")->fetch_assoc()['c'] ?? 0);
    $pendingCount   = (int)($conn->query("SELECT COUNT(*) as c FROM bookings WHERE status IN ('Pending', 'Pending Payment')")->fetch_assoc()['c'] ?? 0);
    $confirmedCount = (int)($conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='Confirmed'")->fetch_assoc()['c'] ?? 0);
    $checkedInCount = (int)($conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='Checked In'")->fetch_assoc()['c'] ?? 0);

    return [
        'selectedStatus'  => $selectedStatus,
        'bookings'        => $bookings,
        'totalCount'      => $totalCount,
        'pendingCount'    => $pendingCount,
        'confirmedCount'  => $confirmedCount,
        'checkedInCount'  => $checkedInCount,
    ];
}
