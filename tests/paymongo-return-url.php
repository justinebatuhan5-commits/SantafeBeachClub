<?php
$book = file_get_contents(__DIR__ . '/../frontend/book.php')
    . file_get_contents(__DIR__ . '/../backend/controllers/booking_controller.php');
$balance = file_get_contents(__DIR__ . '/../frontend/balance_payment.php')
    . file_get_contents(__DIR__ . '/../backend/controllers/balance_payment_controller.php')
    . file_get_contents(__DIR__ . '/../frontend/views/balance_payment/_card.php');
$success = file_get_contents(__DIR__ . '/../frontend/payment_success.php')
    . file_get_contents(__DIR__ . '/../backend/controllers/payment_success_controller.php')
    . file_get_contents(__DIR__ . '/../frontend/views/payment_success/_card.php');

if ($book === false || $balance === false || $success === false) {
    throw new RuntimeException('Unable to read PayMongo return-flow files.');
}
if (str_contains($book, '{CHECKOUT_SESSION_ID}') || str_contains($balance, '{CHECKOUT_SESSION_ID}')) {
    throw new RuntimeException('PayMongo success URLs must not contain unsupported placeholders.');
}
foreach (['JOIN payments p ON p.id = c.payment_id', "p.status IN ('pending','verified')", 'PayMongoService::retrieveCheckoutSession($sessionId)'] as $required) {
    if (!str_contains($success, $required)) {
        throw new RuntimeException('Missing secure return-session resolution: ' . $required);
    }
}

echo "PayMongo return URLs resolve their stored checkout without trusting an unsupported redirect placeholder.\n";
