<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $columns = [
        'confirmation_token_hash' => 'CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL',
        'confirmation_token_expires_at' => 'DATETIME DEFAULT NULL',
        'guest_confirmed_at' => 'DATETIME DEFAULT NULL',
    ];
    $check = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=? AND table_name='manual_refund_details' AND column_name=?");
    foreach ($columns as $column => $definition) {
        $check->bind_param('ss', $schema, $column);
        $check->execute();
        if (!$check->get_result()->fetch_row()) $db->query("ALTER TABLE manual_refund_details ADD COLUMN `{$column}` {$definition}");
    }
    $check->close();

    // TiDB does not support inline UNIQUE constraints in ALTER TABLE ... ADD COLUMN.
    $idxCheck = $db->prepare("SELECT 1 FROM information_schema.statistics WHERE table_schema=? AND table_name='manual_refund_details' AND column_name='confirmation_token_hash' AND non_unique=0 LIMIT 1");
    $idxCheck->bind_param('s', $schema);
    $idxCheck->execute();
    if (!$idxCheck->get_result()->fetch_row()) {
        $db->query('ALTER TABLE manual_refund_details ADD UNIQUE KEY uq_manual_refund_confirmation_token (confirmation_token_hash)');
    }
    $idxCheck->close();
};
