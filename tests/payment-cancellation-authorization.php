<?php
declare(strict_types=1);

// Static regression guard: payment cancellation must be authorized by the
// random cancellation token and must never accept a numeric booking ID alone.
$source = file_get_contents(__DIR__ . '/../frontend/book.php');
if ($source === false) {
    throw new RuntimeException('Unable to read frontend/book.php.');
}

if (str_contains($source, 'cancel_bk_id')) {
    throw new RuntimeException('Public numeric booking cancellation is still enabled.');
}
if (!str_contains($source, "\$_GET['cancel_token']")) {
    throw new RuntimeException('Payment cancellation does not consume a cancellation token.');
}
if (!str_contains($source, 'WHERE cancellation_token = ?')) {
    throw new RuntimeException('Cancellation token is not verified by a prepared database query.');
}
if (!str_contains($source, "preg_match('/\\A[a-f0-9]{32}\\z/'")) {
    throw new RuntimeException('Cancellation token format is not validated.');
}

echo "Payment cancellation requires a random booking token.\n";
