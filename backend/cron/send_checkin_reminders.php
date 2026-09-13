<?php
/**
 * Automated Cron Job Script: Pre-Arrival Check-in Reminders
 *
 * This script can be run:
 * 1. Via CLI / Cron / Windows Task Scheduler:
 *    php -f c:\xampp\htdocs\SantaBeachClub-BookingSystem\backend\cron\send_checkin_reminders.php
 * 2. Via HTTP request (secured with CRON_SECRET or Admin Session):
 *    http://localhost/SantaBeachClub-BookingSystem/backend/cron/send_checkin_reminders.php?key=sfbc_cron_secret_2026
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/prearrival_reminder_helper.php';

// If called via HTTP, verify authorized key or admin session
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $secret_key = getenv('CRON_SECRET') ?: 'sfbc_cron_secret_2026';
    $provided_key = $_GET['key'] ?? '';
    $is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

    if (!$is_admin && $provided_key !== $secret_key) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden: Invalid or missing authorization key.']);
        exit;
    }
}

$days_ahead = isset($_GET['days']) ? max(1, intval($_GET['days'])) : 2;
$results = sf_send_prearrival_checkin_reminders($conn, $days_ahead);

if ($is_cli) {
    echo "[" . date('Y-m-d H:i:s') . "] Pre-Arrival Reminder Cron completed.\n";
    echo "Checked: {$results['checked']}, Sent: {$results['sent']}, Failed: {$results['failed']}\n";
    if (!empty($results['errors'])) {
        echo "Errors:\n" . implode("\n", $results['errors']) . "\n";
    }
    exit(0);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $results
    ], JSON_PRETTY_PRINT);
    exit;
}
