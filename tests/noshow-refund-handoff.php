<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/helpers/noshow_refund_helper.php';

function handoff_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$booking = [
    'status' => 'Confirmed',
    'check_in' => date('Y-m-d', strtotime('+10 days')),
    'check_out' => date('Y-m-d', strtotime('+11 days')),
];
$reason = 'Guest cancellation </script><script>alert("test")</script>';
foreach (['Pending', 'Confirmed'] as $status) {
    $booking['status'] = $status;
    handoff_check(sf_noshow_refund_handoff('receptionist', 25, $booking, $reason)
        === ['booking_id' => 25, 'reason' => $reason], 'Eligible cancellation must preserve the reference and reason.');
}
foreach (['admin', 'guest', '', 'housekeeping'] as $role) {
    handoff_check(sf_noshow_refund_handoff($role, 25, $booking, $reason) === null, 'Handoff is receptionist-only.');
}
foreach (['Cancelled', 'Checked In', 'Checked Out'] as $status) {
    handoff_check(sf_noshow_refund_handoff('receptionist', 25, array_replace($booking, ['status' => $status]), $reason) === null,
        'Invalid no-show transitions must not create a handoff.');
}
handoff_check(sf_noshow_refund_handoff('receptionist', 0, $booking, $reason) === null, 'Invalid booking ID.');
$booking['check_in'] = date('Y-m-d', strtotime('-1 day'));
$booking['check_out'] = date('Y-m-d');
handoff_check(sf_noshow_refund_handoff('receptionist', 25, $booking, $reason) === null,
    'Overdue no-shows must not offer a non-refundable handoff.');

$checkin = file_get_contents(__DIR__ . '/../frontend/admin_checkin.php');
$refunds = file_get_contents(__DIR__ . '/../frontend/admin_refunds.php');
foreach (["['admin', 'receptionist'], true", 'require_csrf_token();', 'sf_noshow_refund_handoff(',
    "SET status = 'Cancelled', cancelled_at = NOW(), cancellation_reason = ?",
    "UPDATE rooms SET status = 'ready'", 'Mark No-Show / Cancelled', 'Reservation Marked No-Show',
    'Location: admin_checkin?cancelled=1&bid=', 'admin_refunds?noshow_handoff=1'] as $fragment) {
    handoff_check(str_contains($checkin, $fragment), 'Missing no-show integration: ' . $fragment);
}
handoff_check((bool)preg_match('/id="noshowForm"[^>]*>\s*<\?php echo csrf_field\(\); \?>/', $checkin),
    'No-show form must include a CSRF token.');
foreach (["\$_SESSION['noshow_refund_handoff'] ?? null", "unset(\$_SESSION['noshow_refund_handoff'])",
    'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT',
    'if (noshowHandoff) openReceptionRefundModal(noshowHandoff)',
    "fd.append('csrf_token', refundCsrfToken)", "action=submit_request', { method: 'POST', body: fd }"] as $fragment) {
    handoff_check(str_contains($refunds, $fragment), 'Missing safe refund handoff: ' . $fragment);
}
echo "No-show refund handoff preserves policy, role, draft, cancellation, and CSRF safeguards.\n";
