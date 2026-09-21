<?php
require_once __DIR__ . '/../helpers/auth_check.php';
require_once __DIR__ . '/../config/db.php';
if (!in_array($_SESSION['admin_role'] ?? '', ['admin','receptionist'], true)) { http_response_code(403); exit; }
$id = (int)($_GET['booking'] ?? 0);
$pid = (int)($_GET['payment'] ?? 0);
if ($pid) {
    $r=pm_query($conn,'SELECT p.receipt_url FROM payments p JOIN discount_payments x ON x.payment_id=p.id WHERE p.id=? AND x.booking_id=?',[$pid,$id])->get_result()->fetch_assoc();
    $key = preg_replace('~^storage/~','',$r['receipt_url'] ?? '');
} else {
    $r=pm_query($conn,'SELECT id_file FROM booking_discounts WHERE booking_id=?',[$id])->get_result()->fetch_assoc();
    $key='discount_ids/'.($r['id_file']??'');
}
$path=private_storage_file($key);
if (!$path) { http_response_code(404); exit('Document unavailable.'); }
discount_audit($conn,$id,$_SESSION['admin_username']??'Staff',$pid?'Receipt viewed':'ID viewed');
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox'); header('Referrer-Policy: no-referrer');
header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($path));
header('Content-Disposition: inline; filename="verification-image.'.pathinfo($path,PATHINFO_EXTENSION).'"');
readfile($path);
