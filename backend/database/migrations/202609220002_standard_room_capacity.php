<?php
declare(strict_types=1);

// Align existing room records with the approved Standard Room capacity.
return static function (mysqli $db): void {
    $stmt = $db->prepare("UPDATE rooms SET capacity = 5 WHERE type = 'standard_room'");
    $stmt->execute();
    $stmt->close();
};
