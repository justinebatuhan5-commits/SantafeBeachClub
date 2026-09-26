<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $columns = [
        'verification_otp_hash' => 'VARCHAR(255) DEFAULT NULL',
        'verification_otp_expires_at' => 'DATETIME DEFAULT NULL',
        'verification_attempts' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        'verification_send_count' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        'verification_last_sent_at' => 'DATETIME DEFAULT NULL',
        'phone_verified_at' => 'DATETIME DEFAULT NULL',
        'verification_locked_at' => 'DATETIME DEFAULT NULL',
    ];
    $check = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=? AND table_name='manual_refund_details' AND column_name=?");
    foreach ($columns as $column => $definition) {
        $check->bind_param('ss', $schema, $column); $check->execute();
        if (!$check->get_result()->fetch_row()) $db->query("ALTER TABLE manual_refund_details ADD COLUMN `{$column}` {$definition}");
    }
    $check->close();
};
