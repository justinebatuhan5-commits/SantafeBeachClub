<?php
require_once __DIR__ . '/business_time_helper.php';
require_once __DIR__ . '/../services/mailer.php';

/**
 * Automatically checks for upcoming guest arrivals (within 1 to 2 days)
 * and sends a Pre-Arrival / Check-in Reminder email with:
 *   - Dates & Accommodation
 *   - Check-in QR pass
 *   - Direct link to send an explanation / request reschedule / cancellation
 *
 * Safe & idempotent: Only sends once per booking (tracked by `prearrival_reminder_sent_at`).
 *
 * @param mysqli $conn
 * @param int $days_ahead Number of days in advance to send reminders (default 1 to 2 days)
 * @return array Summary of processed reminders
 */
function sf_send_prearrival_checkin_reminders(mysqli $conn, int $days_ahead = 2): array
{
    $results = [
        'checked' => 0,
        'sent'    => 0,
        'failed'  => 0,
        'errors'  => [],
    ];

    $current_business_time = sf_get_current_business_datetime($conn);
    $today_str = $current_business_time->format('Y-m-d');
    $target_date_str = $current_business_time->modify("+{$days_ahead} days")->format('Y-m-d');
    $now_timestamp = $current_business_time->format('Y-m-d H:i:s');

    // Select upcoming confirmed or pending bookings within window that haven't received reminder
    $query = "
        SELECT 
            b.id,
            b.guest_name,
            b.guest_email,
            b.accommodation_name,
            b.check_in,
            b.check_out,
            b.cancellation_token,
            b.checkin_token,
            b.status,
            DATEDIFF(b.check_out, b.check_in) AS nights,
            COALESCE(r.price_per_night, rt.price, 0) AS price_per_night,
            (DATEDIFF(b.check_out, b.check_in) * COALESCE(r.price_per_night, rt.price, 0)) AS total_cost,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.booking_id = b.id AND p.status = 'verified') AS amount_paid
        FROM bookings b
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN room_types rt ON b.room_type_id = rt.id
        WHERE b.status IN ('Confirmed', 'Pending')
          AND b.guest_email IS NOT NULL
          AND TRIM(b.guest_email) <> ''
          AND b.check_in >= ?
          AND b.check_in <= ?
          AND b.prearrival_reminder_sent_at IS NULL
        ORDER BY b.check_in ASC, b.id ASC
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $results['errors'][] = 'Database statement prepare failed: ' . $conn->error;
        return $results;
    }

    $stmt->bind_param("ss", $today_str, $target_date_str);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($bookings)) {
        return $results;
    }

    $results['checked'] = count($bookings);

    // Determine Base URL
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Normalize base URL to point to /frontend
    if (strpos($script_dir, '/frontend') !== false) {
        $base_url = 'http://' . $host . $script_dir;
    } else {
        $base_url = 'http://' . $host . '/SantaBeachClub-BookingSystem/frontend';
    }
    $base_url = rtrim($base_url, '/');

    $mark_stmt = $conn->prepare("UPDATE bookings SET prearrival_reminder_sent_at = ? WHERE id = ? AND prearrival_reminder_sent_at IS NULL");

    foreach ($bookings as $b) {
        $booking_id = (int)$b['id'];
        $guest_name = trim((string)$b['guest_name']);
        $guest_email = trim((string)$b['guest_email']);
        $room_name = (string)$b['accommodation_name'];
        $check_in = (string)$b['check_in'];
        $check_out = (string)$b['check_out'];
        $total_cost = (float)($b['total_cost'] ?? 0);
        $amount_paid = (float)($b['amount_paid'] ?? 0);
        $balance_due = max(0, $total_cost - $amount_paid);

        $booking_ref = 'REF-' . str_pad($booking_id, 3, '0', STR_PAD_LEFT);

        // Ensure tokens exist
        $cancellation_token = $b['cancellation_token'] ?? '';
        $checkin_token = $b['checkin_token'] ?? '';

        if (empty($cancellation_token) || empty($checkin_token)) {
            if (empty($cancellation_token)) $cancellation_token = bin2hex(random_bytes(16));
            if (empty($checkin_token)) $checkin_token = bin2hex(random_bytes(16));

            $tok_stmt = $conn->prepare("UPDATE bookings SET cancellation_token = ?, checkin_token = ? WHERE id = ?");
            if ($tok_stmt) {
                $tok_stmt->bind_param("ssi", $cancellation_token, $checkin_token, $booking_id);
                $tok_stmt->execute();
                $tok_stmt->close();
            }
        }

        // Generate URLs
        // Direct link to cancel / send explanation form
        $explanation_url = $base_url . '/cancel_booking?token=' . urlencode($cancellation_token);
        // Link to check-in pass
        $checkin_url = $base_url . '/checkin?ref=' . urlencode($booking_ref) . '&token=' . urlencode($checkin_token);

        // Send email
        $mail_res = sendPreArrivalReminderEmail(
            $guest_email,
            $guest_name,
            $booking_ref,
            $room_name,
            $check_in,
            $check_out,
            $balance_due,
            $explanation_url,
            $checkin_url
        );

        if ($mail_res['success']) {
            if ($mark_stmt) {
                $mark_stmt->bind_param("si", $now_timestamp, $booking_id);
                $mark_stmt->execute();
            }

            // Create an in-app notification for admin reference
            $notif_title = 'Pre-Arrival Reminder Sent';
            $notif_message = "Check-in reminder sent to {$guest_name} ({$booking_ref}) for check-in on {$check_in}.";
            $notif_type = 'info';
            $n_stmt = $conn->prepare("INSERT INTO notifications (title, message, type, booking_id, created_at) VALUES (?, ?, ?, ?, ?)");
            if ($n_stmt) {
                $n_stmt->bind_param("sssis", $notif_title, $notif_message, $notif_type, $booking_id, $now_timestamp);
                $n_stmt->execute();
                $n_stmt->close();
            }

            $results['sent']++;
        } else {
            $results['failed']++;
            $results['errors'][] = "Failed sending to {$guest_email} (Booking #{$booking_id}): " . ($mail_res['error'] ?? 'Unknown error');
        }
    }

    if ($mark_stmt) {
        $mark_stmt->close();
    }

    return $results;
}
