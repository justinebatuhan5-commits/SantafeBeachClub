<?php
/**
 * Retired public booking endpoint.
 *
 * Public booking requests must use frontend/book.php, which validates the
 * complete payment/receipt flow. Staff-created reservations use
 * staff_create_booking.php, which requires an authenticated staff session and
 * a CSRF token. This endpoint intentionally never creates a booking.
 */

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'This public booking endpoint has been retired. Use the secure booking flow.'
]);
