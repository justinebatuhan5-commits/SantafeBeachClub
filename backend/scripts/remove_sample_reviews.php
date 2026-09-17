<?php
// Run once against the local XAMPP database; never fall back to a remote server.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = null;
foreach ([3307, 3306] as $port) {
    try { $conn = new mysqli('127.0.0.1', 'root', '', 'santafe_beach_club', $port); break; }
    catch (mysqli_sql_exception $e) { /* Try the other local XAMPP port. */ }
}
if (!$conn) { fwrite(STDERR, "Local database unavailable; no reviews changed.\n"); exit(1); }
$samples = [
    ['Maria R.', 'Cebu, Philippines', 'Absolutely breathtaking. We woke up to the sound of waves every morning. The staff was warm, attentive, and made our anniversary truly unforgettable.'],
    ['James L.', 'Manila, Philippines', "The Beachview Duplex was perfect for our family. The kids loved the beach access, and the room was immaculate. We'll definitely be back next summer!"],
    ['Sarah C.', 'Makati, Philippines', 'Santa Fe Beach Club is a hidden gem. The calmer waters were perfect for swimming, and the whole property has a peaceful, boutique hotel feel.'],
];
$stmt = $conn->prepare('DELETE FROM reviews WHERE BINARY guest_name = ? AND BINARY guest_location = ? AND BINARY review_text = ? AND rating = 5 AND booking_id IS NULL');
$removed = 0;
$conn->begin_transaction();
try {
    foreach ($samples as [$name, $location, $review]) {
        $stmt->bind_param('sss', $name, $location, $review);
        $stmt->execute();
        $removed += $stmt->affected_rows;
    }
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
echo "Removed $removed matching sample reviews.\n";
echo 'Remaining reviews: ' . $conn->query('SELECT COUNT(*) FROM reviews')->fetch_row()[0] . "\n";
