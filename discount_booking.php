<?php
// Compatibility for private links already sent with the old root-level path.
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$target = 'frontend/discount_booking.php';
if (isset($_GET['token']) && is_string($_GET['token']) && preg_match('/^[a-f0-9]{64}$/D', $_GET['token'])) {
    $target .= '#access=' . rawurlencode($_GET['token']);
} elseif (isset($_GET['booking'])) {
    $target .= '?booking=' . (int)$_GET['booking'];
}
header('Location: ' . $target, true, 302);
exit;
