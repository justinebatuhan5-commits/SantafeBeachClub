<?php
require_once __DIR__ . '/../backend/helpers/security_headers.php';
require_once __DIR__ . '/../backend/config/db.php';

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   Filter inputs
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$filter_type     = isset($_GET['room_type']) ? $_GET['room_type'] : 'any';
$filter_guests   = isset($_GET['guests'])    ? (int)$_GET['guests']    : 1;
$filter_checkin  = isset($_GET['checkin'])   ? $_GET['checkin']        : date('Y-m-d');
$filter_checkout = isset($_GET['checkout'])  ? $_GET['checkout']       : date('Y-m-d', strtotime('+1 day'));

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   Fetch rooms (with unavailable flag for
   rooms already booked in the date range)
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$where_clauses = [];
$where_clauses[] = "r.type IN ('beachview_duplex','seaview_duplex','beach_villa','standard_room','standard_king')";
if ($filter_type !== 'any') {
    $safe_type = $conn->real_escape_string($filter_type);
    $where_clauses[] = "r.type = '$safe_type'";
}
if ($filter_guests > 0) {
    $where_clauses[] = "r.capacity >= " . intval($filter_guests);
}

$where_sql = count($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$safe_checkin  = $conn->real_escape_string($filter_checkin);
$safe_checkout = $conn->real_escape_string($filter_checkout);

$sql = "
    SELECT r.*,
           (
             SELECT COUNT(*)
             FROM bookings b
             WHERE (
                 b.room_id = r.id
                 OR (
                     b.room_id IS NULL AND (
                         (b.room_type_id IS NOT NULL AND b.room_type_id = (SELECT rt.id FROM room_types rt WHERE rt.name = r.type LIMIT 1))
                         OR LOWER(COALESCE(b.accommodation_name, '')) LIKE CONCAT('%', LOWER(r.type), '%')
                     )
                 )
             )
             AND LOWER(COALESCE(b.status, '')) NOT IN ('cancelled', 'canceled', 'checked out')
             AND b.check_in < '$safe_checkout'
             AND b.check_out > '$safe_checkin'
           ) AS active_bookings
    FROM rooms r
    $where_sql
    ORDER BY r.price_per_night ASC
";

$result = $conn->query($sql);
$rooms  = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   Calendar bookings for client availability
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$cal_range_start = date('Y-m-d', strtotime('-14 days'));
$cal_range_end   = date('Y-m-d', strtotime('+120 days'));
$cal_bookings_res = $conn->query("
    SELECT room_id, check_in, check_out, status
    FROM bookings
    WHERE status NOT IN ('Cancelled', 'Checked Out')
      AND check_in < '$cal_range_end'
      AND check_out > '$cal_range_start'
");
$all_cal_bookings = $cal_bookings_res ? $cal_bookings_res->fetch_all(MYSQLI_ASSOC) : [];

$all_rooms_res = $conn->query("SELECT id, room_number, name, type, price_per_night, capacity, status FROM rooms");
$all_rooms_list = $all_rooms_res ? $all_rooms_res->fetch_all(MYSQLI_ASSOC) : [];

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   Display helpers
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$type_labels = [
    'beachview_duplex' => 'Beachview Duplex',
    'seaview_duplex'   => 'Seaview Duplex',
    'beach_villa'      => 'Beach Villa',
    'standard_room'    => 'Standard Room',
    'standard_king'    => 'Standard Family Room',
];

$type_gradients = [
    'beachview_duplex' => 'linear-gradient(135deg, #1a6b8a 0%, #0d9488 50%, #065f46 100%)',
    'seaview_duplex'   => 'linear-gradient(135deg, #1e3a5f 0%, #1a6b8a 50%, #0891b2 100%)',
    'beach_villa'      => 'linear-gradient(135deg, #92400e 0%, #b45309 50%, #d97706 100%)',
    'standard_room'    => 'linear-gradient(135deg, #374151 0%, #4b5563 50%, #6b7280 100%)',
    'standard_king'    => 'linear-gradient(135deg, #4c1d95 0%, #6d28d9 50%, #7c3aed 100%)',
];

$type_icons = [
    'beachview_duplex' => 'ðŸŒŠ',
    'seaview_duplex'   => 'ðŸŒ…',
    'beach_villa'      => 'ðŸ–ï¸',
    'standard_room'    => 'ðŸ›ï¸',
    'standard_king'    => 'ðŸ‘‘',
];

// Fetch custom photos from room_types table
$db_photos = [];
$db_modal_photos = [];
$photo_q = $conn->query("SELECT name, image_url, gallery_images FROM room_types");
if ($photo_q) {
    while ($row = $photo_q->fetch_assoc()) {
        $t_name = $row['name'];
        if (!empty($row['image_url'])) {
            $db_photos[$t_name] = $row['image_url'];
        }
        if (!empty($row['gallery_images'])) {
            $gallery_arr = array_filter(array_map('trim', explode(',', $row['gallery_images'])));
            if (!empty($gallery_arr)) {
                $db_modal_photos[$t_name] = $gallery_arr;
            }
        }
    }
}

$type_photos = [
    'beachview_duplex' => $db_photos['beachview_duplex'] ?? '',
    'seaview_duplex'   => $db_photos['seaview_duplex']   ?? '',
    'beach_villa'      => $db_photos['beach_villa']      ?? '',
    'standard_room'    => $db_photos['standard_room']    ?? '',
    'standard_king'    => $db_photos['standard_king']    ?? '',
];

$type_descriptions = [
    'beachview_duplex' => 'Front-row. Serene. Intimate. Sensual.',
    'seaview_duplex'   => 'Front-row. Inspiring. Captivating. Sensual.',
    'beach_villa'      => 'Beachfront. Natural light-filled. Calm.',
    'standard_room'    => 'Standard Room',
    'standard_king'    => 'Standard Family Room',
];

$type_long_descriptions = [
    'beachview_duplex' => "Front-row. Serene. Intimate. Sensual.\nCozy Modern Tropical bedroom is the other half of the duplex structure facing the beach. Perfect people watching spot by your terrace or enjoy the property's entire beach view inside your room.\nYou can even transform to a blackout window cave-like dwelling with the drop-down projector screen.",
    'seaview_duplex'   => "Front-row. Inspiring. Captivating. Sensual.\nDesigned to make you never want to leave your room. A reading nook and desk crafted to connect you to nature while your cozy bedroom feels like a warm embrace even introverts would love.\nA secret back door offers direct access to the allure of the sea calling just beyond.",
    'beach_villa'      => "Beachfront. Natural light-filled. Calm.\nAn intimate 59sqm tropical modern coastal accommodation featuring atmospheric high ceilings to maximize a biophilic natural light-filled layout.\n*26 sqm bedroom 2x Queen beds\n*12 sqm glass-walled shower room with dual skylit vanity lavatories\n*4 sqm pocket garden for air-drying\n*10 sqm Covered beachfront lounge\n\nMaximum of 2 kids for free, 10 years and below.",
    'standard_room'    => "Standard Room\nMaximum of 2 kids for free, 10 years and below.\nMaximum occupancy of 5 adults.",
    'standard_king'    => "Standard Family Room\nMaximum of 2 kids for free, 10 years and below.",
];

$type_modal_photos = [
    'beachview_duplex' => $db_modal_photos['beachview_duplex'] ?? ($type_photos['beachview_duplex'] ? [$type_photos['beachview_duplex']] : []),
    'seaview_duplex'   => $db_modal_photos['seaview_duplex']   ?? ($type_photos['seaview_duplex']   ? [$type_photos['seaview_duplex']]   : []),
    'beach_villa'      => $db_modal_photos['beach_villa']      ?? ($type_photos['beach_villa']      ? [$type_photos['beach_villa']]      : []),
    'standard_room'    => $db_modal_photos['standard_room']    ?? ($type_photos['standard_room']    ? [$type_photos['standard_room']]    : []),
    'standard_king'    => $db_modal_photos['standard_king']    ?? ($type_photos['standard_king']    ? [$type_photos['standard_king']]    : []),
];

$type_beds = [
    'beachview_duplex' => '1 queen bed',
    'seaview_duplex'   => '1 king bed',
    'beach_villa'      => '2 queen beds',
    'standard_room'    => '1 queen bed or 2 single beds',
    'standard_king'    => '4 single beds',
];

$extra_person_rates = [
    'beachview_duplex' => 1000,
    'seaview_duplex'   => 1000,
    'beach_villa'      => 1000,
    'standard_room'    => 700,
    'standard_king'    => 700,
];

$breakfast_included_types = [
    'beachview_duplex' => true,
    'seaview_duplex'   => true,
    'beach_villa'      => true,
];

$type_amenities = [
    'beachview_duplex' => [
        'Air conditioning',
        'Toiletries',
        'Mini Refrigerator',
        'Towels provided',
        'Shower',
        'Wardrobe',
        'Terrace/patio',
    ],
    'seaview_duplex' => [
        'Air conditioning',
        'Hair dryer',
        'Sofa/lounge chairs',
        'Wardrobe',
        'Ceiling fan',
        'Internet WiFi access in room',
        'Terrace/patio',
        'Work/writing desk',
        'Coffee/tea making facilities',
        'Mini Refrigerator',
        'Toiletries',
        'Daily Mineral Water',
        'Shower',
        'Towels provided',
    ],
    'beach_villa' => [
        'Air conditioning',
        'Bathroom with shower',
        'Internet WiFi access in room',
        'Toiletries',
    ],
    'standard_king' => [
        'Air conditioning',
        'Bathroom with shower',
        'Internet WiFi access in room',
        'Toiletries',
    ],
    'standard_room' => [
        'Air conditioning',
        'Internet WiFi access in room',
        'Shower',
        'Bathroom with shower',
        'Internet cable access in room',
        'Toiletries',
        'Daily Mineral Water',
        'Linen provided',
        'Towels provided',
        'Hot Shower',
        'Non-smoking rooms available',
    ],
];

$nights = max(1, (strtotime($filter_checkout) - strtotime($filter_checkin)) / 86400);

/* Keep the Standard Room inventory at six room units. */
$limited_rooms = [];
$standard_room_count = 0;
foreach ($rooms as $room) {
    if ($room['type'] === 'standard_room') {
        if ($standard_room_count >= 6) {
            continue;
        }
        $standard_room_count++;
    }
    $limited_rooms[] = $room;
}
$rooms = $limited_rooms;

/* Group rooms by type â€” available vs unavailable */
$rooms_by_type = [];
foreach ($rooms as $room) {
    $rooms_by_type[$room['type']][] = $room;
}

$avail_by_type   = [];
$unavail_by_type = [];

foreach ($rooms_by_type as $type => $type_rooms) {
    $avail_units = array_values(array_filter($type_rooms, function($r) {
        return (int)$r['active_bookings'] === 0 && $r['status'] === 'ready';
    }));

    if (!empty($avail_units)) {
        $avail_by_type[$type] = $avail_units;
    } else {
        $unavail_by_type[$type] = $type_rooms;
    }
}

// Booking popularity by room type (all-time, excluding cancelled bookings)
$type_booking_counts = [];
$popularity_sql = "
    SELECT resolved_type AS type_key, COUNT(*) AS booking_count
    FROM (
        SELECT COALESCE(
            rt.name,
            r.type,
            CASE
                WHEN LOWER(COALESCE(b.accommodation_name, '')) LIKE '%beachview duplex%' THEN 'beachview_duplex'
                WHEN LOWER(COALESCE(b.accommodation_name, '')) LIKE '%seaview duplex%' THEN 'seaview_duplex'
                WHEN LOWER(COALESCE(b.accommodation_name, '')) LIKE '%beach villa%' THEN 'beach_villa'
                WHEN LOWER(COALESCE(b.accommodation_name, '')) LIKE '%standard family%' THEN 'standard_king'
                WHEN LOWER(COALESCE(b.accommodation_name, '')) LIKE '%standard room%' THEN 'standard_room'
                ELSE NULL
            END
        ) AS resolved_type
        FROM bookings b
        LEFT JOIN room_types rt ON b.room_type_id = rt.id
        LEFT JOIN rooms r ON b.room_id = r.id
        WHERE LOWER(COALESCE(b.status, '')) NOT IN ('cancelled', 'canceled')
    ) pop
    WHERE resolved_type IS NOT NULL AND resolved_type <> ''
    GROUP BY resolved_type
";
$popularity_res = $conn->query($popularity_sql);
if ($popularity_res) {
    while ($row = $popularity_res->fetch_assoc()) {
        $type_booking_counts[$row['type_key']] = (int)$row['booking_count'];
    }
}

$available_type_keys = array_keys($avail_by_type);
usort($available_type_keys, function($a, $b) use ($type_booking_counts) {
    $countA = $type_booking_counts[$a] ?? 0;
    $countB = $type_booking_counts[$b] ?? 0;
    if ($countA === $countB) {
        return strcmp($a, $b);
    }
    return $countB <=> $countA;
});

$recommended_type_keys = array_slice($available_type_keys, 0, 2);
$other_type_keys = array_slice($available_type_keys, 2);

$recommended_by_type = [];
foreach ($recommended_type_keys as $type_key) {
    $recommended_by_type[$type_key] = $avail_by_type[$type_key];
}

$other_available_by_type = [];
foreach ($other_type_keys as $type_key) {
    $other_available_by_type[$type_key] = $avail_by_type[$type_key];
}

$checkin_fmt  = date('D, d M Y', strtotime($filter_checkin));
$checkout_fmt = date('D, d M Y', strtotime($filter_checkout));
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
    <title>Rooms & Accommodations â€“ Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="assets/css/rooms.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/rooms.css'); ?>">
</head>
<body class="rooms-page">

    <!-- â”€â”€ Header â”€â”€ -->
    <!-- ── Header ── -->
    <?php $active_nav = 'rooms'; require __DIR__ . '/partials/_navbar.php'; ?>

    <section class="rooms-hero">
        <div class="rooms-hero-inner">
            <div class="rooms-hero-copy">
                <p class="section-kicker">Coastal collection</p>
                <h1>Luxury stays curated for every shoreline mood</h1>
                <p>Compare sea-facing suites, family rooms, and tropical villas with live availability and transparent pricing.</p>
                <div class="rooms-hero-meta" aria-label="Highlights">
                    <span>Live availability</span>
                    <span>Flexible dates</span>
                    <span>Transparent pricing</span>
                </div>
            </div>
        </div>
    </section>

    <!-- â”€â”€ Booking Search Bar â”€â”€ -->
    <div class="be-bar-wrapper">
        <form method="GET" action="rooms" class="be-bar-form">
            <div class="be-bar-inner">
                <!-- Date range -->
                <div class="be-field be-field--dates">
                    <div class="be-date-segment">
                        <span class="be-date-label">Check-in</span>
                        <input type="date" id="be-checkin" name="checkin" value="<?php echo htmlspecialchars($filter_checkin); ?>" min="<?php echo date('Y-m-d'); ?>" class="be-date-input">
                    </div>
                    <div class="be-date-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </div>
                    <div class="be-date-segment">
                        <span class="be-date-label">Check-out</span>
                        <input type="date" id="be-checkout" name="checkout" value="<?php echo htmlspecialchars($filter_checkout); ?>" min="<?php echo date('Y-m-d', strtotime($filter_checkin . ' +1 day')); ?>" class="be-date-input">
                    </div>
                    <script>
                    (function() {
                        var checkin  = document.getElementById('be-checkin');
                        var checkout = document.getElementById('be-checkout');
                        if (!checkin || !checkout) return;
                        checkin.addEventListener('change', function() {
                            var next = new Date(checkin.value);
                            next.setDate(next.getDate() + 1);
                            var minStr = next.toISOString().split('T')[0];
                            checkout.min = minStr;
                            if (checkout.value < minStr) {
                                checkout.value = minStr;
                            }
                        });
                    })();
                    </script>
                </div>
                <div class="be-divider"></div>
                <!-- Guests -->
                <div class="be-field be-field--guests">
                    <span class="be-field-label">Guests</span>
                    <select name="guests" class="be-select">
                        <option value="1" <?php if($filter_guests==1) echo 'selected'; ?>>1 adult &middot; 1 room</option>
                        <option value="2" <?php if($filter_guests==2) echo 'selected'; ?>>2 adults &middot; 1 room</option>
                        <option value="3" <?php if($filter_guests==3) echo 'selected'; ?>>3 adults &middot; 1 room</option>
                        <option value="4" <?php if($filter_guests==4) echo 'selected'; ?>>4 adults &middot; 1 room</option>
                        <option value="5" <?php if($filter_guests>=5) echo 'selected'; ?>>5+ adults &middot; 1 room</option>
                    </select>
                </div>
                <div class="be-divider"></div>
                <!-- Room type (as promo-code-style field) -->
                <div class="be-field be-field--type">
                    <span class="be-field-label">Room Type</span>
                    <select name="room_type" class="be-select be-select--type">
                        <option value="any"              <?php if($filter_type=='any')             echo 'selected'; ?>>All Types</option>
                        <option value="beachview_duplex" <?php if($filter_type=='beachview_duplex') echo 'selected'; ?>>Beachview Duplex</option>
                        <option value="seaview_duplex"   <?php if($filter_type=='seaview_duplex')   echo 'selected'; ?>>Seaview Duplex</option>
                        <option value="beach_villa"      <?php if($filter_type=='beach_villa')      echo 'selected'; ?>>Beach Villa</option>
                        <option value="standard_room"    <?php if($filter_type=='standard_room')    echo 'selected'; ?>>Standard Room</option>
                        <option value="standard_king"    <?php if($filter_type=='standard_king')    echo 'selected'; ?>>Standard Family Room</option>
                    </select>
                </div>
                <!-- Search button -->
                <button type="submit" class="be-search-btn" aria-label="Search">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
            </div>
        </form>
        <div class="be-currency">
            <span>Display currency</span>
            <strong>PHP <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></strong>
        </div>
    </div>

    <!-- â”€â”€ Interactive Guest Availability Calendar â”€â”€ -->
    <div class="rooms-cal-wrapper" style="max-width: 1200px; margin: 0 auto 30px; padding: 0 20px;">
        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: #0F172A; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>&#128197;</span> Live Availability & Rates Calendar
                    </h3>
                    <p style="font-size: 13px; color: #64748B; margin: 4px 0 0;">Green dates indicate open availability; red dates are sold out. Click any date to set your check-in!</p>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="button" id="cal-prev-month" class="btn-secondary" style="padding: 6px 14px; font-weight: 700; border-radius: 8px; border: 1px solid #E2E8F0; background: #F8FAFC; cursor: pointer;">&larr; Prev</button>
                    <span id="cal-month-title" style="font-weight: 800; font-size: 15px; color: #0F172A; min-width: 140px; text-align: center;">...</span>
                    <button type="button" id="cal-next-month" class="btn-secondary" style="padding: 6px 14px; font-weight: 700; border-radius: 8px; border: 1px solid #E2E8F0; background: #F8FAFC; cursor: pointer;">Next &rarr;</button>
                </div>
            </div>

            <!-- Calendar Legend -->
            <div style="display: flex; gap: 16px; align-items: center; font-size: 12px; margin-bottom: 14px; flex-wrap: wrap;">
                <span style="display: flex; align-items: center; gap: 6px; color: #15803D; font-weight: 600;">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #22C55E; display: inline-block;"></span> Available
                </span>
                <span style="display: flex; align-items: center; gap: 6px; color: #B45309; font-weight: 600;">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #F59E0B; display: inline-block;"></span> Low Availability (1-2 rooms)
                </span>
                <span style="display: flex; align-items: center; gap: 6px; color: #B91C1C; font-weight: 600;">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #EF4444; display: inline-block;"></span> Sold Out
                </span>
            </div>

            <!-- Calendar Grid -->
            <div id="guest-cal-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; text-align: center;">
                <div style="font-size: 11px; font-weight: 700; color: #64748B; padding: 6px 0;">SUN</div>
                <div style="font-size: 11px; font-weight: 700; color: #64748B; padding: 6px 0;">MON</div>
                <div style="font-size: 11px; font-weight: 700; color: #64748B; padding: 6px 0;">TUE</div>
                <div style="font-size: 11px; font-weight: 700; color: #64748B; padding: 6px 0;">WED</div>
                <div style="font-size: 11px; font-weight: 700; color: #64748B; padding: 6px 0;">THU</div>
                <div style="font-size: 11px; font-weight: 700; color: #64748B; padding: 6px 0;">FRI</div>
                <div style="font-size: 11px; font-weight: 700; color: #64748B; padding: 6px 0;">SAT</div>
            </div>
            <div id="guest-cal-days" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; margin-top: 6px;"></div>
        </div>
    </div>

    <script>
    (function() {
        var currentCalYear = <?php echo (int)date('Y', strtotime($filter_checkin)); ?>;
        var currentCalMonth = <?php echo (int)date('n', strtotime($filter_checkin)); ?>;
        var currentRoomType = <?php echo xss_json($filter_type); ?>;

        var monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];

        function loadGuestCalendar(year, month) {
            var titleEl = document.getElementById('cal-month-title');
            var container = document.getElementById('guest-cal-days');
            if (!titleEl || !container) return;

            titleEl.textContent = monthNames[month - 1] + ' ' + year;
            var apiUrl = (typeof SF_BASE_URL !== 'undefined' && SF_BASE_URL ? SF_BASE_URL : '..') + '/backend/api/availability.php?action=get_month_matrix&year=' + year + '&month=' + month + '&room_type=' + encodeURIComponent(currentRoomType);
            fetch(apiUrl)
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function(data) {
                    if (!data || !data.success) {
                        container.innerHTML = '<div style="grid-column: span 7; color: #EF4444; font-size: 13px;">Unable to load calendar data.</div>';
                        return;
                    }

                    container.innerHTML = '';
                    var firstDayIndex = new Date(year, month - 1, 1).getDay(); // 0 = Sun
                    for (var pad = 0; pad < firstDayIndex; pad++) {
                        var emptyCell = document.createElement('div');
                        emptyCell.style.cssText = 'height: 48px;';
                        container.appendChild(emptyCell);
                    }

                    Object.keys(data.days).forEach(function(dateKey) {
                        var dayItem = data.days[dateKey];
                        var cell = document.createElement('div');
                        var bg = '#ECFDF5';
                        var border = '#A7F3D0';
                        var textColor = '#065F46';
                        var badgeText = dayItem.available + ' left';

                        if (dayItem.is_past) {
                            bg = '#F8FAFC'; border = '#E2E8F0'; textColor = '#94A3B8'; badgeText = 'Past';
                        } else if (dayItem.status === 'sold_out') {
                            bg = '#FEF2F2'; border = '#FECACA'; textColor = '#991B1B'; badgeText = 'Sold Out';
                        } else if (dayItem.status === 'low_stock') {
                            bg = '#FFFBEB'; border = '#FDE68A'; textColor = '#92400E'; badgeText = dayItem.available + ' left';
                        }

                        cell.style.cssText = 'height: 48px; background:' + bg + '; border: 1px solid ' + border + '; border-radius: 8px; padding: 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 12px; cursor: pointer; transition: transform 0.15s;';
                        cell.innerHTML = '<strong style="color:' + textColor + '; font-size:13px;">' + dayItem.day + '</strong>' +
                            '<span style="font-size: 9px; color:' + textColor + '; font-weight: 700;">' + badgeText + '</span>';

                        if (!dayItem.is_past && dayItem.status !== 'sold_out') {
                            cell.onmouseover = function() { this.style.transform = 'scale(1.05)'; };
                            cell.onmouseout = function() { this.style.transform = 'scale(1)'; };
                            cell.onclick = function() {
                                var cinInput = document.getElementById('be-checkin');
                                var coutInput = document.getElementById('be-checkout');
                                if (cinInput && coutInput) {
                                    cinInput.value = dateKey;
                                    var nextDay = new Date(dateKey);
                                    nextDay.setDate(nextDay.getDate() + 1);
                                    coutInput.value = nextDay.toISOString().split('T')[0];
                                    document.querySelector('.be-bar-form').submit();
                                }
                            };
                        }

                        container.appendChild(cell);
                    });
                })
                .catch(function() {
                    container.innerHTML = '<div style="grid-column: span 7; color: #EF4444; font-size: 13px;">Failed to connect to availability API.</div>';
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            var prevBtn = document.getElementById('cal-prev-month');
            var nextBtn = document.getElementById('cal-next-month');

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    currentCalMonth--;
                    if (currentCalMonth < 1) { currentCalMonth = 12; currentCalYear--; }
                    loadGuestCalendar(currentCalYear, currentCalMonth);
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    currentCalMonth++;
                    if (currentCalMonth > 12) { currentCalMonth = 1; currentCalYear++; }
                    loadGuestCalendar(currentCalYear, currentCalMonth);
                });
            }

            loadGuestCalendar(currentCalYear, currentCalMonth);
        });
    })();
    </script>

    <!-- â”€â”€ Tab Navigation â”€â”€ -->
    <!-- â”€â”€ Main Content + Sidebar â”€â”€ -->
    <div class="rooms-layout">

        <!-- â•â• Rooms Main Column â•â• -->
        <main class="rooms-main">
    <div class="room-tabs-bar">
        <div class="room-tabs-inner" role="group" aria-label="Browse room categories">
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
                    <div class="empty-icon">ðŸ–ï¸</div>
                    <h3>No rooms available for your selection</h3>
                    <p>Try adjusting your dates, guest count, or room type to find available accommodations.</p>
                    <a href="rooms" class="btn-reset">View All Rooms</a>
                </div>
                <?php else: ?>
                <?php foreach ($recommended_by_type as $type_key => $type_rooms):
                    $label      = $type_labels[$type_key]      ?? ucfirst(str_replace('_', ' ', $type_key));
                    $gradient   = $type_gradients[$type_key]   ?? 'linear-gradient(135deg,#374151,#6b7280)';
                    $desc       = $type_descriptions[$type_key] ?? '';
                    $long_desc  = $type_long_descriptions[$type_key] ?? $desc;
                    $bed        = $type_beds[$type_key]        ?? '1 bed';
                    $photo      = $type_photos[$type_key]      ?? 'assets/hero_beach.png';
                    $modal_photos = $type_modal_photos[$type_key] ?? [$photo];
                    $amenities  = $type_amenities[$type_key] ?? [];
                    $has_breakfast = !empty($breakfast_included_types[$type_key]);
                    $extra_rate = $extra_person_rates[$type_key] ?? 0;
                    $avail_count = count($type_rooms);
                    $sample_room = $type_rooms[0];
                    $price      = $sample_room['price_per_night'];
                    $capacity   = $sample_room['capacity'];
                    $type_id    = 'room-' . str_replace('_', '-', $type_key);
                    $book_url   = 'book?' . http_build_query([
                        'checkin'   => $filter_checkin,
                        'checkout'  => $filter_checkout,
                        'guests'    => max(1, $filter_guests),
                        'room_type' => $type_key,
                    ]);
                ?>
                <div class="room-type-section" id="<?php echo $type_id; ?>">
                    <!-- Type Header -->
                    <div class="rts-header">
                        <h3 class="rts-title"><?php echo htmlspecialchars($label); ?></h3>
                        <div class="rts-alerts">
                            <?php if ($avail_count === 1): ?>
                            <span class="alert-urgency">
                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                                Only 1 left
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rate Card -->
                    <div class="rate-card">
                        <!-- LEFT: photo + info -->
                        <div class="rate-card-left">
                            <div class="rate-photo rate-photo-clickable" role="button" tabindex="0" aria-label="View photos for <?php echo htmlspecialchars($label, ENT_QUOTES); ?>" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openRoomModal(this); }"
                                 style="background-color: #e7dfd4; background-image: url('<?php echo htmlspecialchars($photo); ?>'); cursor: pointer;"
                                 data-room-name="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
                                 data-room-bed="<?php echo htmlspecialchars($bed, ENT_QUOTES); ?>"
                                 data-room-desc="<?php echo htmlspecialchars($long_desc, ENT_QUOTES); ?>"
                                 data-room-photos="<?php echo htmlspecialchars(json_encode($modal_photos), ENT_QUOTES); ?>"
                                 data-room-amenities="<?php echo htmlspecialchars(json_encode($amenities), ENT_QUOTES); ?>"
                                 onclick="openRoomModal(this)"
                                 title="Click to view photos & amenities">
                                <div class="rate-photo-overlay">
                                    <span class="rate-photo-badge">Explore this room</span>
                                    <div class="rate-photo-copy">
                                        <span class="rate-photo-kicker"><?php echo htmlspecialchars($label); ?></span>
                                        <strong class="rate-photo-title"><?php echo $capacity; ?> guests &middot; <?php echo htmlspecialchars($bed); ?></strong>
                                    </div>
                                </div>
                                <span class="rate-photo-count">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    <?php echo count($modal_photos); ?>
                                </span>
                            </div>
                            <div class="rate-info">
                                <span class="rate-details-label">Your room at a glance</span>
                                <dl class="rate-comparison">
                                    <div><dt>Capacity</dt><dd>Up to <?php echo (int)$capacity; ?> guests</dd></div>
                                    <div><dt>Sleeping arrangements</dt><dd><?php echo htmlspecialchars($bed); ?></dd></div>
                                    <div><dt>Breakfast</dt><dd><?php echo !empty($breakfast_included_types[$type_key]) ? 'Included' : 'Not included'; ?></dd></div>
                                    <div><dt>Extra person / night</dt><dd>&#8369;<?php echo number_format($extra_person_rates[$type_key] ?? 0); ?></dd></div>
                                </dl>
                                <div class="rate-bed">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><line x1="12" y1="10" x2="12" y2="20"/></svg>
                                    <?php echo htmlspecialchars($bed); ?>
                                </div>
                                <p class="rate-desc"><?php echo htmlspecialchars($desc); ?></p>
                                <?php if (!empty($amenities)): ?>
                                <div class="rate-amenity-chips">
                                    <?php foreach (array_slice($amenities, 0, 4) as $chip): ?>
                                    <span class="rate-amenity-chip"><?php echo htmlspecialchars($chip); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <button
                                    class="btn-view-room-info"
                                    type="button"
                                    data-room-name="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
                                    data-room-bed="<?php echo htmlspecialchars($bed, ENT_QUOTES); ?>"
                                    data-room-desc="<?php echo htmlspecialchars($long_desc, ENT_QUOTES); ?>"
                                    data-room-photos="<?php echo htmlspecialchars(json_encode($modal_photos), ENT_QUOTES); ?>"
                                    data-room-amenities="<?php echo htmlspecialchars(json_encode($amenities), ENT_QUOTES); ?>"
                                    onclick="openRoomModal(this)">
                                    View Room Details &amp; Amenities
                                </button>
                            </div>
                        </div>

                        <!-- RIGHT: rate panel -->
                        <div class="rate-card-right">
                            <div class="rate-name-wrap">
                                <span class="rate-plan-tag">Best Flexible Rate</span>
                                <h4 class="rate-name"><?php echo htmlspecialchars($label); ?></h4>
                            </div>
                            <div class="rate-features">
                                <div class="rate-feature">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Flexible cancellation
                                </div>
                                <div class="rate-feature">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2h18l-2 7H5L3 2z"/><path d="M5 9l-1 11h16l-1-11"/><line x1="12" y1="13" x2="12" y2="18"/><line x1="9.5" y1="13" x2="9.5" y2="18"/><line x1="14.5" y1="13" x2="14.5" y2="18"/></svg>
                                    <?php echo $has_breakfast ? 'Breakfast included' : 'No breakfast included'; ?>
                                </div>
                            </div>
                            <div class="rate-pricing">
                                <div class="rate-from-label">From</div>
                                <div class="rate-price-main">&#8369;<?php echo number_format($price, 2); ?></div>
                                <div class="rate-price-sub">per night &middot; <?php echo max(1,$filter_guests); ?> adult<?php echo $filter_guests > 1 ? 's' : ''; ?></div>
                                <div class="rate-price-note">All taxes &amp; fees included</div>
                            </div>
                            <div class="rate-actions-box">
                                <div class="rate-selector-row">
                                    <select class="rate-room-select"
                                            data-type="<?php echo htmlspecialchars($type_key); ?>"
                                            data-label="<?php echo htmlspecialchars($label); ?>"
                                            data-price="<?php echo $price; ?>"
                                            data-extra-price="<?php echo $extra_rate; ?>"
                                            data-nights="<?php echo $nights; ?>"
                                            data-base-book-url="<?php echo htmlspecialchars($book_url); ?>"
                                            data-book-url="<?php echo htmlspecialchars($book_url); ?>"
                                            onchange="roomSelectChanged(this)">
                                        <option value="">Choose a room</option>
                                        <?php foreach ($rooms_by_type[$type_key] as $room_unit):
                                            $unit_avail = (int)$room_unit['active_bookings'] === 0 && $room_unit['status'] === 'ready';
                                            $unit_label = 'Room ' . $room_unit['room_number'];
                                            if ($room_unit['status'] === 'maintenance') {
                                                $unit_label .= ' - Under Maintenance';
                                            } elseif ($room_unit['status'] === 'occupied') {
                                                $unit_label .= ' - Occupied';
                                            } elseif (!$unit_avail) {
                                                $unit_label .= ' - Booked';
                                            }
                                        ?>
                                        <option value="<?php echo $room_unit['id']; ?>"
                                                data-room-number="<?php echo htmlspecialchars($room_unit['room_number']); ?>"
                                                <?php echo $unit_avail ? '' : 'disabled'; ?>>
                                            <?php echo htmlspecialchars($unit_label); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <svg class="select-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                                <div class="rate-extra-status" id="extra-status-<?php echo htmlspecialchars($type_key); ?>" style="display:none;"></div>
                                <button class="rate-extra-persons" type="button" data-type="<?php echo htmlspecialchars($type_key); ?>" onclick="toggleExtraPersons(this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
                                    <span class="rate-extra-persons-label">add extra persons (0)</span>
                                    <svg class="extra-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
                                </button>
                                <div class="rate-extra-panel" id="extra-panel-<?php echo htmlspecialchars($type_key); ?>">
                                    <p class="rate-extra-note">** Please note that infants 0 - 3 years old stay free when using existing bedding</p>
                                    <div class="rate-extra-card">
                                        <div class="rate-extra-rows" id="extra-rows-<?php echo htmlspecialchars($type_key); ?>"></div>
                                        <div class="rate-extra-breakdown" id="extra-breakdown-<?php echo htmlspecialchars($type_key); ?>"></div>
                                        <div class="rate-extra-total">
                                            <span>Total</span>
                                            <span id="extra-total-<?php echo htmlspecialchars($type_key); ?>">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- OTHER AVAILABLE ROOMS TAB -->
            <div id="tab-other" class="tab-panel">
                <div class="rooms-section-intro">
                    <h2>Other Available Rooms</h2>
                    <p>There are more rooms available for your stay</p>
                </div>

                <?php if (empty($other_available_by_type)): ?>
                <div class="rooms-empty">
                    <div class="empty-icon">âœ¨</div>
                    <h3>No additional rooms right now</h3>
                    <p>Your top room recommendations are already shown above.</p>
                </div>
                <?php else: ?>
                <?php foreach ($other_available_by_type as $type_key => $type_rooms):
                    $label      = $type_labels[$type_key]      ?? ucfirst(str_replace('_', ' ', $type_key));
                    $gradient   = $type_gradients[$type_key]   ?? 'linear-gradient(135deg,#374151,#6b7280)';
                    $desc       = $type_descriptions[$type_key] ?? '';
                    $long_desc  = $type_long_descriptions[$type_key] ?? $desc;
                    $bed        = $type_beds[$type_key]        ?? '1 bed';
                    $photo      = $type_photos[$type_key]      ?? 'assets/hero_beach.png';
                    $modal_photos = $type_modal_photos[$type_key] ?? [$photo];
                    $amenities  = $type_amenities[$type_key] ?? [];
                    $has_breakfast = !empty($breakfast_included_types[$type_key]);
                    $extra_rate = $extra_person_rates[$type_key] ?? 0;
                    $avail_count = count($type_rooms);
                    $sample_room = $type_rooms[0];
                    $price      = $sample_room['price_per_night'];
                    $capacity   = $sample_room['capacity'];
                    $type_id    = 'room-' . str_replace('_', '-', $type_key);
                    $book_url   = 'book?' . http_build_query([
                        'checkin'   => $filter_checkin,
                        'checkout'  => $filter_checkout,
                        'guests'    => max(1, $filter_guests),
                        'room_type' => $type_key,
                    ]);
                ?>
                <div class="room-type-section" id="<?php echo $type_id; ?>">
                    <div class="rts-header">
                        <h3 class="rts-title"><?php echo htmlspecialchars($label); ?></h3>
                        <div class="rts-alerts">
                            <?php if ($avail_count === 1): ?>
                            <span class="alert-urgency">
                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                                Only 1 left
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="rate-card">
                        <div class="rate-card-left">
                            <div class="rate-photo rate-photo-clickable" role="button" tabindex="0" aria-label="View photos for <?php echo htmlspecialchars($label, ENT_QUOTES); ?>" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openRoomModal(this); }"
                                 style="background-color: #e7dfd4; background-image: url('<?php echo htmlspecialchars($photo); ?>'); cursor: pointer;"
                                 data-room-name="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
                                 data-room-bed="<?php echo htmlspecialchars($bed, ENT_QUOTES); ?>"
                                 data-room-desc="<?php echo htmlspecialchars($long_desc, ENT_QUOTES); ?>"
                                 data-room-photos="<?php echo htmlspecialchars(json_encode($modal_photos), ENT_QUOTES); ?>"
                                 data-room-amenities="<?php echo htmlspecialchars(json_encode($amenities), ENT_QUOTES); ?>"
                                 onclick="openRoomModal(this)"
                                 title="Click to view photos & amenities">
                                <div class="rate-photo-overlay">
                                    <span class="rate-photo-badge">Explore this room</span>
                                    <div class="rate-photo-copy">
                                        <span class="rate-photo-kicker"><?php echo htmlspecialchars($label); ?></span>
                                        <strong class="rate-photo-title"><?php echo $capacity; ?> guests &middot; <?php echo htmlspecialchars($bed); ?></strong>
                                    </div>
                                </div>
                                <span class="rate-photo-count">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    <?php echo count($modal_photos); ?>
                                </span>
                            </div>
                            <div class="rate-info">
                                <span class="rate-details-label">Your room at a glance</span>
                                <dl class="rate-comparison">
                                    <div><dt>Capacity</dt><dd>Up to <?php echo (int)$capacity; ?> guests</dd></div>
                                    <div><dt>Sleeping arrangements</dt><dd><?php echo htmlspecialchars($bed); ?></dd></div>
                                    <div><dt>Breakfast</dt><dd><?php echo !empty($breakfast_included_types[$type_key]) ? 'Included' : 'Not included'; ?></dd></div>
                                    <div><dt>Extra person / night</dt><dd>&#8369;<?php echo number_format($extra_person_rates[$type_key] ?? 0); ?></dd></div>
                                </dl>
                                <div class="rate-bed">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><line x1="12" y1="10" x2="12" y2="20"/></svg>
                                    <?php echo htmlspecialchars($bed); ?>
                                </div>
                                <p class="rate-desc"><?php echo htmlspecialchars($desc); ?></p>
                                <?php if (!empty($amenities)): ?>
                                <div class="rate-amenity-chips">
                                    <?php foreach (array_slice($amenities, 0, 4) as $chip): ?>
                                    <span class="rate-amenity-chip"><?php echo htmlspecialchars($chip); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <button
                                    class="btn-view-room-info"
                                    type="button"
                                    data-room-name="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
                                    data-room-bed="<?php echo htmlspecialchars($bed, ENT_QUOTES); ?>"
                                    data-room-desc="<?php echo htmlspecialchars($long_desc, ENT_QUOTES); ?>"
                                    data-room-photos="<?php echo htmlspecialchars(json_encode($modal_photos), ENT_QUOTES); ?>"
                                    data-room-amenities="<?php echo htmlspecialchars(json_encode($amenities), ENT_QUOTES); ?>"
                                    onclick="openRoomModal(this)">
                                    View Room Details &amp; Amenities
                                </button>
                            </div>
                        </div>

                        <div class="rate-card-right">
                            <div class="rate-name-wrap">
                                <span class="rate-plan-tag">Best Flexible Rate</span>
                                <h4 class="rate-name"><?php echo htmlspecialchars($label); ?></h4>
                            </div>
                            <div class="rate-features">
                                <div class="rate-feature">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Flexible cancellation
                                </div>
                                <div class="rate-feature">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2h18l-2 7H5L3 2z"/><path d="M5 9l-1 11h16l-1-11"/><line x1="12" y1="13" x2="12" y2="18"/><line x1="9.5" y1="13" x2="9.5" y2="18"/><line x1="14.5" y1="13" x2="14.5" y2="18"/></svg>
                                    <?php echo $has_breakfast ? 'Breakfast included' : 'No breakfast included'; ?>
                                </div>
                            </div>
                            <div class="rate-pricing">
                                <div class="rate-from-label">From</div>
                                <div class="rate-price-main">&#8369;<?php echo number_format($price, 2); ?></div>
                                <div class="rate-price-sub">per night &middot; <?php echo max(1,$filter_guests); ?> adult<?php echo $filter_guests > 1 ? 's' : ''; ?></div>
                                <div class="rate-price-note">All taxes &amp; fees included</div>
                            </div>
                            <div class="rate-actions-box">
                                <div class="rate-selector-row">
                                    <select class="rate-room-select"
                                            data-type="<?php echo htmlspecialchars($type_key); ?>"
                                            data-label="<?php echo htmlspecialchars($label); ?>"
                                            data-price="<?php echo $price; ?>"
                                            data-extra-price="<?php echo $extra_rate; ?>"
                                            data-nights="<?php echo $nights; ?>"
                                            data-base-book-url="<?php echo htmlspecialchars($book_url); ?>"
                                            data-book-url="<?php echo htmlspecialchars($book_url); ?>"
                                            onchange="roomSelectChanged(this)">
                                        <option value="">Choose a room</option>
                                        <?php foreach ($rooms_by_type[$type_key] as $room_unit):
                                            $unit_avail = (int)$room_unit['active_bookings'] === 0 && $room_unit['status'] === 'ready';
                                            $unit_label = 'Room ' . $room_unit['room_number'];
                                            if ($room_unit['status'] === 'maintenance') {
                                                $unit_label .= ' - Under Maintenance';
                                            } elseif ($room_unit['status'] === 'occupied') {
                                                $unit_label .= ' - Occupied';
                                            } elseif (!$unit_avail) {
                                                $unit_label .= ' - Booked';
                                            }
                                        ?>
                                        <option value="<?php echo $room_unit['id']; ?>"
                                                data-room-number="<?php echo htmlspecialchars($room_unit['room_number']); ?>"
                                                <?php echo $unit_avail ? '' : 'disabled'; ?>>
                                            <?php echo htmlspecialchars($unit_label); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <svg class="select-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                                <div class="rate-extra-status" id="extra-status-<?php echo htmlspecialchars($type_key); ?>" style="display:none;"></div>
                                <button class="rate-extra-persons" type="button" data-type="<?php echo htmlspecialchars($type_key); ?>" onclick="toggleExtraPersons(this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
                                    <span class="rate-extra-persons-label">add extra persons (0)</span>
                                    <svg class="extra-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
                                </button>
                                <div class="rate-extra-panel" id="extra-panel-<?php echo htmlspecialchars($type_key); ?>">
                                    <p class="rate-extra-note">** Please note that infants 0 - 3 years old stay free when using existing bedding</p>
                                    <div class="rate-extra-card">
                                        <div class="rate-extra-rows" id="extra-rows-<?php echo htmlspecialchars($type_key); ?>"></div>
                                        <div class="rate-extra-breakdown" id="extra-breakdown-<?php echo htmlspecialchars($type_key); ?>"></div>
                                        <div class="rate-extra-total">
                                            <span>Total</span>
                                            <span id="extra-total-<?php echo htmlspecialchars($type_key); ?>">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($unavail_by_type)): ?>
                <div class="rooms-section-intro" style="margin-top: 28px;">
                    <h2>Unavailable Rooms</h2>
                    <p>These rooms are currently unavailable for your selected dates.</p>
                </div>

                <?php foreach ($unavail_by_type as $type_key => $type_rooms):
                    $label     = $type_labels[$type_key]      ?? ucfirst(str_replace('_', ' ', $type_key));
                    $gradient  = $type_gradients[$type_key]   ?? 'linear-gradient(135deg,#374151,#6b7280)';
                    $desc      = $type_descriptions[$type_key] ?? '';
                    $bed       = $type_beds[$type_key]        ?? '1 bed';
                    $photo     = $type_photos[$type_key]      ?? 'assets/hero_beach.png';
                    $sample_room = $type_rooms[0];
                    $price     = $sample_room['price_per_night'];
                    $capacity  = $sample_room['capacity'];
                    $is_all_maintenance = true;
                    foreach ($type_rooms as $tr) {
                        if ($tr['status'] !== 'maintenance') {
                            $is_all_maintenance = false;
                            break;
                        }
                    }
                    $unavail_reason = $is_all_maintenance ? 'Under Maintenance' : 'Fully Booked';
                ?>
                <div class="room-type-section room-type-section--unavailable">
                    <div class="rts-header">
                        <h3 class="rts-title"><?php echo htmlspecialchars($label); ?></h3>
                        <span class="unavail-badge"><?php echo $unavail_reason; ?></span>
                    </div>
                    <div class="rate-card rate-card--unavailable">
                        <div class="rate-card-left">
                            <div class="rate-photo" style="background-color: #e7dfd4; background-image: url('<?php echo htmlspecialchars($photo); ?>'); opacity: 0.72;">
                                <div class="rate-photo-overlay">
                                    <span class="rate-photo-badge"><?php echo $unavail_reason; ?></span>
                                    <div class="rate-photo-copy">
                                        <span class="rate-photo-kicker"><?php echo htmlspecialchars($label); ?></span>
                                        <strong class="rate-photo-title"><?php echo $capacity; ?> guests &middot; <?php echo htmlspecialchars($bed); ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="rate-info">
                                <span class="rate-details-label">Your room at a glance</span>
                                <dl class="rate-comparison">
                                    <div><dt>Capacity</dt><dd>Up to <?php echo (int)$capacity; ?> guests</dd></div>
                                    <div><dt>Sleeping arrangements</dt><dd><?php echo htmlspecialchars($bed); ?></dd></div>
                                    <div><dt>Breakfast</dt><dd><?php echo !empty($breakfast_included_types[$type_key]) ? 'Included' : 'Not included'; ?></dd></div>
                                    <div><dt>Extra person / night</dt><dd>&#8369;<?php echo number_format($extra_person_rates[$type_key] ?? 0); ?></dd></div>
                                </dl>
                                <div class="rate-bed">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><line x1="12" y1="10" x2="12" y2="20"/></svg>
                                    <?php echo htmlspecialchars($bed); ?>
                                </div>
                                <p class="rate-desc"><?php echo htmlspecialchars($desc); ?></p>
                            </div>
                        </div>
                        <div class="rate-card-right rate-card-right--unavailable">
                            <h4 class="rate-name"><?php echo htmlspecialchars($label); ?></h4>
                            <div class="rate-pricing">
                                <div class="rate-price-main">&#8369;<?php echo number_format($price, 2); ?></div>
                                <div class="rate-price-sub">per night &middot; <?php echo max(1,$filter_guests); ?> adult<?php echo $filter_guests > 1 ? 's' : ''; ?></div>
                            </div>

                            <?php if (!$is_all_maintenance): ?>
                            <div class="rate-date-strip-wrap" id="date-strip-wrap-<?php echo htmlspecialchars($type_key); ?>">
                                <button type="button" class="btn-strip-nav btn-strip-prev" onclick="shiftDateStrip(<?php echo xss_attr_json($type_key); ?>, -3)" aria-label="Previous dates">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                                </button>
                                <div class="rate-date-strip" id="date-strip-<?php echo htmlspecialchars($type_key); ?>" data-type="<?php echo htmlspecialchars($type_key); ?>" data-price="<?php echo $price; ?>">
                                    <!-- Populated via JS -->
                                </div>
                                <button type="button" class="btn-strip-nav btn-strip-next" onclick="shiftDateStrip(<?php echo xss_attr_json($type_key); ?>, 3)" aria-label="Next dates">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                </button>
                            </div>

                            <div class="rate-soldout-actions">
                                <button type="button" class="btn-sold-out" disabled><?php echo $unavail_reason; ?></button>
                                <button type="button" class="btn-check-calendar" onclick="openAvailabilityCalendar(<?php echo xss_attr_json($type_key); ?>, <?php echo xss_attr_json($label); ?>, <?php echo $price; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    Check availability calendar
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="rate-soldout-actions">
                                <button type="button" class="btn-sold-out" disabled><?php echo $unavail_reason; ?></button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>

        <!-- â•â• Booking Summary Sidebar â•â• -->
        <aside class="booking-sidebar" id="booking-sidebar">
            <h3 class="bs-title">Booking Summary</h3>

            <div class="bs-dates">
                <div class="bs-date-col">
                    <div class="bs-date-main"><?php echo $checkin_fmt; ?></div>
                    <div class="bs-date-time">from 14:00</div>
                </div>
                <div class="bs-date-arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </div>
                <div class="bs-date-col bs-date-col--right">
                    <div class="bs-date-main"><?php echo $checkout_fmt; ?></div>
                    <div class="bs-date-time">until 12:00</div>
                </div>
            </div>
            <div class="bs-nights"><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></div>

            <div class="bs-room-info">
                <div class="bs-room-info-header">
                    <span>Room information:</span>
                    <a href="#" class="bs-clear-link" id="bs-clear-link" onclick="clearSelection(event)">Clear selection</a>
                </div>
                <div class="bs-room-placeholder" id="bs-placeholder">Choose a room and add to your booking</div>
                <div class="bs-selected-rooms" id="bs-selected-rooms" style="display:none;"></div>
            </div>

            <div class="bs-total-row">
                <span class="bs-total-label">Total</span>
                <span class="bs-total-amount" id="bs-total">&#8369; 0.00</span>
            </div>
            <div class="bs-tax-note">Price includes all taxes and fees</div>

            <button class="btn-book-now" id="btn-book-now" disabled onclick="proceedToBook()">BOOK NOW</button>
        </aside>

    </div><!-- /.rooms-layout -->

    <!-- â”€â”€ Mobile Sticky Booking Bar â”€â”€ -->
    <div class="mobile-book-bar" id="mobile-book-bar">
        <div class="mobile-book-bar-info">
            <div class="mobile-book-bar-count" id="mobile-bar-count">1 room selected</div>
            <div class="mobile-book-bar-total" id="mobile-bar-total">&#8369; 0.00</div>
        </div>
        <button class="mobile-book-bar-btn" onclick="proceedToBook()">Book Now &rarr;</button>
    </div>

    <div class="room-modal" id="room-modal" aria-hidden="true">
        <div class="room-modal-backdrop" onclick="closeRoomModal()"></div>
        <div class="room-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="room-modal-title">
            <div class="room-modal-header">
                <h3>Room details</h3>
                <button type="button" class="room-modal-close" aria-label="Close room details" onclick="closeRoomModal()">&times;</button>
            </div>
            <div class="room-modal-body">
                <div class="room-modal-gallery">
                    <div class="room-modal-main-wrap">
                        <img src="" alt="" class="room-modal-main-image" id="room-modal-main-image">
                        <span class="room-modal-counter" id="room-modal-counter">1/1</span>
                    </div>
                    <div class="room-modal-thumbs" id="room-modal-thumbs"></div>
                </div>
                <div class="room-modal-content">
                    <h4 class="room-modal-title" id="room-modal-title"></h4>
                    <div class="room-modal-bed" id="room-modal-bed"></div>
                    <p class="room-modal-description" id="room-modal-description"></p>
                    <div class="room-modal-amenities-wrap" id="room-modal-amenities-wrap" style="display:none;">
                        <h5>Amenities</h5>
                        <div class="room-modal-amenities" id="room-modal-amenities"></div>
                    </div>
                </div>
            </div>
            <div class="room-modal-footer">
                <button type="button" class="room-modal-close-btn" onclick="closeRoomModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- â•â• Availability Calendar Modal â•â• -->
    <div class="avail-modal" id="avail-modal" aria-hidden="true" role="dialog">
        <div class="avail-modal-backdrop" onclick="closeAvailabilityCalendar()"></div>
        <div class="avail-modal-dialog">
            <button type="button" class="avail-modal-close" onclick="closeAvailabilityCalendar()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            
            <div class="avail-modal-header">
                <div class="avail-modal-header-info">
                    <span class="avail-modal-kicker">Availability Calendar</span>
                    <h3 class="avail-modal-title" id="avail-modal-title">Standard Room</h3>
                    <div class="avail-modal-price" id="avail-modal-price">From &#8369; 2,900.00 / night</div>
                </div>
                <div class="avail-modal-month-nav">
                    <button type="button" class="btn-cal-month" onclick="changeCalMonth(-1)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Prev</span>
                    </button>
                    <span class="cal-current-month" id="cal-current-month">September 2026</span>
                    <button type="button" class="btn-cal-month" onclick="changeCalMonth(1)">
                        <span>Next</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>

            <div class="avail-modal-grid-wrap">
                <div class="avail-modal-weekdays">
                    <span>SUN</span><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
                </div>
                <div class="avail-modal-days" id="avail-modal-days">
                    <!-- Populated via JavaScript -->
                </div>
            </div>

            <div class="avail-modal-footer">
                <p class="avail-modal-hint">Select any green date to switch your stay and continue booking this room type.</p>
                <div class="avail-legend">
                    <div class="avail-legend-item"><span class="legend-dot legend-dot--avail"></span> Available (Click to select)</div>
                    <div class="avail-legend-item"><span class="legend-dot legend-dot--sold"></span> Sold Out</div>
                </div>
            </div>
        </div>
    </div>

    <!-- â”€â”€ Footer â”€â”€ -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-brand-col">
                <h3>Santa Fe Beach Club</h3>
                <p>Experience the ultimate coastal sophistication. A serene blend of boutique hospitality and tropical elegance.</p>
            </div>
            <div class="footer-links-col">
                <h4>LEGAL</h4>
                <ul>
                    <li><a href="#privacy">Privacy Policy</a></li>
                    <li><a href="#terms">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-links-col">
                <h4>COMPANY</h4>
                <ul>
                    <li><a href="#careers">Careers</a></li>
                    <li><a href="#sustainability">Sustainability</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Santa Fe Beach Club. All rights reserved.</p>
        </div>
    </footer>

