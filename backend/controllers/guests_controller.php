<?php
require_once __DIR__ . '/../helpers/pii_masker.php';

function sfbc_guests_initials(string $name): string
{
    $initials = '';
    foreach (explode(' ', $name) as $part) {
        if ($part !== '') {
            $initials .= strtoupper($part[0]);
        }
    }
    return substr($initials, 0, 2);
}

function sfbc_guests_avatar_colors(string $type): array
{
    return match ($type) {
        'VIP Member'      => ['#FDF4EC', '#7C533C'],
        'Corporate'       => ['#E3F2FD', '#1565C0'],
        'Returning Guest' => ['#F3E8FF', '#7C3AED'],
        default           => ['#F3F4F6', '#4B5563'],
    };
}

function sfbc_guests_type_badge_html(string $type): string
{
    $styles = match ($type) {
        'VIP Member'      => 'background:#FDF4EC;color:#7C533C;border:1px solid #e8d5c0;',
        'Corporate'       => 'background:#E3F2FD;color:#1565C0;border:1px solid #bbdefb;',
        'Returning Guest' => 'background:#F3E8FF;color:#7C3AED;border:1px solid #ddd6fe;',
        default           => 'background:#F3F4F6;color:#4B5563;border:1px solid #e5e7eb;',
    };
    return "<span style='{$styles} padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>" . htmlspecialchars($type) . '</span>';
}

function sfbc_guests_split_name(string $name): array
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $firstName = $parts[0] ?? '';
    $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
    return [$firstName, $lastName];
}

function sfbc_guests_booking_room_type(array $booking): string
{
    $roomType = trim((string)($booking['room_type'] ?? ''));
    if ($roomType !== '') {
        return $roomType;
    }

    $accommodationName = strtolower((string)($booking['accommodation_name'] ?? ''));
    if (strpos($accommodationName, 'beachview duplex') !== false) {
        return 'beachview_duplex';
    }
    if (strpos($accommodationName, 'seaview duplex') !== false) {
        return 'seaview_duplex';
    }
    if (strpos($accommodationName, 'beach villa') !== false) {
        return 'beach_villa';
    }
    if (strpos($accommodationName, 'standard family') !== false) {
        return 'standard_king';
    }

    return 'standard_room';
}

/**
 * Prepares the guest directory summary, per-guest booking history, and tier KPI counts.
 */
