<?php

require_once __DIR__ . '/../helpers/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!in_array($_SESSION['admin_role'] ?? '', ['admin', 'receptionist'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Staff access required.']);
    exit;
}

$token = $_POST['csrf_token'] ?? null;
if (!is_string($token) || !verify_csrf_token($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token. Please refresh the page.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../services/booking_service.php';

RateLimiter::enforce($conn, 'staff_create_booking', 15, 60);

try {
    $status = $_POST['status'] ?? 'Pending';
    if (!is_string($status) || !in_array($status, ['Pending', 'Checked In'], true)) {
        throw new InvalidArgumentException('Invalid initial booking status.');
    }
    echo json_encode(createBooking(getPdoConnection(), $_POST, $status));
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
