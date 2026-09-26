<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/csrf_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Referrer-Policy: no-referrer');
$queryToken = trim((string)($_GET['token'] ?? ''));
if (preg_match('/^[a-f0-9]{64}$/', $queryToken)) {
    $_SESSION['refund_confirmation_token'] = $queryToken;
    header('Location: refund_confirmation.php', true, 303);
    exit;
}
$token = trim((string)($_POST['token'] ?? $_SESSION['refund_confirmation_token'] ?? ''));
$refund = null; $confirmed = false; $error = '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'This confirmation link is invalid.';
} else {
    $hash = hash('sha256', $token);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!verify_csrf_token()) {
            $error = 'Your security session expired. Please reopen the link from your email.';
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT d.refund_request_id,r.booking_id,r.net_refund,d.transfer_reference,b.guest_name FROM manual_refund_details d JOIN refund_requests r ON r.id=d.refund_request_id JOIN bookings b ON b.id=r.booking_id WHERE d.confirmation_token_hash=? AND d.confirmation_token_expires_at>NOW() AND d.guest_confirmed_at IS NULL AND r.status='completed' LIMIT 1 FOR UPDATE");
                $stmt->bind_param('s', $hash); $stmt->execute(); $refund = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$refund) throw new RuntimeException('This confirmation link is expired, already used, or invalid.');
                $update = $conn->prepare('UPDATE manual_refund_details SET guest_confirmed_at=NOW(),confirmation_token_hash=NULL WHERE refund_request_id=? AND guest_confirmed_at IS NULL');
                $update->bind_param('i', $refund['refund_request_id']); $update->execute();
                if ($update->affected_rows !== 1) throw new RuntimeException('This refund was already confirmed.');
                $update->close();
                $notificationTitle = 'Guest Confirmed Refund Receipt';
                $notificationMessage = $refund['guest_name'] . ' confirmed receiving the PHP ' . number_format((float)$refund['net_refund'], 2)
                    . ' GCash refund for REF-' . str_pad((string)$refund['booking_id'], 3, '0', STR_PAD_LEFT)
                    . ' (reference ' . $refund['transfer_reference'] . ').';
                $notificationType = 'success';
                $notification = $conn->prepare('INSERT INTO notifications (title,message,type,booking_id) VALUES (?,?,?,?)');
                $notification->bind_param('sssi', $notificationTitle, $notificationMessage, $notificationType, $refund['booking_id']);
                $notification->execute(); $notification->close();
                $conn->commit(); $confirmed = true;
                unset($_SESSION['refund_confirmation_token']);
                log_activity($conn, 'Guest', 'Manual Refund Receipt Confirmed', 'Guest confirmed receipt for refund request #' . $refund['refund_request_id'] . '.');
            } catch (Throwable $e) { $conn->rollback(); $error = $e->getMessage(); }
        }
    } else {
        $stmt = $conn->prepare("SELECT r.booking_id,r.net_refund,d.transfer_reference,b.guest_name,d.confirmation_token_expires_at FROM manual_refund_details d JOIN refund_requests r ON r.id=d.refund_request_id JOIN bookings b ON b.id=r.booking_id WHERE d.confirmation_token_hash=? AND d.confirmation_token_expires_at>NOW() AND d.guest_confirmed_at IS NULL AND r.status='completed' LIMIT 1");
        $stmt->bind_param('s', $hash); $stmt->execute(); $refund = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$refund) { $error = 'This confirmation link is expired, already used, or invalid.'; unset($_SESSION['refund_confirmation_token']); }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirm GCash Refund</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#172033;font-family:Segoe UI,Arial,sans-serif;padding:24px}.shell{max-width:620px;margin:40px auto}.card{background:#fff;border:1px solid #e4e8f0;border-radius:18px;box-shadow:0 18px 45px rgba(15,23,42,.08);overflow:hidden}.head{padding:26px 30px;background:linear-gradient(135deg,#047857,#10b981);color:#fff}.head h1{margin:0 0 7px;font-size:24px}.body{padding:28px 30px}.summary{background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:16px;margin-bottom:22px;line-height:1.7}.notice{padding:14px;border-radius:9px;line-height:1.5}.error{background:#fff0f0;color:#9b2020}.success{background:#ecfdf5;color:#065f46}.btn{width:100%;border:0;border-radius:9px;background:#047857;color:#fff;padding:13px;font-weight:800;font-size:15px;cursor:pointer}.hint{font-size:13px;color:#64748b;line-height:1.6;margin:18px 0}</style></head><body><main class="shell"><section class="card"><div class="head"><h1>Confirm GCash refund</h1><div>Santa Fe Beach Club secure confirmation</div></div><div class="body">
<?php if ($confirmed): ?><div class="notice success"><strong>Thank you—your refund receipt is confirmed.</strong><br>The resort has been notified that you received the GCash transfer.</div>
<?php elseif ($error !== ''): ?><div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?> Please contact the resort if you still need assistance.</div>
<?php else: ?><div class="summary"><strong>REF-<?= str_pad((string)$refund['booking_id'], 3, '0', STR_PAD_LEFT) ?></strong><br>Guest: <?= htmlspecialchars($refund['guest_name'], ENT_QUOTES, 'UTF-8') ?><br>Refund amount: <strong>₱<?= number_format((float)$refund['net_refund'], 2) ?></strong><br>GCash reference: <strong><?= htmlspecialchars($refund['transfer_reference'], ENT_QUOTES, 'UTF-8') ?></strong></div>
<p class="hint">Please check your GCash account and confirm only if the refund shown above was received. Never share your MPIN, password, or OTP.</p>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><button class="btn" type="submit">I received this GCash refund</button></form>
<?php endif; ?></div></section></main></body></html>
