<?php
$api = file_get_contents(__DIR__ . '/../backend/api/manual_refund_api.php');
$admin = file_get_contents(__DIR__ . '/../frontend/admin_refunds.php');
$guest = file_get_contents(__DIR__ . '/../frontend/refund_details.php');
$migration = file_get_contents(__DIR__ . '/../backend/database/migrations/202609260001_manual_refund_workflow.php');
$confirmationMigration = file_get_contents(__DIR__ . '/../backend/database/migrations/202609260003_refund_guest_confirmation.php');
$confirmationPage = file_get_contents(__DIR__ . '/../frontend/refund_confirmation.php');
$mailer = file_get_contents(__DIR__ . '/../backend/services/mailer.php');
$mfaMigration = file_get_contents(__DIR__ . '/../backend/database/migrations/202609260004_refund_phone_mfa.php');
$smsHelper = file_get_contents(__DIR__ . '/../backend/helpers/sms_helper.php');
foreach ([$api,$admin,$guest,$migration] as $source) if ($source === false) throw new RuntimeException('Unable to read manual refund files.');
foreach (['token_hash','token_expires_at','gcash_account_name','transfer_reference','receipt_path'] as $fragment) {
    if (!str_contains($migration, $fragment)) throw new RuntimeException('Missing manual refund schema: ' . $fragment);
}
foreach (['confirmation_token_hash','confirmation_token_expires_at','guest_confirmed_at'] as $fragment) {
    if (!str_contains($confirmationMigration, $fragment)) throw new RuntimeException('Missing guest confirmation schema: ' . $fragment);
}
foreach (['verification_otp_hash','verification_otp_expires_at','verification_attempts','verification_send_count','phone_verified_at','verification_locked_at'] as $fragment) {
    if (!str_contains($mfaMigration, $fragment)) throw new RuntimeException('Missing refund phone MFA schema: ' . $fragment);
}
foreach (["\$action === 'generate_link'", "\$action === 'guest_submit'", "\$action === 'complete'", "stripos(\$paymentMethod, 'PayMongo')", "stripos(\$paymentMethod, 'GCash')", 'store_private_receipt', "status='details_submitted'", "status='completed'"] as $fragment) {
    if (!str_contains($api, $fragment)) throw new RuntimeException('Missing protected manual refund behavior: ' . $fragment);
}
foreach (["\$action === 'send_otp'", "\$action === 'verify_otp'", "\$action === 'reset_verification'", 'password_hash($otp', 'password_verify($otp', 'phone_verified_at IS NOT NULL', 'RateLimiter::check'] as $fragment) {
    if (!str_contains($api, $fragment)) throw new RuntimeException('Missing refund phone MFA protection: ' . $fragment);
}
foreach (['SMS_PROVIDER','TWILIO_ACCOUNT_SID','SEMAPHORE_API_KEY','sf_normalize_e164','sf_mask_phone'] as $fragment) {
    if (!str_contains($smsHelper, $fragment)) throw new RuntimeException('Missing provider-independent SMS behavior: ' . $fragment);
}
foreach (['guest_confirmed_at=NOW()','confirmation_token_hash=NULL','verify_csrf_token()','Manual Refund Receipt Confirmed'] as $fragment) {
    if (!str_contains($confirmationPage, $fragment)) throw new RuntimeException('Missing secure guest confirmation behavior: ' . $fragment);
}
foreach (['confirmation_token_hash','refund_confirmation.php?token=','Confirm Refund Received'] as $fragment) {
    if (!str_contains($api . $mailer, $fragment)) throw new RuntimeException('Missing confirmation email workflow: ' . $fragment);
}
foreach (['Email GCash Details Link','Record GCash Transfer','Confirm Money Sent','Refund System Used','Manual GCash Transfer','PayMongo Refund','refundCsrfToken','Show full number','toggleManualRefundMobile'] as $fragment) {
    if (!str_contains($admin, $fragment)) throw new RuntimeException('Missing manual refund admin control: ' . $fragment);
}
foreach (['ownership_confirmed','never ask for your GCash MPIN','csrf_token','Verify your saved phone','send_otp','verify_otp'] as $fragment) {
    if (!str_contains($guest, $fragment)) throw new RuntimeException('Missing secure guest refund form behavior: ' . $fragment);
}
foreach (["\$_SESSION['manual_refund_token']", "header('Location: refund_details.php'", "header('Referrer-Policy: no-referrer'"] as $fragment) {
    if (!str_contains($guest, $fragment)) throw new RuntimeException('Refund link token is not removed from the browser address: ' . $fragment);
}
echo "Manual GCash refunds use expiring single-use links, restricted payment routing, private receipts, and administrator completion.\n";
