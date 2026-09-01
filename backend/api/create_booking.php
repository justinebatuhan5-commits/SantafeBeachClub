<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/cors_helper.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_once __DIR__ . '/../services/booking_service.php';

handle_cors();
RateLimiter::enforce($conn, 'create_booking', 15, 60);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = getPdoConnection();

    $result = createBooking($pdo, [
        'room_type_id' => $_POST['room_type_id'] ?? null,
        'check_in' => $_POST['check_in'] ?? null,
        'check_out' => $_POST['check_out'] ?? null,
        'guest_name' => $_POST['guest_name'] ?? null,
        'guest_email' => $_POST['guest_email'] ?? null,
        'status' => $_POST['status'] ?? 'pending',
    ]);

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
