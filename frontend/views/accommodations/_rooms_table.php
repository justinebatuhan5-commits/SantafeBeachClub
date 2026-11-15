<?php
$roomRows    = $roomsData['roomRows'];
$csrfField   = $roomsData['csrfField'];
$showSuccess = $roomsData['showSuccess'];
$errorCode   = $roomsData['errorCode'];
?>
<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Accommodations</h3>
    </div>
    <?php if ($showSuccess): ?>
        <div class="alert alert-success">Room maintenance status updated.</div>
    <?php elseif ($errorCode === 'occupied'): ?>
        <div class="alert alert-error">This room still has an active checked-in booking, so maintenance cannot be cleared yet.</div>
    <?php elseif ($errorCode === 'not_found'): ?>
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
        <?php foreach ($roomRows as $room): ?>
            <?php
                $status      = $room['resolved_status'];
                $statusLabel = sf_room_status_label($status);
                $isOccupied  = !empty($room['is_occupied']);
            ?>
            <tr>
                <td class="room-number-cell"><?php echo htmlspecialchars($room['room_number']); ?></td>
                <td><?php echo htmlspecialchars($room['name']); ?></td>
                <td class="room-type-cell"><?php echo htmlspecialchars($room['type']); ?></td>
                <td><?php echo (int)$room['capacity']; ?> Guests</td>
                <td>PHP <?php echo number_format($room['price_per_night'], 2); ?></td>
                <td>
                    <span class="status-badge <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                    <?php if ($status === 'maintenance' && $isOccupied): ?>
                        <div class="status-note">Active booking detected. Clear maintenance after check-out.</div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="status-action">
                        <?php if ($status === 'maintenance'): ?>
                            <?php if ($isOccupied): ?>
                                <button type="button" class="status-action-btn status-action-btn--disabled" disabled>Maintenance locked</button>
                            <?php else: ?>
                                <form method="POST" action="accommodations" class="room-action-form">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="action" value="clear_maintenance">
                                    <input type="hidden" name="room_id" value="<?php echo (int)$room['id']; ?>">
                                    <button type="submit" class="status-action-btn status-action-btn--neutral">Clear Maintenance</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="POST" action="accommodations" class="room-action-form">
                                <?php echo $csrfField; ?>
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
