<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../backend/controllers/guests_controller.php';

function assert_guests_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$source = file_get_contents(__DIR__ . '/../backend/controllers/guests_controller.php');
assert_guests_test($source !== false, 'Controller file is readable');
assert_guests_test(
    !preg_match('/SELECT\s+guest_name,\s+guest_email,\s+guest_type\b/i', (string)$source),
    'Guests summary query does not select unaggregated guest_type under ONLY_FULL_GROUP_BY'
);
assert_guests_test(
    str_contains((string)$source, 'MIN(TRIM(guest_name)) AS guest_name')
    && str_contains((string)$source, 'ORDER BY MIN(TRIM(guest_name)) ASC'),
    'Guests summary query aggregates non-grouped columns and orders by aggregated guest_name'
);

assert_guests_test(
    sfbc_guests_lookup_key('  Jane@Example.com ', 'Jane Doe') === 'jane@example.com',
    'Lookup key normalizes email casing and whitespace'
);
assert_guests_test(
    sfbc_guests_lookup_key('', '  Walk In Guest ') === 'walk in guest',
    'Lookup key falls back to guest name when email is empty string'
);
assert_guests_test(
    sfbc_guests_lookup_key(null, 'Walk In Guest') === 'walk in guest',
    'Lookup key falls back to guest name when email is null'
);

assert_guests_test(
    sfbc_guests_resolve_type([
        ['guest_type' => 'Returning Guest'],
        ['guest_type' => 'First Visit'],
    ]) === 'Returning Guest',
    'Type resolver upgrades returning guests over first visit'
);
assert_guests_test(
    sfbc_guests_resolve_type([
        ['guest_type' => 'Returning Guest'],
        ['guest_type' => 'VIP Member'],
    ]) === 'VIP Member',
    'Type resolver prefers VIP Member tier across booking history'
);
assert_guests_test(
    sfbc_guests_resolve_type([
        ['guest_type' => 'Walk-in'],
        ['guest_type' => 'First Visit'],
    ]) === 'Walk-in',
    'Type resolver keeps newest custom tier when no higher fixed tier exists'
);

echo "All guests controller checks passed.\n";
