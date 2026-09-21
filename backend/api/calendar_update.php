<?php
/**
 * calendar_update.php — Drag-and-drop save endpoint for the Availability Calendar.
 * Accepts POST with booking_id, new_check_in, new_check_out.
 * Validates dates, checks for overlaps, updates the booking, and logs the change.
 */
require_once __DIR__ . '/../helpers/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/cors_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../helpers/validator_helper.php';

handle_cors();
RateLimiter::enforce($conn, 'calendar_update', 60, 60);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// ── Read input ──────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
$booking_id    = (int)($input['booking_id']    ?? 0);
if (pm_query($conn, 'SELECT booking_id FROM booking_discounts WHERE booking_id=?', [$booking_id])->get_result()->fetch_assoc()) {
    http_response_code(409);
    echo json_encode(['error' => 'Discount bookings have an approved price snapshot. Contact the guest and arrange a new request before changing dates.']);
    exit;
}
$new_check_in  = $input['new_check_in']        ?? '';
$new_check_out = $input['new_check_out']        ?? '';

// ── Validate required fields ────────────────────────────────────────────────
if (!$booking_id || !$new_check_in || !$new_check_out) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: booking_id, new_check_in, new_check_out.']);
    exit;
}

// ── Validate date formats ───────────────────────────────────────────────────
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_check_in) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_check_out)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Use Y-m-d.']);
    exit;
}

// ── Check-out must be after check-in ────────────────────────────────────────
if (strtotime($new_check_out) <= strtotime($new_check_in)) {
    http_response_code(400);
    echo json_encode(['error' => 'Check-out date must be after check-in date.']);
    exit;
}

// ── Fetch the existing booking ──────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, guest_name, room_id, check_in, check_out, status FROM bookings WHERE id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['error' => 'Booking not found.']);
    exit;
}

// ── Cannot reschedule cancelled or checked-out bookings ─────────────────────
if (in_array($booking['status'], ['Cancelled', 'Checked Out'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot reschedule a ' . $booking['status'] . ' booking.']);
    exit;
}

// ── Check for overlapping bookings on the same room ─────────────────────────
$room_id = (int)$booking['room_id'];
$overlap_stmt = $conn->prepare("
    SELECT id, guest_name, check_in, check_out 
    FROM bookings 
    WHERE room_id = ? 
      AND id != ? 
      AND status NOT IN ('Cancelled', 'Checked Out')
      AND check_in < ? 
      AND check_out > ?
    LIMIT 1
");
$overlap_stmt->bind_param("iiss", $room_id, $booking_id, $new_check_out, $new_check_in);
$overlap_stmt->execute();
$overlap = $overlap_stmt->get_result()->fetch_assoc();
$overlap_stmt->close();

if ($overlap) {
    http_response_code(409);
    echo json_encode([
        'error' => 'Date conflict: overlaps with booking #' . $overlap['id'] . ' (' . $overlap['guest_name'] . ', ' . $overlap['check_in'] . ' – ' . $overlap['check_out'] . ').',
    ]);
    exit;
}

// ── Update the booking dates ────────────────────────────────────────────────
$update_stmt = $conn->prepare("UPDATE bookings SET check_in = ?, check_out = ? WHERE id = ?");
$update_stmt->bind_param("ssi", $new_check_in, $new_check_out, $booking_id);
$update_stmt->execute();
$update_stmt->close();

// ── Log the activity ────────────────────────────────────────────────────────
$admin = $_SESSION['admin_username'] ?? 'system';
$old_dates = $booking['check_in'] . ' → ' . $booking['check_out'];
$new_dates = $new_check_in . ' → ' . $new_check_out;
$details = 'Booking #' . $booking_id . ' (' . $booking['guest_name'] . ') rescheduled from ' . $old_dates . ' to ' . $new_dates;
log_activity($conn, $admin, 'Booking Rescheduled (Calendar)', $details);

echo json_encode([
    'success' => true,
    'message' => 'Booking #' . $booking_id . ' rescheduled successfully.',
    'booking' => [
        'id'        => $booking_id,
        'check_in'  => $new_check_in,
        'check_out' => $new_check_out,
    ],
]);
