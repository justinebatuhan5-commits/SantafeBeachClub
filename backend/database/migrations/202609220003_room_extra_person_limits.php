<?php
declare(strict_types=1);

// Configurable extra-person limits for each room type.
return static function (mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS room_extra_person_limits (
        room_type VARCHAR(50) NOT NULL PRIMARY KEY,
        max_extra_adults TINYINT UNSIGNED NOT NULL DEFAULT 0,
        max_extra_children TINYINT UNSIGNED NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $limits = [
        ['beachview_duplex', 1, 1],
        ['seaview_duplex',   1, 1],
        ['beach_villa',      3, 2],
        ['standard_room',    3, 0],
        ['standard_king',    1, 0],
    ];

    $stmt = $db->prepare(
        'INSERT INTO room_extra_person_limits (room_type, max_extra_adults, max_extra_children)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
           max_extra_adults = VALUES(max_extra_adults),
           max_extra_children = VALUES(max_extra_children)'
    );
    foreach ($limits as [$roomType, $adults, $children]) {
        $stmt->bind_param('sii', $roomType, $adults, $children);
        $stmt->execute();
    }
    $stmt->close();
};