<script>
/* â”€â”€ Tab switching â”€â”€ */
function switchTab(tab, btn) {
    document.querySelectorAll('.room-tab').forEach(function(b) {
        b.classList.remove('room-tab--active');
        b.setAttribute('aria-pressed', 'false');
    });
    btn.setAttribute('aria-pressed', 'true');
    btn.classList.add('room-tab--active');

    var target = document.getElementById('tab-' + tab);
    if (!target) return;

    var header = document.querySelector('.main-header');
    var tabsBar = document.querySelector('.room-tabs-bar');
    var headerHeight = header ? Math.ceil(header.getBoundingClientRect().bottom) : 0;
    var tabsHeight = tabsBar ? Math.ceil(tabsBar.getBoundingClientRect().height) : 0;
    var topOffset = headerHeight + 24;
    var targetTop = window.scrollY + target.getBoundingClientRect().top - topOffset;

    window.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
}

function syncRoomTabsOffset() {
    var header = document.querySelector('.main-header');
    if (!header) return;
    var headerBottom = Math.ceil(header.getBoundingClientRect().bottom);
    var tabsBar = document.querySelector('.room-tabs-bar');
    var tabsHeight = tabsBar ? Math.ceil(tabsBar.getBoundingClientRect().height) : 0;
    document.documentElement.style.setProperty('--rooms-sticky-tabs-top', (headerBottom + 8) + 'px');
    document.documentElement.style.setProperty('--rooms-summary-top', (headerBottom + 24) + 'px');
}

