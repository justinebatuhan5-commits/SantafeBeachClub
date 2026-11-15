<?php
/**
 * Shared checkout controller for admin_checkout and receptionist checkout.
 */

/**
 * Processes guest check-out POST submissions and redirects to avoid resubmission.
 */
function sfbc_checkout_handle_post(mysqli $conn, string $redirectRoute, bool $markEarnedRevenue): void
{
    require_csrf_token();
    $bookingId = intval($_POST['booking_id'] ?? 0);

    $stmt = $conn->prepare("UPDATE bookings SET status = 'Checked Out' WHERE id = ?");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $stmt->close();

    if ($markEarnedRevenue) {
        $payStmt = $conn->prepare("UPDATE payments SET accounting_status = 'earned' WHERE booking_id = ? AND status = 'verified'");
        $payStmt->bind_param("i", $bookingId);
        $payStmt->execute();
        $payStmt->close();
    }

    $roomStmt = $conn->prepare("SELECT room_id FROM bookings WHERE id = ?");
    $roomStmt->bind_param("i", $bookingId);
    $roomStmt->execute();
    $roomRes = $roomStmt->get_result();
    if ($roomRes && $roomRes->num_rows > 0) {
        $roomId = (int)$roomRes->fetch_assoc()['room_id'];
        if ($roomId > 0) {
            $updateRoomStmt = $conn->prepare("UPDATE rooms SET status = 'maintenance' WHERE id = ?");
            $updateRoomStmt->bind_param("i", $roomId);
            $updateRoomStmt->execute();
            $updateRoomStmt->close();
        }
    }
    $roomStmt->close();

    header("Location: {$redirectRoute}?success=1");
    exit;
}

/**
 * Loads checked-in stays and overdue checkout metrics for checkout pages.
 */
function sfbc_handle_checkout_page(mysqli $conn, string $redirectRoute, bool $showOverdue, bool $markEarnedRevenue): array
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
        sfbc_checkout_handle_post($conn, $redirectRoute, $markEarnedRevenue);
    }

    sf_create_due_checkout_notifications($conn);
    $bookingsResult = $conn->query("SELECT id, guest_name, guest_email, guest_type, accommodation_name, check_in, check_out FROM bookings WHERE status = 'Checked In' ORDER BY check_out ASC");

    $checkoutTime = null;
    $currentBusinessTime = null;
    $propertyTimezone = null;
    $propertyTimezoneName = '';
    $overdueCount = 0;
    $overdueGuestNames = [];

    if ($showOverdue) {
        $checkoutTime = sf_get_checkout_time_setting($conn);
        $currentBusinessTime = sf_get_current_business_datetime($conn);
        $propertyTimezone = $currentBusinessTime->getTimezone();
        $propertyTimezoneName = $propertyTimezone->getName();
    }

    $bookings = [];
    if ($bookingsResult) {
        while ($row = $bookingsResult->fetch_assoc()) {
            $isDue = false;
            $dueDisplay = '';
            if ($showOverdue && $propertyTimezone instanceof DateTimeZone && $currentBusinessTime instanceof DateTimeInterface) {
                $isDue = sf_is_due_for_checkout((string)$row['check_out'], (string)$checkoutTime, $propertyTimezone, $currentBusinessTime);
                if ($isDue) {
                    $overdueCount++;
                    $overdueGuestNames[] = (string)$row['guest_name'];
                }
                $dueCheckoutAt = sf_get_due_checkout_datetime((string)$row['check_out'], (string)$checkoutTime, $propertyTimezone);
                $dueDisplay = $dueCheckoutAt->format('M d, Y h:i A');
            }

            $row['is_due_for_checkout'] = $isDue;
            $row['due_checkout_display'] = $dueDisplay;
            $bookings[] = $row;
        }
    }

    return [
        'formAction'           => $redirectRoute,
        'showOverdue'          => $showOverdue,
        'bookings'             => $bookings,
        'currentBusinessTime'  => $currentBusinessTime,
        'propertyTimezoneName' => $propertyTimezoneName,
        'overdueCount'         => $overdueCount,
        'overdueGuestNames'    => $overdueGuestNames,
        'csrfField'            => csrf_field(),
        'showSuccess'          => isset($_GET['success']),
    ];
}
