<?php
/**
 * admin_calendar.php — Interactive Availability Calendar (Timeline / Gantt View)
 * Shows all rooms on the Y-axis, dates on the X-axis, with color-coded booking bars.
 * Staff can drag bars to reschedule, click to view details, and filter by room type.
 * Accessible by both Admin and Receptionist roles.
 */
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';

// Fetch distinct room types for the filter dropdown
$room_types = [];
$rtResult = $conn->query("SELECT DISTINCT type FROM rooms ORDER BY type");
if ($rtResult) {
    while ($r = $rtResult->fetch_assoc()) {
        $room_types[] = $r['type'];
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
    <title>Availability Calendar — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <style>
    /* ══════════════════════════════════════════════════════════════════════════
       AVAILABILITY CALENDAR — SCOPED STYLES
       ══════════════════════════════════════════════════════════════════════════ */

    .cal-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .cal-toolbar .cal-nav-group {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .cal-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 7px 14px;
        border: 1px solid var(--border);
        background: var(--card-bg);
        color: var(--text-main);
        border-radius: 8px;
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
    }
    .cal-btn:hover { background: var(--primary-light); border-color: var(--primary); }
    .cal-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
    .cal-btn svg { width: 14px; height: 14px; flex-shrink: 0; }

    .cal-range-label {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-main);
        min-width: 200px;
        text-align: center;
    }

    .cal-filter-select {
        padding: 7px 12px;
        border: 1px solid var(--border);
        background: var(--card-bg);
        color: var(--text-main);
        border-radius: 8px;
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        cursor: pointer;
        margin-left: auto;
    }

    .cal-availability-search {
        display: flex;
        align-items: end;
        gap: 10px;
        flex-wrap: wrap;
        padding: 14px;
        margin-bottom: 16px;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 10px;
    }
    .cal-date-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .cal-date-field label {
        font-size: 11px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .4px;
    }
    .cal-date-field input {
        padding: 8px 10px;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--input-bg, #fff);
        color: var(--text-main);
        font: 13px 'Outfit', sans-serif;
    }
    .cal-availability-btn {
        padding: 9px 14px;
        border: 0;
        border-radius: 7px;
        background: var(--primary);
        color: #fff;
        font: 600 13px 'Outfit', sans-serif;
        cursor: pointer;
    }
    .cal-availability-btn:hover { background: var(--primary-dark); }
    /* ── Availability Result Modal ───────────────────────────────── */
    .avail-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.35);
        backdrop-filter: blur(3px);
        z-index: 800;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .avail-modal-overlay.open { display: flex; }
    .avail-modal {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(0,0,0,.22);
        width: 100%;
        max-width: 560px;
        max-height: 82vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: avail-modal-in .22s cubic-bezier(.22,1,.36,1);
    }
    @keyframes avail-modal-in {
        from { opacity: 0; transform: translateY(18px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .avail-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 20px 22px 16px;
        border-bottom: 1px solid var(--border);
        gap: 12px;
    }
    .avail-modal-head h3 {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-main);
        margin: 0 0 3px;
    }
    .avail-modal-head p {
        font-size: 12.5px;
        color: var(--text-muted);
        margin: 0;
    }
    .avail-modal-close {
        flex-shrink: 0;
        width: 30px; height: 30px;
        border: none;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .15s;
    }
    .avail-modal-close:hover { background: var(--primary-light); }
    .avail-modal-body {
        overflow-y: auto;
        padding: 18px 22px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .avail-section {
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
    }
    .avail-section-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
    }
    .avail-section-dot {
        width: 9px; height: 9px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .avail-section-count {
        margin-left: auto;
        font-size: 13px;
        font-weight: 800;
    }
    .avail-section-body {
        padding: 10px 14px 12px;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .avail-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid;
        cursor: default;
        transition: opacity .15s;
    }
    .avail-chip:hover { opacity: .75; }
    .avail-chip-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    /* per-status theming */
    .avail-section.available  .avail-section-head { background:rgba(4,120,87,.08);  color:#047857; }
    .avail-section.reserved   .avail-section-head { background:rgba(37,99,235,.08); color:#1D4ED8; }
    .avail-section.occupied   .avail-section-head { background:rgba(16,185,129,.08);color:#065F46; }
    .avail-section.maintenance .avail-section-head { background:rgba(217,119,6,.08); color:#92400E; }
    .avail-chip.available  { background:rgba(4,120,87,.07);  border-color:rgba(4,120,87,.25);  color:#065F46; }
    .avail-chip.reserved   { background:rgba(37,99,235,.07); border-color:rgba(37,99,235,.25); color:#1D4ED8; }
    .avail-chip.occupied   { background:rgba(16,185,129,.07);border-color:rgba(16,185,129,.3); color:#065F46; }
    .avail-chip.maintenance{ background:rgba(217,119,6,.07); border-color:rgba(217,119,6,.3);  color:#92400E; }
    .avail-modal-foot {
        padding: 12px 22px;
        border-top: 1px solid var(--border);
        font-size: 11.5px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .avail-empty {
        text-align:center; padding:30px 0;
        color:var(--text-muted); font-size:13px;
    }

    .cal-legend {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    .cal-legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: var(--text-muted);
    }
    .cal-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 3px;
        flex-shrink: 0;
    }

    /* ── Timeline Grid ────────────────────────────────────────────────── */
    .cal-container {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: var(--shadow);
    }

    .cal-scroll-wrapper {
        overflow-x: auto;
        overflow-y: auto;
        max-height: calc(100vh - 260px);
        position: relative;
    }

    .cal-grid {
        display: grid;
        min-width: max-content;
        position: relative;
    }

    /* Header row */
    .cal-header-row {
        display: contents;
    }
    .cal-header-corner {
        position: sticky;
        left: 0;
        top: 0;
        z-index: 20;
        background: var(--card-bg);
        border-bottom: 2px solid var(--border);
        border-right: 2px solid var(--border);
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        min-width: 180px;
        display: flex;
        align-items: center;
    }
    .cal-header-date {
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--card-bg);
        border-bottom: 2px solid var(--border);
        border-right: 1px solid rgba(0,0,0,0.04);
        padding: 6px 0;
        text-align: center;
        min-width: 48px;
        font-size: 11px;
        color: var(--text-muted);
        line-height: 1.3;
    }
    .cal-header-date .cal-day-name {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        opacity: 0.7;
    }
    .cal-header-date .cal-day-num {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-main);
    }
    .cal-header-date.today {
        background: var(--primary-light);
    }
    .cal-header-date.today .cal-day-num {
        color: var(--primary);
    }
    .cal-header-date.weekend {
        background: rgba(0,0,0,0.015);
    }

    /* Room rows */
    .cal-room-label {
        position: sticky;
        left: 0;
        z-index: 5;
        background: var(--card-bg);
        border-bottom: 1px solid var(--border);
        border-right: 2px solid var(--border);
        padding: 8px 14px;
        min-width: 180px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .cal-room-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-main);
        white-space: nowrap;
    }
    .cal-room-type {
        font-size: 11px;
        color: var(--text-muted);
    }
    .cal-room-status {
        display: inline-block;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        margin-right: 6px;
        flex-shrink: 0;
    }
    .cal-room-status.ready { background: #10B981; }
    .cal-room-status.occupied { background: #3B82F6; }
    .cal-room-status.maintenance { background: #F59E0B; }

    .cal-cell {
        border-bottom: 1px solid var(--border);
        border-right: 1px solid rgba(0,0,0,0.04);
        min-width: 48px;
        min-height: 44px;
        position: relative;
    }
    .cal-cell.today {
        background: rgba(124,83,60,0.04);
    }
    .cal-cell.weekend {
        background: rgba(0,0,0,0.012);
    }

    /* Today marker line */
    .cal-today-line {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #E0483E;
        z-index: 8;
        pointer-events: none;
    }

    /* ── Booking Bars ─────────────────────────────────────────────────── */
    .cal-bar {
        position: absolute;
        height: 28px;
        top: 50%;
        transform: translateY(-50%);
        border-radius: 6px;
        display: flex;
        align-items: center;
        padding: 0 8px;
        font-size: 11px;
        font-weight: 600;
        color: #fff;
        cursor: grab;
        z-index: 6;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: box-shadow 0.15s, opacity 0.15s;
        user-select: none;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    }
    .cal-bar:hover {
        box-shadow: 0 3px 12px rgba(0,0,0,0.2);
        z-index: 9;
    }
    .cal-bar.dragging {
        opacity: 0.8;
        cursor: grabbing;
        z-index: 15;
        box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    }
    .cal-bar.status-pending      { background: #F59E0B; color: #FFFFFF; }
    .cal-bar.status-pending-payment { background: #F59E0B; color: #FFFFFF; }
    .cal-bar.status-confirmed    { background: #F59E0B; color: #FFFFFF; }
    .cal-bar.status-checked-in   { background: #10B981; color: #FFFFFF; }
    .cal-bar.status-checked-out  { background: #64748B; color: #FFFFFF; }
    .cal-bar.status-cancelled    { background: #991B1B; color: #FFFFFF; opacity: 1; text-decoration: line-through; cursor: default; }

    .cal-bar-guest {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ── Detail Side Panel ────────────────────────────────────────────── */
    .cal-detail-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.25);
        z-index: 500;
    }
    .cal-detail-overlay.open { display: block; }

    .cal-detail-panel {
        position: fixed;
        top: 0;
        right: -420px;
        width: 400px;
        max-width: 90vw;
        height: 100vh;
        background: var(--card-bg);
        box-shadow: -4px 0 24px rgba(0,0,0,0.12);
        z-index: 510;
        transition: right 0.3s ease;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }
    .cal-detail-panel.open { right: 0; }

    .cal-detail-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px 16px;
        border-bottom: 1px solid var(--border);
    }
    .cal-detail-header h3 {
        font-size: 17px;
        font-weight: 700;
        color: var(--text-main);
    }
    .cal-detail-close {
        width: 32px;
        height: 32px;
        border: none;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
    }
    .cal-detail-close:hover { background: var(--primary-light); }

    .cal-detail-body {
        padding: 20px 24px;
        flex: 1;
    }
    .cal-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 10px 0;
        border-bottom: 1px solid var(--border);
    }
    .cal-detail-row:last-child { border-bottom: none; }
    .cal-detail-label {
        font-size: 12px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.3px;
        font-weight: 500;
    }
    .cal-detail-value {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-main);
        text-align: right;
    }

    .cal-detail-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border);
    }
    .cal-detail-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.15s;
        width: 100%;
        justify-content: center;
    }
    .cal-detail-link:hover { background: var(--primary-dark); }

    /* ── Toast Notification ───────────────────────────────────────────── */
    .cal-toast {
        position: fixed;
        bottom: 30px;
        right: 30px;
        padding: 12px 20px;
        border-radius: 10px;
        font-family: 'Outfit', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        z-index: 600;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        transform: translateY(80px);
        opacity: 0;
        transition: all 0.3s ease;
    }
    .cal-toast.show { transform: translateY(0); opacity: 1; }
    .cal-toast.success { background: #10B981; }
    .cal-toast.error   { background: #EF4444; }

    /* ── Empty state ──────────────────────────────────────────────────── */
    .cal-empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }
    .cal-empty svg {
        width: 48px;
        height: 48px;
        margin-bottom: 12px;
        opacity: 0.4;
    }
    .cal-empty p {
        font-size: 14px;
    }

    /* ── Responsive ───────────────────────────────────────────────────── */
    @media (max-width: 768px) {
        .cal-toolbar { gap: 8px; }
        .cal-range-label { min-width: auto; font-size: 13px; }
        .cal-filter-select { margin-left: 0; }
    }
    </style>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

<!-- ═══ SIDEBAR ═══════════════════════════════════════════════════════════ -->
<?php
$active_page = 'calendar';
require_once 'partials/_sidebar.php';
?>

<div class="admin-main">
    <?php
    $page_title = 'Availability Calendar';
    $page_subtitle = 'Interactive timeline showing real-time room occupancy across all accommodations';
    $header_extra_html = '
        <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius-sm);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span id="topbarDate">' . date('l, F j, Y') . '</span>
        </div>
    ';
    include __DIR__ . '/partials/_page_header.php';
    ?>

    <!-- Body -->
    <div class="admin-body">

        <!-- Toolbar -->
        <div class="cal-toolbar">
            <div class="cal-nav-group">
                <button class="cal-btn" id="calPrev" title="Previous period">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="cal-btn" id="calToday">Today</button>
                <button class="cal-btn" id="calNext" title="Next period">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
            <span class="cal-range-label" id="calRangeLabel"></span>
            <select class="cal-filter-select" id="calFilter">
                <option value="all">All Room Types</option>
                <?php foreach ($room_types as $type): ?>
                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $type))); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="cal-availability-search">
            <div class="cal-date-field">
                <label for="availabilityCheckIn">Check-in</label>
                <input type="date" id="availabilityCheckIn">
            </div>
            <div class="cal-date-field">
                <label for="availabilityCheckOut">Check-out</label>
                <input type="date" id="availabilityCheckOut">
            </div>
            <button type="button" class="cal-availability-btn" id="availabilitySearchBtn">Check Availability</button>
        </div>

        <!-- Availability Result Modal -->
        <div class="avail-modal-overlay" id="availModalOverlay">
            <div class="avail-modal" role="dialog" aria-modal="true" aria-labelledby="availModalTitle">
                <div class="avail-modal-head">
                    <div>
                        <h3 id="availModalTitle">Room Availability</h3>
                        <p id="availModalSubtitle">—</p>
                    </div>
                    <button class="avail-modal-close" id="availModalClose" title="Close">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="avail-modal-body" id="availModalBody">
                    <div class="avail-empty">Searching…</div>
                </div>
                <div class="avail-modal-foot">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    Cancelled &amp; Checked-Out bookings are not counted as conflicts.
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="cal-legend">
            <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#F59E0B;"></div> Pending</div>
            <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#10B981;"></div> Checked In</div>
            <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#94A3B8;"></div> Checked Out</div>
            <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#EF4444;"></div> Cancelled</div>
            <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#E0483E; width:2px; height:14px; border-radius:1px;"></div> Today</div>
        </div>

        <!-- Calendar Container -->
        <div class="cal-container">
            <div class="cal-scroll-wrapper" id="calScrollWrapper">
                <div class="cal-grid" id="calGrid">
                    <!-- Rendered by JavaScript -->
                </div>
            </div>
        </div>

    </div><!-- /admin-body -->
</div><!-- /admin-main -->

<!-- Detail Side Panel -->
<div class="cal-detail-overlay" id="calDetailOverlay"></div>
<div class="cal-detail-panel" id="calDetailPanel">
    <div class="cal-detail-header">
        <h3>Booking Details</h3>
        <button class="cal-detail-close" id="calDetailClose">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="cal-detail-body" id="calDetailBody">
        <!-- Populated dynamically -->
    </div>
    <div class="cal-detail-footer">
        <a class="cal-detail-link" id="calDetailLink" href="#">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            View Full Booking
        </a>
    </div>
</div>

<!-- Toast -->
<div class="cal-toast" id="calToast"></div>

<script>
/* ═══════════════════════════════════════════════════════════════════════════
   AVAILABILITY CALENDAR — JAVASCRIPT
   ═══════════════════════════════════════════════════════════════════════════ */
(function() {
    'use strict';

    // ── Config ───────────────────────────────────────────────────────────
    const DAYS_TO_SHOW = 30;
    const CELL_WIDTH   = 48;
    const ROW_HEIGHT   = 44;

    // ── State ────────────────────────────────────────────────────────────
    let viewStart   = new Date();
    let rooms       = [];
    let bookings    = [];
    let filterType  = 'all';
    let todayKey    = '';

    // Set viewStart to 3 days ago for context
    viewStart.setDate(viewStart.getDate() - 3);
    viewStart.setHours(0, 0, 0, 0);

    // ── DOM refs ─────────────────────────────────────────────────────────
    const grid           = document.getElementById('calGrid');
    const scrollWrapper  = document.getElementById('calScrollWrapper');
    const rangeLabel     = document.getElementById('calRangeLabel');
    const filterSelect   = document.getElementById('calFilter');
    const overlay        = document.getElementById('calDetailOverlay');
    const panel          = document.getElementById('calDetailPanel');
    const detailBody     = document.getElementById('calDetailBody');
    const detailLink     = document.getElementById('calDetailLink');
    const toastEl        = document.getElementById('calToast');

    // ── Helpers ──────────────────────────────────────────────────────────
    function dateStr(d) {
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    function getTodayDate() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return today;
    }

    function updateTopbarDate() {
        const el = document.getElementById('topbarDate');
        if (!el) return;
        el.textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    function setViewStartNearToday() {
        viewStart = getTodayDate();
        viewStart.setDate(viewStart.getDate() - 7);
        viewStart.setHours(0, 0, 0, 0);
    }

    function syncCalendarWithSystemDate() {
        const currentKey = dateStr(getTodayDate());
        if (todayKey !== currentKey) {
            todayKey = currentKey;
            setViewStartNearToday();
            updateTopbarDate();
            fetchData();
            return;
        }

        updateTopbarDate();
        render();
    }

    function addDays(d, n) {
        const r = new Date(d);
        r.setDate(r.getDate() + n);
        return r;
    }

    function diffDays(a, b) {
        return Math.round((b - a) / 86400000);
    }

    function fmtDate(str) {
        const d = new Date(str + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function fmtShortDate(d) {
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function dayNames(d) {
        return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()];
    }

    function isWeekend(d) {
        return d.getDay() === 0 || d.getDay() === 6;
    }

    function statusClass(status) {
        return 'status-' + status.toLowerCase().replace(/\s+/g, '-');
    }

    // ── Toast ────────────────────────────────────────────────────────────
    function toast(msg, type) {
        toastEl.textContent = msg;
        toastEl.className = 'cal-toast ' + type;
        setTimeout(() => toastEl.classList.add('show'), 10);
        setTimeout(() => toastEl.classList.remove('show'), 3500);
    }

    // ── Fetch Data ───────────────────────────────────────────────────────
    function fetchData() {
        const start = dateStr(viewStart);
        const end   = dateStr(addDays(viewStart, DAYS_TO_SHOW));
        rangeLabel.textContent = fmtShortDate(viewStart) + ' – ' + fmtShortDate(addDays(viewStart, DAYS_TO_SHOW - 1));

        // Determine base API path safely whether URL is at /frontend/ or at root /
        const apiPath = window.location.pathname.includes('/frontend/') 
            ? '../backend/api/calendar_api.php' 
            : 'backend/api/calendar_api.php';

        fetch(apiPath + '?start=' + start + '&end=' + end)
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                rooms    = data.rooms || [];
                bookings = data.bookings || [];
                render();
            })
            .catch(err => {
                console.error('Calendar fetch error:', err);
                toast('Failed to load calendar data.', 'error');
            });
    }

    // ── Render Grid ──────────────────────────────────────────────────────
    function render() {
        const filtered = filterType === 'all'
            ? rooms
            : rooms.filter(r => r.type === filterType);

        if (!filtered.length) {
            grid.innerHTML = '<div class="cal-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><p>No rooms found for this filter.</p></div>';
            return;
        }

        const cols = DAYS_TO_SHOW + 1; // +1 for room label column
        grid.style.gridTemplateColumns = '180px ' + 'repeat(' + DAYS_TO_SHOW + ', ' + CELL_WIDTH + 'px)';
        grid.style.gridTemplateRows    = 'auto ' + 'repeat(' + filtered.length + ', ' + ROW_HEIGHT + 'px)';

        let html = '';
        const today = dateStr(getTodayDate());

        // ── Header row ──────────────────────────────────────────────
        html += '<div class="cal-header-corner">Room / Date</div>';
        for (let i = 0; i < DAYS_TO_SHOW; i++) {
            const d = addDays(viewStart, i);
            const ds = dateStr(d);
            let cls = 'cal-header-date';
            if (ds === today) cls += ' today';
            if (isWeekend(d)) cls += ' weekend';
            html += '<div class="' + cls + '">' +
                '<div class="cal-day-name">' + dayNames(d) + '</div>' +
                '<div class="cal-day-num">' + d.getDate() + '</div>' +
                '</div>';
        }

        // ── Room rows ───────────────────────────────────────────────
        filtered.forEach((room, rowIdx) => {
            // Room label
            const statusDot = '<span class="cal-room-status ' + Security.escapeHTML(room.status) + '"></span>';
            html += '<div class="cal-room-label">' +
                '<div class="cal-room-name">' + statusDot + Security.escapeHTML(room.name) + '</div>' +
                '<div class="cal-room-type">' + Security.escapeHTML(room.type_label) + ' · ₱' + room.price.toLocaleString() + '/night</div>' +
                '</div>';

            // Date cells
            for (let i = 0; i < DAYS_TO_SHOW; i++) {
                const d = addDays(viewStart, i);
                const ds = dateStr(d);
                let cls = 'cal-cell';
                if (ds === today) cls += ' today';
                if (isWeekend(d)) cls += ' weekend';
                html += '<div class="' + cls + '" data-room="' + Security.escapeHTML(room.id) + '" data-date="' + ds + '" data-col="' + i + '" data-row="' + rowIdx + '"></div>';
            }
        });

        grid.innerHTML = html;

        // ── Render booking bars ─────────────────────────────────────
        filtered.forEach((room, rowIdx) => {
            const roomBookings = bookings.filter(b => b.room_id === room.id);
            roomBookings.forEach(booking => {
                renderBar(booking, room, rowIdx);
            });
        });

        // ── Render today line ───────────────────────────────────────
        const todayIdx = diffDays(viewStart, getTodayDate());
        if (todayIdx >= 0 && todayIdx < DAYS_TO_SHOW) {
            // Snap the marker to the actual current day, not the current time.
            // This keeps the red line aligned with the calendar date instead of drifting to the next column.
            const leftPx = 180 + (todayIdx + 0.5) * CELL_WIDTH;
            const line = document.createElement('div');
            line.className = 'cal-today-line';
            line.style.left = leftPx + 'px';
            grid.appendChild(line);
        }
    }

    // ── Render a single booking bar ──────────────────────────────────
    function renderBar(booking, room, rowIdx) {
        const ciDate = new Date(booking.check_in + 'T00:00:00');
        const coDate = new Date(booking.check_out + 'T00:00:00');

        const startCol = diffDays(viewStart, ciDate);
        const endCol   = diffDays(viewStart, coDate);

        // Clamp to visible range
        const visStart = Math.max(0, startCol);
        const visEnd   = Math.min(DAYS_TO_SHOW, endCol);
        if (visStart >= visEnd) return;

        const leftPx  = 180 + visStart * CELL_WIDTH + 2;
        const widthPx = (visEnd - visStart) * CELL_WIDTH - 4;
        const topPx   = ROW_HEIGHT + 1 + rowIdx * ROW_HEIGHT; // +1 for header row border

        const bar = document.createElement('div');
        bar.className = 'cal-bar ' + statusClass(booking.status);
        bar.style.left   = leftPx + 'px';
        bar.style.width  = widthPx + 'px';
        bar.style.top    = (topPx + ROW_HEIGHT / 2) + 'px';
        bar.dataset.bookingId = booking.id;
        bar.innerHTML = '<span class="cal-bar-guest">' + Security.escapeHTML(booking.guest_name) + '</span>';
        bar.title = booking.guest_name + ' · ' + booking.check_in + ' &rarr; ' + booking.check_out + ' · ' + booking.status;

        // ── Click &rarr; show details ────────────────────────────────
        bar.addEventListener('click', (e) => {
            if (bar.classList.contains('dragging')) return;
            showDetail(booking);
        });

        // ── Drag & drop (except cancelled/checked out) ──────────
        if (booking.status !== 'Cancelled' && booking.status !== 'Checked Out') {
            initDrag(bar, booking, room, rowIdx);
        } else {
            bar.style.cursor = 'pointer';
        }

        grid.appendChild(bar);
    }

    // ── Drag & Drop Logic ────────────────────────────────────────────
    function initDrag(bar, booking, room, rowIdx) {
        let startX = 0;
        let origLeft = 0;
        let isDragging = false;

        function onDown(e) {
            e.preventDefault();
            isDragging = false;
            startX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
            origLeft = parseInt(bar.style.left, 10);
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onUp);
        }

        function onMove(e) {
            e.preventDefault();
            const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
            const dx = clientX - startX;

            if (!isDragging && Math.abs(dx) > 4) {
                isDragging = true;
                bar.classList.add('dragging');
            }

            if (isDragging) {
                // Snap to cell boundaries
                const snappedDx = Math.round(dx / CELL_WIDTH) * CELL_WIDTH;
                bar.style.left = (origLeft + snappedDx) + 'px';
            }
        }

        function onUp(e) {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onUp);

            if (!isDragging) return;

            bar.classList.remove('dragging');

            const finalLeft = parseInt(bar.style.left, 10);
            const daysMoved = Math.round((finalLeft - origLeft) / CELL_WIDTH);

            if (daysMoved === 0) {
                bar.style.left = origLeft + 'px';
                return;
            }

            // Calculate new dates
            const ciDate = new Date(booking.check_in + 'T00:00:00');
            const coDate = new Date(booking.check_out + 'T00:00:00');
            const newCI = addDays(ciDate, daysMoved);
            const newCO = addDays(coDate, daysMoved);

            // Animate bar back while we wait for API
            const newCIstr = dateStr(newCI);
            const newCOstr = dateStr(newCO);

            // Save via API
            const updateApiPath = window.location.pathname.includes('/frontend/') 
                ? '../backend/api/calendar_update.php' 
                : 'backend/api/calendar_update.php';

            fetch(updateApiPath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    booking_id:    booking.id,
                    new_check_in:  newCIstr,
                    new_check_out: newCOstr,
                }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Update local state
                    booking.check_in  = newCIstr;
                    booking.check_out = newCOstr;
                    bar.title = booking.guest_name + ' · ' + newCIstr + ' &rarr; ' + newCOstr + ' · ' + booking.status;
                    toast('✓ ' + data.message, 'success');
                } else {
                    // Revert
                    bar.style.left = origLeft + 'px';
                    toast('✗ ' + (data.error || 'Failed to reschedule.'), 'error');
                }
            })
            .catch(err => {
                bar.style.left = origLeft + 'px';
                toast('✗ Network error. Please try again.', 'error');
                console.error('Update error:', err);
            });
        }

        bar.addEventListener('mousedown', onDown);
        bar.addEventListener('touchstart', onDown, { passive: false });
    }

    // ── Detail Panel ─────────────────────────────────────────────────
    function showDetail(b) {
        const nights = diffDays(new Date(b.check_in + 'T00:00:00'), new Date(b.check_out + 'T00:00:00'));
        const statusBadge = '<span class="badge badge-' + Security.escapeHTML(b.status.toLowerCase().replace(/\s+/g, '')) + '" style="' + getStatusStyle(b.status) + '">' + Security.escapeHTML(b.status) + '</span>';

        detailBody.innerHTML =
            '<div class="cal-detail-row"><span class="cal-detail-label">Booking #</span><span class="cal-detail-value">' + Security.escapeHTML(b.id) + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-label">Guest</span><span class="cal-detail-value">' + Security.escapeHTML(b.guest_name) + '</span></div>' +
            (b.guest_email ? '<div class="cal-detail-row"><span class="cal-detail-label">Email</span><span class="cal-detail-value">' + Security.escapeHTML(b.guest_email) + '</span></div>' : '') +
            '<div class="cal-detail-row"><span class="cal-detail-label">Room</span><span class="cal-detail-value">' + Security.escapeHTML(b.room_name) + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-label">Check-in</span><span class="cal-detail-value">' + fmtDate(b.check_in) + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-label">Check-out</span><span class="cal-detail-value">' + fmtDate(b.check_out) + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-label">Nights</span><span class="cal-detail-value">' + nights + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-label">Guests</span><span class="cal-detail-value">' + Security.escapeHTML(b.guests_count) + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-label">ETA</span><span class="cal-detail-value">' + Security.escapeHTML(b.eta) + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-label">Status</span><span class="cal-detail-value">' + statusBadge + '</span></div>';

        detailLink.href = 'admin_reservations?highlight=' + b.id;
        overlay.classList.add('open');
        panel.classList.add('open');
    }

    function closeDetail() {
        overlay.classList.remove('open');
        panel.classList.remove('open');
    }

    function getStatusStyle(status) {
        const map = {
            'Pending':     'background:#F59E0B;color:#FFFFFF;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;',
            'Checked In':  'background:#ECFDF5;color:#065F46;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;',
            'Checked Out': 'background:#F1F5F9;color:#475569;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;',
            'Cancelled':   'background:#991B1B;color:#FFFFFF;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;',
        };
        return map[status] || '';
    }

    // ── Event Listeners ──────────────────────────────────────────────
    document.getElementById('calPrev').addEventListener('click', () => {
        viewStart = addDays(viewStart, -7);
        fetchData();
    });

    document.getElementById('calNext').addEventListener('click', () => {
        viewStart = addDays(viewStart, 7);
        fetchData();
    });

    document.getElementById('calToday').addEventListener('click', () => {
        setViewStartNearToday();
        fetchData();
    });

    filterSelect.addEventListener('change', () => {
        filterType = filterSelect.value;
        render();
    });

    document.getElementById('calDetailClose').addEventListener('click', closeDetail);
    overlay.addEventListener('click', closeDetail);

    // ESC to close detail panel
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDetail();
    });

    window.addEventListener('focus', () => {
        syncCalendarWithSystemDate();
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            syncCalendarWithSystemDate();
        }
    });

    setInterval(syncCalendarWithSystemDate, 30000);

    // ── Availability Search ──────────────────────────────────────────
    (function setupAvailabilitySearch() {
        const ciInput  = document.getElementById('availabilityCheckIn');
        const coInput  = document.getElementById('availabilityCheckOut');
        const btn      = document.getElementById('availabilitySearchBtn');
        const overlay  = document.getElementById('availModalOverlay');
        const modalBody= document.getElementById('availModalBody');
        const subtitle = document.getElementById('availModalSubtitle');

        function openModal() { overlay.classList.add('open'); }
        function closeModal() { overlay.classList.remove('open'); }

        document.getElementById('availModalClose').addEventListener('click', closeModal);
        overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

        // Default check-in = today, check-out = tomorrow
        const todayIso    = dateStr(getTodayDate());
        const tomorrowIso = dateStr(addDays(getTodayDate(), 1));
        ciInput.min   = todayIso;
        ciInput.value = todayIso;
        coInput.min   = tomorrowIso;
        coInput.value = tomorrowIso;

        ciInput.addEventListener('change', () => {
            const newMin = dateStr(addDays(new Date(ciInput.value + 'T00:00:00'), 1));
            coInput.min = newMin;
            if (coInput.value <= ciInput.value) coInput.value = newMin;
        });

        btn.addEventListener('click', () => {
            const ci = ciInput.value;
            const co = coInput.value;

            function showModalError(msg) {
                subtitle.textContent = '';
                modalBody.innerHTML = '<div class="avail-empty" style="color:#EF4444;">' + Security.escapeHTML(msg) + '</div>';
                openModal();
            }

            if (!ci || !co) { showModalError('Please select both check-in and check-out dates.'); return; }
            if (co <= ci)   { showModalError('Check-out must be after check-in.'); return; }

            // Open modal immediately with a spinner so it feels instant
            subtitle.textContent = 'Searching…';
            modalBody.innerHTML  = '<div class="avail-empty">⏳ Searching rooms…</div>';
            openModal();
            btn.disabled = true;
            btn.textContent = 'Searching…';

            const viewEnd    = dateStr(addDays(viewStart, DAYS_TO_SHOW));
            const needsExpand = ci < dateStr(viewStart) || co > viewEnd;

            function doSearch() {
                const ciDate = new Date(ci + 'T00:00:00');
                const coDate = new Date(co + 'T00:00:00');
                const nights = Math.round((coDate - ciDate) / 86400000);
                const nightLabel = nights === 1 ? '1 night' : nights + ' nights';

                const available   = [];
                const reserved    = [];
                const occupied    = [];
                const maintenance = [];

                rooms.forEach(room => {
                    if (room.status === 'maintenance') { maintenance.push(room); return; }
                    const conflict = bookings.find(b => {
                        if (b.room_id !== room.id) return false;
                        if (b.status === 'Cancelled' || b.status === 'Checked Out') return false;
                        return b.check_in < co && b.check_out > ci;
                    });
                    if (!conflict)                              available.push(room);
                    else if (conflict.status === 'Checked In') occupied.push(room);
                    else                                        reserved.push(room);
                });

                subtitle.textContent = fmtDate(ci) + '  &rarr;  ' + fmtDate(co) + '  ·  ' + nightLabel;

                // Dot colours matching CSS classes
                const dotColors = { available:'#047857', reserved:'#1D4ED8', occupied:'#059669', maintenance:'#D97706' };
                const labels    = { available:'Available', reserved:'Reserved', occupied:'Occupied (checked-in)', maintenance:'Maintenance' };

                function sectionHtml(key, list) {
                    if (!list.length) return '';
                    const chips = list.map(r =>
                        '<span class="avail-chip ' + key + '" title="' + Security.escapeHTML(r.type_label) + ' · ₱' + r.price.toLocaleString() + '/night">' +
                        '<span class="avail-chip-dot" style="background:' + dotColors[key] + ';"></span>' +
                        Security.escapeHTML(r.name) + '</span>'
                    ).join('');
                    return '<div class="avail-section ' + key + '">' +
                           '  <div class="avail-section-head">' +
                           '    <span class="avail-section-dot" style="background:' + dotColors[key] + ';"></span>' +
                           '    ' + labels[key] +
                           '    <span class="avail-section-count">' + list.length + '</span>' +
                           '  </div>' +
                           '  <div class="avail-section-body">' + chips + '</div>' +
                           '</div>';
                }

                const sections = sectionHtml('available', available) +
                                 sectionHtml('reserved',  reserved)  +
                                 sectionHtml('occupied',  occupied)  +
                                 sectionHtml('maintenance', maintenance);

                modalBody.innerHTML = sections ||
                    '<div class="avail-empty">No rooms found for this date range.</div>';

                btn.disabled = false;
                btn.textContent = 'Check Availability';
            }

            if (needsExpand) {
                const origStart = viewStart;
                viewStart = new Date(ci + 'T00:00:00');
                viewStart.setDate(viewStart.getDate() - 1);
                const start = dateStr(viewStart);
                const end   = dateStr(addDays(viewStart, DAYS_TO_SHOW));
                rangeLabel.textContent = fmtShortDate(viewStart) + ' – ' + fmtShortDate(addDays(viewStart, DAYS_TO_SHOW - 1));
                const searchApiPath = window.location.pathname.includes('/frontend/') 
                    ? '../backend/api/calendar_api.php' 
                    : 'backend/api/calendar_api.php';
                fetch(searchApiPath + '?start=' + start + '&end=' + end)
                    .then(r => r.json())
                    .then(data => {
                        rooms    = data.rooms    || rooms;
                        bookings = data.bookings || bookings;
                        render();
                        doSearch();
                    })
                    .catch(() => {
                        viewStart = origStart;
                        btn.disabled = false;
                        btn.textContent = 'Check Availability';
                        subtitle.textContent = '';
                        modalBody.innerHTML = '<div class="avail-empty" style="color:#EF4444;">Failed to fetch data. Please try again.</div>';
                    });
            } else {
                doSearch();
            }
        });
    })();

    // ── Initial Load ─────────────────────────────────────────────────
    todayKey = dateStr(getTodayDate());
    updateTopbarDate();
    fetchData();

})();
</script>

</body>
</html>
