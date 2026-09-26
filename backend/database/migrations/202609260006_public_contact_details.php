<?php
declare(strict_types=1);

return static function (mysqli $db): void {
    $contacts = [
        'property_phone' => '09505223146',
        'property_email' => 'Justinebatuhan017@gmail.com',
    ];
    $statement = $db->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($contacts as $key => $value) {
        $statement->bind_param('ss', $key, $value);
        $statement->execute();
    }
    $statement->close();
};
