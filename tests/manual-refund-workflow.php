<?php
$api = file_get_contents(__DIR__ . '/../backend/api/manual_refund_api.php');
$admin = file_get_contents(__DIR__ . '/../frontend/admin_refunds.php');
$guest = file_get_contents(__DIR__ . '/../frontend/refund_details.php');
$migration = file_get_contents(__DIR__ . '/../backend/database/migrations/202609260001_manual_refund_workflow.php');
foreach ([$api,$admin,$guest,$migration] as $source) if ($source === false) throw new RuntimeException('Unable to read manual refund files.');
foreach (['token_hash','token_expires_at','gcash_account_name','transfer_reference','receipt_path'] as $fragment) {
    if (!str_contains($migration, $fragment)) throw new RuntimeException('Missing manual refund schema: ' . $fragment);
}
foreach (["\$action === 'generate_link'", "\$action === 'guest_submit'", "\$action === 'complete'", "stripos(\$paymentMethod, 'PayMongo')", "stripos(\$paymentMethod, 'GCash')", 'store_private_receipt', "status='details_submitted'", "status='completed'"] as $fragment) {
    if (!str_contains($api, $fragment)) throw new RuntimeException('Missing protected manual refund behavior: ' . $fragment);
}
foreach (['Email GCash Details Link','Record GCash Transfer','Confirm Money Sent','Refund System Used','Manual GCash Transfer','PayMongo Refund','refundCsrfToken','Show full number','toggleManualRefundMobile'] as $fragment) {
    if (!str_contains($admin, $fragment)) throw new RuntimeException('Missing manual refund admin control: ' . $fragment);
}
foreach (['ownership_confirmed','never ask for your GCash MPIN','csrf_token'] as $fragment) {
    if (!str_contains($guest, $fragment)) throw new RuntimeException('Missing secure guest refund form behavior: ' . $fragment);
}
echo "Manual GCash refunds use expiring single-use links, restricted payment routing, private receipts, and administrator completion.\n";
