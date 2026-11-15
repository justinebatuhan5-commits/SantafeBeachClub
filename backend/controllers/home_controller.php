<?php

/**
 * Load homepage metrics, featured stay photography, gallery preview, and reviews.
 *
 * @return array<string, mixed>
 */
function sfbc_handle_home_page(mysqli $conn): array {
    $review_summary = $conn->query("SELECT COUNT(*) AS total, AVG(rating) AS average_rating FROM reviews WHERE is_approved = 1")->fetch_assoc();

    $hero_highlights = [];
    $highlight_rows = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('hero_stay_title', 'hero_stay_description', 'hero_support_title', 'hero_support_description', 'checkin_time', 'checkout_time')");
    if ($highlight_rows) {
        while ($highlight_row = $highlight_rows->fetch_assoc()) {
            $hero_highlights[$highlight_row['setting_key']] = $highlight_row['setting_value'];
        }
    }

    $hero_feature_cards = [];
    foreach (['stay', 'support'] as $highlight_type) {
        $highlight_title = trim($hero_highlights['hero_' . $highlight_type . '_title'] ?? '');
        $highlight_description = trim($hero_highlights['hero_' . $highlight_type . '_description'] ?? '');
        if ($highlight_title !== '' && $highlight_description !== '') {
            $hero_feature_cards[] = ['title' => $highlight_title, 'description' => $highlight_description];
        }
    }

    $checkin_display = trim((string)($hero_highlights['checkin_time'] ?? ''));
    if ($checkin_display === '') {
        $checkin_display = '14:00';
    }
    $checkout_display = trim((string)($hero_highlights['checkout_time'] ?? ''));
    if ($checkout_display === '') {
        $checkout_display = '12:00';
    }

    // Fetch custom room type primary photos and secondary gallery lists from room_types
    $db_photos = [];
    $db_room_galleries = [];
    $photo_q = @$conn->query("SELECT name, image_url, gallery_images FROM room_types");
    if ($photo_q) {
        while ($row = $photo_q->fetch_assoc()) {
            $key = strtolower(str_replace(' ', '_', trim($row['name'])));
            if (!empty($row['image_url'])) {
                $db_photos[$key] = trim($row['image_url']);
            }
            if (!empty($row['gallery_images'])) {
                $parts = array_values(array_filter(array_map('trim', explode(',', $row['gallery_images']))));
                if (!empty($parts)) {
                    $db_room_galleries[$key] = $parts;
                }
            }
        }
    }

    // Fetch min price and max capacity per room type from rooms table
    $db_prices = [];
    $db_capacities = [];
    $stats_q = @$conn->query("SELECT type, MIN(price_per_night) AS min_price, MAX(capacity) AS max_cap FROM rooms WHERE price_per_night > 0 GROUP BY type");
    if ($stats_q) {
        while ($row = $stats_q->fetch_assoc()) {
            $key = strtolower(str_replace(' ', '_', trim($row['type'])));
            $db_prices[$key] = (float)$row['min_price'];
            if (!empty($row['max_cap'])) {
                $db_capacities[$key] = (int)$row['max_cap'];
            }
        }
    }

    $stays_catalog = [
        'standard_room' => [
            'wing'      => 'Garden Wing',
            'title'     => 'Standard Room',
            'anchor'    => 'rooms#room-standard-room',
            'desc'      => 'A calm sanctuary with cool interiors, garden orientation, and essential coastal comforts for couples or solo travelers.',
            'tags'      => ['Air Conditioning', 'Garden Views', 'Wi-Fi Access'],
            'fallbacks' => [
                'assets/rooms/standard_room/standard_room_6a6ffef28bdfb.png',
                'assets/rooms/standard/standard-room-3.png',
                'assets/rooms/standard_room/standard_room_6a70022b6a0f4.png',
            ],
        ],
        'beachview_duplex' => [
            'wing'      => 'Two-Level Stay',
            'title'     => 'Beachview Duplex',
            'anchor'    => 'rooms#room-beachview-duplex',
            'desc'      => 'A generous two-floor coastal residence with a private terrace facing the shoreline and room for family stays.',
            'tags'      => ['Breakfast Included', 'Private Terrace', 'Beachfront View'],
            'fallbacks' => [
                'assets/rooms/beachview_duplex/beachview_duplex_6a6ff822ca283.png',
                'assets/hero-slide-2.jpg',
            ],
        ],
        'seaview_duplex' => [
            'wing'      => 'Ocean Horizon',
            'title'     => 'Seaview Duplex',
            'anchor'    => 'rooms#room-seaview-duplex',
            'desc'      => 'Split-level living overlooking the sea, crafted with an airy lounge nook and direct coastal light.',
            'tags'      => ['Breakfast Included', 'Reading Lounge', 'Seaview Balcony'],
            'fallbacks' => [
                'assets/hero-slide-3.jpg',
                'assets/hero-slide-5-hd.webp',
            ],
        ],
        'beach_villa' => [
            'wing'      => 'Shoreline Signature',
            'title'     => 'Beach Villa',
            'anchor'    => 'rooms#room-beach-villa',
            'desc'      => 'High-ceiling coastal architecture steps from the sandbar with abundant natural light and a private lounge porch.',
            'tags'      => ['Breakfast Included', 'Beachfront Lounge', 'Two Queen Beds'],
            'fallbacks' => [
                'assets/hero-slide-1-hd.png',
                'assets/hero-slide-6-hd.webp',
            ],
        ],
    ];

    // Seed reserved hero & ambient backgrounds so Signature Stay cards prefer genuine, distinct room photographs
    $used_photo_keys = [
        'resort_bg1.jpg' => true,
        'resort_bg2.jpg' => true,
        'resort_bg3.jpg' => true,
        'hero-slide-1-hd.png' => true,
        'hero-slide-5-hd.webp' => true,
        'hero-slide-6-hd.webp' => true,
        'hero-slide-7-hd.webp' => true,
    ];
    $photo_priority_order = ['beach_villa', 'seaview_duplex', 'beachview_duplex', 'standard_room'];
    $resolved_stay_photos = [];

    foreach ($photo_priority_order as $type_key) {
        $candidates = [];
        if (!empty($db_photos[$type_key])) {
            $candidates[] = $db_photos[$type_key];
        }
        if (!empty($db_room_galleries[$type_key])) {
            foreach ($db_room_galleries[$type_key] as $gallery_img) {
                $candidates[] = $gallery_img;
            }
        }
        foreach ($stays_catalog[$type_key]['fallbacks'] as $fallback_img) {
            $candidates[] = $fallback_img;
        }

        $chosen = $stays_catalog[$type_key]['fallbacks'][0];
        foreach ($candidates as $candidate_raw) {
            $candidate_url = get_image_url($candidate_raw, '', '');
            if ($candidate_url === '') {
                continue;
            }
            $norm = strtolower(basename(parse_url($candidate_url, PHP_URL_PATH) ?: $candidate_url));
            if (!isset($used_photo_keys[$norm])) {
                $used_photo_keys[$norm] = true;
                $chosen = $candidate_url;
                break;
            }
        }
        $resolved_stay_photos[$type_key] = $chosen;
    }

    // Fetch up to 4 latest photos for the Island Moments showcase strip
    $gallery_preview = [];
    $gal_q = @$conn->query("SELECT file_name, created_at FROM gallery ORDER BY created_at DESC LIMIT 4");
    if ($gal_q) {
        $idx = 1;
        while ($g_row = $gal_q->fetch_assoc()) {
            $src = get_image_url($g_row['file_name'], '', 'assets/gallery/');
            if ($src !== '') {
                $ts = !empty($g_row['created_at']) ? strtotime($g_row['created_at']) : false;
                $gallery_preview[] = [
                    'src'   => $src,
                    'alt'   => 'Santa Fe Beach Club island view ' . $idx,
                    'label' => $ts ? date('M Y', $ts) : 'Shoreline View',
                ];
                $idx++;
            }
        }
    }

    $gallery_fallbacks = [
        ['src' => 'assets/hero-slide-6-hd.webp', 'alt' => 'Aerial sunset horizon at Santa Fe Beach Club', 'label' => 'Golden Horizon'],
        ['src' => 'assets/hero-slide-5-hd.webp', 'alt' => 'Turquoise shallow waters along Santa Fe shoreline', 'label' => 'Pantalan Waters'],
        ['src' => 'assets/hero-slide-7-hd.webp', 'alt' => 'Beach loungers and calm morning shoreline', 'label' => 'Morning Shore'],
        ['src' => 'assets/hero-slide-4-hd.png', 'alt' => 'Coconut palms and outrigger boats on Bantayan Island', 'label' => 'Bantayan Coast'],
    ];
    foreach ($gallery_fallbacks as $fallback_tile) {
        if (count($gallery_preview) >= 4) {
            break;
        }
        $already_added = false;
        foreach ($gallery_preview as $existing_tile) {
            if (strtolower(basename($existing_tile['src'])) === strtolower(basename($fallback_tile['src']))) {
                $already_added = true;
                break;
            }
        }
        if (!$already_added) {
            $gallery_preview[] = $fallback_tile;
        }
    }

    // Approved guest reviews
    $reviews_query = $conn->query("SELECT guest_name, guest_location, rating, review_text, created_at FROM reviews WHERE is_approved = 1 ORDER BY id DESC LIMIT 9");
    $reviews_list = [];
    if ($reviews_query) {
        while ($rev_row = $reviews_query->fetch_assoc()) {
            $reviews_list[] = $rev_row;
        }
    }

    return compact(
        'review_summary',
        'hero_feature_cards',
        'checkin_display',
        'checkout_display',
        'db_prices',
        'db_capacities',
        'stays_catalog',
        'resolved_stay_photos',
        'gallery_preview',
        'reviews_list'
    );
}
