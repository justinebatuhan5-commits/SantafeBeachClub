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
    // Separate immutable checkout identity from the final provider transaction ID.
    if (!$db->query("CREATE TABLE IF NOT EXISTS paymongo_checkouts (
        session_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        payment_id INT NOT NULL UNIQUE,
        booking_id INT NOT NULL,
        amount_centavos BIGINT NOT NULL,
        currency CHAR(3) NOT NULL,
        provider_payment_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE
    ) ENGINE=InnoDB")) {
        throw new RuntimeException('Checkout storage is unavailable.');
    }
    // Preserve existing pending checkouts while their original session ID still exists.
    pm_query($db, "INSERT INTO paymongo_checkouts (session_id, payment_id, booking_id, amount_centavos, currency)
        SELECT p.transaction_id, p.id, p.booking_id, ROUND(p.amount * 100), 'PHP'
        FROM payments p WHERE p.payment_method = 'PayMongo Online' AND p.status = 'pending'
        AND p.transaction_id LIKE 'cs\\_%'
        AND NOT EXISTS (SELECT 1 FROM paymongo_checkouts c WHERE c.payment_id = p.id)");
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
