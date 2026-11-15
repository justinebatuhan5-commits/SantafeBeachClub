<?php
/**
 * Controller for frontend/payment_success.php
 */

require_once dirname(__DIR__) . '/helpers/url_helper.php';

function sfbc_handle_payment_success_page(mysqli $conn): array
{
    $bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
    $sessionId = is_string($_GET['session_id'] ?? null) ? trim($_GET['session_id']) : '';

    $isVerified = false;
    $bookingData = null;
    $paymentMethodUsed = 'PayMongo Online';

    header('Cache-Control: no-store');
    if ($bookingId > 0) {
        try {
            pm_checkout_schema($conn);
            if (preg_match('/^cs_[A-Za-z0-9]+$/D', $sessionId)) {
                $stored = pm_query($conn, 'SELECT c.session_id FROM paymongo_checkouts c WHERE c.session_id = ? AND c.booking_id = ?',
                    [$sessionId, $bookingId])->get_result()->fetch_assoc();
            } else {
                // PayMongo returns the configured success URL unchanged. Resolve the
                // newest checkout using the immutable booking/payment relation stored
                // when the session was created; provider retrieval still proves payment.
                $stored = pm_query($conn, "SELECT c.session_id
                    FROM paymongo_checkouts c
                    JOIN payments p ON p.id = c.payment_id AND p.booking_id = c.booking_id
                    WHERE c.booking_id = ? AND p.status IN ('pending','verified')
                    ORDER BY p.id DESC LIMIT 1", [$bookingId])->get_result()->fetch_assoc();
                $sessionId = (string)($stored['session_id'] ?? '');
            }
            if (!$stored) throw new RuntimeException('Unknown checkout.');
            $sessionInfo = PayMongoService::retrieveCheckoutSession($sessionId);
            if (empty($sessionInfo['success'])) throw new RuntimeException('Provider verification unavailable.');
            $confirmation = pm_confirm_checkout($conn, $bookingId, $sessionId, $sessionInfo['data']);
            $isVerified = true;
            $paymentMethodUsed = $confirmation['method'];
        } catch (Throwable $e) {
            error_log('[PayMongo Return] Checkout verification failed for booking ' . $bookingId);
        }
    }

    if ($isVerified) {
        // 2. Fetch booking details for display
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $bookingData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($bookingData) {
            // Trigger confirmation email if not already sent
            if ($isVerified && !empty($bookingData['guest_email']) && empty($bookingData['confirmation_email_sent_at'])) {
                { // The mailer serializes sends and records successful delivery.
                    $bRef = 'REF-' . str_pad($bookingId, 3, '0', STR_PAD_LEFT);
                    $baseUrl = sfbc_public_frontend_url();
                    $cancellationUrl = rtrim($baseUrl, '/') . '/cancel_booking?token=' . urlencode($bookingData['cancellation_token'] ?? '');
                    $checkinUrl = rtrim($baseUrl, '/') . '/checkin?ref=' . urlencode($bRef) . '&token=' . urlencode($bookingData['checkin_token'] ?? '');

                    $amtStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS amount FROM payments WHERE booking_id = ? AND status = 'verified'");
                    $amtStmt->bind_param("i", $bookingId);
                    $amtStmt->execute();
                    $bAmount = (float)($amtStmt->get_result()->fetch_assoc()['amount'] ?? 0);
                    $amtStmt->close();

                    @sendBookingConfirmationEmail(
                        $bookingData['guest_email'],
                        $bookingData['guest_name'],
                        $bRef,
                        $bookingData['accommodation_name'],
                        $bookingData['check_in'],
                        $bookingData['check_out'],
                        $bAmount,
                        $cancellationUrl,
                        $checkinUrl
                    );
                }
            }
        }
    }

    $bookingRef = $bookingId > 0 ? ('REF-' . str_pad($bookingId, 3, '0', STR_PAD_LEFT)) : 'N/A';
    $guestName  = $bookingData['guest_name'] ?? 'Guest';
    $roomName   = $bookingData['accommodation_name'] ?? 'Room';
    $checkInDate = !empty($bookingData['check_in']) ? date('M j, Y', strtotime($bookingData['check_in'])) : '—';

    return compact(
        'isVerified',
        'bookingData',
        'paymentMethodUsed',
        'bookingRef',
        'guestName',
        'roomName',
        'checkInDate'
    );
}
