<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/pii_masker.php';

// Optional status filter
$allowed_statuses = ['Pending', 'Checked In', 'Checked Out', 'Cancelled'];
$selected_status = isset($_GET['status']) ? trim($_GET['status']) : '';
if (!in_array($selected_status, $allowed_statuses, true)) {
    $selected_status = '';
}

// Fetch all reservations
$sql = "SELECT id, guest_name, guest_email, guest_type, accommodation_name, check_in, check_out, status, cancellation_reason FROM bookings";
if ($selected_status !== '') {
    $safe_status = $conn->real_escape_string($selected_status);
    $sql .= " WHERE status = '{$safe_status}'";
}
$sql .= " ORDER BY id DESC";
$bookings_query = $conn->query($sql);

$total_count = $conn->query("SELECT COUNT(*) as c FROM bookings")->fetch_assoc()['c'] ?? 0;
$pending_count = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
$checked_in_count = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='Checked In'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Reservations — Admin Command</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
</head>
<body>

    <?php $active_page = 'reservations'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title = 'Reservations Management';
        $page_subtitle = 'Comprehensive register of all resort guest bookings and status';
        $header_extra_html = '
            <a href="book" target="_blank" class="btn-primary" style="height:38px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Booking
            </a>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <!-- Filter Bar & Quick Stats -->
            <div class="admin-card" style="margin-bottom: 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                    <div class="search-wrapper" style="width: 280px; max-width: 100%;">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" placeholder="Search guests, rooms…" class="search-input" id="reservationSearch" onkeyup="filterTable()" style="width: 100%; border:1.5px solid var(--border); border-radius:var(--radius-sm); padding:8px 14px 8px 36px; background:var(--input-bg); color:var(--text-main); font-size:13px;">
                        <style>
                            .search-wrapper { position: relative; }
                            .search-wrapper .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); }
                            .search-wrapper input:focus { outline: none; border-color: var(--primary); }
                        </style>
                    </div>
                    <form method="GET" action="admin_reservations" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                        <span style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Filter Status:</span>
                        <select name="status" onchange="this.form.submit()" style="padding:8px 14px; border:1.5px solid var(--border); border-radius:var(--radius-sm); font-size:13px; background:var(--input-bg); color:var(--text-main);">
                            <option value="" <?php echo $selected_status === '' ? 'selected' : ''; ?>>All Statuses (<?php echo $total_count; ?>)</option>
                            <option value="Pending" <?php echo $selected_status === 'Pending' ? 'selected' : ''; ?>>Pending (<?php echo $pending_count; ?>)</option>
                            <option value="Checked In" <?php echo $selected_status === 'Checked In' ? 'selected' : ''; ?>>Checked In (<?php echo $checked_in_count; ?>)</option>
                            <option value="Checked Out" <?php echo $selected_status === 'Checked Out' ? 'selected' : ''; ?>>Checked Out</option>
                            <option value="Cancelled" <?php echo $selected_status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <?php if ($selected_status !== ''): ?>
                            <a href="admin_reservations" class="btn-secondary" style="padding:6px 12px; font-size:12px;">Clear Filter</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h3>Guest Reservations Register</h3>
                        <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Showing live reservation records</p>
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
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bookings_query && $bookings_query->num_rows > 0): ?>
                                <?php while ($row = $bookings_query->fetch_assoc()):
                                    $id = $row['id'];
                                    $name = htmlspecialchars($row['guest_name']);
                                    $email = htmlspecialchars($row['guest_email']);
                                    $type = htmlspecialchars($row['guest_type']);
                                    $acc = htmlspecialchars($row['accommodation_name']);
                                    $cin = date('M j, Y', strtotime($row['check_in']));
                                    $cout = date('M j, Y', strtotime($row['check_out']));
                                    $status = htmlspecialchars($row['status']);

                                    $sc = [
                                        'Pending'     => 'badge-pending',
                                        'Checked In'  => 'badge-checkedin',
                                        'Checked Out' => 'badge-checkedout',
                                        'Cancelled'   => 'badge-cancelled'
                                    ];
                                    $cls = $sc[$status] ?? 'badge-pending';
                                    $initials = strtoupper(substr($row['guest_name'], 0, 1));
                                ?>
                                <tr>
                                    <td style="color:var(--text-muted); font-weight:600;">#<?php echo $id; ?></td>
                                    <td>
                                        <div class="guest-profile">
                                            <div class="avatar-letter"><?php echo $initials; ?></div>
                                            <div class="guest-info">
                                                <h4><?php echo $name; ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color:var(--text-muted); font-size:13px; font-family:var(--font-mono, monospace);"><?php echo $email ? htmlspecialchars(mask_email($email)) : '—'; ?></td>
                                    <td><span style="font-size:12px; font-weight:600; color:var(--text-muted);"><?php echo $type; ?></span></td>
                                    <td style="color:var(--text-main); font-weight:500;"><?php echo $acc; ?></td>
                                    <td style="color:var(--text-muted); font-size:12.5px;"><?php echo $cin; ?> &rarr; <?php echo $cout; ?></td>
                                    <td>
                                        <span class="badge <?php echo $cls; ?>"><?php echo $status; ?></span>
                                        <?php if ($status === 'Cancelled' && !empty($row['cancellation_reason'])): ?>
                                            <div style="font-size:11px; color:#EF4444; max-width:130px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:3px;" title="Reason: <?php echo htmlspecialchars($row['cancellation_reason']); ?>">
                                                <?php echo htmlspecialchars($row['cancellation_reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="admin_checkin?search=<?php echo urlencode($row['guest_name']); ?>" class="btn-table-action secondary" title="View Check-in Details">
                                            Manage
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:40px; color:var(--text-muted);">No reservations found matching the criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
    function filterTable() {
        const input = document.getElementById('reservationSearch');
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('#resTable tbody tr');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    }
    </script>
</body>
</html>
