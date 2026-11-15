<?php
require_once __DIR__ . '/business_time_helper.php';
require_once __DIR__ . '/url_helper.php';

function sf_balance_setting(mysqli $db, string $key, string $default): string
{
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
    $stmt->bind_param('s', $key); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return (string)($row['setting_value'] ?? $default);
}

function sf_balance_public_url(): string
{
    return sfbc_public_frontend_url();
}

function sf_balance_deadlines(mysqli $db, string $checkIn, ?string $eta = null): array
{
    $tz = new DateTimeZone(sf_get_property_timezone_setting($db));
    $time = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d/', (string)$eta) ? substr((string)$eta, 0, 5) : '14:00';
    $arrival = new DateTimeImmutable($checkIn . ' ' . $time, $tz);
    $hours = max(1, (int)sf_balance_setting($db, 'balance_due_hours_before_checkin', '24'));
    $grace = max(0, (int)sf_balance_setting($db, 'balance_grace_period_hours', '6'));
    $due = $arrival->modify("-{$hours} hours");
    return ['due' => $due, 'grace' => $due->modify("+{$grace} hours")];
}

function sf_booking_financials(mysqli $db, int $bookingId, bool $lock = false): ?array
{
    $sql = "SELECT b.*, DATEDIFF(b.check_out,b.check_in) AS nights,
        COALESCE(r.price_per_night,rt.price,0) AS price_per_night,
        COALESCE((SELECT d.total_centavos/100 FROM booking_discounts d WHERE d.booking_id=b.id),
                 DATEDIFF(b.check_out,b.check_in)*COALESCE(r.price_per_night,rt.price,0)) AS total_cost,
        (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.booking_id=b.id AND p.status='verified') AS amount_paid
        FROM bookings b LEFT JOIN rooms r ON r.id=b.room_id LEFT JOIN room_types rt ON rt.id=b.room_type_id
        WHERE b.id=? LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
    $stmt = $db->prepare($sql); $stmt->bind_param('i', $bookingId); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) return null;
    $row['total_cost'] = max(0, (float)$row['total_cost']);
    $row['amount_paid'] = max(0, (float)$row['amount_paid']);
    $row['balance_due'] = max(0, $row['total_cost'] - $row['amount_paid']);
    return $row;
}

function sf_initialize_balance_deadline(mysqli $db, int $bookingId): void
{
    $booking = sf_booking_financials($db, $bookingId);
    if (!$booking) return;
    $deadlines = sf_balance_deadlines($db, $booking['check_in'], $booking['eta'] ?? null);
    $due = $deadlines['due']->format('Y-m-d H:i:s');
    $grace = $deadlines['grace']->format('Y-m-d H:i:s');
    $state = $booking['balance_due'] <= 0.009 ? 'paid' : ($booking['amount_paid'] > 0 ? 'balance_pending' : 'deposit_pending');
    $paidAt = $state === 'paid' ? date('Y-m-d H:i:s') : null;
    $stmt = $db->prepare('UPDATE bookings SET balance_due_at=?,balance_grace_expires_at=?,balance_status=?,balance_paid_at=COALESCE(balance_paid_at,?) WHERE id=?');
    $stmt->bind_param('ssssi', $due, $grace, $state, $paidAt, $bookingId); $stmt->execute(); $stmt->close();
}

function sf_refresh_balance_status(mysqli $db, int $bookingId): array
{
    $booking = sf_booking_financials($db, $bookingId);
    if (!$booking) throw new RuntimeException('Booking not found.');
    if (empty($booking['balance_due_at'])) sf_initialize_balance_deadline($db, $bookingId);
    $booking = sf_booking_financials($db, $bookingId);
    $now = sf_get_current_business_datetime($db);
    if ($booking['balance_due'] <= 0.009) $state = 'paid';
    elseif ($booking['amount_paid'] <= 0) $state = 'deposit_pending';
    elseif (!empty($booking['balance_grace_expires_at']) && $now >= new DateTimeImmutable($booking['balance_grace_expires_at'], $now->getTimezone())) $state = 'cancellation_review';
    elseif (!empty($booking['balance_due_at']) && $now >= new DateTimeImmutable($booking['balance_due_at'], $now->getTimezone())) $state = 'overdue';
    else $state = 'balance_pending';
    $paidSql = $state === 'paid' ? 'COALESCE(balance_paid_at,NOW())' : 'balance_paid_at';
    $stmt = $db->prepare("UPDATE bookings SET balance_status=?,balance_paid_at={$paidSql} WHERE id=?");
    $stmt->bind_param('si', $state, $bookingId); $stmt->execute(); $stmt->close();
    $booking['balance_status'] = $state;
    return $booking;
}

