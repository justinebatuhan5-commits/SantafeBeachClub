<?php
/**
 * Native PHP Automation & Scheduler service for Santa Fe Beach Club.
 *
 * Replaces runtime dependence on the excluded Laravel API container/directory
 * so scheduled jobs and manual triggers work identically in Render Docker
 * deployments (PHP 8.2 + TiDB) and local XAMPP environments.
 */

require_once __DIR__ . '/../helpers/business_time_helper.php';
require_once __DIR__ . '/../helpers/prearrival_reminder_helper.php';
require_once __DIR__ . '/../helpers/checkout_notification_helper.php';
require_once __DIR__ . '/discount_service.php';

/**
 * Return the canonical list of supported automation task definitions.
 *
 * @return array<int, array<string, string>>
 */
function sfbc_automation_task_definitions(): array
{
    return [
        [
            'id' => 'reservations:send-reminders',
            'name' => 'Pre-Arrival Reminder Emails',
            'description' => 'Sends reminder emails to guests whose check-in is within 48 hours.',
            'schedule' => 'Daily at 08:00 AM',
            'setting_key' => 'automation_reminders_enabled',
        ],
        [
            'id' => 'staff:notify-checkins',
            'name' => 'Daily Staff Check-In Digest',
            'description' => 'Notifies resort staff of all expected guest arrivals scheduled for today.',
            'schedule' => 'Daily at 06:00 AM',
            'setting_key' => 'automation_checkins_enabled',
        ],
        [
            'id' => 'staff:notify-checkouts',
            'name' => 'Daily Staff Check-Out Digest',
            'description' => 'Reminds housekeeping and front-desk of guests checking out today.',
            'schedule' => 'Daily at 07:00 AM',
            'setting_key' => 'automation_checkouts_enabled',
        ],
        [
            'id' => 'reservations:process-pending',
            'name' => 'Pending Booking Cleanup',
            'description' => 'Automatically cancels unpaid pending reservations older than 24 hours.',
            'schedule' => 'Hourly',
            'setting_key' => 'automation_pending_enabled',
        ],
        [
            'id' => 'reports:generate-daily',
            'name' => 'Daily Financial & Occupancy Snapshot',
            'description' => 'Computes total revenue, confirmed bookings, and logs daily metrics.',
            'schedule' => 'Daily at 11:55 PM',
            'setting_key' => 'automation_reports_enabled',
        ],
    ];
}

/**
 * Parse a setting value into a boolean with a sensible default.
 */
function sfbc_automation_parse_bool(mixed $value, bool $default = true): bool
{
    if ($value === null) {
        return $default;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int) $value !== 0;
    }

    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off', 'disabled'], true)) {
        return false;
    }
    if (in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
        return true;
    }

    return $default;
}

/**
 * Convert a database timestamp into an ISO-8601 ATOM string in the resort timezone.
 */
function sfbc_automation_format_log_timestamp(?string $rawTimestamp, DateTimeZone $tz): ?string
{
    $raw = trim((string) ($rawTimestamp ?? ''));
    if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $tz);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->format(DateTimeInterface::ATOM);
    }

    try {
        return (new DateTimeImmutable($raw, $tz))->format(DateTimeInterface::ATOM);
    } catch (Throwable) {
        return null;
    }
}

/**
 * Resolve the property timezone object safely, falling back to Asia/Manila.
 */
function sfbc_automation_timezone(mysqli $conn): DateTimeZone
{
    $tzName = sf_get_property_timezone_setting($conn);
    try {
        return new DateTimeZone($tzName);
    } catch (Throwable) {
        return new DateTimeZone('Asia/Manila');
    }
}

/**
 * Fetch requested keys from the settings table.
 *
 * @param array<int, string> $keys
 * @return array<string, string>
 */
