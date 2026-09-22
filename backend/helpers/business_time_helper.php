<?php

const SF_DEFAULT_PROPERTY_TIMEZONE = 'Asia/Manila';

function sf_normalize_timezone_identifier(?string $timezone): string
{
    $timezone = trim((string)$timezone);
    if ($timezone === '') {
        return SF_DEFAULT_PROPERTY_TIMEZONE;
    }

    static $valid_timezones = null;
    if ($valid_timezones === null) {
        $valid_timezones = array_flip(DateTimeZone::listIdentifiers());
    }

    return isset($valid_timezones[$timezone]) ? $timezone : SF_DEFAULT_PROPERTY_TIMEZONE;
}

function sf_get_supported_property_timezones(): array
{
    return [
        'Asia/Manila' => '(UTC+08:00) Asia/Manila',
        'Asia/Singapore' => '(UTC+08:00) Asia/Singapore',
        'Asia/Tokyo' => '(UTC+09:00) Asia/Tokyo',
        'Australia/Sydney' => '(UTC+10:00) Australia/Sydney',
        'Europe/London' => '(UTC+00:00) Europe/London',
        'America/Los_Angeles' => '(UTC-08:00/-07:00) America/Los_Angeles',
        'America/New_York' => '(UTC-05:00/-04:00) America/New_York',
        'UTC' => '(UTC+00:00) UTC',
    ];
}

function sf_get_property_timezone_setting(mysqli $conn): string
{
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'property_timezone' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return sf_normalize_timezone_identifier($row['setting_value'] ?? null);
}

function sf_get_current_business_datetime(mysqli $conn): DateTimeImmutable
{
    $timezone = new DateTimeZone(sf_get_property_timezone_setting($conn));
    return new DateTimeImmutable('now', $timezone);
}

/**
 * Calculates dynamic cancellation policy & deadline based on length of stay (nights booked).
 *
 * Rules:
 *   - 1 to 3 nights: 48 hours (2 days) before arrival (14:00)
 *   - 4 to 6 nights: 5 days (120 hours) before arrival (14:00)
 *   - 7+ nights:     7 days (168 hours) before arrival (14:00)
 */
function sf_get_cancellation_policy(string $check_in, string $check_out): array
{
    $cin = strtotime($check_in);
    $cout = strtotime($check_out);
    $nights = max(1, (int)round(($cout - $cin) / 86400));

    if ($nights >= 7) {
        $deadline_hours = 7 * 24; // 7 days (168h)
        $policy_name = "Long Stay Policy (7+ nights: 7 days advance notice)";
        $window_label = "7 days";
    } elseif ($nights >= 4) {
        $deadline_hours = 5 * 24; // 5 days (120h)
        $policy_name = "Extended Stay Policy (4–6 nights: 5 days advance notice)";
        $window_label = "5 days";
    } else {
        $deadline_hours = 2 * 24; // 48 hours (2 days)
        $policy_name = "Standard Policy (1–3 nights: 48 hours advance notice)";
        $window_label = "48 hours";
    }

    $checkin_time_str = $check_in . ' 14:00:00';
    $checkin_timestamp = strtotime($checkin_time_str);
    $deadline_timestamp = $checkin_timestamp - ($deadline_hours * 3600);
    $now_timestamp = time();

    $is_expired = ($now_timestamp >= $deadline_timestamp);
    // Guests cancelling before the applicable deadline receive the paid
    // amount back. After the deadline, the booking is non-refundable.
    $refund_percentage = $is_expired ? 0 : 100;
    $hours_left = max(0, round(($deadline_timestamp - $now_timestamp) / 3600));
    $days_left = ceil($hours_left / 24);

    return [
        'nights'             => $nights,
        'deadline_hours'     => $deadline_hours,
        'policy_name'        => $policy_name,
        'window_label'       => $window_label,
        'deadline_timestamp' => $deadline_timestamp,
        'deadline_formatted' => date('M j, Y \a\t g:i A', $deadline_timestamp),
        'is_expired'         => $is_expired,
        'refund_percentage'  => $refund_percentage,
        'hours_left'         => $hours_left,
        'days_left'          => $days_left,
    ];
}

