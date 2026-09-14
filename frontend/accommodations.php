<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/room_status_helper.php';

$admin = $_SESSION['admin_username'];

function sf_create_room_maintenance_notification(mysqli $conn, string $title, string $message, string $type = 'warning'): void
{
    $notif_stmt = $conn->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, ?)");
    $notif_stmt->bind_param("sss", $title, $message, $type);
    $notif_stmt->execute();
    $notif_stmt->close();
}

// Handle POST request to update room maintenance override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
    $action = $_POST['action'];

    $room_meta_stmt = $conn->prepare("SELECT room_number, name FROM rooms WHERE id = ? LIMIT 1");
    $room_meta_stmt->bind_param("i", $room_id);
    $room_meta_stmt->execute();
    $room_meta_result = $room_meta_stmt->get_result();
    $room_meta = $room_meta_result ? $room_meta_result->fetch_assoc() : null;
    $room_meta_stmt->close();

    if (!$room_meta) {
        header("Location: accommodations?error=not_found");
        exit;
    }

    $room_label = 'Room ' . $room_meta['room_number'] . ' (' . $room_meta['name'] . ')';

    if ($action === 'set_maintenance') {
        $stmt = $conn->prepare("UPDATE rooms SET status = 'maintenance' WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $stmt->close();

        sf_create_room_maintenance_notification(
            $conn,
            'Room set to maintenance',
            $room_label . ' was marked as maintenance by ' . $admin . '. Reception: do not assign this room until maintenance is cleared.',
            'warning'
        );

        header("Location: accommodations?success=1");
        exit;
    }

    if ($action === 'clear_maintenance') {
        if (sf_room_has_checked_in_booking($conn, $room_id)) {
            header("Location: accommodations?error=occupied");
            exit;
        }

        $stmt = $conn->prepare("UPDATE rooms SET status = 'ready' WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $stmt->close();

        sf_create_room_maintenance_notification(
            $conn,
            'Room maintenance cleared',
            $room_label . ' was cleared from maintenance by ' . $admin . '. Reception: this room is now available for assignment.',
            'success'
        );

        header("Location: accommodations?success=1");
        exit;
    }
}

// Fetch all rooms
$checked_in_room_ids = sf_get_checked_in_room_ids($conn);
$room_rows = [];

$rooms_query = $conn->query("SELECT id, room_number, name, type, price_per_night, capacity, status FROM rooms ORDER BY room_number ASC");
if ($rooms_query && $rooms_query->num_rows > 0) {
    while ($room = $rooms_query->fetch_assoc()) {
        $room['resolved_status'] = sf_resolve_room_display_status($room, $checked_in_room_ids);
        $room['is_occupied'] = isset($checked_in_room_ids[(int)$room['id']]);
        $room_rows[] = $room;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accommodation Status — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=5">
    <style>
        .status-select {
            border: none;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 110px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid transparent;
        }
        .status-badge.ready { background: #ECFDF5; color: #065F46; border-color: #A7F3D0; }
        .status-badge.occupied { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
        .status-badge.maintenance { background: #FFFBEB; color: #92400E; border-color: #FCD34D; }
        .status-action {
            display: inline-flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
        }
        .status-action-btn {
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .status-action-btn--warn { background: #F59E0B; color: #fff; }
        .status-action-btn--neutral { background: #F3F4F6; color: #374151; }
        .status-action-btn--disabled { background: #E5E7EB; color: #6B7280; cursor: not-allowed; }
        .status-note {
            color: #6B7280;
            font-size: 12px;
            line-height: 1.4;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        .alert-success {
            background: #ECFDF5;
            color: #065F46;
        }
        .alert-error {
            background: #FEF2F2;
            color: #991B1B;
        }
    </style>
</head>
<body>
    <?php $active_page = 'accommodations'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Accommodation Status';
        $page_subtitle = 'Manage and track room conditions.';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h3>All Accommodations</h3>
            </div>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Room maintenance status updated.</div>
            <?php elseif (isset($_GET['error']) && $_GET['error'] === 'occupied'): ?>
                <div class="alert alert-error">This room still has an active checked-in booking, so maintenance cannot be cleared yet.</div>
            <?php elseif (isset($_GET['error']) && $_GET['error'] === 'not_found'): ?>
                <div class="alert alert-error">The selected room no longer exists.</div>
            <?php endif; ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Room #</th>
                        <th>Accommodation Name</th>
                        <th>Type Code</th>
                        <th>Capacity</th>
                        <th>Rate</th>
                        <th>Current Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($room_rows as $room): ?>
                    <?php
                        $status = $room['resolved_status'];
                        $status_label = sf_room_status_label($status);
                        $is_occupied = !empty($room['is_occupied']);
                    ?>
                    <tr>
                        <td style="font-weight:700;"><?php echo htmlspecialchars($room['room_number']); ?></td>
                        <td><?php echo htmlspecialchars($room['name']); ?></td>
                        <td style="color:var(--text-muted);"><?php echo htmlspecialchars($room['type']); ?></td>
                        <td><?php echo (int)$room['capacity']; ?> Guests</td>
                        <td>PHP <?php echo number_format($room['price_per_night'], 2); ?></td>
                        <td>
                            <span class="status-badge <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_label); ?></span>
                            <?php if ($status === 'maintenance' && $is_occupied): ?>
                                <div class="status-note">Active booking detected. Clear maintenance after check-out.</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="status-action">
                                <?php if ($status === 'maintenance'): ?>
                                    <?php if ($is_occupied): ?>
                                        <button type="button" class="status-action-btn status-action-btn--disabled" disabled>Maintenance locked</button>
                                    <?php else: ?>
                                        <form method="POST" action="accommodations" style="margin:0;">
                                            <input type="hidden" name="action" value="clear_maintenance">
                                            <input type="hidden" name="room_id" value="<?php echo (int)$room['id']; ?>">
                                            <button type="submit" class="status-action-btn status-action-btn--neutral">Clear Maintenance</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="POST" action="accommodations" style="margin:0;">
                                        <input type="hidden" name="action" value="set_maintenance">
                                        <input type="hidden" name="room_id" value="<?php echo (int)$room['id']; ?>">
                                        <button type="submit" class="status-action-btn status-action-btn--warn">Mark Maintenance</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>
