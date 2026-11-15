<?php
$bookings = $reservationsData['bookings'];
$statusClasses = [
    'Pending'     => 'badge-pending',
    'Checked In'  => 'badge-checkedin',
    'Checked Out' => 'badge-checkedout',
    'Cancelled'   => 'badge-cancelled',
];
?>
<div class="card">
    <div class="card-header">
        <div>
            <h2>All Booking Records</h2>
            <p class="res-card-subtitle">Complete list of guest stays</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="arrivals-table" id="resTable">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Guest Name</th>
                    <th>Email</th>
                    <th>Guest Tier</th>
                    <th>Accommodation</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                    <th class="res-col-action">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bookings)): ?>
                    <?php foreach ($bookings as $row):
                        $id       = (int)$row['id'];
                        $name     = htmlspecialchars((string)$row['guest_name']);
                        $email    = htmlspecialchars((string)$row['guest_email']);
                        $type     = htmlspecialchars((string)$row['guest_type']);
                        $acc      = htmlspecialchars((string)$row['accommodation_name']);
                        $checkin  = date('M j, Y', strtotime($row['check_in']));
                        $checkout = date('M j, Y', strtotime($row['check_out']));
                        $status   = htmlspecialchars((string)$row['status']);
                        $cls      = $statusClasses[$status] ?? 'badge-pending';
                        $initials = strtoupper(substr((string)$row['guest_name'], 0, 1));
                    ?>
                    <tr>
                        <td class="res-ref-cell">#<?php echo $id; ?></td>
                        <td>
                            <div class="guest-profile">
                                <div class="avatar-letter"><?php echo $initials; ?></div>
                                <div class="guest-info">
                                    <h4><?php echo $name; ?></h4>
                                </div>
                            </div>
                        </td>
                        <td class="res-email-cell--plain"><?php echo $email ?: '—'; ?></td>
                        <td><span class="res-tier-pill"><?php echo $type; ?></span></td>
                        <td class="res-acc-cell"><?php echo $acc; ?></td>
                        <td class="res-date-cell"><?php echo $checkin; ?></td>
                        <td class="res-date-cell"><?php echo $checkout; ?></td>
                        <td><span class="badge <?php echo $cls; ?>"><?php echo $status; ?></span></td>
                        <td class="res-col-action">
                            <?php if ($status === 'Pending'): ?>
                                <a href="checkin?booking_id=<?php echo $id; ?>" class="btn-table-action primary">Check-in</a>
                            <?php elseif ($status === 'Checked In'): ?>
                                <a href="checkout?booking_id=<?php echo $id; ?>" class="btn-table-action secondary">Check-out</a>
                            <?php else: ?>
                                <span class="res-completed-label">Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="res-empty-cell">No reservations found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
