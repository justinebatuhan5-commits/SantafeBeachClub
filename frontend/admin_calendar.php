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
    <link rel="stylesheet" href="assets/css/admin_calendar.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/admin_calendar.css'); ?>">
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
    $page_subtitle = 'Room occupancy and booking schedule across all accommodations';
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
        <?php include __DIR__ . '/partials/_admin_calendar_content.php'; ?>
    </div><!-- /admin-body -->
</div><!-- /admin-main -->

<?php include __DIR__ . '/partials/_admin_calendar_overlays.php'; ?>

</body>
</html>
