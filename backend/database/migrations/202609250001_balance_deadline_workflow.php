<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $columns = [
        'bookings' => [
            'balance_due_at' => 'DATETIME DEFAULT NULL',
            'balance_grace_expires_at' => 'DATETIME DEFAULT NULL',
            'balance_status' => "VARCHAR(30) NOT NULL DEFAULT 'deposit_pending'",
            'balance_paid_at' => 'DATETIME DEFAULT NULL',
            'balance_reminder_sent_at' => 'DATETIME DEFAULT NULL',
            'balance_final_reminder_sent_at' => 'DATETIME DEFAULT NULL',
            'balance_overdue_notified_at' => 'DATETIME DEFAULT NULL',
            'balance_cancellation_review_at' => 'DATETIME DEFAULT NULL',
            'cancelled_for_nonpayment_at' => 'DATETIME DEFAULT NULL',
        ],
        'payments' => [
            'purpose' => "VARCHAR(20) NOT NULL DEFAULT 'deposit'",
        ],
        'paymongo_checkouts' => [
            'checkout_url' => 'TEXT DEFAULT NULL',
        ],
    ];
    $check = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?');
    foreach ($columns as $table => $defs) foreach ($defs as $column => $definition) {
        $check->bind_param('sss', $schema, $table, $column);
        $check->execute();
        if (!$check->get_result()->fetch_row()) $db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
    $check->close();

    $defaults = [
        'deposit_percentage' => '50',
        'balance_due_hours_before_checkin' => '24',
        'balance_grace_period_hours' => '6',
        'auto_cancel_unpaid_balance' => '0',
    ];
    $setting = $db->prepare('INSERT IGNORE INTO settings (setting_key,setting_value) VALUES (?,?)');
    foreach ($defaults as $key => $value) { $setting->bind_param('ss', $key, $value); $setting->execute(); }
    $setting->close();

    // Preserve current operational behavior while introducing explicit state.
    $db->query("UPDATE payments SET purpose='balance' WHERE payment_method IN ('Front Desk Cash','GCash QR','Front Desk Card') AND purpose='deposit'");
    $db->query("UPDATE bookings SET balance_due_at=TIMESTAMP(DATE_SUB(check_in, INTERVAL 1 DAY), COALESCE(NULLIF(eta,''),'14:00')) WHERE balance_due_at IS NULL");
    $db->query("UPDATE bookings SET balance_grace_expires_at=DATE_ADD(balance_due_at, INTERVAL 6 HOUR) WHERE balance_grace_expires_at IS NULL AND balance_due_at IS NOT NULL");
    $db->query("UPDATE bookings b SET b.balance_status=CASE
        WHEN b.status IN ('Cancelled','Checked Out') THEN 'not_required'
        WHEN b.status='Checked In' THEN 'paid'
        WHEN EXISTS(SELECT 1 FROM payments p WHERE p.booking_id=b.id AND p.status='verified') THEN 'balance_pending'
        ELSE 'deposit_pending' END");
};
