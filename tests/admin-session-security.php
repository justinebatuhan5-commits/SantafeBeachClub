<?php
$helper = file_get_contents(__DIR__ . '/../backend/helpers/admin_session_helper.php');
$otp = file_get_contents(__DIR__ . '/../frontend/verify_otp.php');
$refund = file_get_contents(__DIR__ . '/../backend/api/refund_request_api.php');
$manual = file_get_contents(__DIR__ . '/../backend/api/manual_refund_api.php');
$settings = file_get_contents(__DIR__ . '/../frontend/settings.php');
$sessions = file_get_contents(__DIR__ . '/../frontend/admin_sessions.php');
$migration = file_get_contents(__DIR__ . '/../backend/database/migrations/202609260007_admin_session_security.php');

$checks = [
    'session records use a one-way PHP session hash' => str_contains($helper, "hash('sha256', session_id())"),
    'successful MFA registers the authenticated browser session' => str_contains($otp, 'admin_session_register($conn, $adminId'),
    'refund state changes still require administrator access' => str_contains($refund, 'require_admin_access()') && !str_contains($refund, 'require_recent_admin_password_json()'),
    'manual refund processing still validates the administrator session' => str_contains($manual, 'manual_refund_admin()') && !str_contains($manual, 'require_recent_admin_password_json()'),
    'security-setting changes require recent password confirmation' => str_contains($settings, '$stepUpActions'),
    'current session cannot be remotely revoked from the session list' => str_contains($sessions, 'session_hash<>?'),
    'session schema is installed only through a CLI migration' => str_contains($migration, 'CREATE TABLE IF NOT EXISTS admin_sessions') && !str_contains($helper, 'CREATE TABLE'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
    echo "PASS: {$label}\n";
}
