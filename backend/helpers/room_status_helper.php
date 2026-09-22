<?php

function sf_get_checked_in_room_ids(mysqli $conn): array
{
    $room_ids = [];
    $result = $conn->query("SELECT DISTINCT room_id FROM bookings WHERE status = 'Checked In' AND room_id IS NOT NULL");

    if (!$result) {
        return $room_ids;
    }

    while ($row = $result->fetch_assoc()) {
        $room_ids[(int)$row['room_id']] = true;
    }

    return $room_ids;
}

function sf_get_reserved_room_ids(mysqli $conn): array
{
    $room_ids = [];
    $result = $conn->query("SELECT DISTINCT room_id FROM bookings WHERE room_id IS NOT NULL AND status IN ('Pending', 'Pending Payment', 'Confirmed')");

    if (!$result) {
        return $room_ids;
    }

    while ($row = $result->fetch_assoc()) {
        $room_ids[(int)$row['room_id']] = true;
    }

    return $room_ids;
}

function sf_room_has_checked_in_booking(mysqli $conn, int $room_id): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM bookings WHERE room_id = ? AND status = 'Checked In' LIMIT 1");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result && $result->num_rows > 0;
}

function sf_resolve_room_display_status(array $room, array $occupied_room_ids, array $reserved_room_ids = []): string
{
    if (($room['status'] ?? '') === 'maintenance') {
        return 'maintenance';
    }

    $room_id = isset($room['id']) ? (int)$room['id'] : 0;
    if ($room_id > 0 && isset($occupied_room_ids[$room_id])) {
        return 'occupied';
    }

    if ($room_id > 0 && isset($reserved_room_ids[$room_id])) {
        return 'reserved';
    }

    return 'ready';
}

function sf_room_status_label(string $status): string
{
    switch ($status) {
        case 'occupied':
            return 'Occupied';
        case 'maintenance':
            return 'Maintenance';
        case 'reserved':
            return 'Reserved';
        default:
            return 'Available';
    }
}

/**
 * Handles dynamic rate calculations for Santa Fe Beach Club.
 * Computes night-by-night rates factoring in weekend surcharges, seasonal pricing rules, and coupon discounts.
/**
 * Calculate dynamic stay pricing with night-by-night breakdown, weekend surcharges, seasonal pricing, extra guest fees, and promotions.
 */
