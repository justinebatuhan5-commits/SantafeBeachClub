<?php
$_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';
require_once __DIR__ . '/backend/config/env_loader.php';
load_env(__DIR__ . '/.env');
require_once __DIR__ . '/backend/config/db.php';

echo "DB Host: " . (getenv('DB_HOST') ?: 'localhost') . PHP_EOL;
echo "DB Name: " . (getenv('DB_NAME') ?: 'santafe_db') . PHP_EOL;
echo "DB User: " . (getenv('DB_USER') ?: 'root') . PHP_EOL;
echo "Server info: " . $conn->server_info . PHP_EOL;
echo "DB in use: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . PHP_EOL;

$r = $conn->query("SELECT COUNT(*) as cnt FROM booking_discounts");
echo "Discount rows: " . $r->fetch_assoc()['cnt'] . PHP_EOL;

$r2 = $conn->query("SELECT COUNT(*) as cnt FROM bookings");
echo "Total bookings: " . $r2->fetch_assoc()['cnt'] . PHP_EOL;

// Show env file
echo PHP_EOL . "=== .env content ===" . PHP_EOL;
$env = file_get_contents(__DIR__ . '/.env');
// Hide sensitive data
$env = preg_replace('/(PASSWORD\s*=\s*)\S+/', '$1***', $env);
echo $env . PHP_EOL;
