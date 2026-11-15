<?php
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/cloudinary_helper.php';
require_once __DIR__ . '/../backend/controllers/rooms_controller.php';

extract(sfbc_handle_rooms_page($conn), EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
<?php
$_sf_scheme = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on") ? "https" : "http";
$_sf_host   = $_SERVER["HTTP_HOST"] ?? "localhost";
$_sf_dir    = rtrim(str_replace("/frontend", "", dirname($_SERVER["SCRIPT_NAME"])), "/");
$SF_BASE_URL = $_sf_scheme . "://" . $_sf_host . $_sf_dir;
?>
<script>var SF_BASE_URL = "<?php echo $SF_BASE_URL; ?>";</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms &amp; Accommodations &ndash; Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/rooms.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/rooms.css'); ?>">
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body class="rooms-page">

    <!-- Header -->
    <?php $active_nav = 'rooms'; require __DIR__ . '/partials/_navbar.php'; ?>

    <?php require __DIR__ . '/views/rooms/_search_bar.php'; ?>

    <!-- Interactive Guest Availability Calendar -->
    <?php require __DIR__ . '/partials/_rooms_calendar.php'; ?>

    <!-- Main Content + Sidebar -->
    <div class="rooms-layout" id="room-choice">

        <!-- Rooms Main Column -->
        <main class="rooms-main">
            <div class="room-tabs-bar">
                <div class="room-tabs-inner" role="group" aria-label="Browse room categories">
                    <a href="#booking-sidebar" class="room-tab rooms-summary-shortcut">Booking Summary</a>
                    <button class="room-tab room-tab--active" type="button" aria-pressed="true" data-tab="recommended" onclick="switchTab('recommended', this)">
                        Recommended stays
                        <?php if (!empty($recommended_by_type)): ?><span class="room-tab-count"><?php echo count($recommended_by_type); ?></span><?php endif; ?>
                    </button>
                    <button class="room-tab" type="button" aria-pressed="false" data-tab="other" onclick="switchTab('other', this)">
                        More rooms
                        <?php $other_total_count = count($other_available_by_type) + count($unavail_by_type); ?>
                        <?php if ($other_total_count > 0): ?><span class="room-tab-count"><?php echo $other_total_count; ?></span><?php endif; ?>
                    </button>
                </div>
            </div>

            <!-- RECOMMENDED TAB -->
            <div id="tab-recommended" class="tab-panel">
                <div class="rooms-section-intro">
                    <h2>Rooms Recommended For You</h2>
                    <p>Compare photos, sleeping arrangements, and rates to find your perfect stay.</p>
                </div>

                <?php if (empty($recommended_by_type)): ?>
                <div class="rooms-empty">
                    <div class="empty-icon empty-icon--vector" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
                    <h3>No rooms available for your selection</h3>
                    <p>Try adjusting your dates, guest count, or room type to find available accommodations.</p>
                    <a href="rooms" class="btn-reset">View All Rooms</a>
                </div>
                <?php else: ?>
                    <?php foreach ($recommended_by_type as $type_key => $type_rooms): ?>
                        <?php require __DIR__ . '/views/rooms/_available_room_card.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- OTHER AVAILABLE ROOMS TAB -->
            <div id="tab-other" class="tab-panel">
                <div class="rooms-section-intro">
                    <h2>Other Available Rooms</h2>
                    <p>There are more rooms available for your stay</p>
                </div>

                <?php if (!empty($other_available_by_type)): ?>
                    <?php foreach ($other_available_by_type as $type_key => $type_rooms): ?>
                        <?php require __DIR__ . '/views/rooms/_available_room_card.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($unavail_by_type)): ?>
                <div class="rooms-section-intro" style="margin-top: 28px;">
                    <h2>Unavailable Rooms</h2>
                    <p>These rooms are currently unavailable for your selected dates.</p>
                </div>

                    <?php foreach ($unavail_by_type as $type_key => $type_rooms): ?>
                        <?php require __DIR__ . '/views/rooms/_unavailable_room_card.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>

        <?php require __DIR__ . '/views/rooms/_summary_sidebar.php'; ?>

    <?php require __DIR__ . '/views/rooms/_modals.php'; ?>

    <?php require __DIR__ . '/partials/_footer.php'; ?>

    <script>
    window.SFBC_ROOMS_CONFIG = {
        extraPersonLimits: <?php echo json_encode($extra_person_limits, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        hotelData: {
            checkin: <?php echo xss_json($filter_checkin); ?>,
            checkout: <?php echo xss_json($filter_checkout); ?>,
            nights: <?php echo (int)$nights; ?>,
            guests: <?php echo (int)$filter_guests; ?>,
            rooms: <?php echo xss_json($all_rooms_list); ?>,
            bookings: <?php echo xss_json($all_cal_bookings); ?>
        }
    };
    </script>
    <script src="assets/js/pages/rooms.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/js/pages/rooms.js'); ?>"></script>
</body>
</html>
