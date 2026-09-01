<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/../backend/config/db.php";
require_once __DIR__ . "/../backend/helpers/csrf_helper.php";
require_once __DIR__ . "/../backend/helpers/rate_limiter.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed."]);
    exit;
}

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Security verification failed. Please refresh the page."]);
    exit;
}

RateLimiter::enforce($conn, 'submit_review', 5, 600);

$guest_name     = trim($_POST["guest_name"] ?? "");
$guest_location = trim($_POST["guest_location"] ?? "Verified Guest");
$rating         = max(1, min(5, intval($_POST["rating"] ?? 5)));
$review_text    = trim($_POST["review_text"] ?? "");
$booking_id     = !empty($_POST["booking_id"]) ? intval($_POST["booking_id"]) : null;

if (empty($guest_name) || empty($review_text)) {
    echo json_encode(["success" => false, "error" => "Name and review message are required."]);
    exit;
}

if (strlen($guest_name) > 100 || strlen($review_text) < 5) {
    echo json_encode(["success" => false, "error" => "Please write a descriptive review (at least 5 characters)."]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO reviews (booking_id, guest_name, guest_location, rating, review_text, is_approved) VALUES (?, ?, ?, ?, ?, 1)");
$stmt->bind_param("issis", $booking_id, $guest_name, $guest_location, $rating, $review_text);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Review submitted successfully!"]);
} else {
    echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
}
$stmt->close();