/**
 * Multi-Language (English / Filipino / Cebuano) translation support
 */
function sf_get_current_lang(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'fil', 'ceb'], true)) {
        $_SESSION['app_lang'] = $_GET['lang'];
    }
    return $_SESSION['app_lang'] ?? 'en';
}

function __t(string $key, ?string $lang = null): string
{
    static $dict = [
        'en' => [
            'home' => 'Home',
            'rooms' => 'Rooms',
            'gallery' => 'Gallery',
            'contact' => 'Contact',
            'my_booking' => 'My Booking',
            'book_now' => 'Book Now',
            'hero_title' => 'Escape to Paradise',
            'hero_sub' => 'Book your perfect beach getaway with comfort, luxury, and unforgettable ocean views.',
            'check_in' => 'CHECK-IN',
            'check_out' => 'CHECK-OUT',
            'guests' => 'GUESTS',
            'search' => 'Search',
            'signature_stays' => 'Signature Stays',
            'choose_escape' => 'Choose Your Escape',
            'view_all_rooms' => 'View All Room Details',
            'guest_reviews' => 'Guest Reviews',
            'what_guests_say' => 'What Our Guests Say',
            'from' => 'From',
            'per_night' => '/ night',
        ],
        'fil' => [
            'home' => 'Tahanan',
            'rooms' => 'Mga Kwarto',
            'gallery' => 'Galerya',
            'contact' => 'Makipag-ugnayan',
            'my_booking' => 'Aking Booking',
            'book_now' => 'Mag-book Na',
            'hero_title' => 'Tumakas Patungong Paraiso',
            'hero_sub' => 'I-book ang iyong perpektong bakasyon sa dalampasigan na may ginhawa, karangyaan, at magagandang tanawin ng dagat.',
            'check_in' => 'CHECK-IN',
            'check_out' => 'CHECK-OUT',
            'guests' => 'MGA BISITA',
            'search' => 'Maghanap',
            'signature_stays' => 'Mga Espesyal na Kwarto',
            'choose_escape' => 'Piliin ang Iyong Bakasyon',
            'view_all_rooms' => 'Tingnan ang Lahat ng Kwarto',
            'guest_reviews' => 'Mga Review ng Bisita',
            'what_guests_say' => 'Ano ang Sinasabi ng Aming mga Bisita',
            'from' => 'Magsimula sa',
            'per_night' => '/ gabi',
        ],
        'ceb' => [
            'home' => 'Balay',
            'rooms' => 'Mga Kwarto',
            'gallery' => 'Galeriya',
            'contact' => 'Kontaka Kami',
            'my_booking' => 'Akong Booking',
            'book_now' => 'Mag-book Karon',
            'hero_title' => 'Pahulay sa Paraiso',
            'hero_sub' => 'I-book ang imong perpektong bakasyon sa baybayon nga may kaharuhay, kaluho, ug nindot nga talan-awon sa dagat.',
            'check_in' => 'CHECK-IN',
            'check_out' => 'CHECK-OUT',
            'guests' => 'MGA BISITA',
            'search' => 'Pangitaa',
            'signature_stays' => 'Espesyal nga mga Kwarto',
            'choose_escape' => 'Pilia ang Imong Bakasyon',
            'view_all_rooms' => 'Tan-awa ang Tanang Kwarto',
            'guest_reviews' => 'Mensahe sa mga Bisita',
            'what_guests_say' => 'Unsay Giingon sa Among mga Bisita',
            'from' => 'Sugod sa',
            'per_night' => '/ gabii',
        ]
    ];

    $l = $lang ?? sf_get_current_lang();
    return $dict[$l][$key] ?? ($dict['en'][$key] ?? $key);
}
