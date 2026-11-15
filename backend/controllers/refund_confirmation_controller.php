<?php
declare(strict_types=1);
/**
 * Controller for frontend/refund_confirmation.php
 */

function sfbc_handle_refund_confirmation_page(mysqli $conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Referrer-Policy: no-referrer');

    $queryToken = trim((string)($_GET['token'] ?? ''));
    if (preg_match('/^[a-f0-9]{64}$/', $queryToken)) {
        $_SESSION['refund_confirmation_token'] = $queryToken;
        header('Location: refund_confirmation.php', true, 303);
        exit;
    }

    $token = trim((string)($_POST['token'] ?? $_SESSION['refund_confirmation_token'] ?? ''));
    $refund = null;
    $confirmed = false;
    $error = '';

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
                    $stmt->bind_param('s', $hash);
                    $stmt->execute();
                    $refund = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$refund) {
                        throw new RuntimeException('This confirmation link is expired, already used, or invalid.');
                    }
                    $update = $conn->prepare('UPDATE manual_refund_details SET guest_confirmed_at=NOW(),confirmation_token_hash=NULL WHERE refund_request_id=? AND guest_confirmed_at IS NULL');
                    $update->bind_param('i', $refund['refund_request_id']);
                    $update->execute();
                    if ($update->affected_rows !== 1) {
                        throw new RuntimeException('This refund was already confirmed.');
                    }
                    $update->close();

                    $notificationTitle = 'Guest Confirmed Refund Receipt';
                    $notificationMessage = $refund['guest_name'] . ' confirmed receiving the PHP ' . number_format((float)$refund['net_refund'], 2)
                        . ' GCash refund for REF-' . str_pad((string)$refund['booking_id'], 3, '0', STR_PAD_LEFT)
                        . ' (reference ' . $refund['transfer_reference'] . ').';
                    $notificationType = 'success';
                    $notification = $conn->prepare('INSERT INTO notifications (title,message,type,booking_id) VALUES (?,?,?,?)');
                    $notification->bind_param('sssi', $notificationTitle, $notificationMessage, $notificationType, $refund['booking_id']);
                    $notification->execute();
                    $notification->close();

                    $conn->commit();
                    $confirmed = true;
                    unset($_SESSION['refund_confirmation_token']);
                    log_activity($conn, 'Guest', 'Manual Refund Receipt Confirmed', 'Guest confirmed receipt for refund request #' . $refund['refund_request_id'] . '.');
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        } else {
            $stmt = $conn->prepare("SELECT r.booking_id,r.net_refund,d.transfer_reference,b.guest_name,d.confirmation_token_expires_at FROM manual_refund_details d JOIN refund_requests r ON r.id=d.refund_request_id JOIN bookings b ON b.id=r.booking_id WHERE d.confirmation_token_hash=? AND d.confirmation_token_expires_at>NOW() AND d.guest_confirmed_at IS NULL AND r.status='completed' LIMIT 1");
            $stmt->bind_param('s', $hash);
            $stmt->execute();
            $refund = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$refund) {
                $error = 'This confirmation link is expired, already used, or invalid.';
                unset($_SESSION['refund_confirmation_token']);
            }
        }
    }

    return compact('token', 'refund', 'confirmed', 'error');
}