function sfbc_automation_load_settings(mysqli $conn, array $keys): array
{
    $cleanKeys = array_values(array_unique(array_filter($keys, 'is_string')));
    if ($cleanKeys === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($cleanKeys), '?'));
    $sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$placeholders})";

    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $types = str_repeat('s', count($cleanKeys));
        $stmt->bind_param($types, ...$cleanKeys);
        $stmt->execute();
        $res = $stmt->get_result();

        $values = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $values[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        $stmt->close();

        return $values;
    } catch (Throwable) {
        return [];
    }
}

/**
 * Return task definitions enriched with persisted enable/disable settings.
 *
 * @return array<int, array<string, mixed>>
 */
function sfbc_automation_tasks(mysqli $conn): array
{
    $definitions = sfbc_automation_task_definitions();
    $settingKeys = array_column($definitions, 'setting_key');
    $settings = sfbc_automation_load_settings($conn, $settingKeys);

    $tasks = [];
    foreach ($definitions as $task) {
        $settingKey = $task['setting_key'];
        $tasks[] = [
            'id' => $task['id'],
            'name' => $task['name'],
            'description' => $task['description'],
            'schedule' => $task['schedule'],
            'setting_key' => $settingKey,
            'enabled' => sfbc_automation_parse_bool($settings[$settingKey] ?? null, true),
        ];
    }

    return $tasks;
}

/**
 * Return recent notification records formatted for the Automated Activity Log.
 *
 * @return array<int, array<string, mixed>>
 */
function sfbc_automation_recent_logs(mysqli $conn, int $limit = 20): array
{
    $boundedLimit = max(1, min(100, $limit));
    $tz = sfbc_automation_timezone($conn);

    $stmt = $conn->prepare(
        'SELECT id, title, message, type, booking_id, created_at FROM notifications ORDER BY id DESC LIMIT ?'
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $boundedLimit);
    $stmt->execute();
    $res = $stmt->get_result();

    $logs = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $type = strtolower(trim((string) ($row['type'] ?? 'info')));
        if ($type === '') {
            $type = 'info';
        }

        $logs[] = [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'message' => (string) ($row['message'] ?? ''),
            'type' => $type,
            'booking_id' => isset($row['booking_id']) && $row['booking_id'] !== null ? (int) $row['booking_id'] : null,
            'created_at' => sfbc_automation_format_log_timestamp($row['created_at'] ?? null, $tz),
        ];
    }
    $stmt->close();

    return $logs;
}

/**
 * Return the full overview payload expected by the Automation & Scheduler page.
 *
 * @return array<string, mixed>
 */
function sfbc_automation_overview(mysqli $conn): array
{
    return [
        'success' => true,
        'tasks' => sfbc_automation_tasks($conn),
        'recent_logs' => sfbc_automation_recent_logs($conn, 20),
        'timezone' => sf_get_property_timezone_setting($conn),
        'engine' => 'php-native',
    ];
}

/**
 * Persist a notification entry in the resort business timezone.
 */
