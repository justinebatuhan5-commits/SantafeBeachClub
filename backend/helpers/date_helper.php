<?php
declare(strict_types=1);

/** Parse an exact calendar date without PHP's silent date normalization. */
function sf_parse_exact_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if ($date === false) {
        return null;
    }
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }
    return $date->format('Y-m-d') === $value ? $date : null;
}

/**
 * Canonical ordered map of selectable guest estimated arrival (ETA) slots.
 *
 * @return array<string, string>
 */
function sf_booking_eta_catalog(): array
{
    return [
        '14:00' => '02:00 PM (Standard Check-in)',
        '15:00' => '03:00 PM',
        '16:00' => '04:00 PM',
        '17:00' => '05:00 PM',
        '18:00' => '06:00 PM',
        '20:00' => '08:00 PM (Late Arrival)',
        '22:00' => '10:00 PM+ (Late Night Arrival)',
    ];
}

/**
 * Filter allowed ETA slots for a check-in date relative to resort local time.
 *
 * Same-day bookings hide arrival times that have already passed. After 10:00 PM
 * on the check-in day, "10:00 PM+ (Late Night Arrival)" remains available so
 * late-night guests can still complete tonight's reservation.
 *
 * @return array<string, string>
 */
function sf_available_eta_options(string $checkin, DateTimeImmutable $now): array
{
    $catalog = sf_booking_eta_catalog();
    $checkinDate = sf_parse_exact_date($checkin);
    $today = $now->format('Y-m-d');

    if (!$checkinDate || $checkin > $today) {
        return $catalog;
    }

    if ($checkin < $today) {
        return [];
    }

    $available = [];
    foreach ($catalog as $time => $label) {
        $slot = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $checkin . ' ' . $time, $now->getTimezone());
        if ($slot instanceof DateTimeImmutable && $slot > $now) {
            $available[$time] = $label;
        }
    }

    if ($available === [] && isset($catalog['22:00'])) {
        $available['22:00'] = $catalog['22:00'];
    }

    return $available;
}

/**
 * Determine whether the given ETA option is valid for the check-in date/time.
 */
function sf_is_valid_booking_eta(string $eta, string $checkin, DateTimeImmutable $now): bool
{
    $options = sf_available_eta_options($checkin, $now);
    return isset($options[$eta]);
}

/**
 * Resolve the preselected ETA option: keep preferredEta if still valid,
 * otherwise fall back to the earliest available slot.
 */
function sf_default_booking_eta(string $checkin, DateTimeImmutable $now, ?string $preferredEta = null): string
{
    $options = sf_available_eta_options($checkin, $now);
    if ($options === []) {
        return '';
    }
    if ($preferredEta !== null && isset($options[$preferredEta])) {
        return $preferredEta;
    }
    return (string) array_key_first($options);
}

