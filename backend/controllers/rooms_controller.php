<?php

if (!function_exists('sfbc_resolve_room_photo')) {
    function sfbc_resolve_room_photo(?string $raw_path, string $fallback_rel): string {
        $frontend_dir = dirname(__DIR__, 2) . '/frontend';
        $raw = trim((string) $raw_path);
        if ($raw === '') {
            return $fallback_rel;
        }
        $norm_slash = str_replace('\\', '/', $raw);
        if (basename($norm_slash) === 'hero_beach.png') {
            return $fallback_rel;
        }
        if (function_exists('is_remote_image') && is_remote_image($raw)) {
            return function_exists('get_image_url') ? get_image_url($raw, $fallback_rel) : $raw;
        }
        $rel = ltrim((string) preg_replace('#^(?:\.\./)+#', '', $norm_slash), '/');
        if (strpos($rel, 'frontend/') === 0) {
            $rel = substr($rel, strlen('frontend/'));
        }
        if ($rel !== '' && is_file($frontend_dir . '/' . $rel)) {
            return $rel;
        }
        if ($rel !== '' && strpos($rel, 'assets/') !== 0 && is_file($frontend_dir . '/assets/' . $rel)) {
            return 'assets/' . $rel;
        }
        if (function_exists('get_image_url')) {
            $resolved = get_image_url($raw, $fallback_rel);
            if (function_exists('is_remote_image') && is_remote_image($resolved)) {
                return $resolved;
            }
            $resolved_rel = ltrim(str_replace('\\', '/', $resolved), '/');
            if ($resolved_rel !== '' && is_file($frontend_dir . '/' . $resolved_rel)) {
                return $resolved_rel;
            }
        }
        return $fallback_rel;
    }
}

if (!function_exists('sfbc_pick_valid_room_photos')) {
    function sfbc_pick_valid_room_photos(array $raw_list, array $fallback_list): array {
        $valid = [];
        foreach ($raw_list as $item) {
            $resolved = sfbc_resolve_room_photo((string) $item, '');
            if ($resolved !== '' && !in_array($resolved, $valid, true)) {
                $valid[] = $resolved;
            }
        }
        if (!empty($valid)) {
            return $valid;
        }
        $fallback_valid = [];
        foreach ($fallback_list as $fb) {
            $resolved = sfbc_resolve_room_photo((string) $fb, '');
            if ($resolved !== '' && !in_array($resolved, $fallback_valid, true)) {
                $fallback_valid[] = $resolved;
            }
        }
        return !empty($fallback_valid) ? $fallback_valid : ['assets/hero-slide-4-hd.png'];
    }
}

/**
 * Prepare room availability, catalog metadata, and pricing view data for rooms.php.
 *
 * @return array<string, mixed>
 */
