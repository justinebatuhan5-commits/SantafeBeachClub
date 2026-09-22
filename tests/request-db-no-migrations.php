<?php
declare(strict_types=1);

$requestFiles = [
    __DIR__ . '/../backend/config/db.php',
    __DIR__ . '/../backend/services/discount_service.php',
    __DIR__ . '/../backend/services/paymongo_confirmation.php',
];
$forbidden = ['CREATE TABLE', 'ALTER TABLE', 'CREATE DATABASE', 'DELETE FROM rooms', 'DELETE FROM room_types'];

foreach ($requestFiles as $file) {
    $source = file_get_contents($file);
    foreach ($forbidden as $sql) {
        if (stripos($source, $sql) !== false) {
            throw new RuntimeException(basename($file) . " contains request-time database mutation: {$sql}");
        }
    }
}

$bootstrap = file_get_contents(__DIR__ . '/../backend/config/db.php');
foreach (['$_GET', '_db_migrated', 'discount_maintenance($conn)'] as $trigger) {
    if (str_contains($bootstrap, $trigger)) {
        throw new RuntimeException("db.php contains a request-triggered migration or maintenance hook: {$trigger}");
    }
}

echo "Request database bootstrap contains no schema, catalog, or maintenance mutations.\n";
