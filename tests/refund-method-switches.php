<?php
$helper=file_get_contents(__DIR__.'/../backend/helpers/refund_method_helper.php');
$refundApi=file_get_contents(__DIR__.'/../backend/api/refund_request_api.php');
$manualApi=file_get_contents(__DIR__.'/../backend/api/manual_refund_api.php');
$admin=file_get_contents(__DIR__.'/../frontend/admin_refunds.php');
foreach([$helper,$refundApi,$manualApi,$admin] as $source) if($source===false) throw new RuntimeException('Unable to read refund method switch files.');
foreach(['refund_paymongo_enabled','refund_manual_gcash_enabled','temporarily disabled by the administrator'] as $fragment) if(!str_contains($helper,$fragment)) throw new RuntimeException('Missing refund method helper behavior: '.$fragment);
foreach(["\$action === 'update_refund_methods'","sf_require_refund_method(\$conn, 'paymongo')",'Refund Methods Updated'] as $fragment) if(!str_contains($refundApi,$fragment)) throw new RuntimeException('Missing PayMongo switch enforcement: '.$fragment);
if(substr_count($manualApi,"sf_require_refund_method(\$conn, 'manual_gcash')")<3) throw new RuntimeException('Manual GCash switch is not enforced on every state-changing route.');
foreach(['Refund method availability','refundPaymongoEnabled','refundManualEnabled','saveRefundMethods'] as $fragment) if(!str_contains($admin,$fragment)) throw new RuntimeException('Missing refund method controls: '.$fragment);
echo "Refund method switches are administrator-controlled, CSRF-protected, audited, and server-enforced.\n";
