<?php
declare(strict_types=1);

/**
 * Regression & contract tests for the native PHP Automation & Scheduler API.
 *
 * Ensures the Admin Automation page does not depend on the excluded Laravel
 * container/directory, enforces auth/CSRF/rate-limiting, preserves the 5 task
 * definitions, guards unpaid cleanup against verified payments, and formats
 * activity timestamps safely.
 */

require_once __DIR__ . '/../backend/services/automation_service.php';

$root = dirname(__DIR__);

$assert = static function (bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$label}\n");
};

// --- 1. Contract checks across JS, page wrapper, and API endpoint ---
$jsSource = file_get_contents($root . '/frontend/assets/js/pages/admin-automation.js');
$phpPageSource = file_get_contents($root . '/frontend/admin_automation.php');
$apiSource = file_get_contents($root . '/backend/api/automation_api.php');
$serviceSource = file_get_contents($root . '/backend/services/automation_service.php');

$assert(is_string($jsSource) && $jsSource !== '', 'admin-automation.js is readable');
$assert(is_string($phpPageSource) && $phpPageSource !== '', 'admin_automation.php is readable');
$assert(is_string($apiSource) && $apiSource !== '', 'automation_api.php is readable');
$assert(is_string($serviceSource) && $serviceSource !== '', 'automation_service.php is readable');

$assert(
    !str_contains($jsSource, '../laravel_core/'),
    'admin-automation.js no longer targets excluded ../laravel_core API path'
);
$assert(
    str_contains($jsSource, 'automation_api.php') && str_contains($jsSource, 'SFBC_AUTOMATION_CONFIG'),
    'admin-automation.js reads native automation_api.php configuration'
);
$assert(
    str_contains($jsSource, 'X-CSRF-TOKEN') && str_contains($jsSource, "Accept: 'application/json'"),
    'admin-automation.js sends Accept: application/json and X-CSRF-TOKEN'
);

$assert(
    str_contains($phpPageSource, 'get_csrf_token()') && str_contains($phpPageSource, 'SFBC_AUTOMATION_CONFIG'),
    'admin_automation.php exposes SFBC_AUTOMATION_CONFIG with CSRF token'
);

$assert(
    str_contains($apiSource, "require_api_auth(\$conn, 'admin')"),
    'automation_api.php enforces admin API auth'
);
$assert(
    str_contains($apiSource, 'admin_session_validate($conn)'),
    'automation_api.php validates active admin session state'
);
$assert(
    str_contains($apiSource, 'verify_csrf_token()'),
    'automation_api.php enforces CSRF verification on POST requests'
);
$assert(
    str_contains($apiSource, "RateLimiter::enforce(\$conn, 'automation_api'"),
    'automation_api.php enforces rate limiting'
);

$assert(
    str_contains($serviceSource, 'NOT EXISTS') && str_contains($serviceSource, "LOWER(p.status) = 'verified'"),
    'Pending booking cleanup excludes bookings with verified payments'
);
$assert(
    str_contains($serviceSource, 'FOR UPDATE') && str_contains($serviceSource, 'begin_transaction'),
    'Pending booking cleanup uses row locking inside a transaction'
);

// --- 2. Task catalog & unit behavior checks ---
$expectedIds = [
    'reservations:send-reminders',
    'staff:notify-checkins',
    'staff:notify-checkouts',
    'reservations:process-pending',
    'reports:generate-daily',
];

$defs = sfbc_automation_task_definitions();
$assert(count($defs) === 5, 'Task definitions contain all 5 scheduled jobs');
$assert(array_column($defs, 'id') === $expectedIds, 'Task IDs match frontend trigger contract');

$manila = new DateTimeZone('Asia/Manila');
$iso = sfbc_automation_format_log_timestamp('2026-11-15 16:43:12', $manila);
$assert(
    $iso === '2026-11-15T16:43:12+08:00',
    'Valid database timestamp formats as ISO-8601 ATOM in resort timezone'
);
$assert(
    sfbc_automation_format_log_timestamp('', $manila) === null
    && sfbc_automation_format_log_timestamp('0000-00-00 00:00:00', $manila) === null,
    'Blank and zero-date timestamps return null cleanly'
);

$assert(sfbc_automation_parse_bool('0', true) === false, 'parse_bool handles disabled string "0"');
$assert(sfbc_automation_parse_bool('false', true) === false, 'parse_bool handles disabled string "false"');
$assert(sfbc_automation_parse_bool('1', false) === true, 'parse_bool handles enabled string "1"');
$assert(sfbc_automation_parse_bool(null, true) === true, 'parse_bool falls back to default on null');

$summary = sfbc_automation_summarize_bookings([
    ['id' => 101, 'guest_name' => 'Ana', 'accommodation_name' => 'Suite A'],
    ['id' => 102, 'guest_name' => 'Ben', 'accommodation_name' => 'Villa B'],
    ['id' => 103, 'guest_name' => 'Cara', 'accommodation_name' => 'Room C'],
], 2);
$assert(
    $summary === 'REF-101 (Ana - Suite A), REF-102 (Ben - Villa B), +1 more',
    'Digest summary formats sample rows and truncates overflow cleanly'
);

final class sfbc_test_no_db_mysqli extends mysqli
{
    public function __construct()
    {
        // Deliberately avoid parent::__construct() so unit tests run offline.
    }

    public function prepare(string $query): mysqli_stmt|false
    {
        return false;
    }
}

$stubConn = new sfbc_test_no_db_mysqli();

$tasksWithFallback = sfbc_automation_tasks($stubConn);
$assert(count($tasksWithFallback) === 5, 'sfbc_automation_tasks falls back cleanly when settings query fails');
$assert($tasksWithFallback[0]['enabled'] === true, 'Tasks default to enabled=true when settings are unavailable');

$invalidEmpty = sfbc_automation_trigger_task($stubConn, '', 'tester');
$assert(
    $invalidEmpty['status'] === 400 && ($invalidEmpty['payload']['success'] ?? true) === false,
    'Trigger rejects empty task ID with HTTP 400'
);

$invalidUnknown = sfbc_automation_trigger_task($stubConn, 'system:drop-database', 'tester');
$assert(
    $invalidUnknown['status'] === 400
    && ($invalidUnknown['payload']['error'] ?? '') === 'Invalid automation task ID.',
    'Trigger rejects unknown task ID with HTTP 400'
);

fwrite(STDOUT, "ALL AUTOMATION API TESTS PASSED\n");