function sfbc_handle_rooms_page(mysqli $conn): array {
    $filter_type     = isset($_GET['room_type']) ? $_GET['room_type'] : 'any';
    $filter_guests   = isset($_GET['guests'])    ? (int)$_GET['guests']    : 1;
    $filter_checkin  = isset($_GET['checkin'])   ? $_GET['checkin']        : date('Y-m-d');
    $filter_checkout = isset($_GET['checkout'])  ? $_GET['checkout']       : date('Y-m-d', strtotime('+1 day'));

    $where_clauses = [];
    $where_clauses[] = "r.type IN ('beachview_duplex','seaview_duplex','beach_villa','standard_room','standard_king','cottage','villa')";
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

    $type_labels = [
        'beachview_duplex' => 'Beachview Duplex',
        'seaview_duplex'   => 'Seaview Duplex',
        'beach_villa'      => 'Beach Villa',
        'standard_room'    => 'Standard Room',
        'standard_king'    => 'Standard Family Room',
    ];

    // Extra-person limits are database-managed. Keep the same safe defaults until
    // the migration has been run on a deployment database.
    $extra_person_limits = [
        'beachview_duplex' => ['adults' => 1, 'children' => 1],
        'seaview_duplex'   => ['adults' => 1, 'children' => 1],
        'beach_villa'      => ['adults' => 3, 'children' => 2],
        'standard_room'    => ['adults' => 2, 'children' => 0],
        'standard_king'    => ['adults' => 1, 'children' => 0],
        'cottage'          => ['adults' => 2, 'children' => 1],
        'villa'            => ['adults' => 4, 'children' => 2],
    ];
    try {
        $limitResult = $conn->query('SELECT room_type, max_extra_adults, max_extra_children FROM room_extra_person_limits');
        if ($limitResult) {
            while ($limit = $limitResult->fetch_assoc()) {
                $extra_person_limits[$limit['room_type']] = [
                    'adults' => (int)$limit['max_extra_adults'],
                    'children' => (int)$limit['max_extra_children'],
                ];
            }
        }
    } catch (mysqli_sql_exception $e) {
        // The application remains usable until the deployment migration creates it.
    }

    $type_gradients = [
        'beachview_duplex' => 'linear-gradient(135deg, #1a6b8a 0%, #0d9488 50%, #065f46 100%)',
        'seaview_duplex'   => 'linear-gradient(135deg, #1e3a5f 0%, #1a6b8a 50%, #0891b2 100%)',
        'beach_villa'      => 'linear-gradient(135deg, #92400e 0%, #b45309 50%, #d97706 100%)',
        'standard_room'    => 'linear-gradient(135deg, #374151 0%, #4b5563 50%, #6b7280 100%)',
        'standard_king'    => 'linear-gradient(135deg, #4c1d95 0%, #6d28d9 50%, #7c3aed 100%)',
    ];

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
        'standard_room'    => "Standard Room\nMaximum of 2 kids for free, 10 years and below.\nMaximum occupancy of 5 guests.",
        'standard_king'    => "Standard Family Room\nMaximum of 2 kids for free, 10 years and below.",
    ];

    $type_modal_photos = [
        'beachview_duplex' => $db_modal_photos['beachview_duplex'] ?? ($type_photos['beachview_duplex'] ? [$type_photos['beachview_duplex']] : []),
        'seaview_duplex'   => $db_modal_photos['seaview_duplex']   ?? ($type_photos['seaview_duplex']   ? [$type_photos['seaview_duplex']]   : []),
        'beach_villa'      => $db_modal_photos['beach_villa']      ?? ($type_photos['beach_villa']      ? [$type_photos['beach_villa']]      : []),
        'standard_room'    => $db_modal_photos['standard_room']    ?? ($type_photos['standard_room']    ? [$type_photos['standard_room']]    : []),
        'standard_king'    => $db_modal_photos['standard_king']    ?? ($type_photos['standard_king']    ? [$type_photos['standard_king']]    : []),
    ];

    $sfbc_fallback_gradients = [
        'beachview_duplex' => 'linear-gradient(135deg, #3B2B22 0%, #6D4C38 55%, #986842 100%)',
        'seaview_duplex'   => 'linear-gradient(135deg, #2F241E 0%, #5C3D2B 55%, #8A5A3A 100%)',
        'beach_villa'      => 'linear-gradient(135deg, #4A3326 0%, #7C533C 55%, #C5A880 100%)',
        'standard_room'    => 'linear-gradient(135deg, #3D3129 0%, #635145 55%, #8D7869 100%)',
        'standard_king'    => 'linear-gradient(135deg, #352922 0%, #5A463B 55%, #7F6554 100%)',
        'cottage'          => 'linear-gradient(135deg, #423127 0%, #72523D 55%, #A67B5B 100%)',
        'villa'            => 'linear-gradient(135deg, #2D221B 0%, #593E2D 55%, #9C724E 100%)',
    ];
    $type_gradients = array_merge($type_gradients, $sfbc_fallback_gradients);

    $type_icons = [];

    $sfbc_fallback_galleries = [
        'beachview_duplex' => [
            'assets/rooms/beachview_duplex/beachview_duplex_6a6ff822ca283.png',
            'assets/hero-slide-4-hd.png',
            'assets/hero-slide-5-hd.webp',
        ],
        'seaview_duplex' => [
            'assets/hero-slide-5-hd.webp',
            'assets/hero-slide-4-hd.png',
            'assets/images/resort_bg2.jpg',
        ],
        'beach_villa' => [
            'assets/hero-slide-6-hd.webp',
            'assets/hero-slide-7-hd.webp',
            'assets/images/resort_bg1.jpg',
        ],
        'standard_room' => [
            'assets/rooms/standard_room/standard_room_6a6ffef28bdfb.png',
            'assets/rooms/standard_room/standard_room_6a70022b6a0f4.png',
            'assets/rooms/standard_room/standard_room_6a70022d45a85.png',
            'assets/rooms/standard_room/standard_room_6a70022f5e234.png',
        ],
        'standard_king' => [
            'assets/rooms/standard/standard-room-2.png',
            'assets/rooms/standard/standard-room-3.png',
            'assets/rooms/standard/standard-room-4.png',
            'assets/rooms/standard/standard-room-5.png',
        ],
        'cottage' => [
            'assets/images/resort_bg1.jpg',
            'assets/images/resort_bg2.jpg',
            'assets/hero-slide-4-hd.png',
        ],
        'villa' => [
            'assets/hero-slide-7-hd.webp',
            'assets/hero-slide-6-hd.webp',
            'assets/images/resort_bg3.jpg',
        ],
    ];

    foreach ($sfbc_fallback_galleries as $tk => $fb_gallery) {
        $primary_fb = $fb_gallery[0] ?? 'assets/hero-slide-4-hd.png';
        $type_photos[$tk] = sfbc_resolve_room_photo($db_photos[$tk] ?? ($type_photos[$tk] ?? ''), $primary_fb);
        $raw_modal = $db_modal_photos[$tk] ?? [];
        if (empty($raw_modal) && !empty($db_photos[$tk])) {
            $raw_modal = [$db_photos[$tk]];
        }
        $modal_list = sfbc_pick_valid_room_photos($raw_modal, $fb_gallery);
        if (!empty($type_photos[$tk]) && !in_array($type_photos[$tk], $modal_list, true)) {
            array_unshift($modal_list, $type_photos[$tk]);
        }
        $type_modal_photos[$tk] = $modal_list;
    }

    $type_descriptions['cottage'] = $type_descriptions['cottage'] ?? 'Cozy beachfront nipa-inspired cottage steps from the shoreline.';
    $type_descriptions['villa']   = $type_descriptions['villa']   ?? 'Spacious coastal sanctuary with sunlit living area and beachfront access.';
    $type_long_descriptions['cottage'] = $type_long_descriptions['cottage'] ?? "Cozy beachfront cottage designed for relaxed island mornings with shaded porch seating and quick shore access.";
    $type_long_descriptions['villa']   = $type_long_descriptions['villa']   ?? "Spacious beachfront villa with sunlit interiors, private lounge space, and direct tropical garden views.\nMaximum of 2 kids for free, 10 years and below.";

    $type_beds = [
        'beachview_duplex' => '1 queen bed',
        'seaview_duplex'   => '1 king bed',
        'beach_villa'      => '2 queen beds',
        'standard_room'    => '1 queen bed or 2 single beds',
        'standard_king'    => '4 single beds',
        'cottage'          => '1 queen bed',
        'villa'            => '2 queen beds',
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
        'cottage' => [
            'Air conditioning',
            'Private veranda',
            'Hot and cold shower',
            'Toiletries',
            'Towels provided',
            'WiFi access',
            'Daily Mineral Water',
        ],
        'villa' => [
            'Air conditioning',
            'High ceiling lounge',
            'Dual vanity bathroom',
            'Mini Refrigerator',
            'Pocket garden',
            'WiFi access',
            'Daily Mineral Water',
            'Toiletries',
            'Towels provided',
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

    // Keep the Standard Room inventory at six room units.
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

    // Group rooms by type — available vs unavailable
    $rooms_by_type = [];
    foreach ($rooms as $room) {
        $rooms_by_type[$room['type']][] = $room;
    }

    $avail_by_type   = [];
    $unavail_by_type = [];

    foreach ($rooms_by_type as $type => $type_rooms) {
        $avail_units = array_values(array_filter($type_rooms, function($r) {
            return (int)$r['active_bookings'] === 0 && in_array(strtolower((string)$r['status']), ['ready', 'available'], true);
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

    return compact(
        'filter_type',
        'filter_guests',
        'filter_checkin',
        'filter_checkout',
        'rooms',
        'all_cal_bookings',
        'all_rooms_list',
        'type_labels',
        'extra_person_limits',
        'type_gradients',
        'type_icons',
        'type_photos',
        'type_descriptions',
        'type_long_descriptions',
        'type_modal_photos',
        'type_beds',
        'extra_person_rates',
        'breakfast_included_types',
        'type_amenities',
        'nights',
        'rooms_by_type',
        'avail_by_type',
        'unavail_by_type',
        'recommended_by_type',
        'other_available_by_type',
        'checkin_fmt',
        'checkout_fmt'
    );
}