function sfbc_automation_log_notification(
    mysqli $conn,
    string $title,
    string $message,
    string $type = 'info',
    ?int $bookingId = null,
    ?DateTimeImmutable $timestamp = null
): void {
    $now = $timestamp ?? sf_get_current_business_datetime($conn);
    $createdAt = $now->format('Y-m-d H:i:s');

    if ($bookingId !== null) {
        $stmt = $conn->prepare(
            'INSERT INTO notifications (title, message, type, booking_id, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('sssis', $title, $message, $type, $bookingId, $createdAt);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO notifications (title, message, type, created_at) VALUES (?, ?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('ssss', $title, $message, $type, $createdAt);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Format a compact guest/accommodation sample list for digest notifications.
 *
 * @param array<int, array<string, mixed>> $rows
 */
function sfbc_automation_summarize_bookings(array $rows, int $maxItems = 5): string
{
    $total = count($rows);
    if ($total === 0) {
        return 'none';
    }

    $parts = [];
    foreach (array_slice($rows, 0, $maxItems) as $row) {
        $id = (int) ($row['id'] ?? 0);
        $guest = trim((string) ($row['guest_name'] ?? 'Guest'));
        $room = trim((string) ($row['accommodation_name'] ?? ''));
        $label = 'REF-' . $id . ' (' . $guest . ($room !== '' ? ' - ' . $room : '') . ')';
        $parts[] = $label;
    }

    if ($total > $maxItems) {
        $parts[] = '+' . ($total - $maxItems) . ' more';
    }

    return implode(', ', $parts);
}

/**
 * Send pre-arrival email reminders for guests arriving within 48 hours.
 *
 * @return array{message: string, output: string}
 */
function sfbc_automation_run_send_reminders(mysqli $conn, DateTimeImmutable $now): array
{
    $result = sf_send_prearrival_checkin_reminders($conn, 2);
    $checked = (int) ($result['checked'] ?? 0);
    $sent = (int) ($result['sent'] ?? 0);
    $failed = (int) ($result['failed'] ?? 0);
    $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];

    $summary = sprintf(
        'Checked %d eligible booking(s): sent %d reminder(s), %d failed.',
        $checked,
        $sent,
        $failed
    );

    $logType = $failed > 0 ? 'warning' : ($sent > 0 ? 'success' : 'info');
    sfbc_automation_log_notification(
        $conn,
        'Pre-Arrival Reminder Run',
        $summary,
        $logType,
        null,
        $now
    );

    $outputLines = [$summary];
    if ($errors !== []) {
        $outputLines[] = 'Errors: ' . implode('; ', array_slice($errors, 0, 5));
    }

    return [
        'message' => $summary,
        'output' => implode("\n", $outputLines),
    ];
}

/**
 * Compile and log today's expected guest check-ins for resort staff.
 *
 * @return array{message: string, output: string}
 */
function sfbc_automation_run_notify_checkins(mysqli $conn, DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    $rows = [];

    $stmt = $conn->prepare(
        "SELECT id, guest_name, accommodation_name, status
         FROM bookings
         WHERE check_in = ?
           AND status IN ('Confirmed', 'Pending', 'Pending Payment')
         ORDER BY id ASC"
    );
    if ($stmt) {
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
    }

    $count = count($rows);
    if ($count > 0) {
        $message = sprintf(
            '%d guest arrival(s) scheduled for %s: %s.',
            $count,
            $today,
            sfbc_automation_summarize_bookings($rows)
        );
    } else {
        $message = sprintf('No guest arrivals scheduled for %s.', $today);
    }

    sfbc_automation_log_notification(
        $conn,
        'Daily Staff Check-In Digest',
        $message,
        $count > 0 ? 'success' : 'info',
        null,
        $now
    );

    return [
        'message' => $message,
        'output' => $message,
    ];
}

/**
 * Dispatch due checkout reminders and log today's checked-in departure digest.
 *
 * @return array{message: string, output: string}
 */
function sfbc_automation_run_notify_checkouts(mysqli $conn, DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    $rows = [];

    $stmt = $conn->prepare(
        "SELECT id, guest_name, accommodation_name, check_out
         FROM bookings
         WHERE check_out = ?
           AND status = 'Checked In'
         ORDER BY id ASC"
    );
    if ($stmt) {
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
    }

    sf_create_due_checkout_notifications($conn);

    $count = count($rows);
    if ($count > 0) {
        $message = sprintf(
            '%d checked-in stay(s) scheduled for check-out on %s: %s.',
            $count,
            $today,
            sfbc_automation_summarize_bookings($rows)
        );
    } else {
        $message = sprintf('No checked-in guests scheduled to check out on %s.', $today);
    }

    sfbc_automation_log_notification(
        $conn,
        'Daily Staff Check-Out Digest',
        $message,
        $count > 0 ? 'success' : 'info',
        null,
        $now
    );

    return [
        'message' => $message,
        'output' => $message,
    ];
}

/**
 * Cancel stale unpaid pending reservations older than 24 hours safely.
 *
 * Bookings with any verified payment row are never auto-cancelled.
 *
 * @return array{message: string, output: string}
 */
function sfbc_automation_run_process_pending(mysqli $conn, DateTimeImmutable $now): array
{
    $cutoff = $now->modify('-24 hours')->format('Y-m-d H:i:s');
    $nowFormatted = $now->format('Y-m-d H:i:s');
    $cancelReason = 'Auto-cancelled after 24 hours without verified payment.';

    $candidateIds = [];
    $stmt = $conn->prepare(
        "SELECT b.id
         FROM bookings b
         WHERE b.status IN ('Pending', 'Pending Payment')
           AND b.created_at <= ?
           AND NOT EXISTS (
               SELECT 1
               FROM payments p
               WHERE p.booking_id = b.id
                 AND LOWER(p.status) = 'verified'
           )
         ORDER BY b.id ASC"
    );
    if ($stmt) {
        $stmt->bind_param('s', $cutoff);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $candidateIds[] = (int) $row['id'];
        }
        $stmt->close();
    }

    $cancelledRefs = [];
    foreach ($candidateIds as $bookingId) {
        $conn->begin_transaction();
        try {
            $lockStmt = $conn->prepare(
                "SELECT b.id, b.room_id
                 FROM bookings b
                 WHERE b.id = ?
                   AND b.status IN ('Pending', 'Pending Payment')
                   AND NOT EXISTS (
                       SELECT 1
                       FROM payments p
                       WHERE p.booking_id = b.id
                         AND LOWER(p.status) = 'verified'
                   )
                 FOR UPDATE"
            );
            if (!$lockStmt) {
                $conn->rollback();
                continue;
            }
            $lockStmt->bind_param('i', $bookingId);
            $lockStmt->execute();
            $locked = $lockStmt->get_result()->fetch_assoc();
            $lockStmt->close();

            if (!$locked) {
                $conn->rollback();
                continue;
            }

            $updStmt = $conn->prepare(
                "UPDATE bookings
                 SET status = 'Cancelled', cancelled_at = ?, cancellation_reason = ?
                 WHERE id = ?"
            );
            if ($updStmt) {
                $updStmt->bind_param('ssi', $nowFormatted, $cancelReason, $bookingId);
                $updStmt->execute();
                $updStmt->close();
            }

            if (!empty($locked['room_id'])) {
                $roomId = (int) $locked['room_id'];
                $roomStmt = $conn->prepare(
                    "UPDATE rooms SET status = 'ready' WHERE id = ? AND status = 'occupied'"
                );
                if ($roomStmt) {
                    $roomStmt->bind_param('i', $roomId);
                    $roomStmt->execute();
                    $roomStmt->close();
                }
            }

            $conn->commit();
            $cancelledRefs[] = 'REF-' . $bookingId;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    if ($cancelledRefs !== [] && function_exists('discount_maintenance')) {
        try {
            discount_maintenance($conn);
        } catch (Throwable) {
            // Optional discount synchronization must not fail cleanup results.
        }
    }

    $count = count($cancelledRefs);
    if ($count > 0) {
        $message = sprintf(
            'Auto-cancelled %d unpaid pending reservation(s) older than 24 hours: %s.',
            $count,
            implode(', ', $cancelledRefs)
        );
        $logType = 'warning';
    } else {
        $message = 'No stale unpaid pending reservations required cancellation.';
        $logType = 'info';
    }

    sfbc_automation_log_notification(
        $conn,
        'Pending Booking Cleanup',
        $message,
        $logType,
        null,
        $now
    );

    return [
        'message' => $message,
        'output' => $message,
    ];
}

/**
 * Compute and log today's revenue, new bookings, and occupancy snapshot.
 *
 * @return array{message: string, output: string}
 */
function sfbc_automation_run_daily_report(mysqli $conn, DateTimeImmutable $now): array
{
    $dayStart = $now->setTime(0, 0, 0);
    $dayEnd = $dayStart->modify('+1 day');
    $startStr = $dayStart->format('Y-m-d H:i:s');
    $endStr = $dayEnd->format('Y-m-d H:i:s');
    $todayDate = $now->format('Y-m-d');

    $revenue = 0.0;
    $paymentCount = 0;
    $payStmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS revenue, COUNT(*) AS payment_count
         FROM payments
         WHERE LOWER(status) = 'verified'
           AND paid_at >= ?
           AND paid_at < ?"
    );
    if ($payStmt) {
        $payStmt->bind_param('ss', $startStr, $endStr);
        $payStmt->execute();
        $payRow = $payStmt->get_result()->fetch_assoc();
        $revenue = (float) ($payRow['revenue'] ?? 0);
        $paymentCount = (int) ($payRow['payment_count'] ?? 0);
        $payStmt->close();
    }

    $newBookings = 0;
    $bookStmt = $conn->prepare(
        'SELECT COUNT(*) AS new_bookings FROM bookings WHERE created_at >= ? AND created_at < ?'
    );
    if ($bookStmt) {
        $bookStmt->bind_param('ss', $startStr, $endStr);
        $bookStmt->execute();
        $bookRow = $bookStmt->get_result()->fetch_assoc();
        $newBookings = (int) ($bookRow['new_bookings'] ?? 0);
        $bookStmt->close();
    }

    $activeStays = 0;
    $occStmt = $conn->prepare(
        "SELECT COUNT(*) AS active_stays
         FROM bookings
         WHERE status = 'Checked In'
            OR (check_in <= ? AND check_out > ? AND status IN ('Confirmed', 'Checked In'))"
    );
    if ($occStmt) {
        $occStmt->bind_param('ss', $todayDate, $todayDate);
        $occStmt->execute();
        $occRow = $occStmt->get_result()->fetch_assoc();
        $activeStays = (int) ($occRow['active_stays'] ?? 0);
        $occStmt->close();
    }

    $message = sprintf(
        'Snapshot for %s — Verified Revenue: PHP %s (%d payment(s)), New Bookings: %d, Active Stays: %d.',
        $todayDate,
        number_format($revenue, 2),
        $paymentCount,
        $newBookings,
        $activeStays
    );

    sfbc_automation_log_notification(
        $conn,
        'Daily Financial & Occupancy Snapshot',
        $message,
        'info',
        null,
        $now
    );

    return [
        'message' => $message,
        'output' => $message,
    ];
}

/**
 * Trigger an allowed automation task synchronously and return HTTP status + payload.
 *
 * @return array{status: int, payload: array<string, mixed>}
 */
function sfbc_automation_trigger_task(mysqli $conn, string $taskId, string $actor = 'system'): array
{
    $cleanTaskId = trim($taskId);
    $validIds = array_column(sfbc_automation_task_definitions(), 'id');
    if ($cleanTaskId === '' || !in_array($cleanTaskId, $validIds, true)) {
        return [
            'status' => 400,
            'payload' => [
                'success' => false,
                'error' => 'Invalid automation task ID.',
            ],
        ];
    }

    $now = sf_get_current_business_datetime($conn);

    $result = match ($cleanTaskId) {
        'reservations:send-reminders' => sfbc_automation_run_send_reminders($conn, $now),
        'staff:notify-checkins' => sfbc_automation_run_notify_checkins($conn, $now),
        'staff:notify-checkouts' => sfbc_automation_run_notify_checkouts($conn, $now),
        'reservations:process-pending' => sfbc_automation_run_process_pending($conn, $now),
        'reports:generate-daily' => sfbc_automation_run_daily_report($conn, $now),
    };

    if (function_exists('log_activity')) {
        log_activity($conn, $actor, 'Automation Task Triggered', $cleanTaskId . ': ' . $result['message']);
    }

    return [
        'status' => 200,
        'payload' => [
            'success' => true,
            'task_id' => $cleanTaskId,
            'message' => $result['message'],
            'output' => $result['output'],
        ],
    ];
}
