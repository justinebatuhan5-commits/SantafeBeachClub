<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function check_public_contact(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$root = dirname(__DIR__);
$contact = file_get_contents($root . '/frontend/contact.php')
    . "\n" . file_get_contents($root . '/frontend/views/contact/_content.php');
$mailer = file_get_contents($root . '/backend/services/mailer.php');
$terms = file_get_contents($root . '/terms.php');
$schema = file_get_contents($root . '/backend/database/migrations/202609220001_request_schema.php');
$contactMigration = file_get_contents($root . '/backend/database/migrations/202609260006_public_contact_details.php');

foreach ([$contact, $mailer, $schema] as $source) {
    check_public_contact(str_contains($source, '0950 522 3146') || str_contains($source, '09505223146'), 'Public phone number is current');
}
foreach ([$contact, $mailer, $terms, $schema] as $source) {
    check_public_contact(str_contains($source, 'Justinebatuhan017@gmail.com'), 'Public email address is current');
}
check_public_contact(!str_contains($contact, 'info@santafebeachclub.com') && !str_contains($contact, 'bookings@santafebeachclub.com'), 'Contact page no longer displays retired email addresses');
check_public_contact(str_contains($contactMigration, "'property_phone' => '09505223146'") && str_contains($contactMigration, "'property_email' => 'Justinebatuhan017@gmail.com'"), 'Existing installations receive the updated contact settings');
