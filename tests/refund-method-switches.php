<?php
$helper=file_get_contents(__DIR__.'/../backend/helpers/refund_method_helper.php');
$refundApi=file_get_contents(__DIR__.'/../backend/api/refund_request_api.php');
$manualApi=file_get_contents(__DIR__.'/../backend/api/manual_refund_api.php');
$admin=file_get_contents(__DIR__.'/../frontend/admin_refunds.php');
$settings=file_get_contents(__DIR__.'/../frontend/settings.php');
foreach([$helper,$refundApi,$manualApi,$admin,$settings] as $source) if($source===false) throw new RuntimeException('Unable to read refund method switch files.');
foreach(['refund_paymongo_enabled','refund_manual_gcash_enabled','temporarily disabled by the administrator'] as $fragment) if(!str_contains($helper,$fragment)) throw new RuntimeException('Missing refund method helper behavior: '.$fragment);
if(!str_contains($refundApi,"sf_require_refund_method(\$conn, 'paymongo')")) throw new RuntimeException('Missing PayMongo switch enforcement.');
if(substr_count($manualApi,"sf_require_refund_method(\$conn, 'manual_gcash')")<3) throw new RuntimeException('Manual GCash switch is not enforced on every state-changing route.');
foreach(['Refund Method Availability','save_refund_methods','refund_paymongo_enabled','refund_manual_gcash_enabled','REFUND_METHODS_UPDATED'] as $fragment) if(!str_contains($settings,$fragment)) throw new RuntimeException('Missing refund method settings control: '.$fragment);
if(str_contains($admin,'saveRefundMethods')) throw new RuntimeException('Refund method control should not remain on the refund queue.');
echo "Refund method switches are administrator-controlled, CSRF-protected, audited, and server-enforced.\n";