function sfbc_handle_guests_page(mysqli $conn): array
{
    $guestsResult = $conn->query("
        SELECT
            guest_name,
            guest_email,
            guest_type,
            COUNT(id) AS total_bookings,
            MIN(check_in) AS first_visit,
            MAX(check_in) AS last_visit,
            SUM(CASE WHEN status = 'Cancelled' THEN 0 ELSE 1 END) AS confirmed_bookings
        FROM bookings
        GROUP BY guest_email, guest_name
        ORDER BY guest_name ASC
    ");

    $rawGuests = [];
    if ($guestsResult) {
        while ($row = $guestsResult->fetch_assoc()) {
            $rawGuests[] = $row;
        }
    }

    $bookingsResult = $conn->query("
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

    $bookingsByEmail = [];
    if ($bookingsResult) {
        while ($booking = $bookingsResult->fetch_assoc()) {
            $key = strtolower(trim((string)($booking['guest_email'] ?? $booking['guest_name'])));
            $bookingsByEmail[$key][] = $booking;
        }
    }

    $vipCount = 0;
    $corporateCount = 0;
    $returningCount = 0;
    $firstVisitCount = 0;
    $guestRows = [];

    foreach ($rawGuests as $row) {
        $type = (string)($row['guest_type'] ?? 'First Visit');
        if ($type === 'VIP Member') {
            $vipCount++;
        } elseif ($type === 'Corporate') {
            $corporateCount++;
        } elseif ($type === 'Returning Guest') {
            $returningCount++;
        } else {
            $firstVisitCount++;
        }

        $name = (string)$row['guest_name'];
        $email = (string)($row['guest_email'] ?? '');
        $total = (int)$row['total_bookings'];
        $lastVisit = (string)($row['last_visit'] ?? '');
        $firstVisit = (string)($row['first_visit'] ?? '');
        $initials = sfbc_guests_initials($name);
        [$avatarBg, $avatarText] = sfbc_guests_avatar_colors($type);

        $key = strtolower(trim($email !== '' ? $email : $name));
        $bookings = $bookingsByEmail[$key] ?? [];

        $totalSpend = 0.0;
        foreach ($bookings as $bk) {
            if ($bk['status'] !== 'Cancelled' && !empty($bk['price_per_night'])) {
                $nights = max(1, (int)$bk['nights']);
                $totalSpend += $nights * (float)$bk['price_per_night'];
            }
        }

        $guestPhone = '';
        $guestCountry = '';
        $guestSpecialRequests = '';
        if (!empty($bookings)) {
            $guestPhone = (string)($bookings[0]['guest_phone'] ?? '');
            $guestCountry = (string)($bookings[0]['guest_country'] ?? '');
            $guestSpecialRequests = (string)($bookings[0]['guest_special_requests'] ?? '');
        }

        [$guestFirstName, $guestLastName] = sfbc_guests_split_name($name);
        $recentBooking = $bookings[0] ?? [];
        $recentRoomType = sfbc_guests_booking_room_type($recentBooking);
        $bookAgainUrl = 'book?' . http_build_query([
            'room_type'  => $recentRoomType,
            'first_name' => $guestFirstName,
            'last_name'  => $guestLastName,
            'email'      => $email,
            'phone'      => $guestPhone,
            'country'    => $guestCountry,
            'comments'   => $guestSpecialRequests,
        ]);

        $mappedBookings = array_map(static function (array $bk): array {
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
        }, $bookings);

        $profileData = json_encode([
            'name'             => $name,
            'email'            => $email,
            'masked_email'     => mask_email($email),
            'phone'            => $guestPhone,
            'masked_phone'     => mask_phone($guestPhone),
            'country'          => $guestCountry,
            'special_requests' => $guestSpecialRequests,
            'type'             => $type,
            'total'            => $total,
            'first_visit'      => $firstVisit,
            'last_visit'       => $lastVisit,
            'spend'            => $totalSpend,
            'initials'         => $initials,
            'avatarBg'         => $avatarBg,
            'avatarText'       => $avatarText,
            'book_again_url'   => $bookAgainUrl,
            'bookings'         => $mappedBookings,
        ]);

        $guestRows[] = [
            'name'        => $name,
            'email'       => $email,
            'maskedEmail' => mask_email($email),
            'type'        => $type,
            'typeBadge'   => sfbc_guests_type_badge_html($type),
            'total'       => $total,
            'lastVisit'   => $lastVisit,
            'initials'    => $initials,
            'avatarBg'    => $avatarBg,
            'avatarText'  => $avatarText,
            'profileJson' => $profileData ?: '{}',
        ];
    }

    $frontendDir = __DIR__ . '/../../frontend';

    return [
        'guestRows'       => $guestRows,
        'totalGuests'     => count($rawGuests),
        'vipCount'        => $vipCount,
        'corporateCount'  => $corporateCount,
        'returningCount'  => $returningCount,
        'firstVisitCount' => $firstVisitCount,
        'versions'        => [
            'adminCss'     => filemtime($frontendDir . '/assets/css/admin.css'),
            'dashboardCss' => filemtime($frontendDir . '/assets/css/dashboard.css'),
            'pageCss'      => file_exists($frontendDir . '/assets/css/pages/guests.css')
                ? filemtime($frontendDir . '/assets/css/pages/guests.css')
                : 1,
            'pageJs'       => file_exists($frontendDir . '/assets/js/pages/guests.js')
                ? filemtime($frontendDir . '/assets/js/pages/guests.js')
                : 1,
        ],
    ];
}
