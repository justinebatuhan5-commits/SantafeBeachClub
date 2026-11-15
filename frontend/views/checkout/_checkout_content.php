<?php
$formAction           = $checkoutData['formAction'];
$showOverdue          = $checkoutData['showOverdue'];
$bookings             = $checkoutData['bookings'];
$currentBusinessTime  = $checkoutData['currentBusinessTime'];
$propertyTimezoneName = $checkoutData['propertyTimezoneName'];
$overdueCount         = $checkoutData['overdueCount'];
$overdueGuestNames    = $checkoutData['overdueGuestNames'];
$csrfField            = $checkoutData['csrfField'];
$showSuccess          = $checkoutData['showSuccess'];
?>
<section class="dashboard-grid checkout-grid">
    <?php if ($showSuccess): ?>
        <div class="alert-success">Guest successfully checked out! Room is now marked for maintenance.</div>
    <?php endif; ?>

    <?php if ($showOverdue && $overdueCount > 0 && $currentBusinessTime instanceof DateTimeInterface): ?>
        <div class="alert-warning">
            <?php echo $overdueCount; ?> guest<?php echo $overdueCount === 1 ? '' : 's'; ?> <?php echo $overdueCount === 1 ? 'is' : 'are'; ?> already due for check-out as of <?php echo htmlspecialchars($currentBusinessTime->format('M d, Y h:i A')); ?> <?php echo htmlspecialchars($propertyTimezoneName); ?>.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>Current Guests (Pending Departure)</h2>
        </div>

        <table class="reservations-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Guest Name</th>
                    <th>Email</th>
                    <th>Accommodation</th>
                    <th>Check-in Date</th>
                    <th>Check-out Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bookings)): ?>
                    <?php foreach ($bookings as $row):
                        $id            = htmlspecialchars((string)$row['id']);
                        $name          = htmlspecialchars((string)$row['guest_name']);
                        $email         = htmlspecialchars((string)$row['guest_email']);
                        $accommodation = htmlspecialchars((string)$row['accommodation_name']);
                        $checkin       = htmlspecialchars((string)$row['check_in']);
                        $checkout      = htmlspecialchars((string)$row['check_out']);
                        $isDue         = !empty($row['is_due_for_checkout']);
                        $dueDisplay    = htmlspecialchars((string)($row['due_checkout_display'] ?? ''));
                    ?>
                        <tr<?php echo $isDue ? ' class="checkout-row-overdue"' : ''; ?>>
                            <td>#<?php echo $id; ?></td>
                            <td><strong><?php echo $name; ?></strong></td>
                            <td><?php echo $email; ?></td>
                            <td><?php echo $accommodation; ?></td>
                            <td><?php echo $checkin; ?></td>
                            <td>
                                <strong><?php echo $checkout; ?></strong>
                                <?php if ($showOverdue && $isDue): ?>
                                    <span class="checkout-warning-badge">Due now</span>
                                    <span class="checkout-overdue-time">Scheduled checkout: <?php echo $dueDisplay; ?> <?php echo htmlspecialchars($propertyTimezoneName); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" id="checkout-form-<?php echo $id; ?>" class="checkout-inline-form">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="action" value="checkout">
                                    <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                                    <button type="button" class="btn-checkout" data-checkout-id="<?php echo $id; ?>" data-checkout-name="<?php echo $name; ?>">Check-out</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="checkout-empty-cell">No current guests to check out</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div id="checkoutModal" class="checkout-confirm-modal">
    <div class="checkout-confirm-card">
        <h3 class="checkout-confirm-title">Confirm Check-out</h3>
        <p class="checkout-confirm-body">Are you sure you want to check out <strong id="checkoutGuestName" class="checkout-confirm-guest"></strong>? This action cannot be undone and will mark the room for maintenance.</p>
        <div class="checkout-confirm-actions">
            <button type="button" class="checkout-cancel-btn" data-close-checkout-modal>Cancel</button>
            <button type="button" id="confirmCheckoutBtn" class="checkout-submit-btn">Yes, Check Out</button>
        </div>
    </div>
</div>

<?php if ($showOverdue && $currentBusinessTime instanceof DateTimeInterface): ?>
<div id="overdueModal" class="overdue-modal" role="dialog" aria-modal="true" aria-labelledby="overdueModalTitle" data-overdue-guests="<?php echo htmlspecialchars(json_encode($overdueGuestNames), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="overdue-modal-card">
        <h3 id="overdueModalTitle" class="overdue-modal-title">Overdue Check-out Warning</h3>
        <div class="overdue-modal-body">
            The following guest(s) are already due for check-out as of <strong><?php echo htmlspecialchars($currentBusinessTime->format('M d, Y h:i A')); ?> <?php echo htmlspecialchars($propertyTimezoneName); ?></strong>.
            <ul id="overdueGuestList" class="overdue-modal-list"></ul>
        </div>
        <div class="overdue-modal-actions">
            <button type="button" class="overdue-modal-btn" data-close-overdue-modal>Close</button>
        </div>
    </div>
</div>
<?php endif; ?>
