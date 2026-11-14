<?php
declare(strict_types=1);

/**
 * chatbot_tools.php — Grounded Read-Only Tools for the Guest & Staff AI Assistants
 *
 * Reuses existing Santa Fe Beach Club services and helpers:
 *  - getAvailableRooms()       (backend/services/booking_service.php)
 *  - calculateStayPricing()    (backend/helpers/room_status_helper.php)
 *  - sf_get_cancellation_policy() (backend/helpers/business_time_helper.php)
 *  - get_guest_booking()       (backend/helpers/guest_auth_helper.php)
 *  - mask_email(), mask_phone() (backend/helpers/pii_masker.php)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/booking_service.php';
require_once __DIR__ . '/../helpers/room_status_helper.php';
require_once __DIR__ . '/../helpers/business_time_helper.php';
require_once __DIR__ . '/../helpers/guest_auth_helper.php';
require_once __DIR__ . '/../helpers/pii_masker.php';
require_once __DIR__ . '/chatbot_direct_helper.php';

/**
 * Canonical room type metadata and human-readable display labels.
 */
function sf_chatbot_room_metadata(): array
{
    return [
        'beachview_duplex' => [
            'slug' => 'beachview_duplex',
            'name' => 'Beachview Duplex',
            'image_url' => 'assets/rooms/beachview_duplex/beachview_duplex_6a6ff822ca283.png',
            'base_capacity' => 2,
            'max_extra_adults' => 1,
            'max_extra_children' => 1,
            'extra_adult_fee' => 1000.00,
            'default_price' => 6900.00,
            'highlights' => 'Direct beachfront duplex suite, air conditioning, private bath, veranda, Wi-Fi.',
        ],
        'seaview_duplex' => [
            'slug' => 'seaview_duplex',
            'name' => 'Seaview Duplex',
            'image_url' => 'assets/hero-slide-3.jpg',
            'base_capacity' => 2,
            'max_extra_adults' => 1,
            'max_extra_children' => 1,
            'extra_adult_fee' => 1000.00,
            'default_price' => 7900.00,
            'highlights' => 'Elevated panoramic sea view duplex, air conditioning, private bath, balcony, Wi-Fi.',
        ],
        'beach_villa' => [
            'slug' => 'beach_villa',
            'name' => 'Beach Villa',
            'image_url' => 'assets/hero-slide-1.jpg',
            'base_capacity' => 4,
            'max_extra_adults' => 3,
            'max_extra_children' => 2,
            'extra_adult_fee' => 1000.00,
            'default_price' => 7900.00,
            'highlights' => 'Spacious beachfront villa ideal for families and groups, air conditioning, private bath, Wi-Fi.',
        ],
        'standard_king' => [
            'slug' => 'standard_king',
            'name' => 'Standard Family Room',
            'image_url' => 'assets/rooms/standard/standard-room-2.png',
            'base_capacity' => 4,
            'max_extra_adults' => 1,
            'max_extra_children' => 0,
            'extra_adult_fee' => 700.00,
            'default_price' => 4300.00,
            'highlights' => 'Comfortable family accommodation for 4 guests, air conditioning, private bath, Wi-Fi.',
        ],
        'standard_room' => [
            'slug' => 'standard_room',
            'name' => 'Standard Room',
            'image_url' => 'assets/rooms/standard/standard-room-1.png',
            'base_capacity' => 2,
            'max_extra_adults' => 3,
            'max_extra_children' => 0,
            'extra_adult_fee' => 700.00,
            'default_price' => 2900.00,
            'highlights' => 'Cozy resort room with air conditioning, private bath, Wi-Fi, and beach access.',
        ],
    ];
}

function sf_chatbot_room_display_name(string $slug): string
{
    $meta = sf_chatbot_room_metadata();
    return $meta[$slug]['name'] ?? ucwords(str_replace('_', ' ', $slug));
}

/**
 * Normalize user or model room type text into a valid room_type slug, or null if 'any'/unknown.
 * Supports synonyms (e.g. cottage -> beach_villa) and typo/spelling tolerance (Levenshtein fuzzy matching).
 */
function sf_chatbot_normalize_room_slug(?string $input): ?string
{
    if ($input === null) {
        return null;
    }
    $clean = strtolower(trim($input));
    if ($clean === '' || $clean === 'any' || $clean === 'all') {
        return null;
    }

    $meta = sf_chatbot_room_metadata();
    if (isset($meta[$clean])) {
        return $clean;
    }

    // 1. Direct & typo-tolerant substring checks
    if (preg_match('/\b(seaview|sea\s*view|seaveiw|sea\s*veiw|seavue|ocean\s*view\s*duplex)\b/i', $clean)) {
        return 'seaview_duplex';
    }
    if (preg_match('/\b(beachview|beach\s*view|beachveiw|bechview|duplex|duplx|duplexx|dooplex|dupex)\b/i', $clean)) {
        return 'beachview_duplex';
    }
    if (preg_match('/\b(villa|villas|vila|villla|vlla|vllia|cottage|cottages|cotage|cotagge|kubo|casita)\b/i', $clean)) {
        return 'beach_villa';
    }
    if (preg_match('/\b(family|famlily|famly|standard\s*king|king\s*room|king\s*bed)\b/i', $clean)) {
        return 'standard_king';
    }
    if (preg_match('/\b(standard|standrd|stndrd|stanard|standrad|std\s*room)\b/i', $clean)) {
        return 'standard_room';
    }

    // 2. Token-level fuzzy Levenshtein matching for misspelled accommodation names
    $tokens = array_filter(explode(' ', preg_replace('/[^a-z0-9\s]/', ' ', $clean) ?? ''));
    $fuzzyTargets = [
        'seaview'   => 'seaview_duplex',
        'beachview' => 'beachview_duplex',
        'duplex'    => 'beachview_duplex',
        'villa'     => 'beach_villa',
        'cottage'   => 'beach_villa',
        'family'    => 'standard_king',
        'standard'  => 'standard_room',
    ];
    foreach ($tokens as $tok) {
        $len = strlen($tok);
        if ($len < 4) {
            continue;
        }
        // Do not fuzzy-match common non-room words like 'village' or 'avail'
        if (in_array($tok, ['avail', 'available', 'value', 'valid', 'visit', 'duplo'], true)) {
            continue;
        }
        foreach ($fuzzyTargets as $targetWord => $slug) {
            $maxDist = strlen($targetWord) >= 7 ? 2 : 1;
            if (levenshtein($tok, $targetWord) <= $maxDist && $tok[0] === $targetWord[0]) {
                return $slug;
            }
        }
    }

    return null;
}

/**
 * Retrieve the live room catalog grounded on `rooms`, `room_types`, and `room_extra_person_limits`.
 */
