<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/room_status_helper.php';

// ── Overdue check-out alert (simple, no helper dependencies) ─────────────────
$_overdue_guest_names = [];
$_overdue_now_display = date('M d, Y h:i A');
$_overdue_tz_name     = 'Asia/Manila';
try {
    $_ov_res = $conn->query(
        "SELECT guest_name FROM bookings
         WHERE status = 'Checked In'
           AND check_out < CURDATE()
         ORDER BY check_out ASC"
    );
    if ($_ov_res) {
        while ($_ov_row = $_ov_res->fetch_assoc()) {
            $_overdue_guest_names[] = (string)$_ov_row['guest_name'];
        }
    }
} catch (Throwable $_ov_err) {
    $_overdue_guest_names = [];
}

// Calculate live dashboard metrics
$checkins_today = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE DATE(check_in) = CURDATE() AND status != 'Cancelled'")->fetch_assoc()['count'] ?? 0;
$checkouts_today = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE DATE(check_out) = CURDATE() AND status != 'Cancelled'")->fetch_assoc()['count'] ?? 0;
$pending_rsvps = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending'")->fetch_assoc()['count'] ?? 0;
$total_guests = $conn->query("SELECT SUM(guests_count) as count FROM bookings WHERE status = 'Checked In'")->fetch_assoc()['count'] ?? 0;
$total_guests = (int)($total_guests ?? 0);