function calculateStayPricing(mysqli $conn, string $roomType, string $checkIn, string $checkOut, ?string $promoCode = null, int $guests = 2, int $extraAdultsOverride = -1): array
{
    $start = new DateTime($checkIn);
    $end = new DateTime($checkOut);

    if ($start >= $end) {
        $nights = 1;
    } else {
        $interval = $start->diff($end);
        $nights = max(1, (int)$interval->days);
    }

    // 1. Fetch room base price
    $basePrice = 2900.00;
    $stmt = $conn->prepare("SELECT price_per_night FROM rooms WHERE type = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $roomType);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $basePrice = (float)$res->fetch_assoc()['price_per_night'];
        }
        $stmt->close();
    }

    // 2. Fetch active pricing rules
    $activeRules = [];
    $rulesQuery = $conn->query("
        SELECT * FROM pricing_rules 
        WHERE is_active = 1 
          AND (room_type = 'all' OR room_type = '" . $conn->real_escape_string($roomType) . "')
    ");
    if ($rulesQuery) {
        $activeRules = $rulesQuery->fetch_all(MYSQLI_ASSOC);
    }

    // 3. Calculate night by night
    $currentDate = clone $start;
    $nightlyBreakdown = [];
    $subtotal = 0.00;
    $weekendSurchargeTotal = 0.00;
    $seasonalAdjustmentTotal = 0.00;

    for ($i = 0; $i < $nights; $i++) {
        $dateStr = $currentDate->format('Y-m-d');
        $dayOfWeek = (int)$currentDate->format('w'); // 0 = Sun, 5 = Fri, 6 = Sat
        $nightPrice = $basePrice;
        $appliedNotes = [];

        foreach ($activeRules as $rule) {
            if ($rule['rule_type'] === 'weekend') {
                $days = array_map('intval', explode(',', $rule['days_of_week'] ?? '5,6,0'));
                if (in_array($dayOfWeek, $days, true)) {
                    $adj = (float)$rule['adjustment_value'];
                    if ($rule['adjustment_type'] === 'percent') {
                        $diff = $basePrice * ($adj / 100);
                    } else {
                        $diff = $adj;
                    }
                    $nightPrice += $diff;
                    $weekendSurchargeTotal += $diff;
                    $appliedNotes[] = $rule['title'] . ' (+' . ($rule['adjustment_type'] === 'percent' ? $adj . '%' : '₱' . number_format($adj, 2)) . ')';
                }
            } elseif ($rule['rule_type'] === 'date_range') {
                $rStart = $rule['start_date'] ?? '';
                $rEnd = $rule['end_date'] ?? '';
                if ($rStart && $rEnd && $dateStr >= $rStart && $dateStr <= $rEnd) {
                    $adj = (float)$rule['adjustment_value'];
                    if ($rule['adjustment_type'] === 'percent') {
                        $diff = $basePrice * ($adj / 100);
                    } else {
                        $diff = $adj;
                    }
                    $nightPrice += $diff;
                    $seasonalAdjustmentTotal += $diff;
                    $appliedNotes[] = $rule['title'] . ' (+' . ($rule['adjustment_type'] === 'percent' ? $adj . '%' : '₱' . number_format($adj, 2)) . ')';
                }
            }
        }

        $nightPrice = max(0, $nightPrice);
        $subtotal += $nightPrice;
        $nightlyBreakdown[] = [
            'date' => $dateStr,
            'day_name' => $currentDate->format('D'),
            'price' => $nightPrice,
            'notes' => implode(', ', $appliedNotes),
        ];

        $currentDate->modify('+1 day');
    }

    // 3.5 Extra Person / Extra Adult calculation. Each nightly room rate
    // includes the room's standard adult capacity; extra adults are charged
    // once per stay, not once per night.
    $extraRates = [
        'beachview_duplex' => 1000.00,
        'seaview_duplex'   => 1000.00,
        'beach_villa'      => 1000.00,
        'standard_room'    => 700.00,
        'standard_king'    => 700.00,
    ];
    // Base pax included in the nightly rate (no extra charge up to this count)
    $includedCapacity = [
        'beachview_duplex' => 2, // 6,900/night includes 2 pax
        'seaview_duplex'   => 2, // 7,900/night includes 2 pax
        'beach_villa'      => 4, // 7,900/night includes 4 pax
        'standard_king'    => 4, // 4,300/night includes 4 pax
        'standard_room'    => 2, // 2,900/night includes 2 pax
    ];
    $extraRatePerAdult = $extraRates[$roomType] ?? 700.00;
    // If caller passed an explicit extra-adult count (from rooms.php selector),
    // use it directly. Otherwise fall back to capacity-based math.
    if ($extraAdultsOverride >= 0) {
        $extraAdults = $extraAdultsOverride;
    } else {
        $baseCapacity = $includedCapacity[$roomType] ?? 2;
        $extraAdults  = max(0, $guests - $baseCapacity);
    }
    $extraPersonTotal = $extraAdults * $extraRatePerAdult;
    $subtotal += $extraPersonTotal;

    // 4. Promo Code Validation & Discount Application
    $discountAmount = 0.00;
    $promoDetails = null;
    $promoError = null;

    if (!empty($promoCode)) {
        $codeClean = strtoupper(trim($promoCode));
        $promoStmt = $conn->prepare("
            SELECT * FROM promotions 
            WHERE (UPPER(code) = ? OR UPPER(title) = ?) 
              AND is_active = 1 
              AND valid_from <= CURDATE() 
              AND valid_until >= CURDATE() 
            LIMIT 1
        ");
        if ($promoStmt) {
            $promoStmt->bind_param("ss", $codeClean, $codeClean);
            $promoStmt->execute();
            $promoRes = $promoStmt->get_result();
            if ($promoRes && $promoRes->num_rows > 0) {
                $promoDetails = $promoRes->fetch_assoc();
                $usageStmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE UPPER(COALESCE(promo_code, '')) = ? AND LOWER(COALESCE(status, '')) NOT IN ('cancelled', 'canceled')");
                $usageStmt->bind_param('s', $codeClean);
                $usageStmt->execute();
                $promoDetails['usage_count'] = (int)$usageStmt->get_result()->fetch_row()[0];
                $usageStmt->close();

                if ($promoDetails['usage_limit'] !== null && $promoDetails['usage_count'] >= (int)$promoDetails['usage_limit']) {
                    $promoError = 'This promo code has reached its usage limit.';
                    $promoDetails = null;
                }
            }

            if ($promoDetails) {
                $dtype = $promoDetails['discount_type'];
                $dval = (float)$promoDetails['discount_value'];

                if ($dtype === 'percent') {
                    $discountAmount = ($subtotal * ($dval / 100));
                } else {
                    $discountAmount = min($subtotal, $dval);
                }
            } elseif ($promoError === null) {
                $promoError = 'Invalid or expired promo code.';
            }
            $promoStmt->close();
        }
    }

    $finalTotal = max(0, $subtotal - $discountAmount);
    $depositAmount = $finalTotal / 2; // 50% deposit

    return [
        'base_price_per_night' => $basePrice,
        'nights' => $nights,
        'guests' => $guests,
        'extra_adults' => $extraAdults,
        'extra_rate_per_adult' => $extraRatePerAdult,
        'extra_person_total' => $extraPersonTotal,
        'subtotal' => $subtotal,
        'weekend_surcharge_total' => $weekendSurchargeTotal,
        'seasonal_adjustment_total' => $seasonalAdjustmentTotal,
        'has_dynamic_pricing' => ($weekendSurchargeTotal > 0 || $seasonalAdjustmentTotal > 0 || $extraPersonTotal > 0),
        'promo_code' => $promoDetails ? ($promoDetails['code'] ?: $promoDetails['title']) : null,
        'promo_details' => $promoDetails,
        'promo_error' => $promoError,
        'discount_amount' => $discountAmount,
        'total_amount' => $finalTotal,
        'deposit_amount' => $depositAmount,
        'remaining_balance' => $finalTotal - $depositAmount,
        'nightly_breakdown' => $nightlyBreakdown,
    ];
}