function sf_chatbot_get_room_catalog(mysqli $conn): array
{
    $meta = sf_chatbot_room_metadata();
    $catalog = [];

    foreach ($meta as $slug => $info) {
        $catalog[$slug] = [
            'room_type_id' => 0,
            'slug' => $slug,
            'display_name' => $info['name'],
            'image_url' => $info['image_url'] ?? 'assets/hero-slide-2.jpg',
            'price_per_night' => $info['default_price'],
            'base_capacity' => $info['base_capacity'],
            'max_extra_adults' => $info['max_extra_adults'],
            'max_extra_children' => $info['max_extra_children'],
            'max_total_guests' => $info['base_capacity'] + $info['max_extra_adults'] + $info['max_extra_children'],
            'extra_adult_fee_per_stay' => $info['extra_adult_fee'],
            'usable_units' => 0,
            'highlights' => $info['highlights'],
        ];
    }

    // Load room_types IDs, prices, and custom image_url
    try {
        $rtRes = $conn->query("SELECT id, name, price, image_url FROM room_types");
        if ($rtRes) {
            while ($row = $rtRes->fetch_assoc()) {
                $slug = $row['name'];
                if (isset($catalog[$slug])) {
                    $catalog[$slug]['room_type_id'] = (int)$row['id'];
                    $rtPrice = (float)$row['price'];
                    // Guard against test price (< 500 PHP)
                    if ($rtPrice >= 500.00) {
                        $catalog[$slug]['price_per_night'] = $rtPrice;
                    }
                    if (!empty($row['image_url'])) {
                        $catalog[$slug]['image_url'] = trim((string)$row['image_url']);
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // Continue with defaults if query fails
    }

    // Physical rooms table is the pricing and usable inventory source of truth
    try {
        $rRes = $conn->query("
            SELECT type,
                   COUNT(*) AS usable_units,
                   MAX(price_per_night) AS room_price
            FROM rooms
            WHERE status <> 'maintenance'
            GROUP BY type
        ");
        if ($rRes) {
            while ($row = $rRes->fetch_assoc()) {
                $slug = $row['type'];
                if (isset($catalog[$slug])) {
                    $catalog[$slug]['usable_units'] = (int)$row['usable_units'];
                    $rPrice = (float)$row['room_price'];
                    if ($rPrice >= 500.00) {
                        $catalog[$slug]['price_per_night'] = $rPrice;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // Continue with defaults
    }

    // Extra person limits from DB if present
    try {
        $limRes = $conn->query("SELECT room_type, max_extra_adults, max_extra_children FROM room_extra_person_limits");
        if ($limRes) {
            while ($row = $limRes->fetch_assoc()) {
                $slug = $row['room_type'];
                if (isset($catalog[$slug])) {
                    $catalog[$slug]['max_extra_adults'] = (int)$row['max_extra_adults'];
                    $catalog[$slug]['max_extra_children'] = (int)$row['max_extra_children'];
                    $catalog[$slug]['max_total_guests'] = $catalog[$slug]['base_capacity']
                        + $catalog[$slug]['max_extra_adults']
                        + $catalog[$slug]['max_extra_children'];
                }
            }
        }
    } catch (Throwable $e) {
        // Table may not exist yet; defaults already set
    }

    return array_values($catalog);
}

/**
 * Build a relative pre-filled booking URL for the frontend widget.
 */
function sf_chatbot_build_booking_url(string $roomSlug, string $checkIn, string $checkOut, int $guests = 2, ?string $promoCode = null): string
{
    $params = [
        'room_type' => $roomSlug,
        'checkin' => $checkIn,
        'checkout' => $checkOut,
        'guests' => max(1, $guests),
    ];
    if ($promoCode !== null && trim($promoCode) !== '') {
        $params['promo_code'] = strtoupper(trim($promoCode));
    }
    return 'book?' . http_build_query($params);
}

/**
 * Build visual Room Cards (with image, nightly rate, live stay total/deposit, and BOOK NOW link)
 * for the Reservation Assistance horizontal carousel in the guest chatbot widget.
 */
function sf_chatbot_build_room_cards(
    mysqli $conn,
    ?array $dates = null,
    int $guests = 2,
    ?string $targetSlug = null,
    ?array $recommendations = null,
    int $nights = 1
): array {
    $guests = max(1, min(15, $guests));
    $nights = max(1, min(30, $nights));
    $cin = (is_array($dates) && !empty($dates[0])) ? (string)$dates[0] : null;
    $cout = (is_array($dates) && !empty($dates[1])) ? (string)$dates[1] : null;
    $hasDates = ($cin !== null && $cout !== null && isValidDateRange($cin, $cout));

    // If caller passed scored recommendation candidates, format them directly
    if (is_array($recommendations) && !empty($recommendations)) {
        $cards = [];
        foreach (array_slice($recommendations, 0, 5) as $r) {
            $slug = (string)($r['slug'] ?? $r['room_type'] ?? 'standard_room');
            $price = (float)($r['price_per_night'] ?? 3450.0);
            $staySummary = null;
            if (!empty($r['stay_total']) && !empty($r['deposit_required'])) {
                $staySummary = 'Total: ₱' . number_format((float)$r['stay_total'], 0) . ' · 50% Dep: ₱' . number_format((float)$r['deposit_required'], 0);
            }
            $cards[] = [
                'slug' => $slug,
                'display_name' => (string)($r['display_name'] ?? sf_chatbot_room_display_name($slug)),
                'image_url' => (string)($r['image_url'] ?? 'assets/hero-slide-2.jpg'),
                'price_per_night' => $price,
                'price_label' => '₱' . number_format($price, 0) . '/night',
                'capacity_label' => 'Up to ' . (int)($r['max_capacity'] ?? $r['base_capacity'] ?? 2) . ' guests',
                'badge' => (string)($r['badge'] ?? 'Recommended'),
                'stay_summary' => $staySummary,
                'booking_url' => (string)($r['booking_url'] ?? ('book?room_type=' . $slug . '&guests=' . $guests)),
                'is_available' => true,
            ];
        }
        return $cards;
    }

    // If dates are provided, query live availability & stay totals
    if ($hasDates) {
        $avail = sf_chatbot_tool_check_availability($conn, (string)$cin, (string)$cout, $guests, $targetSlug);
        $cards = [];
        foreach (($avail['rooms'] ?? []) as $ar) {
            if (empty($ar['is_available'])) {
                continue;
            }
            $slug = (string)$ar['room_type'];
            $price = (float)($ar['pricing']['base_price_per_night'] ?? $ar['price_per_night'] ?? 3450.0);
            $staySummary = null;
            if (!empty($ar['pricing']['total_amount'])) {
                $staySummary = 'Total: ₱' . number_format((float)$ar['pricing']['total_amount'], 0)
                    . ' · 50% Dep: ₱' . number_format((float)$ar['pricing']['deposit_amount'], 0);
            }
            $cards[] = [
                'slug' => $slug,
                'display_name' => (string)$ar['display_name'],
                'image_url' => (string)($ar['image_url'] ?? 'assets/hero-slide-2.jpg'),
                'price_per_night' => $price,
                'price_label' => '₱' . number_format($price, 0) . '/night',
                'capacity_label' => 'Up to ' . (int)($ar['max_adults_with_extra'] ?? $ar['base_capacity'] ?? 2) . ' guests',
                'badge' => (int)$ar['available_units'] . ' unit(s) open',
                'stay_summary' => $staySummary,
                'booking_url' => (string)($ar['booking_url'] ?? sf_chatbot_build_booking_url($slug, (string)$cin, (string)$cout, $guests)),
                'is_available' => true,
            ];
        }
        if (!empty($cards)) {
            return $cards;
        }
    }

    // Otherwise, build cards from the live room catalog
    $catalog = sf_chatbot_get_room_catalog($conn);
    $cards = [];
    foreach ($catalog as $room) {
        if ($targetSlug !== null && $room['slug'] !== $targetSlug) {
            continue;
        }
        $slug = (string)$room['slug'];
        $price = (float)$room['price_per_night'];
        $maxGuests = (int)$room['base_capacity'] + (int)$room['max_extra_adults'];
        if ($targetSlug === null && $guests > 2 && $maxGuests < $guests) {
            continue;
        }
        $staySummary = '50% Deposit: ₱' . number_format($price * 0.5, 0) . '/night';
        if ($nights > 1 || $guests > (int)$room['base_capacity']) {
            $est = sf_chatbot_estimate_by_nights($conn, $slug, $guests, $nights);
            if (!empty($est['success'])) {
                if (!empty($est['fits_guests'])) {
                    $nightWord = $nights === 1 ? '1 night' : ($nights . ' nights');
                    $staySummary = 'Est. ₱' . number_format((float)$est['total_amount'], 0)
                        . ' (' . $nightWord . ') · 50% Dep: ₱' . number_format((float)$est['deposit_amount'], 0);
                } else {
                    $staySummary = 'Fits up to ' . $maxGuests . ' adults (group of ' . $guests . ' may need extra bed/room)';
                }
            }
        }
        $cards[] = [
            'slug' => $slug,
            'display_name' => (string)$room['display_name'],
            'image_url' => (string)($room['image_url'] ?? 'assets/hero-slide-2.jpg'),
            'price_per_night' => $price,
            'price_label' => '₱' . number_format($price, 0) . '/night',
            'capacity_label' => 'Includes ' . (int)$room['base_capacity'] . ' (max ' . $maxGuests . ' guests)',
            'badge' => 'Available to Book',
            'stay_summary' => $staySummary,
            'booking_url' => 'book?room_type=' . rawurlencode($slug) . '&guests=' . $guests,
            'is_available' => true,
        ];
    }
    return $cards;
}

/**
 * Tool 1: Check real date-range availability across all or specific room types.
 */
function sf_chatbot_tool_check_availability(
    mysqli $conn,
    string $checkIn,
    string $checkOut,
    int $guests = 2,
    ?string $roomType = null
): array {
    $guests = max(1, min(20, $guests));
    if (!isValidDateRange($checkIn, $checkOut)) {
        return [
            'success' => false,
            'error' => 'Invalid date range. Check-in and check-out must be YYYY-MM-DD and check-out must be after check-in.',
        ];
    }

    $targetSlug = sf_chatbot_normalize_room_slug($roomType);
    $catalog = sf_chatbot_get_room_catalog($conn);
    $pdo = getPdoConnection();

    $results = [];
    foreach ($catalog as $room) {
        if ($targetSlug !== null && $room['slug'] !== $targetSlug) {
            continue;
        }

        $availableUnits = 0;
        if ($room['room_type_id'] > 0) {
            $availableUnits = getAvailableRooms($pdo, (int)$room['room_type_id'], $checkIn, $checkOut);
        }

        $fitsGuests = ($guests <= ($room['base_capacity'] + $room['max_extra_adults']));
        $pricing = null;
        try {
            $calc = calculateStayPricing($conn, $room['slug'], $checkIn, $checkOut, null, $guests);
            $pricing = [
                'nights' => $calc['nights'],
                'base_price_per_night' => $calc['base_price_per_night'],
                'extra_adults' => $calc['extra_adults'],
                'extra_person_total' => $calc['extra_person_total'],
                'weekend_surcharge_total' => $calc['weekend_surcharge_total'],
                'seasonal_adjustment_total' => $calc['seasonal_adjustment_total'],
                'total_amount' => $calc['total_amount'],
                'deposit_amount' => $calc['deposit_amount'],
                'remaining_balance' => $calc['remaining_balance'],
            ];
        } catch (Throwable $e) {
            $pricing = null;
        }

        $results[] = [
            'room_type' => $room['slug'],
            'display_name' => $room['display_name'],
            'image_url' => $room['image_url'] ?? 'assets/hero-slide-2.jpg',
            'price_per_night' => (float)$room['price_per_night'],
            'available_units' => $availableUnits,
            'is_available' => ($availableUnits > 0),
            'fits_requested_guests' => $fitsGuests,
            'base_capacity' => $room['base_capacity'],
            'max_adults_with_extra' => $room['base_capacity'] + $room['max_extra_adults'],
            'pricing' => $pricing,
            'booking_url' => ($availableUnits > 0)
                ? sf_chatbot_build_booking_url($room['slug'], $checkIn, $checkOut, $guests)
                : null,
        ];
    }

    return [
        'success' => true,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'guests' => $guests,
        'rooms' => $results,
    ];
}

/**
 * Tool 2: Estimate stay pricing including weekend/seasonal rules, extra guests, and promo codes.
 */
function sf_chatbot_tool_estimate_price(
    mysqli $conn,
    string $roomType,
    string $checkIn,
    string $checkOut,
    int $guests = 2,
    ?string $promoCode = null
): array {
    $slug = sf_chatbot_normalize_room_slug($roomType) ?? 'standard_room';
    $guests = max(1, min(15, $guests));

    if (!isValidDateRange($checkIn, $checkOut)) {
        return [
            'success' => false,
            'error' => 'Invalid check-in or check-out date. Use YYYY-MM-DD with check-out after check-in.',
        ];
    }

    try {
        $calc = calculateStayPricing($conn, $slug, $checkIn, $checkOut, $promoCode, $guests);
        $policy = sf_get_cancellation_policy($checkIn, $checkOut);
        return [
            'success' => true,
            'room_type' => $slug,
            'display_name' => sf_chatbot_room_display_name($slug),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'nights' => $calc['nights'],
            'guests' => $guests,
            'base_price_per_night' => $calc['base_price_per_night'],
            'extra_adults' => $calc['extra_adults'],
            'extra_rate_per_adult' => $calc['extra_rate_per_adult'],
            'extra_person_total' => $calc['extra_person_total'],
            'weekend_surcharge_total' => $calc['weekend_surcharge_total'],
            'seasonal_adjustment_total' => $calc['seasonal_adjustment_total'],
            'promo_code_applied' => $calc['promo_code'],
            'promo_error' => $calc['promo_error'],
            'discount_amount' => $calc['discount_amount'],
            'total_amount' => $calc['total_amount'],
            'deposit_required_now' => $calc['deposit_amount'],
            'remaining_balance_due_24h_before_arrival' => $calc['remaining_balance'],
            'cancellation_policy' => $policy['policy_name'],
            'free_cancellation_deadline' => $policy['deadline_formatted'],
            'booking_url' => sf_chatbot_build_booking_url($slug, $checkIn, $checkOut, $guests, $calc['promo_code']),
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Tool 3: Calculate exact cancellation deadline & refund eligibility for a stay.
 */
function sf_chatbot_tool_get_cancellation_policy(string $checkIn, string $checkOut): array
{
    if (!isValidDateRange($checkIn, $checkOut)) {
        return [
            'success' => false,
            'error' => 'Please provide valid check_in and check_out dates in YYYY-MM-DD format.',
        ];
    }
    $policy = sf_get_cancellation_policy($checkIn, $checkOut);
    return [
        'success' => true,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'nights' => $policy['nights'],
        'policy_name' => $policy['policy_name'],
        'advance_notice_required' => $policy['window_label'],
        'deadline_formatted' => $policy['deadline_formatted'],
        'is_deadline_passed' => $policy['is_expired'],
        'refund_percentage_if_cancelled_now' => $policy['refund_percentage'],
        'rules_summary' => '1–3 nights: cancel at least 48 hours (2 days) before 2:00 PM check-in for 100% refund. 4–6 nights: 5 days advance notice. 7+ nights: 7 days advance notice. After the deadline, bookings are non-refundable.',
    ];
}

/**
 * Tool 4: Validate a promo code or list active promotions.
 */
function sf_chatbot_tool_validate_promo(mysqli $conn, ?string $code = null): array
{
    $clean = strtoupper(trim((string)$code));
    if ($clean !== '') {
        $stmt = $conn->prepare("
            SELECT code, title, description, discount_type, discount_value, usage_limit, valid_from, valid_until
            FROM promotions
            WHERE (UPPER(code) = ? OR UPPER(title) = ?)
              AND is_active = 1
              AND valid_from <= CURDATE()
              AND valid_until >= CURDATE()
            LIMIT 1
        ");
        $stmt->bind_param('ss', $clean, $clean);
        $stmt->execute();
        $promo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$promo) {
            return [
                'success' => false,
                'valid' => false,
                'code' => $clean,
                'message' => 'This promo code is invalid or expired.',
            ];
        }

        if ($promo['usage_limit'] !== null) {
            $uStmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE UPPER(COALESCE(promo_code, '')) = ? AND LOWER(COALESCE(status, '')) NOT IN ('cancelled', 'canceled')");
            $uStmt->bind_param('s', $clean);
            $uStmt->execute();
            $used = (int)$uStmt->get_result()->fetch_row()[0];
            $uStmt->close();
            if ($used >= (int)$promo['usage_limit']) {
                return [
                    'success' => false,
                    'valid' => false,
                    'code' => $clean,
                    'message' => 'This promo code has reached its usage limit.',
                ];
            }
        }

        return [
            'success' => true,
            'valid' => true,
            'code' => $promo['code'] ?: $promo['title'],
            'title' => $promo['title'],
            'description' => $promo['description'],
            'discount' => $promo['discount_type'] === 'percent'
                ? ($promo['discount_value'] . '% off')
                : ('₱' . number_format((float)$promo['discount_value'], 2) . ' off'),
            'valid_until' => $promo['valid_until'],
        ];
    }

    // List active promos
    $promos = [];
    $res = $conn->query("
        SELECT code, title, description, discount_type, discount_value, valid_until
        FROM promotions
        WHERE is_active = 1
          AND valid_from <= CURDATE()
          AND valid_until >= CURDATE()
        ORDER BY id DESC
        LIMIT 5
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $promos[] = [
                'code' => $row['code'] ?: $row['title'],
                'title' => $row['title'],
                'discount' => $row['discount_type'] === 'percent'
                    ? ($row['discount_value'] . '% off')
                    : ('₱' . number_format((float)$row['discount_value'], 2) . ' off'),
                'valid_until' => $row['valid_until'],
            ];
        }
    }

    return [
        'success' => true,
        'active_promotions' => $promos,
    ];
}

/**
 * Tool 5: Get detailed room specifications and capacity limits.
 */
function sf_chatbot_tool_get_room_details(mysqli $conn, ?string $roomType = null): array
{
    $slug = sf_chatbot_normalize_room_slug($roomType);
    $catalog = sf_chatbot_get_room_catalog($conn);
    if ($slug !== null) {
        foreach ($catalog as $room) {
            if ($room['slug'] === $slug) {
                return ['success' => true, 'room' => $room];
            }
        }
    }
    return ['success' => true, 'rooms' => $catalog];
}

/**
 * Tool 6: Look up the current guest's booking ONLY if already OTP-verified in session.
 */
function sf_chatbot_tool_lookup_verified_booking(mysqli $conn): array
{
    $guestSession = get_guest_booking();
    if (!$guestSession || empty($guestSession['booking']['id'])) {
        return [
            'verified' => false,
            'requires_otp' => true,
            'instruction' => 'The guest has not verified their booking via OTP yet. Ask them to click the "Verify My Booking (OTP)" button in the chat or enter their Booking Reference (e.g., REF-001) and Email so we can send a 6-digit verification code.',
        ];
    }

    $bookingId = (int)$guestSession['booking']['id'];
    $stmt = $conn->prepare("
        SELECT id, guest_name, guest_email, guest_phone, accommodation_name, check_in, check_out,
               guests_count, extra_adults, extra_children, eta, status, payment_method,
               payment_deadline, promo_code, discount_amount, refund_status
        FROM bookings
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        return [
            'verified' => false,
            'error' => 'Verified booking record could not be found.',
        ];
    }

    // Sum verified and pending payments
    $pStmt = $conn->prepare("
        SELECT status, SUM(amount) AS total
        FROM payments
        WHERE booking_id = ?
        GROUP BY status
    ");
    $pStmt->bind_param('i', $bookingId);
    $pStmt->execute();
    $pRes = $pStmt->get_result();
    $paidVerified = 0.0;
    $paidPending = 0.0;
    while ($pRow = $pRes->fetch_assoc()) {
        if ($pRow['status'] === 'verified') {
            $paidVerified += (float)$pRow['total'];
        } elseif ($pRow['status'] === 'pending') {
            $paidPending += (float)$pRow['total'];
        }
    }
    $pStmt->close();

    $policy = sf_get_cancellation_policy($booking['check_in'], $booking['check_out']);
    $bookingRef = 'REF-' . str_pad((string)$booking['id'], 3, '0', STR_PAD_LEFT);

    return [
        'verified' => true,
        'booking_ref' => $bookingRef,
        'guest_name' => $booking['guest_name'],
        'guest_email_masked' => mask_email($booking['guest_email']),
        'guest_phone_masked' => mask_phone($booking['guest_phone']),
        'accommodation' => $booking['accommodation_name'],
        'check_in' => $booking['check_in'],
        'check_out' => $booking['check_out'],
        'eta' => $booking['eta'],
        'guests_count' => (int)$booking['guests_count'],
        'status' => $booking['status'],
        'payment_method' => $booking['payment_method'],
        'verified_paid_amount' => $paidVerified,
        'pending_verification_amount' => $paidPending,
        'payment_deadline' => $booking['payment_deadline'],
        'refund_status' => $booking['refund_status'],
        'cancellation_policy' => $policy['policy_name'],
        'cancellation_deadline' => $policy['deadline_formatted'],
        'can_cancel_for_full_refund' => !$policy['is_expired'],
        'manage_booking_url' => 'my_booking',
    ];
}

/**
 * Duration-capable backend pricing estimator for direct guest Q&A and multi-turn follow-ups
 * (works both with specific calendar dates AND when guests only mention guest count / nights).
 */
function sf_chatbot_estimate_by_nights(
    mysqli $conn,
    string $roomType,
    int $guests = 2,
    int $nights = 1,
    ?string $checkIn = null,
    ?string $checkOut = null,
    ?string $promoCode = null
): array {
    $slug = sf_chatbot_normalize_room_slug($roomType) ?? trim($roomType);
    $guests = max(1, $guests);
    $nights = max(1, $nights);

    $catalog = sf_chatbot_get_room_catalog($conn);
    $unit = null;
    foreach ($catalog as $row) {
        if ($row['slug'] === $slug) {
            $unit = $row;
            break;
        }
    }
    if (!$unit) {
        return ['success' => false, 'error' => 'Accommodation type not found.'];
    }

    $baseCap = (int)($unit['base_capacity'] ?? 2);
    $maxExtraAdults = (int)($unit['max_extra_adults'] ?? 0);
    $maxExtraChildren = (int)($unit['max_extra_children'] ?? 0);
    $maxAdultsCap = $baseCap + $maxExtraAdults;
    $maxTotalCap = (int)($unit['max_total_guests'] ?? ($maxAdultsCap + $maxExtraChildren));
    $fitsGuests = $guests <= $maxAdultsCap;

    if ($checkIn !== null && $checkOut !== null && $checkIn !== '' && $checkOut !== '') {
        $dated = sf_chatbot_tool_estimate_price($conn, $slug, $checkIn, $checkOut, $guests, $promoCode);
        if (!empty($dated['success'])) {
            $dated['base_capacity'] = $baseCap;
            $dated['max_capacity'] = $maxAdultsCap;
            $dated['max_total_guests'] = $maxTotalCap;
            $dated['fits_guests'] = $fitsGuests;
            $dated['has_specific_dates'] = true;
            return $dated;
        }
    }

    $baseRate = (float)($unit['price_per_night'] ?? 0);
    $extraAdultRateOnce = (float)($unit['extra_adult_fee_per_stay'] ?? (in_array($slug, ['beachview_duplex', 'seaview_duplex', 'beach_villa'], true) ? 1000 : 700));
    $extraAdults = $fitsGuests ? max(0, $guests - $baseCap) : 0;
    $extraPersonTotal = $extraAdults * $extraAdultRateOnce; // Charged ONCE per stay per resort pricing rules
    $roomSubtotal = $baseRate * $nights;
    $subtotal = $roomSubtotal + $extraPersonTotal;

    $depositPct = 50.0;
    try {
        $sRes = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'deposit_percentage' LIMIT 1");
        if ($sRes && ($sRow = $sRes->fetch_assoc())) {
            $val = (float)$sRow['setting_value'];
            if ($val > 0 && $val <= 100) {
                $depositPct = $val;
            }
        }
    } catch (Throwable $e) {
    }

    $depositAmount = round($subtotal * ($depositPct / 100), 2);

    return [
        'success' => true,
        'room_type' => $slug,
        'display_name' => (string)$unit['display_name'],
        'base_price_per_night' => $baseRate,
        'base_capacity' => $baseCap,
        'max_capacity' => $maxAdultsCap,
        'max_extra_adults' => $maxExtraAdults,
        'max_extra_children' => (int)($unit['max_extra_children'] ?? 0),
        'fits_guests' => $fitsGuests,
        'guests' => $guests,
        'nights' => $nights,
        'has_specific_dates' => false,
        'extra_adults' => $extraAdults,
        'extra_rate_per_adult' => $extraAdultRateOnce,
        'extra_person_total' => $extraPersonTotal,
        'room_subtotal' => $roomSubtotal,
        'total_amount' => $subtotal,
        'deposit_amount' => $depositAmount,
        'remaining_balance' => round($subtotal - $depositAmount, 2),
        'booking_url' => 'book?room_type=' . rawurlencode($slug) . '&guests=' . $guests,
    ];
}

/**
 * Filter and price all accommodations that can accommodate a specific guest count.
 */
function sf_chatbot_price_fitting_rooms(
    mysqli $conn,
    int $guests,
    int $nights = 1,
    ?string $checkIn = null,
    ?string $checkOut = null
): array {
    $guests = max(1, $guests);
    $nights = max(1, $nights);
    $catalog = sf_chatbot_get_room_catalog($conn);
    $fitting = [];

    foreach ($catalog as $room) {
        $roomMaxAdults = (int)($room['base_capacity'] ?? 2) + (int)($room['max_extra_adults'] ?? 0);
        if ($roomMaxAdults < $guests) {
            continue;
        }
        $est = sf_chatbot_estimate_by_nights($conn, (string)$room['slug'], $guests, $nights, $checkIn, $checkOut);
        if (!empty($est['success']) && !empty($est['fits_guests'])) {
            $fitting[] = $est;
        }
    }

    // Prioritize natural base-4 group units (Standard Family Room, Beach Villa) cleanly, then by total cost
    usort($fitting, static function (array $a, array $b): int {
        $aBase4 = ((int)$a['base_capacity'] >= 4) ? 0 : 1;
        $bBase4 = ((int)$b['base_capacity'] >= 4) ? 0 : 1;
        if ($aBase4 !== $bBase4) {
            return $aBase4 <=> $bBase4;
        }
        return (float)$a['total_amount'] <=> (float)$b['total_amount'];
    });

    return $fitting;
}

/**
 * Tool 7: Smart Personalized Accommodation Recommender based on guest count, group type, occasion, budget, preferences, and optional travel dates.
 */
function sf_chatbot_tool_recommend_accommodation(
    mysqli $conn,
    int $guests = 2,
    ?string $preference = null,
    ?float $maxBudget = null,
    ?string $checkIn = null,
    ?string $checkOut = null
): array {
    $guests = max(1, min(20, $guests));
    $catalog = sf_chatbot_get_room_catalog($conn);
    $prefLower = strtolower((string)$preference);

    // If travel dates are available, load real-time availability map
    $availMap = [];
    $hasValidDates = ($checkIn !== null && $checkOut !== null && isValidDateRange($checkIn, $checkOut));
    if ($hasValidDates) {
        $availCheck = sf_chatbot_tool_check_availability($conn, $checkIn, $checkOut, $guests, null);
        if (!empty($availCheck['success']) && !empty($availCheck['rooms'])) {
            foreach ($availCheck['rooms'] as $ar) {
                $arKey = (string)($ar['room_type'] ?? $ar['slug'] ?? '');
                if ($arKey !== '') {
                    $availMap[$arKey] = $ar;
                }
            }
        }
    }

    $candidates = [];
    foreach ($catalog as $room) {
        $base = (int)$room['base_capacity'];
        $maxAdults = $base + (int)$room['max_extra_adults'];
        $price = (float)$room['price_per_night'];
        $slug = (string)$room['slug'];

        // Exclude rooms where guests exceed absolute max extra adult capacity
        if ($guests > $maxAdults) {
            continue;
        }

        // Exclude rooms exceeding max budget if specified
        if ($maxBudget !== null && $maxBudget > 0 && $price > $maxBudget) {
            continue;
        }

        // Check date availability if dates were provided
        $isAvailableOnDates = true;
        $availableUnits = (int)$room['usable_units'];
        $stayTotal = null;
        $depositRequired = null;
        if ($hasValidDates && isset($availMap[$slug])) {
            $isAvailableOnDates = (bool)$availMap[$slug]['is_available'];
            $availableUnits = (int)$availMap[$slug]['available_units'];
            if (!$isAvailableOnDates) {
                continue; // Filter out rooms that are sold out on the guest's dates
            }
            if (!empty($availMap[$slug]['pricing'])) {
                $stayTotal = (float)$availMap[$slug]['pricing']['total_amount'];
                $depositRequired = (float)$availMap[$slug]['pricing']['deposit_amount'];
            }
        }

        $score = 0;
        $badge = 'Recommended';
        $reason = '';

        // Capacity suitability scoring
        if ($guests === $base) {
            $score += 25; // Perfect fit without extra person fees
        } elseif ($guests < $base) {
            $score += 15; // Plenty of room
        } else {
            $score += 10; // Fits with extra person allowance
        }

        // Preference scoring: Honeymoon / Anniversary / Couples
        if (preg_match('/\b(honeymoon|anniversary|romantic|couple|mag-asawa|kasal|monthsary|sunset)\b/', $prefLower)) {
            if ($slug === 'seaview_duplex') {
                $score += 40;
                $badge = 'Best for Honeymoon & Couples';
                $reason = 'Elevated panoramic sea view with a private balcony for romantic sunsets';
            } elseif ($slug === 'beachview_duplex') {
                $score += 35;
                $badge = 'Romantic Beachfront';
                $reason = 'Intimate ground-level beachfront veranda just steps from the shoreline';
            } elseif ($slug === 'beach_villa') {
                $score += 28;
                $badge = 'Luxury Private Villa';
                $reason = 'Standalone luxury beachfront villa with maximum privacy';
            }
        }

        // Preference scoring: Senior Citizens / Accessibility / Ground Floor
        if (preg_match('/\b(senior|elderly|lola|lolo|parents|accessible|ground floor|no stairs|wheelchair)\b/', $prefLower)) {
            if ($slug === 'beachview_duplex') {
                $score += 38;
                $badge = 'Senior-Friendly Ground Floor';
                $reason = 'Easy ground-level access with beachfront veranda and zero steep stairs';
            } elseif ($slug === 'beach_villa') {
                $score += 32;
                $badge = 'Spacious Ground-Level Villa';
                $reason = 'Standalone ground-level villa ideal for multi-generational families with seniors';
            } elseif ($slug === 'standard_king') {
                $score += 25;
                $badge = 'Accessible Family Comfort';
                $reason = 'Convenient access and generous floor space for up to 4 guests';
            }
        }

        // Preference scoring: Remote Work / Workation / Solo
        if (preg_match('/\b(work|workation|remote|laptop|wifi|quiet|solo|peaceful)\b/', $prefLower)) {
            if ($slug === 'seaview_duplex') {
                $score += 32;
                $badge = 'Best Workation View';
                $reason = 'Quiet upper-level balcony with inspiring ocean views and complimentary Wi-Fi';
            } elseif ($slug === 'standard_room') {
                $score += 30;
                $badge = 'Smart Solo / Workation Pick';
                $reason = 'Quiet, air-conditioned comfort with Wi-Fi at our most economical nightly rate';
            }
        }

        // Preference scoring: Beachfront & Views
        if (str_contains($prefLower, 'view') || str_contains($prefLower, 'beach') || str_contains($prefLower, 'sea') || str_contains($prefLower, 'ocean') || str_contains($prefLower, 'dagat')) {
            if ($slug === 'seaview_duplex' && $reason === '') {
                $score += 30;
                $badge = 'Best Ocean View';
                $reason = 'Elevated panoramic sea view with private balcony';
            } elseif ($slug === 'beachview_duplex' && $reason === '') {
                $score += 30;
                $badge = 'Top Beachfront Choice';
                $reason = 'Direct ground-level veranda just steps from the white sand';
            } elseif ($slug === 'beach_villa' && $reason === '') {
                $score += 25;
                $badge = 'Beachfront Luxury';
                $reason = 'Spacious standalone villa right along the shoreline';
            }
        }

        // Preference scoring: Families & Groups
        if (str_contains($prefLower, 'family') || str_contains($prefLower, 'group') || str_contains($prefLower, 'barkada') || str_contains($prefLower, 'pamilya') || str_contains($prefLower, 'kids') || $guests >= 4) {
            if ($slug === 'beach_villa') {
                $score += 35;
                $badge = 'Best for Families & Groups';
                $reason = 'Spacious standalone layout accommodating up to 7 guests with extra bedding';
            } elseif ($slug === 'standard_king') {
                $score += 30;
                $badge = 'Best Value for Families';
                $reason = 'Dedicated 4-guest base setup with private bath at an affordable rate';
            }
        }

        // Preference scoring: Budget
        if (str_contains($prefLower, 'budget') || str_contains($prefLower, 'cheap') || str_contains($prefLower, 'affordable') || str_contains($prefLower, 'mura') || str_contains($prefLower, 'barato') || str_contains($prefLower, 'tipid')) {
            if ($slug === 'standard_room') {
                $score += 35;
                $badge = 'Best Budget Choice';
                $reason = 'Most affordable room with aircon, private bath, and full resort beach access';
            } elseif ($slug === 'standard_king') {
                $score += 25;
                $badge = 'High Capacity Value';
                $reason = 'Great per-person cost efficiency for up to 4 to 5 guests';
            }
        }

        // Romance / Couples default
        if ($guests <= 2 && empty($reason)) {
            if ($slug === 'seaview_duplex') {
                $badge = 'Most Romantic';
                $reason = 'Private balcony with scenic sunset views, ideal for couples';
                $score += 15;
            } elseif ($slug === 'beachview_duplex') {
                $badge = 'Beachfront Relaxation';
                $reason = 'Direct beachfront veranda for a peaceful coastal escape';
                $score += 15;
            } elseif ($slug === 'standard_room') {
                $badge = 'Budget Cozy';
                $reason = 'Clean and comfortable with all essential amenities';
                $score += 10;
            }
        }

        if (empty($reason)) {
            $reason = "Accommodates up to {$maxAdults} guests comfortably with private bath and air conditioning";
        }

        $extraAdults = max(0, $guests - $base);
        $extraFee = $extraAdults * (float)$room['extra_adult_fee_per_stay'];

        $bookingUrl = $hasValidDates
            ? sf_chatbot_build_booking_url($slug, (string)$checkIn, (string)$checkOut, $guests, null)
            : ('book?room_type=' . $slug . '&guests=' . $guests);

        $candidates[] = [
            'slug' => $slug,
            'display_name' => $room['display_name'],
            'image_url' => $room['image_url'] ?? 'assets/hero-slide-2.jpg',
            'badge' => $badge,
            'price_per_night' => $price,
            'base_capacity' => $base,
            'max_capacity' => $maxAdults,
            'extra_adults_needed' => $extraAdults,
            'extra_fee_per_stay' => $extraFee,
            'available_units' => $availableUnits,
            'stay_total' => $stayTotal,
            'deposit_required' => $depositRequired,
            'check_in' => $hasValidDates ? $checkIn : null,
            'check_out' => $hasValidDates ? $checkOut : null,
            'reason' => $reason,
            'score' => $score,
            'booking_url' => $bookingUrl,
        ];
    }

    usort($candidates, static function(array $a, array $b): int {
        return $b['score'] <=> $a['score'];
    });

    return [
        'success' => true,
        'requested_guests' => $guests,
        'max_budget' => $maxBudget,
        'check_in' => $hasValidDates ? $checkIn : null,
        'check_out' => $hasValidDates ? $checkOut : null,
        'recommendations' => $candidates,
        'multi_room_suggested' => ($guests > 7),
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// PHASE 6: READ-ONLY STAFF ASSISTANT TOOLS (RBAC-GATED)
// ═══════════════════════════════════════════════════════════════════════════

function sf_staff_assistant_routes(string $role = 'receptionist'): array
{
    $isAdmin = ($role === 'admin');
    return [
        'reservations'   => $isAdmin ? 'admin_reservations' : 'reservations',
        'checkin'        => 'admin_checkin',
        'calendar'       => 'admin_calendar',
        'guests'         => 'guests',
        'payments'       => 'payments',
        'refunds'        => 'admin_refunds',
        'discounts'      => 'discount_requests.php',
        'inquiries'      => 'admin_inquiries',
        'live_support'   => 'admin_live_support',
        'reports'        => $isAdmin ? 'admin_reports' : null,
        'promotions'     => $isAdmin ? 'admin_promotions' : null,
        'reviews'        => $isAdmin ? 'admin_reviews' : null,
        'accommodations' => $isAdmin ? 'accommodations' : null,
        'logs'           => $isAdmin ? 'admin_logs' : null,
        'sessions'       => $isAdmin ? 'admin_sessions' : null,
        'chatbot'        => $isAdmin ? 'admin_chatbot' : null,
    ];
}

function sf_staff_human_room_label(string $slug): string
{
    $meta = sf_chatbot_room_metadata();
    if (isset($meta[$slug]['name'])) {
        return (string)$meta[$slug]['name'];
    }
    $clean = trim(str_replace(['_', '-'], ' ', $slug));
    return $clean !== '' ? ucwords($clean) : 'Room';
}

function sf_staff_table_exists(mysqli $conn, string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safe === '') {
        return false;
    }
    try {
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function sf_staff_channel_sql(mysqli $conn, string $channel): string
{
    if (function_exists('sf_inquiry_channel_sql_condition')) {
        return sf_inquiry_channel_sql_condition($conn, $channel, 'i');
    }
    $hasConv = sf_staff_table_exists($conn, 'chatbot_conversations');
    $isLiveCond = $hasConv
        ? "(i.subject LIKE '[AI Concierge Handoff]%' OR EXISTS (SELECT 1 FROM chatbot_conversations cc WHERE cc.inquiry_id = i.id))"
        : "(i.subject LIKE '[AI Concierge Handoff]%')";
    if ($channel === 'live_chat') {
        return $isLiveCond;
    }
    if ($channel === 'contact') {
        return "(NOT {$isLiveCond})";
    }
    return '1=1';
}

/**
 * Staff Tool 1: Front desk operations, in-house guests, and overdue check-outs.
 */
function sf_staff_tool_today_operations(mysqli $conn, ?string $date = null, string $role = 'receptionist'): array
{
    $targetDate = ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : date('Y-m-d');
    $routes = sf_staff_assistant_routes($role);

    $arrStmt = $conn->prepare("
        SELECT b.id, b.guest_name, b.guest_email, b.accommodation_name, b.eta, b.status, b.guests_count,
               r.room_number
        FROM bookings b
        LEFT JOIN rooms r ON r.id = b.room_id
        WHERE b.check_in = ? AND LOWER(COALESCE(b.status, '')) NOT IN ('cancelled', 'canceled')
        ORDER BY b.eta ASC, b.id ASC
        LIMIT 25
    ");
    $arrStmt->bind_param('s', $targetDate);
    $arrStmt->execute();
    $arrRes = $arrStmt->get_result();
    $arrivals = [];
    while ($row = $arrRes->fetch_assoc()) {
        $arrivals[] = [
            'booking_ref' => 'REF-' . str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT),
            'guest_name' => $row['guest_name'],
            'guest_email_masked' => mask_email($row['guest_email']),
            'accommodation' => $row['accommodation_name'],
            'room_number' => $row['room_number'] ?: null,
            'eta' => $row['eta'],
            'guests' => (int)$row['guests_count'],
            'status' => $row['status'],
        ];
    }
    $arrStmt->close();

    $depStmt = $conn->prepare("
        SELECT b.id, b.guest_name, b.accommodation_name, b.status, r.room_number
        FROM bookings b
        LEFT JOIN rooms r ON r.id = b.room_id
        WHERE b.check_out = ? AND b.status IN ('Checked In', 'Confirmed')
        ORDER BY b.id ASC
        LIMIT 25
    ");
    $depStmt->bind_param('s', $targetDate);
    $depStmt->execute();
    $depRes = $depStmt->get_result();
    $departures = [];
    while ($row = $depRes->fetch_assoc()) {
        $departures[] = [
            'booking_ref' => 'REF-' . str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT),
            'guest_name' => $row['guest_name'],
            'accommodation' => $row['accommodation_name'],
            'room_number' => $row['room_number'] ?: null,
            'status' => $row['status'],
        ];
    }
    $depStmt->close();

    $inHouseGuests = [];
    $overdueCheckouts = [];
    $todayIso = date('Y-m-d');
    $inHouseRes = $conn->query("
        SELECT b.id, b.guest_name, b.accommodation_name, b.check_in, b.check_out, b.guests_count, b.balance_status,
               r.room_number
        FROM bookings b
        LEFT JOIN rooms r ON r.id = b.room_id
        WHERE b.status = 'Checked In'
        ORDER BY b.check_out ASC, b.id ASC
        LIMIT 15
    ");
    if ($inHouseRes) {
        while ($row = $inHouseRes->fetch_assoc()) {
            $item = [
                'booking_ref' => 'REF-' . str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT),
                'guest_name' => $row['guest_name'],
                'accommodation' => $row['accommodation_name'],
                'room_number' => $row['room_number'] ?: null,
                'check_in' => $row['check_in'],
                'check_out' => $row['check_out'],
                'guests' => (int)$row['guests_count'],
                'balance_status' => (string)($row['balance_status'] ?? ''),
            ];
            $inHouseGuests[] = $item;
            if (!empty($row['check_out']) && $row['check_out'] < $todayIso) {
                $overdueCheckouts[] = $item;
            }
        }
    }

    $checkedInCount = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'Checked In'")->fetch_row()[0] ?? 0);
    $pendingBookings = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE status IN ('Pending', 'Pending Payment')")->fetch_row()[0] ?? 0);

    return [
        'success' => true,
        'date' => $targetDate,
        'arrivals_count' => count($arrivals),
        'arrivals' => $arrivals,
        'departures_count' => count($departures),
        'departures' => $departures,
        'currently_checked_in_total' => $checkedInCount,
        'in_house_guests' => $inHouseGuests,
        'overdue_checkouts_count' => count($overdueCheckouts),
        'overdue_checkouts' => $overdueCheckouts,
        'pending_bookings_total' => $pendingBookings,
        'checkin_desk_url' => $routes['checkin'],
        'reservations_url' => $routes['reservations'],
    ];
}

/**
 * Staff Tool 2: Accurate occupancy summary across ALL physical rooms and room_types.
 */
function sf_staff_tool_occupancy_summary(mysqli $conn, ?string $startDate = null, ?string $endDate = null): array
{
    $start = ($startDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) ? $startDate : date('Y-m-d');
    $end = ($endDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) && $endDate > $start)
        ? $endDate
        : date('Y-m-d', strtotime($start . ' +1 day'));

    $totalRooms = (int)($conn->query("SELECT COUNT(*) FROM rooms")->fetch_row()[0] ?? 0);
    $maintenanceRooms = (int)($conn->query("SELECT COUNT(*) FROM rooms WHERE LOWER(COALESCE(status, '')) = 'maintenance'")->fetch_row()[0] ?? 0);
    $usableRooms = max(0, $totalRooms - $maintenanceRooms);

    $maintenanceList = [];
    $mRes = $conn->query("SELECT room_number, name, type FROM rooms WHERE LOWER(COALESCE(status, '')) = 'maintenance' ORDER BY room_number ASC LIMIT 15");
    if ($mRes) {
        while ($mRow = $mRes->fetch_assoc()) {
            $maintenanceList[] = [
                'room_number' => (string)($mRow['room_number'] ?? ''),
                'name' => (string)($mRow['name'] ?? ''),
                'display_type' => sf_staff_human_room_label((string)($mRow['type'] ?? '')),
            ];
        }
    }

    $pdo = getPdoConnection();
    $roomBreakdown = [];
    $totalAvailable = 0;

    $physicalByType = [];
    $pRes = $conn->query("
        SELECT type,
               SUM(CASE WHEN LOWER(COALESCE(status, '')) <> 'maintenance' THEN 1 ELSE 0 END) AS usable_cnt
        FROM rooms
        GROUP BY type
    ");
    if ($pRes) {
        while ($pRow = $pRes->fetch_assoc()) {
            $physicalByType[(string)$pRow['type']] = (int)$pRow['usable_cnt'];
        }
    }

    $rtRes = $conn->query("SELECT id, name FROM room_types ORDER BY id ASC");
    $coveredSlugs = [];
    if ($rtRes) {
        while ($rt = $rtRes->fetch_assoc()) {
            $rtId = (int)$rt['id'];
            $slug = (string)$rt['name'];
            $coveredSlugs[$slug] = true;
            $usableForType = $physicalByType[$slug] ?? 0;
            if ($usableForType <= 0 && !isset($physicalByType[$slug])) {
                continue;
            }
            $availableForType = getAvailableRooms($pdo, $rtId, $start, $end);
            $totalAvailable += $availableForType;
            $roomBreakdown[] = [
                'room_type' => $slug,
                'display_name' => sf_staff_human_room_label($slug),
                'usable_units' => $usableForType,
                'available_units' => $availableForType,
            ];
        }
    }

    // Account for any physical rooms whose type slug is absent from room_types
    foreach ($physicalByType as $slug => $usableCnt) {
        if (!isset($coveredSlugs[$slug]) && $usableCnt > 0) {
            $totalAvailable += $usableCnt;
            $roomBreakdown[] = [
                'room_type' => $slug,
                'display_name' => sf_staff_human_room_label($slug),
                'usable_units' => $usableCnt,
                'available_units' => $usableCnt,
            ];
        }
    }

    $occupiedUnits = max(0, $usableRooms - $totalAvailable);
    $occupancyPct = $usableRooms > 0 ? round(($occupiedUnits / $usableRooms) * 100, 1) : 0.0;

    return [
        'success' => true,
        'start_date' => $start,
        'end_date' => $end,
        'total_physical_rooms' => $totalRooms,
        'maintenance_rooms' => $maintenanceRooms,
        'maintenance_list' => $maintenanceList,
        'usable_rooms' => $usableRooms,
        'booked_or_occupied_units' => $occupiedUnits,
        'available_units' => $totalAvailable,
        'occupancy_rate_percent' => $occupancyPct,
        'by_room_type' => $roomBreakdown,
        'calendar_url' => 'admin_calendar',
    ];
}

/**
 * Staff Tool 3: Pending payments, refund requests, discount IDs, split Contact Inquiries vs Live Support Chats, and pending reviews.
 */
function sf_staff_tool_pending_actions(mysqli $conn, string $role = 'receptionist'): array
{
    $routes = sf_staff_assistant_routes($role);
    $pendingPayments = (int)($conn->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetch_row()[0] ?? 0);
    $pendingRefunds = (int)($conn->query("SELECT COUNT(*) FROM refund_requests WHERE status = 'requested'")->fetch_row()[0] ?? 0);
    $pendingDiscounts = (int)($conn->query("SELECT COUNT(*) FROM booking_discounts WHERE state = 'Awaiting ID verification'")->fetch_row()[0] ?? 0);
    $pendingBookings = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE status IN ('Pending', 'Pending Payment')")->fetch_row()[0] ?? 0);

    $contactCond = sf_staff_channel_sql($conn, 'contact');
    $liveCond = sf_staff_channel_sql($conn, 'live_chat');

    $unreadContactInquiries = (int)($conn->query("SELECT COUNT(*) FROM inquiries i WHERE i.status = 'Unread' AND {$contactCond}")->fetch_row()[0] ?? 0);
    $unreadLiveChats = (int)($conn->query("SELECT COUNT(*) FROM inquiries i WHERE i.status = 'Unread' AND {$liveCond}")->fetch_row()[0] ?? 0);
    $openLiveChats = (int)($conn->query("SELECT COUNT(*) FROM inquiries i WHERE i.status <> 'Resolved' AND {$liveCond}")->fetch_row()[0] ?? 0);

    $activeLiveSessions = 0;
    if (sf_staff_table_exists($conn, 'chatbot_conversations')) {
        $activeLiveSessions = (int)($conn->query("SELECT COUNT(*) FROM chatbot_conversations WHERE live_chat_active = 1")->fetch_row()[0] ?? 0);
    }

    $pendingReviews = 0;
    if (sf_staff_table_exists($conn, 'reviews')) {
        $pendingReviews = (int)($conn->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0")->fetch_row()[0] ?? 0);
    }

    return [
        'success' => true,
        'pending_payments_count' => $pendingPayments,
        'pending_payments_url' => $routes['payments'],
        'pending_refund_requests_count' => $pendingRefunds,
        'pending_refunds_url' => $routes['refunds'],
        'pending_discount_verifications_count' => $pendingDiscounts,
        'pending_discounts_url' => $routes['discounts'],
        'pending_bookings_count' => $pendingBookings,
        'reservations_url' => $routes['reservations'],
        'unread_inquiries_count' => $unreadContactInquiries,
        'inquiries_url' => $routes['inquiries'],
        'unread_live_chats_count' => $unreadLiveChats,
        'open_live_chats_count' => $openLiveChats,
        'active_live_sessions_count' => $activeLiveSessions,
        'total_chatbot_handoffs' => $openLiveChats,
        'chatbot_admin_url' => $routes['live_support'],
        'live_support_url' => $routes['live_support'],
        'pending_reviews_count' => $pendingReviews,
        'reviews_url' => $routes['reviews'],
    ];
}

/**
 * Staff Tool 4: Revenue summary (strictly restricted to 'admin' role).
 */
function sf_staff_tool_revenue_summary(mysqli $conn, string $role, int $days = 30): array
{
    if ($role !== 'admin') {
        return [
            'success' => false,
            'error' => 'Access Denied: Revenue and financial totals are restricted to Administrator accounts.',
        ];
    }

    $days = max(1, min(365, $days));
    $sinceDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END), 0) AS verified_revenue,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_revenue,
            COALESCE(SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END), 0) AS refunded_amount,
            COUNT(*) AS payment_records
        FROM payments
        WHERE paid_at >= ?
    ");
    $stmt->bind_param('s', $sinceDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $overdueBalancesCount = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE balance_status IN ('overdue', 'unpaid', 'pending') AND LOWER(COALESCE(status, '')) NOT IN ('cancelled', 'canceled', 'checked out')")->fetch_row()[0] ?? 0);

    return [
        'success' => true,
        'period_days' => $days,
        'since' => $sinceDate,
        'verified_revenue_php' => (float)($row['verified_revenue'] ?? 0),
        'pending_verification_php' => (float)($row['pending_revenue'] ?? 0),
        'refunded_total_php' => (float)($row['refunded_amount'] ?? 0),
        'payment_records_count' => (int)($row['payment_records'] ?? 0),
        'overdue_or_pending_balances_count' => $overdueBalancesCount,
        'reports_url' => 'admin_reports',
    ];
}

/**
 * Staff Tool 5: Booking & Guest lookup across bookings and rooms.
 */
function sf_staff_tool_booking_lookup(mysqli $conn, string $query, string $role = 'receptionist'): array
{
    $routes = sf_staff_assistant_routes($role);
    $raw = trim($query);
    $idMatch = 0;
    if (preg_match('/^(?:ref[-\s]*)?#?(\d{1,7})$/i', $raw, $m)) {
        $idMatch = (int)$m[1];
    }

    $sql = "
        SELECT b.id, b.guest_name, b.guest_email, b.guest_phone, b.accommodation_name,
               b.check_in, b.check_out, b.guests_count, b.status, b.payment_status,
               b.balance_status, b.promo_code, r.room_number
        FROM bookings b
        LEFT JOIN rooms r ON r.id = b.room_id
    ";

    $matches = [];
    if ($idMatch > 0) {
        $stmt = $conn->prepare($sql . " WHERE b.id = ? LIMIT 5");
        $stmt->bind_param('i', $idMatch);
    } else {
        $cleanTerm = trim((string)preg_replace('/\s+/', ' ', $raw));
        if ($cleanTerm === '') {
            return ['success' => true, 'query' => $raw, 'matches' => [], 'routes' => $routes];
        }
        $like = '%' . $cleanTerm . '%';
        $stmt = $conn->prepare($sql . "
            WHERE b.guest_name LIKE ?
               OR b.guest_email LIKE ?
               OR b.guest_phone LIKE ?
               OR b.payment_reference LIKE ?
               OR CONCAT('REF-', LPAD(b.id, 3, '0')) LIKE ?
            ORDER BY b.check_in DESC, b.id DESC
            LIMIT 8
        ");
        $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
    }

    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $matches[] = [
                'booking_id' => (int)$row['id'],
                'booking_ref' => 'REF-' . str_pad((string)$row['id'], 3, '0', STR_PAD_LEFT),
                'guest_name' => (string)$row['guest_name'],
                'guest_email_masked' => mask_email((string)$row['guest_email']),
                'accommodation' => (string)$row['accommodation_name'],
                'room_number' => $row['room_number'] ?: null,
                'check_in' => (string)$row['check_in'],
                'check_out' => (string)$row['check_out'],
                'guests_count' => (int)$row['guests_count'],
                'status' => (string)$row['status'],
                'payment_status' => (string)($row['payment_status'] ?? 'pending'),
                'balance_status' => (string)($row['balance_status'] ?? ''),
                'promo_code' => (string)($row['promo_code'] ?? ''),
            ];
        }
        $stmt->close();
    }

    return [
        'success' => true,
        'query' => $raw,
        'matches' => $matches,
        'routes' => $routes,
    ];
}

/**
 * Staff Tool 6: Full room inventory, rates, maintenance rooms, pricing rules, and active promotions.
 */
function sf_staff_tool_rooms_and_promotions(mysqli $conn, string $role = 'receptionist'): array
{
    $routes = sf_staff_assistant_routes($role);
    $roomTypes = [];
    $res = $conn->query("
        SELECT r.type,
               COUNT(*) AS total_units,
               SUM(CASE WHEN LOWER(COALESCE(r.status, '')) = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_units,
               MAX(r.price_per_night) AS room_price,
               MAX(rt.price) AS catalog_price
        FROM rooms r
        LEFT JOIN room_types rt ON rt.name = r.type
        GROUP BY r.type
        ORDER BY MIN(r.id) ASC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $price = (float)($row['room_price'] > 0 ? $row['room_price'] : $row['catalog_price']);
            $roomTypes[] = [
                'type' => (string)$row['type'],
                'display_name' => sf_staff_human_room_label((string)$row['type']),
                'total_units' => (int)$row['total_units'],
                'maintenance_units' => (int)$row['maintenance_units'],
                'price_per_night' => $price,
            ];
        }
    }

    $maintenanceRooms = [];
    $mRes = $conn->query("SELECT room_number, name, type FROM rooms WHERE LOWER(COALESCE(status, '')) = 'maintenance' ORDER BY room_number ASC LIMIT 15");
    if ($mRes) {
        while ($row = $mRes->fetch_assoc()) {
            $maintenanceRooms[] = [
                'room_number' => (string)$row['room_number'],
                'name' => (string)$row['name'],
                'display_type' => sf_staff_human_room_label((string)$row['type']),
            ];
        }
    }

    $promotions = [];
    if (sf_staff_table_exists($conn, 'promotions')) {
        $pRes = $conn->query("
            SELECT title, code, discount_type, discount_value, valid_from, valid_until
            FROM promotions
            WHERE is_active = 1 AND (valid_until IS NULL OR valid_until >= CURDATE())
            ORDER BY valid_until ASC, id DESC
            LIMIT 8
        ");
        if ($pRes) {
            while ($row = $pRes->fetch_assoc()) {
                $promotions[] = [
                    'title' => (string)$row['title'],
                    'code' => (string)($row['code'] ?? ''),
                    'discount_type' => (string)$row['discount_type'],
                    'discount_value' => (float)$row['discount_value'],
                    'valid_from' => (string)($row['valid_from'] ?? ''),
                    'valid_until' => (string)($row['valid_until'] ?? ''),
                ];
            }
        }
    }

    $pricingRulesCount = 0;
    if (sf_staff_table_exists($conn, 'pricing_rules')) {
        $pricingRulesCount = (int)($conn->query("SELECT COUNT(*) FROM pricing_rules WHERE is_active = 1")->fetch_row()[0] ?? 0);
    }

    return [
        'success' => true,
        'room_types' => $roomTypes,
        'maintenance_rooms' => $maintenanceRooms,
        'active_promotions' => $promotions,
        'active_pricing_rules_count' => $pricingRulesCount,
        'routes' => $routes,
    ];
}

/**
 * Staff Tool 7: Guest communications summary (split Live Support Chats vs Contact Us Inquiries + Reviews).
 */
function sf_staff_tool_guest_comms_summary(mysqli $conn, string $focus = 'all', string $role = 'receptionist'): array
{
    $routes = sf_staff_assistant_routes($role);
    $liveCond = sf_staff_channel_sql($conn, 'live_chat');
    $contactCond = sf_staff_channel_sql($conn, 'contact');

    $waitingLiveChats = [];
    $lRes = $conn->query("
        SELECT i.id, i.guest_name, i.status, i.created_at
        FROM inquiries i
        WHERE {$liveCond} AND i.status <> 'Resolved'
        ORDER BY (i.status = 'Unread') DESC, i.id DESC
        LIMIT 5
    ");
    if ($lRes) {
        while ($row = $lRes->fetch_assoc()) {
            $waitingLiveChats[] = [
                'id' => (int)$row['id'],
                'guest_name' => (string)$row['guest_name'],
                'status' => (string)$row['status'],
                'created_at' => (string)$row['created_at'],
            ];
        }
    }

    $waitingInquiries = [];
    $cRes = $conn->query("
        SELECT i.id, i.guest_name, i.subject, i.status, i.created_at
        FROM inquiries i
        WHERE {$contactCond} AND i.status <> 'Resolved'
        ORDER BY (i.status = 'Unread') DESC, i.id DESC
        LIMIT 5
    ");
    if ($cRes) {
        while ($row = $cRes->fetch_assoc()) {
            $waitingInquiries[] = [
                'id' => (int)$row['id'],
                'guest_name' => (string)$row['guest_name'],
                'subject' => (string)$row['subject'],
                'status' => (string)$row['status'],
                'created_at' => (string)$row['created_at'],
            ];
        }
    }

    $reviewsSummary = [
        'total_reviews' => 0,
        'average_rating' => 0.0,
        'pending_moderation_count' => 0,
        'latest' => [],
    ];
    if (sf_staff_table_exists($conn, 'reviews')) {
        $agg = $conn->query("
            SELECT COUNT(*) AS total_cnt,
                   COALESCE(AVG(rating), 0) AS avg_rating,
                   SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) AS pending_cnt
            FROM reviews
        ");
        if ($agg && ($rRow = $agg->fetch_assoc())) {
            $reviewsSummary['total_reviews'] = (int)$rRow['total_cnt'];
            $reviewsSummary['average_rating'] = round((float)$rRow['avg_rating'], 1);
            $reviewsSummary['pending_moderation_count'] = (int)$rRow['pending_cnt'];
        }
        $latestRev = $conn->query("SELECT guest_name, rating, is_approved, created_at FROM reviews ORDER BY id DESC LIMIT 3");
        if ($latestRev) {
            while ($rev = $latestRev->fetch_assoc()) {
                $reviewsSummary['latest'][] = [
                    'guest_name' => (string)$rev['guest_name'],
                    'rating' => (int)$rev['rating'],
                    'approved' => !empty($rev['is_approved']),
                    'created_at' => (string)$rev['created_at'],
                ];
            }
        }
    }

    return [
        'success' => true,
        'focus' => $focus,
        'open_live_chats_count' => count($waitingLiveChats),
        'waiting_live_chats' => $waitingLiveChats,
        'open_contact_inquiries_count' => count($waitingInquiries),
        'waiting_contact_inquiries' => $waitingInquiries,
        'reviews' => $reviewsSummary,
        'routes' => $routes,
    ];
}

/**
 * Staff Tool 8: Admin-only Activity Logs, Security Alerts, and Active Staff Sessions.
 */
function sf_staff_tool_admin_audit_summary(mysqli $conn, string $role): array
{
    if ($role !== 'admin') {
        return [
            'success' => false,
            'error' => 'Access Denied: Staff activity logs, security logs, and active sessions are restricted to Administrator accounts.',
        ];
    }

    $activeSessions = [];
    if (sf_staff_table_exists($conn, 'admin_sessions')) {
        $sRes = $conn->query("
            SELECT user_type, username, ip_address, last_seen_at
            FROM admin_sessions
            WHERE revoked_at IS NULL
            ORDER BY last_seen_at DESC
            LIMIT 8
        ");
        if ($sRes) {
            while ($row = $sRes->fetch_assoc()) {
                $activeSessions[] = [
                    'user_type' => (string)$row['user_type'],
                    'username' => (string)$row['username'],
                    'ip_address' => (string)$row['ip_address'],
                    'last_seen_at' => (string)$row['last_seen_at'],
                ];
            }
        }
    }

    $recentActivity = [];
    if (sf_staff_table_exists($conn, 'activity_logs')) {
        $aRes = $conn->query("SELECT admin_username, action, details, created_at FROM activity_logs ORDER BY id DESC LIMIT 5");
        if ($aRes) {
            while ($row = $aRes->fetch_assoc()) {
                $recentActivity[] = [
                    'username' => (string)$row['admin_username'],
                    'action' => (string)$row['action'],
                    'details' => (string)($row['details'] ?? ''),
                    'created_at' => (string)$row['created_at'],
                ];
            }
        }
    }

    $security24hCount = 0;
    if (sf_staff_table_exists($conn, 'security_logs')) {
        $since = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $secStmt = $conn->prepare("SELECT COUNT(*) FROM security_logs WHERE created_at >= ?");
        if ($secStmt) {
            $secStmt->bind_param('s', $since);
            $secStmt->execute();
            $security24hCount = (int)($secStmt->get_result()->fetch_row()[0] ?? 0);
            $secStmt->close();
        }
    }

    return [
        'success' => true,
        'active_sessions_count' => count($activeSessions),
        'active_sessions' => $activeSessions,
        'recent_activity' => $recentActivity,
        'security_events_24h' => $security24hCount,
        'logs_url' => 'admin_logs',
        'sessions_url' => 'admin_sessions',
    ];
}