// Fetch current arrivals list
$bookings_query = $conn->query("
    SELECT b.id, b.guest_name, b.guest_phone, b.guest_email, b.guest_type, b.accommodation_name, b.eta, b.status, b.room_id, b.check_in, b.check_out, r.room_number 
    FROM bookings b
    LEFT JOIN rooms r ON r.id = b.room_id
    WHERE b.status = 'Pending' AND DATE(b.check_in) = CURDATE() 
    ORDER BY b.id DESC
");

$checked_in_room_ids = sf_get_checked_in_room_ids($conn);
$reserved_room_ids = sf_get_reserved_room_ids($conn);
$room_status_rows = [];
$occupied_rooms = 0;
$available_rooms = 0;

// Fetch full details of each room including who is currently staying or reserved
$rooms_query = $conn->query("
    SELECT r.id, r.room_number, r.status, r.type,
           b.id AS booking_id, b.guest_name, b.guest_phone, b.guest_email, b.guest_type, b.check_in, b.check_out, b.status AS booking_status
    FROM rooms r
    LEFT JOIN bookings b ON b.room_id = r.id AND b.status IN ('Checked In', 'Pending', 'Confirmed') AND b.check_in <= CURDATE() AND b.check_out >= CURDATE()
    ORDER BY r.room_number ASC
");

if ($rooms_query && $rooms_query->num_rows > 0) {
    while ($room = $rooms_query->fetch_assoc()) {
        $room['resolved_status'] = sf_resolve_room_display_status($room, $checked_in_room_ids, $reserved_room_ids);
        $room_status_rows[] = $room;

        if ($room['resolved_status'] === 'occupied') {
            $occupied_rooms++;
        } elseif ($room['resolved_status'] === 'ready') {
            $available_rooms++;
        }
    }
}

// Fetch room types for the booking form dropdown
$room_types = [];
try {
    $rt_q = $conn->query("SELECT id, name, price AS base_price FROM room_types ORDER BY id ASC");
    if ($rt_q && $rt_q->num_rows > 0) {
        while ($row = $rt_q->fetch_assoc()) {
            $room_types[] = $row;
        }
    }
} catch (Throwable $e) {
    $room_types = [];
}

if (empty($room_types)) {
    $room_types = [
        ['id' => 1, 'name' => 'beachview_duplex', 'base_price' => 3500],
        ['id' => 2, 'name' => 'seaview_duplex', 'base_price' => 4200],
        ['id' => 3, 'name' => 'beach_villa', 'base_price' => 5500],
        ['id' => 4, 'name' => 'standard_room', 'base_price' => 2800],
        ['id' => 5, 'name' => 'standard_king', 'base_price' => 3200]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Cockpit — Santa Fe Beach Club</title>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=5">
    <style>
        /* Room Quick Action Modal & Interactive Elements */
        .room-block {
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .room-block:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        
        .stay-pill-btn {
            background: var(--input-bg, #F8FAFC);
            border: 1px solid var(--border, #E2E8F0);
            padding: 5px 12px;
            border-radius: 99px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-main, #334155);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .stay-pill-btn:hover {
            background: var(--primary, #84563C);
            color: #FFFFFF;
            border-color: var(--primary, #84563C);
        }

        .room-detail-modal-box {
            background: var(--card-bg, #FFFFFF);
            border-radius: 18px;
            padding: 26px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body>

    <?php $active_page = 'dashboard'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php
        $page_title = 'Reception Console';
        $page_subtitle = "Real-time front desk operations • " . date('l, F j, Y');
        $header_extra_html = '
            <div class="search-wrapper" style="position:relative;">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search by name, room, phone, or Ref ID..." class="search-input" id="dashboardSearch" onkeyup="filterArrivalsTable()">
            </div>
            <button class="btn-new-res-top" onclick="openReservationModal()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Reservation
            </button>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            <!-- ═══ METRICS ROW (6 KPIs) ═══ -->
            <section class="metrics-row">
                <div class="metric-card">
                    <div class="metric-info">
                        <h3>Today's Check-ins</h3>
                        <div class="metric-number" id="arrivalsCount"><?php echo (int)$checkins_today; ?></div>
                    </div>
                    <div class="metric-icon-wrapper brown">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-info">
                        <h3>Today's Check-outs</h3>
                        <div class="metric-number"><?php echo (int)$checkouts_today; ?></div>
                    </div>
                    <div class="metric-icon-wrapper" style="background:#F1F5F9; color:#475569;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-info">
                        <h3>Pending RSVPs</h3>
                        <div class="metric-number" style="color:var(--orange);"><?php echo (int)$pending_rsvps; ?></div>
                    </div>
                    <div class="metric-icon-wrapper orange">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-info">
                        <h3>Occupied Units</h3>
                        <div class="metric-number" id="occupiedRoomsCount" style="color:var(--blue);"><?php echo (int)$occupied_rooms; ?></div>
                    </div>
                    <div class="metric-icon-wrapper blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><circle cx="8" cy="14" r="2"/><circle cx="16" cy="14" r="2"/></svg>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-info">
                        <h3>Ready / Available</h3>
                        <div class="metric-number" id="availableRoomsCount" style="color:var(--green);"><?php echo (int)$available_rooms; ?></div>
                    </div>
                    <div class="metric-icon-wrapper green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-info">
                        <h3>In-House Guests</h3>
                        <div class="metric-number"><?php echo (int)$total_guests; ?></div>
                    </div>
                    <div class="metric-icon-wrapper purple">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                </div>
            </section>

            <!-- ═══ LOWER GRID: ARRIVALS TABLE + LIVE ROOM MATRIX ═══ -->
            <section class="dashboard-grid">
                <!-- Today's Arrivals -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2>Expected Arrivals Today</h2>
                            <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Pending arrivals scheduled for today</p>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <select id="arrivalTypeFilter" onchange="filterArrivalsTable()" style="padding:6px 12px; border:1px solid var(--border); border-radius:var(--radius-sm); font-size:12.5px; background:var(--input-bg); color:var(--text-main);">
                                <option value="">All Guest Tiers</option>
                                <option value="First Visit">First Visit</option>
                                <option value="Returning Guest">Returning Guest</option>
                                <option value="VIP Member">VIP Member</option>
                                <option value="Corporate">Corporate</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="arrivals-table" id="arrivalsTable">
                            <thead>
                                <tr>
                                    <th>Guest Details</th>
                                    <th>Accommodation</th>
                                    <th>ETA</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($bookings_query && $bookings_query->num_rows > 0) {
                                    $idx = 0;
                                    while ($row = $bookings_query->fetch_assoc()) {
                                        $row_id = 'row-' . $row['id'];
                                        $name_raw = $row['guest_name'];
                                        $type_raw = $row['guest_type'];
                                        $accommodation_raw = $row['accommodation_name'];
                                        $eta = htmlspecialchars($row['eta'] ?? '14:00');
                                        $name = htmlspecialchars($name_raw);
                                        $type = htmlspecialchars($type_raw);
                                        $accommodation = htmlspecialchars($accommodation_raw);
                                        $phone = htmlspecialchars($row['guest_phone'] ?? '');
                                        $email = htmlspecialchars($row['guest_email'] ?? '');
                                        $ref_id = 'REF-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
                                        $room_no = htmlspecialchars($row['room_number'] ?? '');
                                        
                                        $initials = '';
                                        $parts = explode(' ', $name_raw);
                                        foreach ($parts as $p) {
                                            $initials .= strtoupper($p[0] ?? '');
                                        }
                                        $initials = substr($initials, 0, 2);
                                        
                                        $btnClass = ($idx === 0) ? 'primary' : 'secondary';
                                        $idx++;
                                        
                                        echo "<tr id='{$row_id}' data-guest-type='" . htmlspecialchars($type_raw, ENT_QUOTES) . "' data-search-text='" . strtolower(htmlspecialchars("{$name_raw} {$phone} {$email} {$ref_id} {$accommodation_raw} {$room_no}", ENT_QUOTES)) . "'>";
                                        echo "<td>
                                                <div class='guest-profile'>
                                                    <div class='avatar-letter'>{$initials}</div>
                                                    <div class='guest-info'>
                                                        <h4>{$name}</h4>
                                                        <p>{$type} &bull; <span style='font-family:monospace; color:var(--primary);'>{$ref_id}</span></p>
                                                    </div>
                                                </div>
                                              </td>";
                                        echo "<td style='color:var(--text-muted); font-size:13px;'>
                                                <strong>{$accommodation}</strong>" . ($room_no ? " <span style='color:var(--text-main); font-weight:700;'>(Room {$room_no})</span>" : "") . "
                                              </td>";
                                        echo "<td class='eta-cell' style='font-weight:600; color:var(--text-main);'>{$eta}</td>";
                                        echo "<td style='text-align: right;'>
                                                <button class='btn-table-action {$btnClass}' onclick='checkInGuest(" . json_encode($row_id) . ", " . json_encode($name_raw) . ", " . json_encode($accommodation_raw) . ")'>
                                                    <svg width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5'><polyline points='20 6 9 17 4 12'/></svg>
                                                    Express Check-in
                                                </button>
                                              </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' style='text-align: center; color: var(--text-muted); padding: 36px 20px;'>
                                        <svg width='32' height='32' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.5' style='opacity:0.4; margin-bottom:8px;'><circle cx='12' cy='12' r='10'/><polyline points='12 6 12 12 14 14'/></svg>
                                        <p>No pending arrivals scheduled for today</p>
                                    </td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Live Accommodation Status Grid -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2>Live Unit Availability</h2>
                            <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Click any room for quick actions</p>
                        </div>
                        <a href="admin_calendar" class="btn-view-all">Full Matrix &rarr;</a>
                    </div>

                    <div class="room-grid" id="roomStatusGrid">
                        <?php
                        if (count($room_status_rows) > 0) {
                            foreach ($room_status_rows as $room) {
                                $num = htmlspecialchars($room['room_number']);
                                $status = htmlspecialchars($room['resolved_status']);
                                $roomJson = htmlspecialchars(json_encode($room), ENT_QUOTES, 'UTF-8');
                                echo "<div class='room-block {$status}' data-room='{$num}' data-status='{$status}' onclick='openRoomDetailModal({$roomJson})' title='Room {$num} • " . ucfirst($status) . "'>{$num}</div>";
                            }
                        }
                        ?>
                    </div>

                    <div class="legend-container">
                        <div class="legend-item">
                            <span class="legend-dot ready"></span>
                            Ready (Available)
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot occupied"></span>
                            Occupied (In-House)
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot reserved"></span>
                            Reserved (Booked)
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot maintenance"></span>
                            Turnover / Maintenance
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- ═══ MODAL 1: INTERACTIVE ROOM QUICK-DETAIL ═══ -->
    <div class="modal-overlay" id="roomDetailModal" onclick="if(event.target===this) closeRoomDetailModal();">
        <div class="room-detail-modal-box">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 id="rm-modal-title" style="font-family:var(--font-heading); font-size:20px; font-weight:800; color:var(--text-main); margin:0;">Room 101</h2>
                    <p id="rm-modal-type" style="font-size:13px; color:var(--text-muted); margin:3px 0 0;">Standard Room</p>
                </div>
                <span id="rm-modal-badge" style="padding:4px 10px; border-radius:99px; font-size:11.5px; font-weight:700; text-transform:uppercase;">Ready</span>
            </div>

            <div id="rm-modal-body" style="background:var(--input-bg, #F8FAFC); border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:20px; font-size:13.5px;">
                <!-- Filled dynamically via JS -->
            </div>

            <div id="rm-modal-actions" style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn-secondary" onclick="closeRoomDetailModal()" style="height:38px; padding:0 16px; font-size:13px;">Close</button>
                <div id="rm-modal-btn-slot"></div>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL 2: QUICK RESERVATION MODAL ═══ -->
    <div class="modal-overlay" id="newReservationModal" onclick="if(event.target===this) closeReservationModal();">
        <div class="modal-box">
            <div class="modal-header">
                <h2>New Walk-in Reservation</h2>
                <button class="modal-close" onclick="closeReservationModal()">&times;</button>
            </div>
            <form id="newReservationForm" onsubmit="event.preventDefault();">
                <div class="admin-form-group">
                    <label for="newGuestName">Full Guest Name <span class="req">*</span></label>
                    <input type="text" id="newGuestName" required placeholder="e.g. Maria Santos">
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label for="newGuestEmail">Email Address</label>
                        <input type="email" id="newGuestEmail" placeholder="guest@example.com">
                    </div>
                    <div class="admin-form-group">
                        <label for="newGuestType">Guest Tier</label>
                        <select id="newGuestType">
                            <option value="First Visit">First Visit</option>
                            <option value="Returning Guest">Returning Guest</option>
                            <option value="VIP Member">VIP Member</option>
                            <option value="Corporate">Corporate</option>
                        </select>
                    </div>
                </div>

                <div class="admin-form-group">
                    <label for="newRoomType">Accommodation Type</label>
                    <select id="newRoomType">
                        <?php foreach ($room_types as $rt): ?>
                            <option value="<?php echo $rt['id']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $rt['name'])); ?> (₱<?php echo number_format($rt['base_price'] ?? 3000, 0); ?>/night)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Stay Duration Quick Pick -->
                <div class="admin-form-group">
                    <label>Stay Duration Quick-Set</label>
                    <div style="display:flex; gap:8px; margin-bottom:6px;">
                        <button type="button" class="stay-pill-btn" onclick="setStayDuration(1)">+ 1 Night</button>
                        <button type="button" class="stay-pill-btn" onclick="setStayDuration(2)">+ 2 Nights</button>
                        <button type="button" class="stay-pill-btn" onclick="setStayDuration(3)">+ 3 Nights</button>
                        <button type="button" class="stay-pill-btn" onclick="setStayDuration(7)">+ 1 Week</button>
                    </div>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label for="newCheckin">Check-in Date</label>
                        <input type="date" id="newCheckin" required value="<?php echo date('Y-m-d'); ?>" onchange="updateStayFromDates()">
                    </div>
                    <div class="admin-form-group">
                        <label for="newCheckout">Check-out Date</label>
                        <input type="date" id="newCheckout" required value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label for="newGuestsCount">Guests</label>
                        <input type="number" id="newGuestsCount" required min="1" max="12" value="2">
                    </div>
                    <div class="admin-form-group">
                        <label for="newETA">Expected Arrival (ETA)</label>
                        <input type="time" id="newETA" required value="14:00">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px;">
                    <button type="button" class="btn-secondary" onclick="submitReservation('Pending')">
                        Save as Pending
                    </button>
                    <button type="button" class="btn-primary" onclick="submitReservation('Checked In')" style="justify-content:center;">
                        Direct Check-in
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // ── Room Matrix Quick-Detail Popup ──────────────────────────
        function openRoomDetailModal(room) {
            const modal = document.getElementById('roomDetailModal');
            document.getElementById('rm-modal-title').innerText = `Room ${room.room_number}`;
            document.getElementById('rm-modal-type').innerText = (room.type || 'Standard Accommodation').replace(/_/g, ' ').toUpperCase();
            
            const badge = document.getElementById('rm-modal-badge');
            const body = document.getElementById('rm-modal-body');
            const btnSlot = document.getElementById('rm-modal-btn-slot');
            
            const status = room.resolved_status;
            badge.innerText = status;
            
            if (status === 'ready') {
                badge.style.background = '#ECFDF5';
                badge.style.color = '#059669';
                body.innerHTML = `
                    <div style="color:#059669; font-weight:700; margin-bottom:6px;">✓ Unit Cleaned & Available</div>
                    <p style="margin:0; color:var(--text-muted); font-size:12.5px;">This room is ready for immediate walk-in guest check-in or allocation.</p>
                `;
                btnSlot.innerHTML = `
                    <button type="button" class="btn-primary" onclick="closeRoomDetailModal(); openReservationModal();" style="height:38px; padding:0 16px; font-size:13px;">
                        Walk-in Check-in &rarr;
                    </button>
                `;
            } else if (status === 'occupied') {
                badge.style.background = '#EFF6FF';
                badge.style.color = '#2563EB';
                body.innerHTML = `
                    <div style="font-weight:700; color:var(--text-main); margin-bottom:4px;">Guest: ${room.guest_name || 'In-House Guest'}</div>
                    <div style="font-size:12.5px; color:var(--text-muted); margin-bottom:3px;">Tier: <strong>${room.guest_type || 'Standard'}</strong></div>
                    <div style="font-size:12.5px; color:var(--text-muted); margin-bottom:3px;">Check-in: ${room.check_in || 'Active'}</div>
                    <div style="font-size:12.5px; color:var(--text-muted);">Scheduled Check-out: <strong>${room.check_out || 'Today'}</strong></div>
                `;
                btnSlot.innerHTML = `
                    <a href="checkout" class="btn-primary" style="height:38px; padding:0 16px; font-size:13px; text-decoration:none; display:inline-flex; align-items:center;">
                        Go to Check-out &rarr;
                    </a>
                `;
            } else if (status === 'reserved') {
                badge.style.background = '#FFFBEB';
                badge.style.color = '#D97706';
                body.innerHTML = `
                    <div style="font-weight:700; color:#D97706; margin-bottom:4px;">Reserved: ${room.guest_name || 'Upcoming Arrival'}</div>
                    <div style="font-size:12.5px; color:var(--text-muted); margin-bottom:3px;">Expected: ${room.check_in || 'Today'}</div>
                    <div style="font-size:12.5px; color:var(--text-muted);">Stay until: ${room.check_out || '—'}</div>
                `;
                btnSlot.innerHTML = `
                    <a href="admin_reservations" class="btn-primary" style="height:38px; padding:0 16px; font-size:13px; text-decoration:none; display:inline-flex; align-items:center;">
                        View Reservation &rarr;
                    </a>
                `;
            } else {
                badge.style.background = '#FEF2F2';
                badge.style.color = '#EF4444';
                body.innerHTML = `
                    <div style="color:#EF4444; font-weight:700; margin-bottom:4px;">⚠️ Room in Turnover / Maintenance</div>
                    <p style="margin:0; color:var(--text-muted); font-size:12.5px;">Under cleaning inspection or scheduled repairs.</p>
                `;
                btnSlot.innerHTML = `
                    <a href="settings" class="btn-secondary" style="height:38px; padding:0 16px; font-size:13px; text-decoration:none; display:inline-flex; align-items:center;">
                        Manage Rooms
                    </a>
                `;
            }
            
            modal.classList.add('open');
        }

        function closeRoomDetailModal() {
            document.getElementById('roomDetailModal').classList.remove('open');
        }

        // ── Reservation Modal & Duration Helpers ────────────────────
        function setStayDuration(nights) {
            const checkinVal = document.getElementById('newCheckin').value || new Date().toISOString().split('T')[0];
            const start = new Date(checkinVal);
            start.setDate(start.getDate() + nights);
            const yyyy = start.getFullYear();
            const mm = String(start.getMonth() + 1).padStart(2, '0');
            const dd = String(start.getDate()).padStart(2, '0');
            document.getElementById('newCheckout').value = `${yyyy}-${mm}-${dd}`;
        }

        function openReservationModal() {
            document.getElementById('newReservationModal').classList.add('open');
        }

        function closeReservationModal() {
            document.getElementById('newReservationModal').classList.remove('open');
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeReservationModal();
                closeRoomDetailModal();
            }
        });

        function submitReservation(status) {
            const name = document.getElementById('newGuestName').value.trim();
            if (!name) {
                alert('Please enter guest name.');
                return;
            }
            const email = document.getElementById('newGuestEmail').value;
            const type = document.getElementById('newGuestType').value;
            const roomTypeId = document.getElementById('newRoomType').value;
            const checkin = document.getElementById('newCheckin').value;
            const checkout = document.getElementById('newCheckout').value;
            const guests = document.getElementById('newGuestsCount').value;
            const eta = document.getElementById('newETA').value;
            
            const formData = new FormData();
            formData.append('guest_name', name);
            formData.append('guest_email', email);
            formData.append('guest_type', type);
            formData.append('room_type_id', roomTypeId);
            formData.append('check_in', checkin);
            formData.append('check_out', checkout);
            formData.append('guests_count', guests);
            formData.append('eta', eta);
            formData.append('status', status);
            
            fetch('../backend/api/create_booking.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || data.error || 'Failed to save reservation.'));
                }
            })
            .catch(err => {
                console.error('Error saving reservation:', err);
                alert('An error occurred while creating the reservation.');
            });
        }

        function checkInGuest(rowId, name, room) {
            const bookingId = rowId.split('-')[1];
            const formData = new FormData();
            formData.append('action', 'checkin');
            formData.append('booking_id', bookingId);
            formData.append('format', 'json');

            fetch('checkin', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById(rowId);
                    if (row) row.remove();
                    window.location.reload();
                } else {
                    alert('Check-in failed. Please try again.');
                }
            })
            .catch(err => {
                console.error('Check-in error:', err);
                alert('An error occurred during check-in.');
            });
        }

        // ── Search Across Everything (Guest, Phone, Ref ID, Room) ───
        function filterArrivalsTable() {
            const query = (document.getElementById('dashboardSearch')?.value || '').toLowerCase().trim();
            const selectedType = document.getElementById('arrivalTypeFilter').value;
            const rows = document.querySelectorAll('#arrivalsTable tbody tr');
            
            rows.forEach(row => {
                const searchText = (row.getAttribute('data-search-text') || '');
                const guestType = (row.getAttribute('data-guest-type') || '');
                const matchesType = !selectedType || guestType === selectedType;
                const matchesQuery = !query || searchText.includes(query);
                 
                if (matchesQuery && matchesType) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        filterArrivals();
    </script>

<!-- ── Overdue Check-out Warning Modal ────────────────────────────────────── -->
<style>
    #overdueCheckoutModal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.72);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        z-index: 99999;
        padding: 20px;
        animation: ocm-fadein .28s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes ocm-fadein { from { opacity:0; } to { opacity:1; } }
    .ocm-card {
        background: #FFFFFF;
        width: min(100%, 500px);
        border-radius: 20px;
        box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(226, 232, 240, 0.8);
        overflow: hidden;
        animation: ocm-slidein .32s cubic-bezier(.34, 1.25, .64, 1);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    @keyframes ocm-slidein { from { transform: translateY(24px) scale(.95); } to { transform: translateY(0) scale(1); } }
    .ocm-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 22px 26px;
        background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
        border-bottom: 3px solid #D97706;
        color: #fff;
        position: relative;
    }
    .ocm-logo-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #FFFFFF;
        padding: 3px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .ocm-logo-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 9px;
    }
    .ocm-header-text {
        flex: 1;
        min-width: 0;
    }
    .ocm-brand-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #F59E0B;
        margin-bottom: 2px;
    }
    .ocm-header-text h3 {
        margin: 0;
        font-size: 17px;
        font-weight: 700;
        color: #FFFFFF;
        letter-spacing: -0.2px;
    }
    .ocm-header-text p {
        margin: 3px 0 0;
        font-size: 12px;
        color: #94A3B8;
    }
    .ocm-close-x {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: #94A3B8;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
        font-size: 16px;
        line-height: 1;
    }
    .ocm-close-x:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #FFFFFF;
    }
    .ocm-body {
        padding: 22px 26px 14px;
        background: #FAFAFA;
    }
    .ocm-banner {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #FEF3C7;
        border: 1px solid #FCD34D;
        border-radius: 12px;
        padding: 11px 14px;
        margin-bottom: 16px;
    }
    .ocm-banner-icon {
        font-size: 17px;
        line-height: 1.2;
    }
    .ocm-banner-text {
        font-size: 12.5px;
        color: #92400E;
        line-height: 1.5;
        font-weight: 500;
    }
    .ocm-list-label {
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #64748B;
        margin-bottom: 8px;
    }
    .ocm-guest-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 180px;
        overflow-y: auto;
    }
    .ocm-guest-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-left: 4px solid #EA580C;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        color: #1E293B;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .ocm-guest-name {
        display: flex;
        align-items: center;
        gap: 9px;
    }
    .ocm-guest-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        background: #FEE2E2;
        color: #DC2626;
        border-radius: 6px;
        letter-spacing: 0.2px;
    }
    .ocm-timestamp {
        font-size: 11.5px;
        color: #94A3B8;
        margin: 14px 0 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .ocm-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 26px 20px;
        background: #FFFFFF;
        border-top: 1px solid #F1F5F9;
    }
    .ocm-btn-dismiss {
        padding: 10px 18px;
        border: 1px solid #E2E8F0;
        background: #F8FAFC;
        color: #475569;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.15s ease;
    }
    .ocm-btn-dismiss:hover {
        background: #F1F5F9;
        color: #1E293B;
        border-color: #CBD5E1;
    }
    .ocm-btn-go {
        padding: 10px 22px;
        border: none;
        background: linear-gradient(135deg, #D97706 0%, #B45309 100%);
        color: #FFFFFF;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
        font-size: 13px;
        box-shadow: 0 4px 14px rgba(217, 119, 6, 0.35);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.15s ease;
    }
    .ocm-btn-go:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(217, 119, 6, 0.45);
        filter: brightness(1.05);
    }
</style>

<div id="overdueCheckoutModal" role="dialog" aria-modal="true" aria-labelledby="ocmTitle">
    <div class="ocm-card">
        <div class="ocm-header">
            <div class="ocm-logo-wrap">
                <img src="assets/logo.png" alt="Santa Fe Beach Club Logo" onerror="this.src='assets/logo.jpg'">
            </div>
            <div class="ocm-header-text">
                <div class="ocm-brand-badge">
                    <span>★</span> Santa Fe Beach Club
                </div>
                <h3 id="ocmTitle">Overdue Check-out Alert</h3>
                <p>Action required &bull; Scheduled departure time has passed</p>
            </div>
            <button class="ocm-close-x" onclick="document.getElementById('overdueCheckoutModal').style.display='none'" title="Close">&times;</button>
        </div>
        <div class="ocm-body">
            <div class="ocm-banner">
                <span class="ocm-banner-icon">⏰</span>
                <span class="ocm-banner-text">The following guest(s) have passed their scheduled check-out date and are still marked as <strong>Checked In</strong>. Please process their departure to free up accommodations.</span>
            </div>
            <div class="ocm-list-label">Overdue Guest(s)</div>
            <ul class="ocm-guest-list" id="ocmGuestList"></ul>
            <p class="ocm-timestamp">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                As of <?php echo htmlspecialchars($_overdue_now_display); ?> &bull; <?php echo htmlspecialchars($_overdue_tz_name); ?>
            </p>
        </div>
        <div class="ocm-footer">
            <button class="ocm-btn-dismiss" onclick="document.getElementById('overdueCheckoutModal').style.display='none'">
                Dismiss
            </button>
            <a class="ocm-btn-go" href="admin_checkout">
                <span>Go to Check-out Page</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</div>

<script>
(function(){
    var names = <?php echo json_encode($_overdue_guest_names, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    if (names.length > 0) {
        var list = document.getElementById('ocmGuestList');
        list.innerHTML = names.map(function(n){
            return '<li><div class="ocm-guest-name"><span>👤</span> ' + n + '</div><span class="ocm-guest-badge">Overdue</span></li>';
        }).join('');
        document.getElementById('overdueCheckoutModal').style.display = 'flex';
    }
})();
</script>

</body>
</html>