/* â”€â”€ Booking summary state â”€â”€ */
var selectedRooms = {};
var extraPersonsByType = {};

function roomSelectChanged(sel) {
    // Update the book URL with the specific room ID selected
    var roomId = sel.value;
    var baseUrl = sel.dataset.baseBookUrl || sel.dataset.bookUrl;
    if (roomId) {
        sel.dataset.bookUrl = baseUrl + '&room_id=' + encodeURIComponent(roomId);
    } else {
        sel.dataset.bookUrl = baseUrl;
    }
    updateSummary();
}

function updateSummary() {
    selectedRooms = {};
    document.querySelectorAll('.rate-room-select').forEach(function(sel) {
        var qty = sel.value ? 1 : 0; // Any room selected = 1 room
        if (qty > 0) {
            var key = sel.dataset.type;
            var selectedOpt = sel.options[sel.selectedIndex];
            var roomNum = selectedOpt ? (selectedOpt.dataset.roomNumber || '') : '';
            selectedRooms[key] = {
                label:    sel.dataset.label + (roomNum ? ' &middot; Room ' + roomNum : ''),
                price:    parseFloat(sel.dataset.price),
                extraPrice: parseFloat(sel.dataset.extraPrice || '0'),
                nights:   parseInt(sel.dataset.nights),
                qty:      1,
                bookUrl:  sel.dataset.bookUrl
            };
        }
    });
    syncExtraPersonsPanels();
    renderSummary();
}

