<?php
/**
 * api_heartbeat.php — Session Keepalive & Activity Pulse Endpoint
 * Santa Fe Beach Club
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Check if user is authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Session expired or invalid'
    ]);
    exit;
}

// Refresh session activity timestamp
$_SESSION['last_activity'] = time();

echo json_encode([
    'success' => true,
    'authenticated' => true,
    'last_activity' => $_SESSION['last_activity'],
    'role' => $_SESSION['admin_role'] ?? 'receptionist'
]);
