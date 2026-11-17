<?php
$selectedStatus = $reservationsData['selectedStatus'];
$bookings       = $reservationsData['bookings'];
$totalCount     = $reservationsData['totalCount'];
$pendingCount   = $reservationsData['pendingCount'];
$confirmedCount = $reservationsData['confirmedCount'];
$checkedInCount = $reservationsData['checkedInCount'];

$statusClasses = [
    'Pending'         => 'badge-pending',
    'Pending Payment' => 'badge-pending',
    'Confirmed'       => 'badge-confirmed',
    'Checked In'      => 'badge-checkedin',
    'Checked Out'     => 'badge-checkedout',
    'Cancelled'       => 'badge-cancelled',
];
?>
<div class="admin-card res-filter-card">
    <div class="res-filter-bar">
        <div class="search-wrapper res-search-wrapper">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search guests, rooms…" class="search-input res-search-input" id="reservationSearch">
        </div>
        <form method="GET" action="admin_reservations" class="res-status-form">
            <span class="res-status-label">Filter Status:</span>
            <select name="status" class="res-status-select" data-auto-submit-status>
                <option value="" <?php echo $selectedStatus === '' ? 'selected' : ''; ?>>All Statuses (<?php echo $totalCount; ?>)</option>
                <option value="Confirmed" <?php echo $selectedStatus === 'Confirmed' ? 'selected' : ''; ?>>Confirmed (<?php echo $confirmedCount; ?>)</option>
                <option value="Pending" <?php echo $selectedStatus === 'Pending' ? 'selected' : ''; ?>>Pending (<?php echo $pendingCount; ?>)</option>
                <option value="Checked In" <?php echo $selectedStatus === 'Checked In' ? 'selected' : ''; ?>>Checked In (<?php echo $checkedInCount; ?>)</option>
                <option value="Checked Out" <?php echo $selectedStatus === 'Checked Out' ? 'selected' : ''; ?>>Checked Out</option>
                <option value="Cancelled" <?php echo $selectedStatus === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <?php if ($selectedStatus !== ''): ?>
                <a href="admin_reservations" class="btn-secondary res-clear-filter">Clear Filter</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <div>
            <h3>Guest Reservations Register</h3>
            <p class="res-card-subtitle">Showing live reservation records</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table" id="resTable">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Guest Name</th>
                    <th>Email / Contact</th>
                    <th>Tier</th>
                    <th>Accommodation</th>
                    <th>Stay Dates</th>
                    <th>Status</th>
                    <th class="res-col-action">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bookings)): ?>
                    <?php foreach ($bookings as $row):
                        $id         = (int)$row['id'];
                        $name       = htmlspecialchars($row['guest_name'] ?? '');
                        $email      = htmlspecialchars($row['guest_email'] ?? '');
                        $phoneRaw   = trim((string)($row['guest_phone'] ?? ''));
                        $countryRaw = trim((string)($row['guest_country'] ?? ''));
                        $type       = htmlspecialchars($row['guest_type'] ?? '');
                        $acc        = htmlspecialchars($row['accommodation_name'] ?? '');
                        $cin        = date('M j, Y', strtotime($row['check_in']));
                        $cout       = date('M j, Y', strtotime($row['check_out']));
                        $status     = htmlspecialchars($row['status'] ?? '');
                        $cls        = $statusClasses[$status] ?? 'badge-pending';
                        $initials   = strtoupper(substr((string)$row['guest_name'], 0, 1));
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
                        <td class="res-email-cell">
                            <div><?php echo $email ? htmlspecialchars(mask_email($email)) : '—'; ?></div>
                            <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px; font-family:var(--font-body, sans-serif);">
                                <?php echo htmlspecialchars($phoneRaw !== '' ? mask_phone($phoneRaw) : 'Email only'); ?><?php if ($countryRaw !== '' && strcasecmp($countryRaw, 'Philippines') !== 0): ?> &bull; <?php echo htmlspecialchars($countryRaw); ?><?php endif; ?>
                            </div>
                        </td>
                        <td><span class="res-tier-pill"><?php echo $type; ?></span></td>
                        <td class="res-acc-cell"><?php echo $acc; ?></td>
                        <td class="res-date-cell"><?php echo $cin; ?> &rarr; <?php echo $cout; ?></td>
                        <td>
                            <span class="badge <?php echo $cls; ?>"><?php echo $status; ?></span>
                            <?php if ($status === 'Cancelled' && !empty($row['cancellation_reason'])): ?>
                                <div class="res-cancel-reason" title="Reason: <?php echo htmlspecialchars($row['cancellation_reason']); ?>">
                                    <?php echo htmlspecialchars($row['cancellation_reason']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="res-col-action">
                            <a href="admin_checkin?search=<?php echo urlencode((string)$row['guest_name']); ?>" class="btn-table-action secondary" title="View Check-in Details">
                                Manage
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="res-empty-cell">No reservations found matching the criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