function syncExtraPersonsPanels() {
    Object.keys(extraPersonsByType).forEach(function(type) {
        var panel = document.getElementById('extra-panel-' + type);
        if (panel && panel.classList.contains('rate-extra-panel--open')) {
            renderExtraPersonsPanel(type);
        } else {
            updateExtraPersonsSummary(type);
        }
    });
}

function renderSummary() {
    var placeholder   = document.getElementById('bs-placeholder');
    var selectedDiv   = document.getElementById('bs-selected-rooms');
    var totalEl       = document.getElementById('bs-total');
    var bookBtn       = document.getElementById('btn-book-now');
    var clearLink     = document.getElementById('bs-clear-link');

    var keys = Object.keys(selectedRooms);
    var grandTotal = 0;

    if (keys.length === 0) {
        if (placeholder) placeholder.style.display  = 'block';
        if (selectedDiv) selectedDiv.style.display  = 'none';
        if (totalEl)     totalEl.innerHTML        = '&#8369; 0.00';
        if (bookBtn)     bookBtn.disabled           = true;
        if (clearLink)   clearLink.style.visibility = 'hidden';
        // Hide mobile bar
        var mobileBar = document.getElementById('mobile-book-bar');
        if (mobileBar) mobileBar.classList.remove('mobile-book-bar--active');
        return;
    }

    clearLink.style.visibility = 'visible';
    placeholder.style.display  = 'none';
    selectedDiv.style.display  = 'block';
    selectedDiv.innerHTML = '';

    keys.forEach(function(key) {
        var r = selectedRooms[key];
        var personList = extraPersonsByType[key] || [];
        var totalAdults = personList.reduce(function(sum, v) { return sum + (parseInt(v.adults, 10) || 0); }, 0);
        var totalChildren = personList.reduce(function(sum, v) { return sum + (parseInt(v.children, 10) || 0); }, 0);

        var roomSubtotal = r.price * r.nights * r.qty;
        var extraSubtotal = totalAdults * r.extraPrice;
        var subtotal = roomSubtotal + extraSubtotal;
        grandTotal += subtotal;

        var el = document.createElement('div');
        el.className = 'bs-room-line';
        el.innerHTML =
            '<span class="bs-room-name">' + Number(r.qty) + 'x ' + escapeRoomText(r.label) + '</span>' +
            '<span class="bs-room-subtotal">&#8369; ' + roomSubtotal.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</span>';
        selectedDiv.appendChild(el);

        if (extraSubtotal > 0) {
            var extraEl = document.createElement('div');
            extraEl.className = 'bs-room-line bs-room-line--extra';
            extraEl.innerHTML =
                '<span class="bs-room-name">Extra adults (' + totalAdults + ')</span>' +
                '<span class="bs-room-subtotal">&#8369; ' + extraSubtotal.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</span>';
            selectedDiv.appendChild(extraEl);
        }
        if (totalChildren > 0) {
            var childEl = document.createElement('div');
            childEl.className = 'bs-room-line bs-room-line--extra';
            childEl.innerHTML =
                '<span class="bs-room-name">Children (' + totalChildren + ')</span>' +
                '<span class="bs-room-subtotal">Free</span>';
            selectedDiv.appendChild(childEl);
        }
    });

    totalEl.innerHTML = '&#8369; ' + grandTotal.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    bookBtn.disabled = false;

    // Mobile bottom bar
    var mobileBar  = document.getElementById('mobile-book-bar');
    var mobileCount = document.getElementById('mobile-bar-count');
    var mobileTotal = document.getElementById('mobile-bar-total');
    if (mobileBar) {
        var totalRooms = keys.reduce(function(sum, k) { return sum + selectedRooms[k].qty; }, 0);
        mobileBar.classList.add('mobile-book-bar--active');
        mobileCount.textContent = totalRooms + ' room' + (totalRooms > 1 ? 's' : '') + ' selected';
        mobileTotal.innerHTML = '&#8369; ' + grandTotal.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
}

function clearSelection(e) {
    e.preventDefault();
    document.querySelectorAll('.rate-room-select').forEach(function(sel) { sel.value = ''; });
    selectedRooms = {};
    extraPersonsByType = {};
    document.querySelectorAll('.rate-extra-status').forEach(function(el) {
        el.style.display = 'none';
        el.textContent = '';
    });
    document.querySelectorAll('.rate-extra-persons-label').forEach(function(label) {
        label.textContent = 'add extra persons (0)';
    });
    document.querySelectorAll('.rate-extra-panel').forEach(function(panel) {
        panel.classList.remove('rate-extra-panel--open');
    });
    document.querySelectorAll('.rate-extra-persons').forEach(function(btn) {
        btn.classList.remove('rate-extra-persons--open');
    });
    renderSummary();
}

function proceedToBook() {
    var keys = Object.keys(selectedRooms);
    if (keys.length === 0) return;
    var targetUrl = selectedRooms[keys[0]].bookUrl;
    var personList = extraPersonsByType[keys[0]] || [];
    var totalAdults = personList.reduce(function(sum, v) { return sum + (parseInt(v.adults, 10) || 0); }, 0);
    var totalChildren = personList.reduce(function(sum, v) { return sum + (parseInt(v.children, 10) || 0); }, 0);
    if (totalAdults > 0) targetUrl += '&extra_adults=' + encodeURIComponent(totalAdults);
    if (totalChildren > 0) targetUrl += '&extra_children=' + encodeURIComponent(totalChildren);
    window.location.href = targetUrl;
}

function toggleExtraPersons(btn) {
    var type = btn.getAttribute('data-type');
    var panel = document.getElementById('extra-panel-' + type);
    var isOpen = panel.classList.contains('rate-extra-panel--open');

    document.querySelectorAll('.rate-extra-panel').forEach(function(item) {
        item.classList.remove('rate-extra-panel--open');
    });
    document.querySelectorAll('.rate-extra-persons').forEach(function(item) {
        item.classList.remove('rate-extra-persons--open');
    });

    if (!isOpen) {
        renderExtraPersonsPanel(type);
        panel.classList.add('rate-extra-panel--open');
        btn.classList.add('rate-extra-persons--open');
    }
}

function getSelectedRoomQty(type) {
    return 1; // You can now only select 1 specific room at a time
}

function renderExtraPersonsPanel(type) {
    var qty = getSelectedRoomQty(type);
    var rowsWrap = document.getElementById('extra-rows-' + type);
    var values = extraPersonsByType[type] || [];
    values = values.slice(0, qty);
    while (values.length < qty) {
        values.push({ adults: 0, children: 0 });
    }
    extraPersonsByType[type] = values;

    rowsWrap.innerHTML = '';
    values.forEach(function(item, index) {
        var row = document.createElement('div');
        row.className = 'rate-extra-row';

        var label = document.createElement('div');
        label.className = 'rate-extra-room-label';
        label.textContent = 'Room ' + (index + 1);

        // Adults dropdown
        var adultWrap = document.createElement('div');
        adultWrap.className = 'rate-extra-select-wrap';
        var adultSelect = document.createElement('select');
        adultSelect.className = 'rate-extra-select';
        [0, 1, 2, 3, 4].forEach(function(count) {
            var opt = document.createElement('option');
            opt.value = count;
            opt.textContent = count + ' adult' + (count !== 1 ? 's' : '');
            if (count === (item.adults || 0)) opt.selected = true;
            adultSelect.appendChild(opt);
        });
        adultSelect.onchange = function() {
            updateExtraPersonCount(type, index, 'adults', this.value);
        };
        var chevron1 = document.createElement('span');
        chevron1.className = 'rate-extra-select-chevron';
        chevron1.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        adultWrap.appendChild(adultSelect);
        adultWrap.appendChild(chevron1);

        // Children dropdown
        var childWrap = document.createElement('div');
        childWrap.className = 'rate-extra-select-wrap';
        var childSelect = document.createElement('select');
        childSelect.className = 'rate-extra-select';
        [0, 1, 2].forEach(function(count) {
            var opt = document.createElement('option');
            opt.value = count;
            opt.textContent = count + ' child' + (count === 1 ? '' : 'ren');
            if (count === (item.children || 0)) opt.selected = true;
            childSelect.appendChild(opt);
        });
        childSelect.onchange = function() {
            updateExtraPersonCount(type, index, 'children', this.value);
        };
        var chevron2 = document.createElement('span');
        chevron2.className = 'rate-extra-select-chevron';
        chevron2.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        childWrap.appendChild(childSelect);
        childWrap.appendChild(chevron2);

        row.appendChild(label);
        row.appendChild(adultWrap);
        row.appendChild(childWrap);
        rowsWrap.appendChild(row);
    });

    updateExtraPersonsSummary(type);
}

function updateExtraPersonCount(type, index, field, value) {
    var values = extraPersonsByType[type] || [];
    if (!values[index]) values[index] = { adults: 0, children: 0 };
    values[index][field] = parseInt(value, 10) || 0;
    extraPersonsByType[type] = values;
    updateExtraPersonsSummary(type);
}

function updateExtraPersonsSummary(type) {
    var values = extraPersonsByType[type] || [];
    var totalAdults = values.reduce(function(sum, v) { return sum + (parseInt(v.adults, 10) || 0); }, 0);
    var totalChildren = values.reduce(function(sum, v) { return sum + (parseInt(v.children, 10) || 0); }, 0);
    var totalPersons = totalAdults + totalChildren;

    var totalEl = document.getElementById('extra-total-' + type);
    var breakdownEl = document.getElementById('extra-breakdown-' + type);
    var statusEl = document.getElementById('extra-status-' + type);
    var btn = document.querySelector('.rate-extra-persons[data-type="' + type + '"]');
    var roomSelect = document.querySelector('.rate-room-select[data-type="' + type + '"]');
    var extraRate = roomSelect ? (parseFloat(roomSelect.getAttribute('data-extra-price') || '0')) : 0;
    var extraCharge = totalAdults * extraRate;

    // Update breakdown table row
    if (breakdownEl) {
        breakdownEl.innerHTML = '';
        if (totalPersons > 0) {
            values.forEach(function(v, index) {
                var adultCost = (v.adults || 0) * extraRate;
                var bRow = document.createElement('div');
                bRow.className = 'rate-extra-breakdown-row';
                bRow.innerHTML =
                    '<div class="rate-extra-breakdown-col">' + (index + 1) + '</div>' +
                    '<div class="rate-extra-breakdown-col">' + (adultCost > 0 ? ('â‚± ' + adultCost.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})) : '-') + '</div>' +
                    '<div class="rate-extra-breakdown-col">-</div>';
                breakdownEl.appendChild(bRow);
            });
        }
    }

    // Update Total
    if (totalEl) {
        totalEl.textContent = extraCharge > 0
            ? ('&#8369; ' + extraCharge.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}))
            : (totalPersons > 0 ? 'Free / Included' : '-');
    }

    // Update Button Label
    if (btn) {
        var label = btn.querySelector('.rate-extra-persons-label');
        if (label) {
            label.textContent = 'add extra persons (' + totalPersons + ')';
        }
    }

    // Update Status line above button
    if (statusEl) {
        var parts = [];
        if (totalAdults > 0) parts.push(totalAdults + ' adult' + (totalAdults > 1 ? 's' : ''));
        if (totalChildren > 0) parts.push(totalChildren + ' child' + (totalChildren === 1 ? '' : 'ren'));
        if (parts.length > 0) {
            statusEl.textContent = parts.join(' and ') + ' has been added.';
            statusEl.style.display = 'block';
        } else {
            statusEl.textContent = '';
            statusEl.style.display = 'none';
        }
    }

    renderSummary();
}

