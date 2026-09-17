<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';

// Fetch all reservations
$bookings_query = $conn->query("SELECT id, guest_name, guest_email, guest_type, accommodation_name, check_in, check_out, status FROM bookings ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations Directory — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo filemtime(__DIR__ . '/assets/css/dashboard.css'); ?>">
</head>
<body>

    <?php $active_page = 'reservations'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Guest Reservations';
        $page_subtitle = 'Front desk registry and booking logs';
        $header_extra_html = '
            <div class="search-wrapper">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search guests..." class="search-input" id="reservationSearch" onkeyup="filterTable()">
            </div>
            <a href="dashboard" class="btn-primary" style="height:38px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Booking
            </a>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2>All Booking Records</h2>
                        <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Complete list of guest stays</p>
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
                                    $checkin = date('M j, Y', strtotime($row['check_in']));
                                    $checkout = date('M j, Y', strtotime($row['check_out']));
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
                                    <td style="color:var(--text-muted); font-size:13px;"><?php echo $email ?: '—'; ?></td>
                                    <td><span style="font-size:12px; font-weight:600; color:var(--text-muted);"><?php echo $type; ?></span></td>
                                    <td style="color:var(--text-main); font-weight:500;"><?php echo $acc; ?></td>
                                    <td style="color:var(--text-muted); font-size:12.5px;"><?php echo $checkin; ?></td>
                                    <td style="color:var(--text-muted); font-size:12.5px;"><?php echo $checkout; ?></td>
                                    <td><span class="badge <?php echo $cls; ?>"><?php echo $status; ?></span></td>
                                    <td style="text-align: right;">
                                        <?php if ($status === 'Pending'): ?>
                                            <a href="checkin?booking_id=<?php echo $id; ?>" class="btn-table-action primary">Check-in</a>
                                        <?php elseif ($status === 'Checked In'): ?>
                                            <a href="checkout?booking_id=<?php echo $id; ?>" class="btn-table-action secondary">Check-out</a>
                                        <?php else: ?>
                                            <span style="color:var(--text-subtle); font-size:12px;">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding:40px; color:var(--text-muted);">No reservations found.</td>
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
