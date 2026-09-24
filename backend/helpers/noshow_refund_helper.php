<?php
require_once __DIR__ . '/business_time_helper.php';

/** Build a request draft only; payment and eligibility are rechecked on submission. */
function sf_noshow_refund_handoff(string $role, int $booking_id, array $booking, string $reason): ?array
{
    if ($role !== 'receptionist' || $booking_id <= 0
        || !in_array($booking['status'] ?? '', ['Pending', 'Confirmed'], true)) {
        return null;
    }
    $policy = sf_get_cancellation_policy($booking['check_in'], $booking['check_out']);
    if (($policy['refund_percentage'] ?? 0) <= 0) {
        return null;
    }
    return ['booking_id' => $booking_id, 'reason' => $reason];
}