function syncExtraPersonsPanels() {
    Object.keys(extraPersonsByType).forEach(function(type) {
        var panel = document.getElementById('extra-panel-' + type);
        if (panel && panel.classList.contains('rate-extra-panel--open')) {
            renderExtraPersonsPanel(type);
        } else {
            updateExtraPersonsSummary(type);
        }
    });
}

function setRoomModalImage(src, alt, index, total, thumbEl) {
    var mainImage = document.getElementById('room-modal-main-image');
    var counter = document.getElementById('room-modal-counter');
    mainImage.src = src;
    mainImage.alt = alt;
    counter.textContent = (index + 1) + '/' + total;

    document.querySelectorAll('.room-modal-thumb').forEach(function(thumb) {
        thumb.classList.remove('room-modal-thumb--active');
    });
    if (thumbEl) {
        thumbEl.classList.add('room-modal-thumb--active');
    }
}

function openRoomModal(btn) {
    var modal = document.getElementById('room-modal');
    var name = btn.getAttribute('data-room-name') || '';
    var bed = btn.getAttribute('data-room-bed') || '';
    var desc = btn.getAttribute('data-room-desc') || '';
    var photos = [];
    var amenities = [];

    try {
        photos = JSON.parse(btn.getAttribute('data-room-photos') || '[]');
    } catch (e) {
        photos = [];
    }
    try {
        amenities = JSON.parse(btn.getAttribute('data-room-amenities') || '[]');
    } catch (e) {
        amenities = [];
    }

    document.getElementById('room-modal-title').textContent = name.toUpperCase();
    document.getElementById('room-modal-bed').textContent = bed;
    document.getElementById('room-modal-description').textContent = desc;
    var amenitiesWrap = document.getElementById('room-modal-amenities-wrap');
    var amenitiesEl = document.getElementById('room-modal-amenities');
    amenitiesEl.innerHTML = '';
    if (amenities.length) {
        amenities.forEach(function(item) {
            var amenity = document.createElement('div');
            amenity.className = 'room-modal-amenity';
            amenity.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span></span>';
            amenity.querySelector('span').textContent = item;
            amenitiesEl.appendChild(amenity);
        });
        amenitiesWrap.style.display = '';
    } else {
        amenitiesWrap.style.display = 'none';
    }

    var thumbsWrap = document.getElementById('room-modal-thumbs');
    thumbsWrap.innerHTML = '';
    if (!photos.length) {
        photos = ['assets/hero_beach.png'];
    }

    photos.forEach(function(src, index) {
        var thumb = document.createElement('button');
        thumb.type = 'button';
        thumb.className = 'room-modal-thumb' + (index === 0 ? ' room-modal-thumb--active' : '');
        var image = document.createElement('img');
        image.src = src;
        image.alt = name + ' thumbnail ' + (index + 1);
        thumb.appendChild(image);
        thumb.onclick = function() {
            setRoomModalImage(src, name, index, photos.length, thumb);
        };
        thumbsWrap.appendChild(thumb);
    });

    setRoomModalImage(photos[0], name, 0, photos.length, thumbsWrap.querySelector('.room-modal-thumb'));
    modal.classList.add('room-modal--open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('body-modal-open');
}

function closeRoomModal() {
    var modal = document.getElementById('room-modal');
    if (modal) {
        modal.classList.remove('room-modal--open');
        modal.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('body-modal-open');
}
window.hotelData = {
    checkin: <?php echo xss_json($filter_checkin); ?>,
    checkout: <?php echo xss_json($filter_checkout); ?>,
    nights: <?php echo $nights; ?>,
    guests: <?php echo $filter_guests; ?>,
    rooms: <?php echo xss_json($all_rooms_list); ?>,
    bookings: <?php echo xss_json($all_cal_bookings); ?>
};

/* State for Date Strips & Modal Calendar */
function escapeRoomText(value) {
    var span = document.createElement('span');
    span.textContent = value == null ? '' : String(value);
    return span.innerHTML;
}

var dateStripOffsets = {};
var currentCalType = '';
var currentCalLabel = '';
var currentCalPrice = 0;
var currentCalYear = new Date().getFullYear();
var currentCalMonth = new Date().getMonth();

/* Helper: Check availability for a room type on a specific date */
function checkTypeAvailabilityOnDate(type, dateStr) {
    var matchingRooms = (window.hotelData.rooms || []).filter(function(r) {
        return r.type === type && r.status !== 'maintenance';
    });
    var matchingRoomIds = matchingRooms.map(function(r) { return String(r.id); });
    if (matchingRooms.length === 0) {
        return { available: false, count: 0, reason: 'Sold out' };
    }

    var overlappingBookings = (window.hotelData.bookings || []).filter(function(b) {
        return matchingRoomIds.indexOf(String(b.room_id)) !== -1
            && b.check_in <= dateStr
            && b.check_out >= dateStr;
    }).sort(function(a, b) {
        return String(a.check_out).localeCompare(String(b.check_out));
    });

    var availCount = 0;
    matchingRooms.forEach(function(r) {
        var isBooked = (window.hotelData.bookings || []).some(function(b) {
            var roomName = (r.type || '').toLowerCase();
            var bookingName = (b.accommodation_name || '').toLowerCase();
            var normalizedRoomName = roomName.replace(/_/g, ' ');
            var directMatch = b.room_id == r.id && b.check_in <= dateStr && b.check_out >= dateStr;
            var legacyTypeMatch = (!directMatch)
                && b.check_in <= dateStr
                && b.check_out >= dateStr
                && (
                    (typeof b.room_type_id !== 'undefined' && b.room_type_id !== null && String(b.room_type_id) === String(r.id))
                    || bookingName.indexOf(normalizedRoomName) !== -1
                    || bookingName.indexOf(roomName) !== -1
                );
            return directMatch || legacyTypeMatch;
        });
        if (!isBooked) availCount++;
    });

    return {
        available: availCount > 0,
        count: availCount,
        reason: availCount > 0 ? 'Available' : 'Sold out',
        bookedCheckin: overlappingBookings.length ? overlappingBookings[0].check_in : null,
        bookedCheckout: overlappingBookings.length ? overlappingBookings[0].check_out : null
    };
}

/* Helper: Format date object to YYYY-MM-DD */
function formatDateISO(d) {
    var year = d.getFullYear();
    var month = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

function formatShortStayDate(isoDate) {
    var d = new Date(isoDate + 'T00:00:00');
    if (isNaN(d.getTime())) return isoDate;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

/* Render Date Strip for a room type */
function renderDateStrip(type) {
    var stripEl = document.getElementById('date-strip-' + type);
    if (!stripEl) return;

    var price = parseFloat(stripEl.getAttribute('data-price') || '0');
    var offset = dateStripOffsets[type] || 0;

    var baseDate = new Date(window.hotelData.checkin + 'T00:00:00');
    if (isNaN(baseDate.getTime())) baseDate = new Date();

    stripEl.innerHTML = '';
    var daysToShow = 4;

    for (var i = 0; i < daysToShow; i++) {
        var d = new Date(baseDate);
        d.setDate(d.getDate() + offset + i);
        var iso = formatDateISO(d);
        var avail = checkTypeAvailabilityOnDate(type, iso);

        // Format day name & date: "Wed 02 Sep"
        var dayName = d.toLocaleDateString('en-US', { weekday: 'short' });
        var dayNum  = String(d.getDate()).padStart(2, '0');
        var monthName = d.toLocaleDateString('en-US', { month: 'short' });
        var dateLabel = dayName + ' ' + dayNum + ' ' + monthName;

        var isCurrentCheckin = (iso === window.hotelData.checkin);

        var item = document.createElement('div');
        item.className = 'date-strip-item' + 
            (!avail.available ? ' date-strip-item--soldout' : '') +
            (isCurrentCheckin ? ' date-strip-item--active' : '');

        var html = '<span class="ds-date">' + dateLabel + '</span>' +
                   '<span class="ds-price">&#8369; ' + price.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</span>';

        if (!avail.available) {
            html += '<span class="ds-badge-sold">' + escapeRoomText(avail.reason) + '</span>';
            if (avail.bookedCheckin && avail.bookedCheckout) {
                html += '<span class="ds-booked-range">'
                    + formatShortStayDate(avail.bookedCheckin)
                    + ' to '
                    + formatShortStayDate(avail.bookedCheckout)
                    + '</span>';
            }
        }

        item.innerHTML = html;

        if (avail.available) {
            (function(targetDate) {
                item.onclick = function() {
                    selectDateAndReload(targetDate, type);
                };
            })(iso);
        }

        stripEl.appendChild(item);
    }
}

function shiftDateStrip(type, diff) {
    dateStripOffsets[type] = (dateStripOffsets[type] || 0) + diff;
    renderDateStrip(type);
}

function selectDateAndReload(newCheckin, roomType) {
    var nights = window.hotelData.nights || 1;
    var d = new Date(newCheckin + 'T00:00:00');
    d.setDate(d.getDate() + nights);
    var newCheckout = formatDateISO(d);

    var params = new URLSearchParams(window.location.search);
    params.set('checkin', newCheckin);
    params.set('checkout', newCheckout);
    if (roomType) params.set('room_type', roomType);

    window.location.search = params.toString();
}

/* â”€â”€ Availability Calendar Modal Logic â”€â”€ */
function openAvailabilityCalendar(type, label, price) {
    currentCalType = type;
    currentCalLabel = label;
    currentCalPrice = price;

    var baseDate = new Date(window.hotelData.checkin + 'T00:00:00');
    if (isNaN(baseDate.getTime())) baseDate = new Date();
    currentCalYear = baseDate.getFullYear();
    currentCalMonth = baseDate.getMonth();

    document.getElementById('avail-modal-title').textContent = label;
    document.getElementById('avail-modal-price').textContent = 'From â‚± ' + price.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' / night';

    renderCalendarDays();

    var modal = document.getElementById('avail-modal');
    modal.classList.add('avail-modal--open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('body-modal-open');
}

function closeAvailabilityCalendar() {
    var modal = document.getElementById('avail-modal');
    if (modal) {
        modal.classList.remove('avail-modal--open');
        modal.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('body-modal-open');
}

function changeCalMonth(diff) {
    currentCalMonth += diff;
    if (currentCalMonth > 11) {
        currentCalMonth = 0;
        currentCalYear++;
    } else if (currentCalMonth < 0) {
        currentCalMonth = 11;
        currentCalYear--;
    }
    renderCalendarDays();
}

function renderCalendarDays() {
    var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('cal-current-month').textContent = monthNames[currentCalMonth] + ' ' + currentCalYear;

    var daysContainer = document.getElementById('avail-modal-days');
    daysContainer.innerHTML = '';

    var firstDayIndex = new Date(currentCalYear, currentCalMonth, 1).getDay();
    var daysInMonth = new Date(currentCalYear, currentCalMonth + 1, 0).getDate();

    var todayStr = formatDateISO(new Date());

    // Empty lead cells
    for (var e = 0; e < firstDayIndex; e++) {
        var emptyCell = document.createElement('div');
        emptyCell.className = 'cal-day-cell cal-day-cell--empty';
        daysContainer.appendChild(emptyCell);
    }

    // Day cells
    for (var day = 1; day <= daysInMonth; day++) {
        var dateObj = new Date(currentCalYear, currentCalMonth, day);
        var iso = formatDateISO(dateObj);
        var isPast = iso < todayStr;
        var isToday = iso === todayStr;

        var cell = document.createElement('div');
        cell.className = 'cal-day-cell';

        if (isPast) {
            cell.classList.add('cal-day-cell--past');
            cell.innerHTML = '<span class="cal-day-num">' + day + '</span>';
        } else {
            var avail = checkTypeAvailabilityOnDate(currentCalType, iso);
            if (isToday) cell.classList.add('cal-day-cell--today');

            if (avail.available) {
                cell.classList.add('cal-day-cell--avail');
                cell.innerHTML = 
                    '<span class="cal-day-num">' + day + '</span>' +
                    '<span class="cal-day-price">&#8369; ' + Math.round(currentCalPrice).toLocaleString('en-PH') + '</span>' +
                    '<span class="cal-day-tag cal-day-tag--avail">Avail</span>';

                (function(targetDate) {
                    cell.onclick = function() {
                        selectDateAndReload(targetDate, currentCalType);
                    };
                })(iso);
            } else {
                cell.classList.add('cal-day-cell--sold');
                var soldRange = '';
                if (avail.bookedCheckin && avail.bookedCheckout) {
                    soldRange = '<span class="cal-day-booked">'
                        + formatShortStayDate(avail.bookedCheckin)
                        + ' to '
                        + formatShortStayDate(avail.bookedCheckout)
                        + '</span>';
                }
                cell.innerHTML = 
                    '<span class="cal-day-num">' + day + '</span>' +
                    '<span class="cal-day-price" style="color:#A39B94;">&#8369; ' + Math.round(currentCalPrice).toLocaleString('en-PH') + '</span>' +
                    '<span class="cal-day-tag cal-day-tag--sold">' + escapeRoomText(avail.reason) + '</span>' +
                    soldRange;
            }
        }

        daysContainer.appendChild(cell);
    }
}

/* Init on DOM load */
document.addEventListener('DOMContentLoaded', function() {
    syncRoomTabsOffset();
    var cl = document.getElementById('bs-clear-link');
    if (cl) cl.style.visibility = 'hidden';

    // Render all date strips present on the page
    document.querySelectorAll('.rate-date-strip').forEach(function(strip) {
        var type = strip.getAttribute('data-type');
        if (type) renderDateStrip(type);
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRoomModal();
            closeAvailabilityCalendar();
        }
    });

    window.addEventListener('resize', syncRoomTabsOffset);
    if (window.ResizeObserver) {
        var stickyObserver = new ResizeObserver(syncRoomTabsOffset);
        document.querySelectorAll('.main-header, .room-tabs-bar').forEach(function(el) { stickyObserver.observe(el); });
    }
    if (document.fonts) document.fonts.ready.then(syncRoomTabsOffset);
});
</script>
</body>
</html>


