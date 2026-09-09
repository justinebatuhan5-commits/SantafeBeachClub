<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/pii_masker.php';

// ── Guest summary query ───────────────────────────────────────────────────────
$guests_result = $conn->query("
    SELECT 
        guest_name,
        guest_email,
        guest_type,
        COUNT(id)              AS total_bookings,
        MIN(check_in)          AS first_visit,
        MAX(check_in)          AS last_visit,
        SUM(CASE WHEN status = 'Cancelled' THEN 0 ELSE 1 END) AS confirmed_bookings
    FROM bookings
    GROUP BY guest_email, guest_name
    ORDER BY guest_name ASC
");

$guests = [];
if ($guests_result) {
    while ($r = $guests_result->fetch_assoc()) {
        $guests[] = $r;
    }
}

// ── Per-guest booking history (for profile modal) ────────────────────────────
$bookings_result = $conn->query("
    SELECT 
        b.id, b.guest_email, b.guest_name,
        b.accommodation_name, b.check_in, b.check_out,
        b.guests_count, b.status, b.payment_method,
        b.guest_phone, b.guest_country, b.guest_special_requests,
        r.price_per_night, r.type AS room_type,
        DATEDIFF(b.check_out, b.check_in) AS nights
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    ORDER BY b.check_in DESC
");

$bookings_by_email = [];
if ($bookings_result) {
    while ($b = $bookings_result->fetch_assoc()) {
        $key = strtolower(trim($b['guest_email'] ?? $b['guest_name']));
        $bookings_by_email[$key][] = $b;
    }
}

// ── Summary counts ────────────────────────────────────────────────────────────
$total_guests    = count($guests);
$vip_count       = 0;
$corporate_count = 0;
$returning_count = 0;
$first_visit_count = 0;
foreach ($guests as $g) {
    $t = $g['guest_type'];
    if ($t === 'VIP Member')        $vip_count++;
    elseif ($t === 'Corporate')     $corporate_count++;
    elseif ($t === 'Returning Guest') $returning_count++;
    else                            $first_visit_count++;
}

// ── Helper: initials & avatar colours ────────────────────────────────────────
function get_initials(string $name): string {
    $initials = '';
    foreach (explode(' ', $name) as $p) {
        if (!empty($p)) $initials .= strtoupper($p[0]);
    }
    return substr($initials, 0, 2);
}
function avatar_colors(string $type): array {
    return match($type) {
        'VIP Member'      => ['#FDF4EC', '#7C533C'],
        'Corporate'       => ['#E3F2FD', '#1565C0'],
        'Returning Guest' => ['#F3E8FF', '#7C3AED'],
        default           => ['#F3F4F6', '#4B5563'],
    };
}
function type_badge(string $type): string {
    $styles = match($type) {
        'VIP Member'      => 'background:#FDF4EC;color:#7C533C;border:1px solid #e8d5c0;',
        'Corporate'       => 'background:#E3F2FD;color:#1565C0;border:1px solid #bbdefb;',
        'Returning Guest' => 'background:#F3E8FF;color:#7C3AED;border:1px solid #ddd6fe;',
        default           => 'background:#F3F4F6;color:#4B5563;border:1px solid #e5e7eb;',
    };
    return "<span style='$styles padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>" . htmlspecialchars($type) . "</span>";
}

function split_guest_name(string $name): array {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first_name = $parts[0] ?? '';
    $last_name = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
    return [$first_name, $last_name];
}

function booking_room_type(array $booking): string {
    $room_type = trim((string)($booking['room_type'] ?? ''));
    if ($room_type !== '') {
        return $room_type;
    }

    $accommodation_name = strtolower((string)($booking['accommodation_name'] ?? ''));
    if (strpos($accommodation_name, 'beachview duplex') !== false) {
        return 'beachview_duplex';
    }
    if (strpos($accommodation_name, 'seaview duplex') !== false) {
        return 'seaview_duplex';
    }
    if (strpos($accommodation_name, 'beach villa') !== false) {
        return 'beach_villa';
    }
    if (strpos($accommodation_name, 'standard family') !== false) {
        return 'standard_king';
    }

    return 'standard_room';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=5">
    <style>
        /* ── KPI Stat Cards ──────────────────────────────────── */
        .guest-stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 1200px) { .guest-stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px)  { .guest-stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px)  { .guest-stats-grid { grid-template-columns: 1fr; } }

        .guest-stat-card {
            background: var(--card-bg, #fff);
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid var(--border, #E2E8F0);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .guest-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
            border-color: rgba(132, 86, 60, 0.3);
        }
        .guest-stat-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .guest-stat-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }
        .guest-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
            font-family: var(--font-heading);
        }
        .guest-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }
        .guest-stat-card:hover .guest-stat-icon {
            transform: scale(1.06);
        }

        /* ── Filter tabs ─────────────────────────────────────── */
        .filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
        .filter-tab {
            padding: 6px 16px;
            border-radius: 99px;
            font-size: 12.5px; font-weight: 600;
            cursor: pointer;
            border: 1.5px solid var(--border, #E2E8F0);
            background: var(--input-bg, #F8FAFC);
            color: var(--text-muted);
            transition: all .15s ease;
        }
        .filter-tab:hover { border-color: var(--primary, #84563C); color: var(--primary); }
        .filter-tab.active { background: var(--primary, #84563C); color: #fff; border-color: var(--primary); box-shadow: 0 2px 8px rgba(132,86,60,0.3); }

        /* ── Guest table ─────────────────────────────────────── */
        .guests-table { width: 100%; border-collapse: collapse; }
        .guests-table th {
            padding: 11px 16px; text-align: left;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-muted); border-bottom: 1.5px solid var(--border);
            background: var(--sidebar-hover, #F8FAFC);
        }
        .guests-table th:first-child { border-radius: 10px 0 0 0; }
        .guests-table th:last-child  { border-radius: 0 10px 0 0; text-align: right; }
        .guests-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-light, #F1F5F9);
            color: var(--text-main); font-size: 13.5px; vertical-align: middle;
        }
        .guests-table tbody tr { transition: background 0.15s; }
        .guests-table tbody tr:hover td { background: var(--primary-subtle, #FDF8F5); }
        .guests-table tbody tr:last-child td { border-bottom: none; }

        .guest-profile-cell { display: flex; align-items: center; gap: 12px; }
        .g-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 14px; flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .g-name { font-weight: 700; font-size: 14px; color: var(--text-main); }
        .g-sub  { font-size: 12px; color: var(--text-muted); margin-top: 1px; }

        .type-pill {
            display: inline-block;
            padding: 4px 12px; border-radius: 99px;
            font-size: 11.5px; font-weight: 700;
        }
        .booking-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 28px; height: 28px; padding: 0 8px;
            border-radius: 99px; font-weight: 800; font-size: 13px;
            background: var(--primary-subtle, #FDF4EC);
            color: var(--primary, #84563C);
            border: 1px solid rgba(132,86,60,0.18);
        }

        .btn-view-profile {
            display: inline-flex; align-items: center; gap: 6px;
            background: transparent;
            color: var(--primary, #84563C);
            border: 1.5px solid var(--primary, #84563C);
            padding: 7px 16px; border-radius: 99px;
            font-size: 12.5px; font-weight: 700; cursor: pointer;
            transition: all .15s ease;
            white-space: nowrap;
        }
        .btn-view-profile:hover {
            background: var(--primary, #84563C); color: #fff;
            box-shadow: 0 4px 12px rgba(132,86,60,0.3);
        }

        .no-results-state {
            text-align: center; padding: 60px 20px; color: var(--text-muted);
        }
        .no-results-state svg { opacity: 0.25; margin-bottom: 12px; }
        .no-results-state p { font-size: 15px; font-weight: 600; }

        /* ── Modal ───────────────────────────────────────────── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);
            z-index: 1000; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border);
            border-radius: 20px; width: 100%; max-width: 760px;
            max-height: 90vh; overflow-y: auto;
            box-shadow: 0 30px 80px rgba(0,0,0,0.25);
            animation: modalIn .2s cubic-bezier(0.16,1,0.3,1);
        }
        @keyframes modalIn { from { transform: scale(0.96) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }

        .modal-header {
            padding: 28px 28px 22px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: flex-start; gap: 18px;
            position: sticky; top: 0;
            background: var(--card-bg, #fff); border-radius: 20px 20px 0 0; z-index: 1;
        }
        .modal-avatar-lg {
            width: 72px; height: 72px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; font-weight: 800; flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
        }
        .modal-guest-name { font-size: 22px; font-weight: 800; color: var(--text-main); margin-bottom: 3px; font-family: var(--font-heading); }
        .modal-guest-email { font-size: 13.5px; color: var(--text-muted); margin-bottom: 8px; }
        .modal-header-actions { margin-left: auto; display: flex; align-items: center; gap: 10px; }

        .btn-book-again {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--primary-grad, #84563C); color: #fff;
            padding: 9px 18px; border-radius: 99px;
            font-weight: 700; font-size: 13px; text-decoration: none;
            transition: all .15s; box-shadow: 0 2px 8px rgba(132,86,60,0.3);
        }
        .btn-book-again:hover { box-shadow: 0 6px 16px rgba(132,86,60,0.4); transform: translateY(-1px); }

        .modal-close {
            background: var(--input-bg); border: 1px solid var(--border);
            cursor: pointer; color: var(--text-muted);
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transition: all .15s;
        }
        .modal-close:hover { background: var(--sidebar-hover); color: var(--text-main); }

        .modal-body { padding: 24px 28px; }

        .modal-kpi-row {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 26px;
        }
        .modal-kpi {
            background: var(--input-bg, #F8FAFC);
            border: 1px solid var(--border); border-radius: 14px;
            padding: 16px; text-align: center;
        }
        .modal-kpi-val { font-size: 24px; font-weight: 800; color: var(--text-main); font-family: var(--font-heading); }
        .modal-kpi-lbl { font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }

        .modal-section-title {
            font-size: 11.5px; font-weight: 700; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 12px;
        }
        .contact-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
            background: var(--input-bg); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px; margin-bottom: 22px;
        }
        .contact-label { font-size: 11.5px; color: var(--text-muted); font-weight: 600; margin-bottom: 3px; }
        .contact-value { font-size: 14px; color: var(--text-main); font-weight: 600; }

        .booking-history-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .booking-history-table th {
            padding: 9px 12px; text-align: left;
            background: var(--sidebar-hover); color: var(--text-muted); font-weight: 700;
            text-transform: uppercase; font-size: 10.5px; letter-spacing: .5px;
        }
        .booking-history-table th:first-child { border-radius: 8px 0 0 8px; }
        .booking-history-table th:last-child  { border-radius: 0 8px 8px 0; }
        .booking-history-table td { padding: 11px 12px; border-bottom: 1px solid var(--border-light); color: var(--text-main); }
        .booking-history-table tbody tr:last-child td { border-bottom: none; }
        .status-pill { padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
        .status-pill.pending    { background: rgba(245,158,11,0.12); color: #F59E0B; }
        .status-pill.checked-in { background: rgba(16,185,129,0.12); color: #10B981; }
        .status-pill.checked-out{ background: rgba(59,130,246,0.12); color: #3B82F6; }
        .status-pill.cancelled  { background: rgba(239,68,68,0.12);  color: #EF4444; }
        .empty-history { text-align: center; color: var(--text-muted); padding: 30px 0; font-size: 14px; }

        .btn-export {
            display: inline-flex; align-items: center; gap: 7px;
            background: var(--card-bg); color: var(--text-main);
            border: 1.5px solid var(--border); padding: 8px 16px;
            border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer;
            transition: all 0.15s;
        }
        .btn-export:hover { background: var(--sidebar-hover); border-color: var(--primary); color: var(--primary); }
    </style>
</head>
<body>

<?php $active_page = 'guests'; include __DIR__ . '/partials/_sidebar.php'; ?>

<main class="main-content">
<?php
$page_title    = 'Guest Management';
$page_subtitle = 'Guest directory, profiles and booking history';
$header_extra_html = '
    <div class="search-wrapper" style="position:relative;">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Search by name or email..." class="search-input" id="guestSearch">
    </div>
';
include __DIR__ . '/partials/_page_header.php';
?>

<div class="admin-body">

    <!-- ══ KPI Stats Row ══════════════════════════════════════════ -->
    <div class="guest-stats-grid">
        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">Total Guests</div>
                <div class="guest-stat-value"><?= $total_guests ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#E8F0FE; color:#1A73E8;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>

        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">VIP Members</div>
                <div class="guest-stat-value"><?= $vip_count ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#FFF3E8; color:#84563C;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
        </div>

        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">Corporate</div>
                <div class="guest-stat-value"><?= $corporate_count ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#E3F2FD; color:#1565C0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
            </div>
        </div>

        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">First Visit</div>
                <div class="guest-stat-value"><?= $first_visit_count ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#E6F4EA; color:#0D652D;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
        </div>

        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">Returning</div>
                <div class="guest-stat-value"><?= $returning_count ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#F3E8FF; color:#7C3AED;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
    </div>

    <!-- ══ Guest Directory Card ═══════════════════════════════════ -->
    <div class="card" style="padding:0; overflow:hidden;">
        <!-- Card toolbar -->
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:20px 24px 16px;">
            <div>
                <h2 style="font-size:17px; font-weight:800; color:var(--text-main); margin:0; font-family:var(--font-heading);">Guest Directory</h2>
                <p style="font-size:13px; color:var(--text-muted); margin-top:3px;">
                    <span id="visibleCount"><?= $total_guests ?></span> guest<?= $total_guests !== 1 ? 's' : '' ?> total
                </p>
            </div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all">All</button>
                    <button class="filter-tab" data-filter="VIP Member">VIP</button>
                    <button class="filter-tab" data-filter="Returning Guest">Returning</button>
                    <button class="filter-tab" data-filter="Corporate">Corporate</button>
                    <button class="filter-tab" data-filter="First Visit">First Visit</button>
                </div>
                <button id="exportCsvBtn" class="btn-export">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export CSV
                </button>
            </div>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto; padding: 0 8px 16px;">
            <table class="guests-table" id="guestTable">
                <thead>
                    <tr>
                        <th style="padding-left:24px;">Guest Profile</th>
                        <th>Email</th>
                        <th>Tier</th>
                        <th>Bookings</th>
                        <th>Last Visit</th>
                        <th style="text-align:right; padding-right:24px;">Action</th>
                    </tr>
                </thead>
                <tbody id="guestTableBody">
                    <?php if (empty($guests)): ?>
                    <tr class="guest-row">
                        <td colspan="6">
                            <div class="no-results-state">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                <p>No guests found in the directory.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($guests as $row):
                        $name       = $row['guest_name'];
                        $email      = $row['guest_email'] ?? '';
                        $type       = $row['guest_type']  ?? 'First Visit';
                        $total      = (int)$row['total_bookings'];
                        $last_visit = $row['last_visit']  ?? '';
                        $first_visit= $row['first_visit'] ?? '';
                        $initials   = get_initials($name);
                        [$avatarBg, $avatarText] = avatar_colors($type);

                        $key      = strtolower(trim($email !== '' ? $email : $name));
                        $bookings = $bookings_by_email[$key] ?? [];

                        $total_spend = 0;
                        foreach ($bookings as $bk) {
                            if ($bk['status'] !== 'Cancelled' && $bk['price_per_night']) {
                                $nights = max(1, (int)$bk['nights']);
                                $total_spend += $nights * (float)$bk['price_per_night'];
                            }
                        }

                        $guest_phone = '';
                        $guest_country = '';
                        $guest_special_requests = '';
                        if (!empty($bookings)) {
                            $guest_phone = $bookings[0]['guest_phone'] ?? '';
                            $guest_country = $bookings[0]['guest_country'] ?? '';
                            $guest_special_requests = $bookings[0]['guest_special_requests'] ?? '';
                        }

                        [$guest_first_name, $guest_last_name] = split_guest_name($name);
                        $recent_booking = $bookings[0] ?? [];
                        $recent_room_type = booking_room_type($recent_booking);
                        $recent_guests = max(1, (int)($recent_booking['guests_count'] ?? 2));
                        $recent_nights = max(1, (int)($recent_booking['nights'] ?? 1));
                        $rebook_checkin = date('Y-m-d');
                        $rebook_checkout = date('Y-m-d', strtotime('+' . $recent_nights . ' day'));
                        $book_again_url = 'book?' . http_build_query([
                            'room_type' => $recent_room_type,
                            'first_name' => $guest_first_name,
                            'last_name' => $guest_last_name,
                            'email' => $email,
                            'phone' => $guest_phone,
                            'country' => $guest_country,
                            'comments' => $guest_special_requests,
                        ]);

                        // Encode booking rows as JSON for the modal
                        $bookings_json = json_encode(array_map(function($bk) {
                            return [
                                'id'        => (int)$bk['id'],
                                'room'      => $bk['accommodation_name'],
                                'check_in'  => $bk['check_in'],
                                'check_out' => $bk['check_out'],
                                'guests'    => (int)$bk['guests_count'],
                                'nights'    => max(1, (int)$bk['nights']),
                                'status'    => $bk['status'],
                                'payment'   => $bk['payment_method'] ?? 'Pay at Check-in',
                                'rate'      => (float)($bk['price_per_night'] ?? 0),
                            ];
                        }, $bookings));

                        $profile_data = json_encode([
                            'name'        => $name,
                            'email'       => $email,
                            'masked_email'=> mask_email($email),
                            'phone'       => $guest_phone,
                            'masked_phone'=> mask_phone($guest_phone),
                            'country'     => $guest_country,
                            'special_requests' => $guest_special_requests,
                            'type'        => $type,
                            'total'       => $total,
                            'first_visit' => $first_visit,
                            'last_visit'  => $last_visit,
                            'spend'       => $total_spend,
                            'initials'    => $initials,
                            'avatarBg'    => $avatarBg,
                            'avatarText'  => $avatarText,
                            'book_again_url' => $book_again_url,
                            'bookings'    => json_decode($bookings_json),
                        ]);
                    ?>
                    <tr class="guest-row" data-type="<?= htmlspecialchars($type) ?>"
                        data-name="<?= htmlspecialchars(strtolower($name)) ?>"
                        data-email="<?= htmlspecialchars(strtolower($email)) ?>">
                        <td>
                            <div class="guest-profile-small">
                                <div class="avatar-small" style="background:<?= $avatarBg ?>;color:<?= $avatarText ?>;"><?= htmlspecialchars($initials) ?></div>
                                <span class="guest-name-main"><?= htmlspecialchars($name) ?></span>
                            </div>
                        </td>
                        <td style="color:#555;font-size:14px; font-family:var(--font-mono, monospace);"><?= htmlspecialchars(mask_email($email)) ?></td>
                        <td><?= type_badge($type) ?></td>
                        <td><span class="booking-count-badge"><?= $total ?></span></td>
                        <td style="color:#555;font-size:14px;"><?= htmlspecialchars($last_visit) ?></td>
                        <td>
                            <button type="button" class="btn-view"
                                onclick='openGuestProfile(<?= htmlspecialchars($profile_data, ENT_QUOTES) ?>)'>
                                View Profile
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="noResultsMsg" style="display:none;" class="no-results">No guests match your search.</div>
        </div>
        <div style="height:8px;"></div>
    </div>

</div>
</main>

<!-- ═══════════════════ Guest Profile Modal ═══════════════════ -->
<div class="modal-overlay" id="guestModal" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-avatar-lg" id="modalAvatar"></div>
            <div style="flex:1; min-width:0;">
                <div class="modal-guest-name" id="modalName"></div>
                <div class="modal-guest-email" id="modalEmail"></div>
                <div id="modalTypeBadge"></div>
            </div>
            <div class="modal-header-actions">
                <a id="modalBookAgainBtn" class="btn-book-again" href="book?step=2">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                    Book Again
                </a>
                <button class="modal-close" onclick="closeModal()" title="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div class="modal-body">
            <!-- KPI row -->
            <div class="modal-kpi-row">
                <div class="modal-kpi">
                    <div class="modal-kpi-val" id="modalTotalBookings">—</div>
                    <div class="modal-kpi-lbl">Total Bookings</div>
                </div>
                <div class="modal-kpi">
                    <div class="modal-kpi-val" id="modalFirstVisit">—</div>
                    <div class="modal-kpi-lbl">First Visit</div>
                </div>
                <div class="modal-kpi">
                    <div class="modal-kpi-val" id="modalTotalSpend">—</div>
                    <div class="modal-kpi-lbl">Est. Total Spend</div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="modal-section-title">Contact Information</div>
            <div class="contact-grid">
                <div>
                    <div class="contact-label">Phone</div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="contact-value" id="modalPhone" style="font-family:var(--font-mono, monospace);">—</div>
                        <button type="button" id="btnTogglePhone" onclick="togglePhoneVisibility()" style="display:none; background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:12px; padding:2px 6px; border-radius:4px; border:1px solid var(--border);" title="Toggle Phone Mask">
                            👁️ Show
                        </button>
                    </div>
                </div>
                <div>
                    <div class="contact-label">Country</div>
                    <div class="contact-value" id="modalCountry">—</div>
                </div>
            </div>

            <!-- Special Requests -->
            <div id="modalSpecialRequestsSection" style="display:none; margin-bottom:22px;">
                <div class="modal-section-title">Special Requests / Notes</div>
                <div style="padding:14px 16px; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.25); border-radius:10px; font-size:13.5px; color:var(--text-main); line-height:1.6;" id="modalSpecialRequests"></div>
            </div>

            <!-- Booking History -->
            <div class="modal-section-title">Booking History</div>
            <div id="modalBookingHistory" style="overflow-x:auto; border-radius:10px; border:1px solid var(--border);"></div>
        </div>
    </div>
</div>

<script>
// ── Guest data rows ──────────────────────────────────────────────────────────
const ALL_ROWS = Array.from(document.querySelectorAll('#guestTableBody .guest-row[data-name]'));
let activeFilter = 'all';

// ── Search ────────────────────────────────────────────────────────────────────
document.getElementById('guestSearch').addEventListener('input', function () {
    applyFilters(this.value.toLowerCase().trim());
});

// ── Filter tabs ───────────────────────────────────────────────────────────────
document.querySelectorAll('.filter-tab').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        activeFilter = this.dataset.filter;
        applyFilters(document.getElementById('guestSearch').value.toLowerCase().trim());
    });
});

function applyFilters(searchTerm) {
    let visible = 0;
    ALL_ROWS.forEach(row => {
        const typeMatch   = activeFilter === 'all' || row.dataset.type === activeFilter;
        const searchMatch = searchTerm === '' ||
            row.dataset.name.includes(searchTerm) ||
            row.dataset.email.includes(searchTerm);
        const show = typeMatch && searchMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('visibleCount').textContent = visible;
    document.getElementById('noResultsMsg').style.display = (visible === 0 && ALL_ROWS.length > 0) ? 'block' : 'none';
}

// ── Export CSV ────────────────────────────────────────────────────────────────
document.getElementById('exportCsvBtn').addEventListener('click', function () {
    const visibleRows = ALL_ROWS.filter(r => r.style.display !== 'none');
    if (!visibleRows.length) { alert('No guests to export.'); return; }

    const headers = ['Guest Name', 'Email', 'Tier', 'Total Bookings', 'Last Visit'];
    const lines   = [headers.join(',')];

    visibleRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const name      = cells[0].querySelector('.g-name')?.textContent.trim() ?? '';
        const email     = cells[1].textContent.trim();
        const type      = cells[2].textContent.trim();
        const bookings  = cells[3].textContent.trim();
        const lastVisit = cells[4].textContent.trim();
        lines.push([name, email, type, bookings, lastVisit].map(v => `"${v.replace(/"/g,'""')}"`).join(','));
    });

    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'guests_' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
});

// ── Profile Modal ─────────────────────────────────────────────────────────────
function openGuestProfile(data) {
    const av = document.getElementById('modalAvatar');
    av.textContent       = data.initials;
    av.style.background  = data.avatarBg;
    av.style.color       = data.avatarText;

    document.getElementById('modalName').textContent          = data.name;
    document.getElementById('modalEmail').textContent         = data.email || '—';
    document.getElementById('modalBookAgainBtn').href         = data.book_again_url || 'book?step=2';
    document.getElementById('modalTotalBookings').textContent = data.total;
    document.getElementById('modalFirstVisit').textContent    = data.first_visit || '—';
    document.getElementById('modalTotalSpend').textContent    = data.spend > 0
        ? '₱' + Number(data.spend).toLocaleString('en-PH', {minimumFractionDigits:0})
        : '—';

    // Phone masking & toggle
    window._currentModalRawPhone    = data.phone || '';
    window._currentModalMaskedPhone = data.masked_phone || '—';
    window._isPhoneRevealed         = false;

    const phoneEl = document.getElementById('modalPhone');
    const toggleBtn = document.getElementById('btnTogglePhone');
    phoneEl.textContent = window._currentModalMaskedPhone;

    if (data.phone && data.phone.trim() !== '') {
        toggleBtn.style.display = 'inline-flex';
        toggleBtn.innerHTML = '👁️ Show';
    } else {
        toggleBtn.style.display = 'none';
    }

    document.getElementById('modalCountry').textContent = data.country || '—';

    const specialRequestsSection = document.getElementById('modalSpecialRequestsSection');
    const specialRequestsDiv     = document.getElementById('modalSpecialRequests');
    if (data.special_requests && data.special_requests.trim()) {
        specialRequestsDiv.textContent = data.special_requests;
        specialRequestsSection.style.display = 'block';
    } else {
        specialRequestsSection.style.display = 'none';
    }

    const badgeStyles = {
        'VIP Member':      'background:rgba(132,86,60,0.1);color:#7C533C;border:1px solid rgba(132,86,60,0.25);',
        'Corporate':       'background:rgba(59,130,246,0.1);color:#1565C0;border:1px solid rgba(59,130,246,0.25);',
        'Returning Guest': 'background:rgba(124,58,237,0.1);color:#7C3AED;border:1px solid rgba(124,58,237,0.25);',
    };
    const bs = badgeStyles[data.type] || 'background:rgba(16,185,129,0.1);color:#059669;border:1px solid rgba(16,185,129,0.25);';
    document.getElementById('modalTypeBadge').innerHTML =
        `<span style="${bs}padding:4px 14px;border-radius:99px;font-size:12px;font-weight:700;display:inline-block;">${data.type}</span>`;

    const hist = document.getElementById('modalBookingHistory');
    if (!data.bookings || data.bookings.length === 0) {
        hist.innerHTML = '<div class="empty-history">No booking records found.</div>';
    } else {
        const statusClass = s => {
            if (!s) return '';
            const map = { 'Pending':'pending', 'Checked In':'checked-in', 'Checked Out':'checked-out', 'Cancelled':'cancelled' };
            return map[s] || 'pending';
        };
        let rows = '';
        data.bookings.forEach(b => {
            const amount = b.rate > 0 ? '₱' + (b.rate * b.nights).toLocaleString('en-PH') : '—';
            rows += `<tr>
                <td><strong>#${b.id}</strong></td>
                <td>${b.room}</td>
                <td>${b.check_in}</td>
                <td>${b.check_out}</td>
                <td>${b.nights} night${b.nights !== 1 ? 's' : ''}</td>
                <td><span class="status-pill ${statusClass(b.status)}">${b.status}</span></td>
                <td><strong>${amount}</strong></td>
            </tr>`;
        });
        hist.innerHTML = `<table class="booking-history-table">
            <thead><tr>
                <th>#ID</th><th>Room</th><th>Check-in</th><th>Check-out</th>
                <th>Nights</th><th>Status</th><th>Amount</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
    }

    document.getElementById('guestModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('guestModal').classList.remove('open');
    document.body.style.overflow = '';
}

function togglePhoneVisibility() {
    const phoneEl = document.getElementById('modalPhone');
    const toggleBtn = document.getElementById('btnTogglePhone');
    window._isPhoneRevealed = !window._isPhoneRevealed;

    if (window._isPhoneRevealed) {
        phoneEl.textContent = window._currentModalRawPhone;
        toggleBtn.innerHTML = '🙈 Hide';
    } else {
        phoneEl.textContent = window._currentModalMaskedPhone;
        toggleBtn.innerHTML = '👁️ Show';
    }
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

<script src="assets/js/sidebar-toggle.js"></script>
</body>
</html>

