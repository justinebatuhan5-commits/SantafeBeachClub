<?php
$initialOutputLevel = ob_get_level();
ob_start();
ini_set('display_errors', '0');

function discount_document_clean_output(int $initialOutputLevel): void
{
    while (ob_get_level() > $initialOutputLevel) ob_end_clean();
}

require_once __DIR__ . '/../helpers/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/private_storage_helper.php';
require_once __DIR__ . '/../services/discount_service.php';
if (!in_array($_SESSION['admin_role'] ?? '', ['admin','receptionist'], true)) {
    discount_document_clean_output($initialOutputLevel);
    http_response_code(403);
    exit;
}
$id = (int)($_GET['booking'] ?? 0);
$pid = (int)($_GET['payment'] ?? 0);
if ($pid) {
    $r=pm_query($conn,'SELECT p.receipt_url FROM payments p JOIN discount_payments x ON x.payment_id=p.id WHERE p.id=? AND x.booking_id=?',[$pid,$id])->get_result()->fetch_assoc();
    $key = preg_replace('~^storage/~','',$r['receipt_url'] ?? '');
} else {
    $r=pm_query($conn,'SELECT id_file FROM booking_discounts WHERE booking_id=?',[$id])->get_result()->fetch_assoc();
    $key='discount_ids/'.($r['id_file']??'');
}
try {
    $path = private_storage_file($key);
} catch (Throwable $e) {
    error_log("discount_document error for key [{$key}]: " . $e->getMessage());
    $path = null;
}
if (!$path) {
    error_log("discount_document: File not found or inaccessible for key [{$key}].");
    discount_document_clean_output($initialOutputLevel);
    http_response_code(404); 
    exit('Document unavailable.'); 
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'], true)) {
    error_log("discount_document: Unsupported MIME type [{$mime}] for key [{$key}].");
    discount_document_clean_output($initialOutputLevel);
    http_response_code(415);
    exit('Unsupported document type.');
}
// Viewing must not fail merely because an audit insert is temporarily unavailable.
try {
    discount_audit($conn,$id,$_SESSION['admin_username']??'Staff',$pid?'Receipt viewed':'ID viewed');
} catch (Throwable $e) {
    error_log("discount_document: Audit logging failed for booking [{$id}]: " . $e->getMessage());
}
// Included configuration/services must never leak whitespace, warnings or notices
// into the binary response. Even one leading byte makes a valid image undecodable.
discount_document_clean_output($initialOutputLevel);
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox'); header('Referrer-Policy: no-referrer');
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="verification-image.'.pathinfo($path,PATHINFO_EXTENSION).'"');
if (readfile($path) === false) {
    error_log("discount_document: Failed while streaming key [{$key}].");
}
