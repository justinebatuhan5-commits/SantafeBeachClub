<?php

function pm_query(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if (!$stmt || !$stmt->execute($params)) {
        throw new RuntimeException('Payment database operation failed.');
    }
    return $stmt;
}

function pm_checkout_schema(mysqli $db): void
{
    // Availability check only. The CLI deployment migration owns this table.
    $db->query('SELECT 1 FROM paymongo_checkouts LIMIT 0');
}

function pm_store_checkout(mysqli $db, string $sessionId, int $paymentId, int $bookingId, float $amount): void
{
    if (!preg_match('/^cs_[A-Za-z0-9]+$/D', $sessionId) || $amount <= 0) {
        throw new RuntimeException('Invalid checkout response.');
    }
    pm_checkout_schema($db);
    pm_query($db, "INSERT INTO paymongo_checkouts
        (session_id, payment_id, booking_id, amount_centavos, currency) VALUES (?, ?, ?, ?, 'PHP')",
        [$sessionId, $paymentId, $bookingId, (int)round($amount * 100)]);
}

/** Only trusted provider responses may be passed here (API retrieval or signed webhook). */
function pm_validate_checkout(array $checkout, array $session): array
{
    $attributes = $session['attributes'] ?? [];
    if (($session['id'] ?? '') !== $checkout['session_id']
        || (string)($attributes['metadata']['booking_id'] ?? '') !== (string)$checkout['booking_id']) {
        throw new RuntimeException('Checkout does not match booking.');
    }
    $paid = array_values(array_filter($attributes['payments'] ?? [],
        static fn($p) => ($p['attributes']['status'] ?? '') === 'paid'));
    if (count($paid) !== 1) {
        throw new RuntimeException('Expected one completed payment.');
    }
    $payment = $paid[0];
    $amount = $payment['attributes']['amount'] ?? null;
    if (!is_int($amount) || $amount <= 0 || $amount !== (int)$checkout['amount_centavos']
        || ($payment['attributes']['currency'] ?? '') !== $checkout['currency']
        || !preg_match('/^pay_[A-Za-z0-9]+$/D', $payment['id'] ?? '')) {
        throw new RuntimeException('Payment amount, currency or identity does not match.');
    }
    return $payment;
}

function pm_confirm_checkout(mysqli $db, int $bookingId, string $sessionId, array $session): array
{
    pm_checkout_schema($db);
    if (!$db->begin_transaction()) throw new RuntimeException('Cannot start payment transaction.');
    try {
        $checkout = pm_query($db, 'SELECT * FROM paymongo_checkouts WHERE session_id = ? AND booking_id = ? FOR UPDATE',
            [$sessionId, $bookingId])->get_result()->fetch_assoc();
        if (!$checkout) throw new RuntimeException('Unknown checkout.');
        $paid = pm_validate_checkout($checkout, $session);
        $paymentId = (int)$checkout['payment_id'];
        $txn = $paid['id'];
        $payment = pm_query($db, 'SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId])->get_result()->fetch_assoc();
        $booking = pm_query($db, 'SELECT status FROM bookings WHERE id = ? FOR UPDATE', [$bookingId])->get_result()->fetch_assoc();
        if (!$payment || !$booking || (int)$payment['booking_id'] !== $bookingId
            || (int)round((float)$payment['amount'] * 100) !== (int)$checkout['amount_centavos']) {
            throw new RuntimeException('Stored payment does not match checkout.');
        }
        if ($checkout['provider_payment_id'] !== null) {
            if ($checkout['provider_payment_id'] !== $txn || $payment['status'] !== 'verified'
                || $payment['transaction_id'] !== $txn
                || !in_array(strtolower($booking['status']), ['confirmed', 'checked in', 'checked out'], true)) {
                throw new RuntimeException('Payment cannot be confirmed again.');
            }
            if (!$db->commit()) throw new RuntimeException('Cannot commit payment transaction.');
            return ['method' => $payment['payment_method'], 'new' => false];
        }
        if ($payment['status'] !== 'pending'
            || !in_array(strtolower($booking['status']), ['pending', 'pending payment', 'confirmed'], true)) {
            throw new RuntimeException('Payment or booking is no longer pending.');
        }
        $discountBooking = null;
        if (function_exists('discount_locked') && pm_query($db, 'SELECT booking_id FROM booking_discounts WHERE booking_id=?', [$bookingId])->get_result()->fetch_assoc()) {
            $discountBooking = discount_locked($db, $bookingId);
            if (!in_array($discountBooking['state'], ['Awaiting Deposit', 'Confirmed'], true)
                || (int)$checkout['amount_centavos'] !== discount_due($db, $discountBooking)) {
                throw new RuntimeException('Discount approval or outstanding payment amount does not match.');
            }
        }
        // Covers historical transactions; the unique index below arbitrates concurrent claims.
        if (pm_query($db, 'SELECT id FROM payments WHERE transaction_id = ? AND id <> ? LIMIT 1',
            [$txn, $paymentId])->get_result()->fetch_assoc()) {
            throw new RuntimeException('Provider transaction already used.');
        }
        pm_query($db, 'UPDATE paymongo_checkouts SET provider_payment_id = ? WHERE session_id = ?', [$txn, $sessionId]);
        $method = 'PayMongo (' . ucfirst($paid['attributes']['source']['type'] ?? 'Online') . ')';
        pm_query($db, "UPDATE payments SET status = 'verified', payment_method = ?, transaction_id = ? WHERE id = ?",
            [$method, $txn, $paymentId]);
        pm_query($db, "UPDATE bookings SET status = 'Confirmed' WHERE id = ?", [$bookingId]);
        if ($discountBooking) {
            pm_query($db, "UPDATE booking_discounts SET state='Confirmed' WHERE booking_id=?", [$bookingId]);
            discount_audit($db, $bookingId, 'PayMongo', 'Payment verified', $txn);
        }
        pm_query($db, "INSERT INTO payment_action_history (payment_id, action, performed_by, details)
            VALUES (?, 'verified', 'PayMongo System', ?)", [$paymentId, 'Verified checkout ' . $sessionId . '. Ref: ' . $txn]);
        pm_query($db, "INSERT INTO notifications (title, message, type, booking_id)
            VALUES ('Online Payment Verified', ?, 'payment', ?)", ['Payment verified for booking ' . $bookingId . '. Ref: ' . $txn, $bookingId]);
        if (!$db->commit()) throw new RuntimeException('Cannot commit payment transaction.');
        return ['method' => $method, 'new' => true];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
