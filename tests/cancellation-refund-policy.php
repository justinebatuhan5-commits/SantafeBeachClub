<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/helpers/business_time_helper.php';

function policy_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$futureCheckIn = date('Y-m-d', strtotime('+10 days'));
$futureCheckOut = date('Y-m-d', strtotime('+11 days'));
$refundable = sf_get_cancellation_policy($futureCheckIn, $futureCheckOut);
policy_check($refundable['is_expired'] === false, 'Future cancellation window should be open.');
policy_check($refundable['refund_percentage'] === 100, 'Cancellation before the deadline must be fully refundable.');

$nearCheckIn = date('Y-m-d', strtotime('-1 day'));
$nearCheckOut = date('Y-m-d');
$expired = sf_get_cancellation_policy($nearCheckIn, $nearCheckOut);
policy_check($expired['is_expired'] === true, 'Near-term cancellation window should be expired.');
policy_check($expired['refund_percentage'] === 0, 'Cancellation after the deadline must be non-refundable.');

echo "Cancellation policy supplies 100% before the deadline and 0% after it.\n";
