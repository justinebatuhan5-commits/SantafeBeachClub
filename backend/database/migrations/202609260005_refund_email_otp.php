<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $schema = $db->query('SELECT DATABASE()')->fetch_row()[0];
    $column = 'email_verified_at';
    $check = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=? AND table_name='manual_refund_details' AND column_name=?");
    $check->bind_param('ss', $schema, $column); $check->execute();
    if (!$check->get_result()->fetch_row()) $db->query('ALTER TABLE manual_refund_details ADD COLUMN email_verified_at DATETIME DEFAULT NULL');
    $check->close();
};
