<?php
require_once __DIR__ . '/../backend/helpers/auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/balance_payment_helper.php';
require_once __DIR__ . '/../backend/controllers/checkin_legacy_controller.php';

// Reception and administration now share one check-in workflow. Keep this
// legacy route only as a safe compatibility redirect for old links and QR URLs.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = http_build_query($_GET);
    header('Location: admin_checkin' . ($query !== '' ? '?' . $query : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sfbc_handle_legacy_checkin_post($conn);
}

header('Location: admin_checkin');
exit;
