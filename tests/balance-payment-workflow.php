<?php
$migration=file_get_contents(__DIR__.'/../backend/database/migrations/202609250001_balance_deadline_workflow.php');
$helper=file_get_contents(__DIR__.'/../backend/helpers/balance_payment_helper.php');
$guest=file_get_contents(__DIR__.'/../frontend/balance_payment.php')
    .file_get_contents(__DIR__.'/../backend/controllers/balance_payment_controller.php')
    .file_get_contents(__DIR__.'/../frontend/views/balance_payment/_card.php');
$confirm=file_get_contents(__DIR__.'/../backend/services/paymongo_confirmation.php');
$admin=file_get_contents(__DIR__.'/../frontend/admin_checkin.php')
    .file_get_contents(__DIR__.'/../backend/controllers/admin_checkin_controller.php')
    .file_get_contents(__DIR__.'/../frontend/views/admin_checkin/_arrivals_table.php');
$refund=file_get_contents(__DIR__.'/../backend/api/refund_request_api.php');
$guestRefund=file_get_contents(__DIR__.'/../frontend/refund_request_form.php')
    .file_get_contents(__DIR__.'/../backend/controllers/refund_request_controller.php')
    .file_get_contents(__DIR__.'/../frontend/views/refund_request/_form.php');
foreach ([$migration,$helper,$guest,$confirm,$admin,$refund,$guestRefund] as $source) if ($source===false) throw new RuntimeException('Unable to read workflow files.');
if (!str_contains($guest, "helpers/csrf_helper.php")) throw new RuntimeException('Balance payment page must load CSRF protection before rendering its form.');
foreach (['balance_due_at','balance_grace_expires_at','balance_status','purpose'] as $fragment) if(!str_contains($migration,$fragment)) throw new RuntimeException('Missing migration: '.$fragment);
foreach (['sf_balance_deadlines','sf_refresh_balance_status','cancellation_review','sf_process_balance_deadlines','sf_send_balance_reminders'] as $fragment) if(!str_contains($helper,$fragment)) throw new RuntimeException('Missing state behavior: '.$fragment);
foreach (['Pay Remaining Balance with PayMongo',"purpose='balance'",'pm_store_checkout'] as $fragment) if(!str_contains($guest,$fragment)) throw new RuntimeException('Missing guest payment behavior: '.$fragment);
if (!str_contains($guest, "balance_status']==='cancellation_review'") || !str_contains($guest, 'Online payment is disabled')) throw new RuntimeException('Expired balance review still exposes online payment.');
foreach (["balance_status='paid'","payment['purpose']",'sf_initialize_balance_deadline'] as $fragment) if(!str_contains($confirm,$fragment)) throw new RuntimeException('Missing provider confirmation behavior: '.$fragment);
foreach (['The remaining balance must be paid before check-in',"purpose) VALUES",'sf_process_balance_deadlines'] as $fragment) if(!str_contains($admin,$fragment)) throw new RuntimeException('Missing check-in enforcement: '.$fragment);
foreach (['zero refund','zero-value refund'] as $fragment) if(!str_contains($refund,$fragment)) throw new RuntimeException('Missing zero refund safeguard: '.$fragment);
if(!str_contains($guestRefund,'$net_refund < 0.01')) throw new RuntimeException('Guest refund form accepts a zero refund.');
echo "Balance deadlines, PayMongo balance checkout, overdue review, check-in enforcement and zero-refund safeguards are present.\n";