function sf_process_balance_deadlines(mysqli $db): array
{
    $ids = [];
    $result = $db->query("SELECT id FROM bookings WHERE status IN ('Pending','Confirmed') AND balance_status IN ('balance_pending','overdue')");
    while ($row = $result->fetch_assoc()) $ids[] = (int)$row['id'];
    $summary = ['checked'=>count($ids),'overdue'=>0,'review'=>0,'emails'=>0];
    foreach ($ids as $id) {
        $before = sf_booking_financials($db, $id);
        $after = sf_refresh_balance_status($db, $id);
        if ($after['balance_status'] === ($before['balance_status'] ?? '')) continue;
        if ($after['balance_status'] === 'overdue') {
            $summary['overdue']++;
            $title='Balance Payment Overdue'; $type='warning';
            $message='REF-' . str_pad((string)$id,3,'0',STR_PAD_LEFT) . ' has an unpaid balance of PHP ' . number_format($after['balance_due'],2) . '.';
            $db->query("UPDATE bookings SET balance_overdue_notified_at=NOW() WHERE id={$id}");
            if (!empty($after['guest_email'])) {
                require_once __DIR__ . '/../services/mailer.php';
                $base=sf_balance_public_url();
                $mail=sendBalanceDeadlineEmail($after['guest_email'],$after['guest_name'],'REF-'.str_pad((string)$id,3,'0',STR_PAD_LEFT),$after['balance_due'],date('M j, Y g:i A',strtotime($after['balance_due_at'])),$base.'/balance_payment.php?token='.urlencode((string)$after['cancellation_token']),'overdue');
                if ($mail['success']) $summary['emails']++;
            }
        } elseif ($after['balance_status'] === 'cancellation_review') {
            $summary['review']++;
            $title='Booking Needs Cancellation Review'; $type='alert';
            $message='REF-' . str_pad((string)$id,3,'0',STR_PAD_LEFT) . ' remains unpaid after the grace period. Review before cancelling and releasing the room.';
            $db->query("UPDATE bookings SET balance_cancellation_review_at=NOW() WHERE id={$id}");
        } else continue;
        $stmt=$db->prepare('INSERT INTO notifications (title,message,type,booking_id) VALUES (?,?,?,?)');
        $stmt->bind_param('sssi',$title,$message,$type,$id); $stmt->execute(); $stmt->close();
    }
    return $summary;
}

function sf_send_balance_reminders(mysqli $db): array
{
    require_once __DIR__ . '/../services/mailer.php';
    $summary=['checked'=>0,'sent'=>0,'failed'=>0];
    $result=$db->query("SELECT id FROM bookings WHERE status IN ('Pending','Confirmed') AND balance_status='balance_pending' AND balance_due_at>NOW() AND balance_due_at<=DATE_ADD(NOW(),INTERVAL 72 HOUR)");
    while($row=$result->fetch_assoc()) {
        $b=sf_refresh_balance_status($db,(int)$row['id']);
        if($b['balance_due']<=0.009) continue;
        $summary['checked']++;
        $hours=(strtotime($b['balance_due_at'])-time())/3600;
        $stage=$hours<=24?'final':'reminder';
        $column=$stage==='final'?'balance_final_reminder_sent_at':'balance_reminder_sent_at';
        if(!empty($b[$column])) continue;
        $base=sf_balance_public_url();
        $mail=sendBalanceDeadlineEmail($b['guest_email'],$b['guest_name'],'REF-'.str_pad((string)$b['id'],3,'0',STR_PAD_LEFT),$b['balance_due'],date('M j, Y g:i A',strtotime($b['balance_due_at'])),$base.'/balance_payment.php?token='.urlencode((string)$b['cancellation_token']),$stage);
        if($mail['success']) { $db->query("UPDATE bookings SET {$column}=NOW() WHERE id=".(int)$b['id']); $summary['sent']++; } else $summary['failed']++;
    }
    return $summary;
}
