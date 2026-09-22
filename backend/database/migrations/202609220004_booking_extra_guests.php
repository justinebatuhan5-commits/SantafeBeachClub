<?php
declare(strict_types=1);

// Preserve the guest breakdown so invoices and receipts can distinguish the
// room's included occupants from added adults and children.
return static function (mysqli $db): void {
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $check = $db->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ? AND column_name = ?'
    );

    foreach ([
        'extra_adults' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER guests_count',
        'extra_children' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER extra_adults',
    ] as $column => $definition) {
        $table = 'bookings';
        $check->bind_param('sss', $schema, $table, $column);
        $check->execute();
        if (!$check->get_result()->fetch_row()) {
            $db->query("ALTER TABLE bookings ADD COLUMN {$column} {$definition}");
        }
    }
    $check->close();
};
