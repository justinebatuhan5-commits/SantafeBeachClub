<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $stmt = $db->prepare('INSERT IGNORE INTO settings (setting_key,setting_value) VALUES (?,?)');
    $value = '1';
    foreach (['refund_paymongo_enabled','refund_manual_gcash_enabled'] as $key) {
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
    }
    $stmt->close();
};
