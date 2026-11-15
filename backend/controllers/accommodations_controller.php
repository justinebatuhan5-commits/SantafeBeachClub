<?php
/**
 * Accommodation status controller.
 *
 * Handles maintenance overrides and computes live room availability states.
 */

/**
 * Inserts a room maintenance status change notification.
 */
function sf_create_room_maintenance_notification(mysqli $conn, string $title, string $message, string $type = 'warning'): void
{
    $notifStmt = $conn->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, ?)");
    $notifStmt->bind_param("sss", $title, $message, $type);
    $notifStmt->execute();
    $notifStmt->close();
}

/**
 * Handles POST actions for setting or clearing room maintenance overrides.
 */
function sfbc_accommodations_handle_post(mysqli $conn, string $admin): void
{
    require_csrf_token();
    $roomId = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
    $action = $_POST['action'] ?? '';

    $roomMetaStmt = $conn->prepare("SELECT room_number, name FROM rooms WHERE id = ? LIMIT 1");
    $roomMetaStmt->bind_param("i", $roomId);
    $roomMetaStmt->execute();
    $roomMetaResult = $roomMetaStmt->get_result();
    $roomMeta = $roomMetaResult ? $roomMetaResult->fetch_assoc() : null;
    $roomMetaStmt->close();

    if (!$roomMeta) {
        header("Location: accommodations?error=not_found");
        exit;
    }

    $roomLabel = 'Room ' . $roomMeta['room_number'] . ' (' . $roomMeta['name'] . ')';

    if ($action === 'set_maintenance') {
        $stmt = $conn->prepare("UPDATE rooms SET status = 'maintenance' WHERE id = ?");
        $stmt->bind_param("i", $roomId);
        $stmt->execute();
        $stmt->close();

        sf_create_room_maintenance_notification(
            $conn,
            'Room set to maintenance',
            $roomLabel . ' was marked as maintenance by ' . $admin . '. Reception: do not assign this room until maintenance is cleared.',
            'warning'
        );

        header("Location: accommodations?success=1");
        exit;
    }

    if ($action === 'clear_maintenance') {
        if (sf_room_has_checked_in_booking($conn, $roomId)) {
            header("Location: accommodations?error=occupied");
            exit;
        }

        $stmt = $conn->prepare("UPDATE rooms SET status = 'ready' WHERE id = ?");
        $stmt->bind_param("i", $roomId);
        $stmt->execute();
        $stmt->close();

        sf_create_room_maintenance_notification(
            $conn,
            'Room maintenance cleared',
            $roomLabel . ' was cleared from maintenance by ' . $admin . '. Reception: this room is now available for assignment.',
            'success'
        );

        header("Location: accommodations?success=1");
        exit;
    }
}

/**
 * Loads rooms with resolved display statuses for the accommodations table.
 */
function sfbc_handle_accommodations_page(mysqli $conn): array
{
    $admin = $_SESSION['admin_username'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        sfbc_accommodations_handle_post($conn, $admin);
    }

    $checkedInRoomIds = sf_get_checked_in_room_ids($conn);
    $roomRows = [];

    $roomsQuery = $conn->query("SELECT id, room_number, name, type, price_per_night, capacity, status FROM rooms ORDER BY room_number ASC");
    if ($roomsQuery && $roomsQuery->num_rows > 0) {
        while ($room = $roomsQuery->fetch_assoc()) {
            $room['resolved_status'] = sf_resolve_room_display_status($room, $checkedInRoomIds);
            $room['is_occupied'] = isset($checkedInRoomIds[(int)$room['id']]);
            $roomRows[] = $room;
        }
    }

    return [
        'roomRows'  => $roomRows,
        'csrfField' => csrf_field(),
        'showSuccess' => isset($_GET['success']),
        'errorCode'   => $_GET['error'] ?? '',
    ];
}
