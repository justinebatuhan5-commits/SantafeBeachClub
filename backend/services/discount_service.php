<?php
require_once __DIR__ . '/paymongo_confirmation.php';
require_once __DIR__ . '/../helpers/private_storage_helper.php';

function discount_schema(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS booking_discounts (
        booking_id INT PRIMARY KEY, category VARCHAR(10) NOT NULL, eligible_guest VARCHAR(150) NOT NULL,
        id_file VARCHAR(100) NULL, token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        state VARCHAR(40) NOT NULL DEFAULT 'Awaiting ID verification', original_centavos BIGINT NOT NULL,
        total_centavos BIGINT NOT NULL, deposit_centavos BIGINT NOT NULL,
        room_id INT NOT NULL, check_in DATE NOT NULL, check_out DATE NOT NULL, guests_count INT NOT NULL,
        hold_until DATETIME NOT NULL, note TEXT NULL, reviewed_by VARCHAR(100) NULL,
        reviewed_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(state, hold_until)
    ) ENGINE=InnoDB");
    $db->query("CREATE TABLE IF NOT EXISTS discount_history (
        id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, actor VARCHAR(100) NOT NULL,
        action VARCHAR(60) NOT NULL, details TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $db->query("CREATE TABLE IF NOT EXISTS discount_payments (
        payment_id INT PRIMARY KEY, booking_id INT NOT NULL, purpose VARCHAR(10) NOT NULL,
        checkout_url TEXT NULL, reference_key CHAR(64) NULL UNIQUE, INDEX(booking_id)
    ) ENGINE=InnoDB");
}

function discount_amounts(int $original, bool $approved = true): array {
    if ($original <= 0) throw new RuntimeException('The booking amount must be positive.');
    $discount = $approved ? (int)round($original * 0.20) : 0;
    $total = $original - $discount;
    return ['discount' => $discount, 'total' => $total, 'deposit' => (int)round($total / 2)];
}

function discount_audit(mysqli $db, int $id, string $actor, string $action, string $details = ''): void {
    pm_query($db, 'INSERT INTO discount_history (booking_id, actor, action, details) VALUES (?, ?, ?, ?)', [$id, $actor, $action, $details]);
}

function discount_maintenance(mysqli $db): void {
    // Canonical booking statuses keep existing availability queries compatible.
    $db->query("UPDATE bookings b JOIN booking_discounts d ON d.booking_id=b.id
        SET b.status='Cancelled', d.state='Expired'
        WHERE b.status IN ('Pending', 'Pending Payment') AND d.hold_until < NOW()
        AND d.state NOT IN ('Confirmed', 'Expired', 'Cancelled')
        AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id=b.id AND p.status='verified')");
    $db->query("UPDATE booking_discounts d JOIN bookings b ON b.id=d.booking_id
        SET d.state='Cancelled' WHERE b.status='Cancelled' AND d.state NOT IN ('Expired','Cancelled')");
    $days = max(1, min(365, (int)(getenv('DISCOUNT_ID_RETENTION_DAYS') ?: 30)));
    $rows = pm_query($db, 'SELECT booking_id,id_file FROM booking_discounts WHERE id_file IS NOT NULL AND COALESCE(reviewed_at,created_at) < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days])->get_result();
    while ($row = $rows->fetch_assoc()) {
        try {
            $path = private_storage_file('discount_ids/' . $row['id_file']);
            if ($path && !unlink($path)) continue;
            pm_query($db, 'UPDATE booking_discounts SET id_file=NULL WHERE booking_id=? AND id_file=?', [$row['booking_id'], $row['id_file']]);
        } catch (Throwable $e) { error_log('Discount ID retention cleanup could not access private storage.'); }
    }
}

function discount_upload(array $file, string $kind): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Upload a JPG or PNG image no larger than 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
    if (!$ext || !getimagesize($file['tmp_name'])) throw new RuntimeException('Only valid JPG and PNG images are accepted.');
    require_once __DIR__ . '/../helpers/file_scanner.php';
    $scan = scan_uploaded_file($file['tmp_name'], $ext);
    if (!$scan['safe']) throw new RuntimeException('The uploaded file failed the security scan.');
    $name = bin2hex(random_bytes(24)) . '.' . $ext;
    $path = private_storage_directory($kind, true) . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Could not store the upload privately.');
    chmod($path, 0644);
    return $name;
}

function discount_link(array $request): string {
    $base = rtrim(getenv('PUBLIC_APP_URL') ?: 'https://santafbeachclub.com', '/');
    // Use the real frontend file: PHP URLs are not routed consistently by hosts.
    if (!str_ends_with($base, '/frontend')) $base .= '/frontend';
    // Fragments are exchanged for a session and are not sent in HTTP URLs.
    return $base . '/discount_booking.php#access=' . rawurlencode($request['token']);
}

function discount_email(mysqli $db, int $id): bool {
    $d = pm_query($db, 'SELECT d.*, b.guest_email,b.guest_name,b.accommodation_name,b.check_in,b.check_out,b.cancellation_token,b.checkin_token FROM booking_discounts d JOIN bookings b ON b.id=d.booking_id WHERE d.booking_id=?', [$id])->get_result()->fetch_assoc();
    if (!$d) return false;
    require_once __DIR__ . '/mailer.php';

    // If confirmed, send the official Booking Confirmation email with check-in pass (no link to discount payment page)
    if ($d['state'] === 'Confirmed') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $base_url = rtrim($protocol . $host . $script_dir, '/');
        $frontend_url = (strpos($base_url, '/frontend') !== false) ? $base_url : $base_url . '/frontend';

        $cancellation_url = $d['cancellation_token'] ? $frontend_url . "/cancel_booking.php?token=" . $d['cancellation_token'] : null;
        $checkin_url = $d['checkin_token'] ? $frontend_url . "/checkin_pass.php?token=" . $d['checkin_token'] : null;

        $total_price = (float)($d['total_centavos'] / 100);
        $result = sendBookingConfirmationEmail(
            $d['guest_email'],
            $d['guest_name'],
            'REF-' . $id,
            $d['accommodation_name'] ?? 'Room',
            $d['check_in'],
            $d['check_out'],
            $total_price,
            $cancellation_url,
            $checkin_url
        );
        if (!empty($result['success'])) {
            discount_audit($db, $id, 'System', 'Booking confirmation email sent');
            return true;
        }
        discount_audit($db, $id, 'System', 'Booking confirmation email failed', $result['error'] ?? '');
        return false;
    }

    try {
        $mail = createMailer();
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
        $mail->addAddress($d['guest_email'], $d['guest_name']);
        $mail->Subject = 'Santa Fe Beach Club — booking REF-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT);
        $mail->isHTML(false);
        $paid = (int)pm_query($db, "SELECT COALESCE(ROUND(SUM(amount)*100),0) FROM payments WHERE booking_id=? AND status='verified'", [$id])->get_result()->fetch_row()[0];
        $prices = 'Total: PHP ' . number_format($d['total_centavos']/100,2) . '. Deposit: PHP ' . number_format($d['deposit_centavos']/100,2) . '. Verified payments: PHP ' . number_format($paid/100,2) . '. Remaining total: PHP ' . number_format(max(0,$d['total_centavos']-$paid)/100,2) . '. Balance deadline: ' . date('d M Y',strtotime($d['check_in'].' -1 day')) . '.';
        $holdText = "Room hold deadline: {$d['hold_until']} (Philippine time). Approval alone does not confirm your reservation; a verified 50% deposit is required.";
        $mail->Body = "Your booking status: {$d['state']}\n{$d['note']}\n{$prices}\n{$holdText}\nView your price, verification status, and payment options securely:\n" . discount_link($d) . "\nDo not share this private link. Prices are estimates until approval or acceptance of the regular price.";
        $mail->send();
        discount_audit($db, $id, 'System', 'Email sent');
        return true;
    } catch (Throwable $e) {
        discount_audit($db, $id, 'System', 'Email failed', 'Use the private link or retry sending.');
        return false;
    }
}

function discount_create(mysqli $db, array $data, array $file): array {
    if (!in_array($data['category'], ['senior','pwd'], true) || trim($data['eligible_guest']) === '' || strlen($data['eligible_guest']) > 150) throw new RuntimeException('Select a discount type and identify the eligible staying guest.');
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || trim($data['name']) === '') throw new RuntimeException('Enter your guest details before requesting a discount.');
    foreach (['checkin','checkout'] as $field) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data[$field]);
        if (!$date || $date->format('Y-m-d') !== $data[$field]) throw new RuntimeException('Choose valid stay dates.');
    }
    if ($data['checkin'] < date('Y-m-d') || $data['checkout'] <= $data['checkin'] || $data['guests'] < 1) throw new RuntimeException('Choose valid future stay dates and at least one guest.');
    $amounts = discount_amounts($data['original']);
    $name = discount_upload($file, 'discount_ids');
    $db->begin_transaction();
    try {
        // Serialize requests for this room type, including concurrent discount submissions.
        $rooms = pm_query($db, "SELECT id,name FROM rooms WHERE type=? AND status<>'maintenance' ORDER BY id FOR UPDATE", [$data['room_type']])->get_result();
        $room = null;
        while ($candidate = $rooms->fetch_assoc()) {
            $busy = pm_query($db, "SELECT id FROM bookings WHERE room_id=? AND status IN ('Confirmed','Checked In','Pending','Pending Payment') AND check_in<? AND check_out>? LIMIT 1", [$candidate['id'], $data['checkout'], $data['checkin']])->get_result()->fetch_assoc();
            if (!$busy) { $room = $candidate; break; }
        }
        if (!$room) throw new RuntimeException('No room is available for these dates.');
        $hours = max(1, min(72, (int)(getenv('DISCOUNT_HOLD_HOURS') ?: 24)));
        $hold = date('Y-m-d H:i:s', min(time() + $hours * 3600, strtotime($data['checkin'] . ' 13:30:00')));
        if (strtotime($hold) <= time()) throw new RuntimeException('Please contact reception for same-day bookings.');
        pm_query($db, "INSERT INTO bookings (guest_name,guest_email,guest_type,check_in,check_out,guests_count,room_id,accommodation_name,eta,status,checkin_token,cancellation_token,payment_method,guest_phone,discount_amount) VALUES (?,?,'First Visit',?,?,?,?,?,?,'Pending Payment',?,?,'Discount Request',?,0)",
            [$data['name'],$data['email'],$data['checkin'],$data['checkout'],$data['guests'],$room['id'],$room['name'],$data['eta'],bin2hex(random_bytes(16)),bin2hex(random_bytes(16)),$data['phone']]);
        $id = (int)$db->insert_id;
        $token = bin2hex(random_bytes(32));
        pm_query($db, 'INSERT INTO booking_discounts (booking_id,category,eligible_guest,id_file,token,original_centavos,total_centavos,deposit_centavos,room_id,check_in,check_out,guests_count,hold_until) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$id,$data['category'],$data['eligible_guest'],$name,$token,$data['original'],$amounts['total'],$amounts['deposit'],$room['id'],$data['checkin'],$data['checkout'],$data['guests'],$hold]);
        discount_audit($db, $id, 'Guest', 'Discount requested', $data['category']);
        pm_query($db, "INSERT INTO notifications (title,message,type,booking_id) VALUES ('Discount verification requested',?,'warning',?)", ['Review booking REF-' . $id . ' in Discount Requests.', $id]);
        $db->commit();
        return ['booking_id'=>$id,'token'=>$token];
    } catch (Throwable $e) {
        $db->rollback();
        @unlink(private_storage_directory('discount_ids') . '/' . $name);
        throw $e;
    }
}

function discount_locked(mysqli $db, int $id): array {
    $b = pm_query($db, 'SELECT * FROM bookings WHERE id=? FOR UPDATE', [$id])->get_result()->fetch_assoc();
    $d = pm_query($db, 'SELECT * FROM booking_discounts WHERE booking_id=? FOR UPDATE', [$id])->get_result()->fetch_assoc();
    if (!$b || !$d) throw new RuntimeException('Booking not found.');
    if ($b['status'] === 'Cancelled' || in_array($d['state'], ['Expired','Cancelled'], true)) throw new RuntimeException('This room hold has ended. Contact reception to arrange a new booking or resolve a payment.');
    if ($d['state'] !== 'Confirmed' && strtotime($d['hold_until']) <= time()) throw new RuntimeException('The room hold has expired.');
    foreach (['room_id','check_in','check_out','guests_count'] as $field) {
        if ((string)$d[$field] !== (string)$b[$field]) throw new RuntimeException('Booking details changed. Contact reception to cancel and submit a new discount request.');
    }
    return array_merge($b, $d);
}

function discount_review(mysqli $db, int $id, string $action, string $note, string $actor): void {
    $db->begin_transaction();
    try {
        $d = discount_locked($db, $id);
        if (!in_array($d['state'], ['Awaiting ID verification','More information needed'], true)) throw new RuntimeException('This request has already been reviewed.');
        $state = ['approve'=>'Awaiting Deposit','decline'=>'Discount declined','information'=>'More information needed'][$action] ?? null;
        if (!$state || strlen($note) > 2000 || ($action !== 'approve' && trim($note) === '')) throw new RuntimeException('Provide a valid decision and explanation.');
        if ($action === 'approve' && !$d['id_file']) throw new RuntimeException('Ask the guest to upload their ID again before approval.');
        pm_query($db, 'UPDATE booking_discounts SET state=?,note=?,reviewed_by=?,reviewed_at=NOW() WHERE booking_id=?', [$state,$note,$actor,$id]);
        if ($action === 'approve') pm_query($db, 'UPDATE bookings SET discount_amount=?,promo_code=NULL WHERE id=?', [($d['original_centavos']-$d['total_centavos'])/100,$id]);
        if ($action === 'decline') {
            $regular = discount_amounts((int)$d['original_centavos'], false);
            pm_query($db, 'UPDATE booking_discounts SET total_centavos=?,deposit_centavos=? WHERE booking_id=?', [$regular['total'],$regular['deposit'],$id]);
        }
        discount_audit($db, $id, $actor, $state, $note);
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

function discount_due(mysqli $db, array $d): int {
    $paid = (int)pm_query($db, "SELECT COALESCE(ROUND(SUM(amount)*100),0) FROM payments WHERE booking_id=? AND status='verified'", [$d['booking_id']])->get_result()->fetch_row()[0];
    return max(0, (int)($d['state'] === 'Confirmed' ? $d['total_centavos'] : $d['deposit_centavos']) - $paid);
}

function discount_start_payment(mysqli $db, int $id, string $method, string $reference = '', ?array $file = null): ?string {
    if (!in_array($method, ['PayMongo Online','GCash QR','Bank Deposit'], true)) throw new RuntimeException('Select a supported payment method.');
    $receipt = null;
    if ($method !== 'PayMongo Online') {
        $reference = trim($reference);
        if ($reference === '') {
            $reference = 'MANUAL-' . strtoupper(bin2hex(random_bytes(4)));
        } elseif (strlen($reference) > 100) {
            throw new RuntimeException('Reference must be under 100 characters.');
        }
        $receipt = discount_upload($file ?? [], 'receipts');
    }
    // Schema DDL must precede the transaction.
    pm_checkout_schema($db);
    $db->begin_transaction();
    try {
        $d = discount_locked($db, $id);
        if (!in_array($d['state'], ['Awaiting Deposit','Confirmed'], true)) throw new RuntimeException('Payment is available only after approval or acceptance of the regular price.');
        $existing = pm_query($db, "SELECT x.checkout_url FROM discount_payments x JOIN payments p ON p.id=x.payment_id WHERE x.booking_id=? AND p.status='pending' LIMIT 1", [$id])->get_result()->fetch_assoc();
        if ($existing) {
            if ($existing['checkout_url'] && $method === 'PayMongo Online') { $db->commit(); return $existing['checkout_url']; }
            throw new RuntimeException('A payment is already pending. Contact reception before starting another payment.');
        }
        $due = discount_due($db, $d);
        if ($due <= 0) throw new RuntimeException('No payment is due.');
        pm_query($db, "INSERT INTO payments (booking_id,guest_name,guest_email,amount,payment_method,transaction_id,status,receipt_url) VALUES (?,?,?,?,?,?,'pending',?)", [$id,$d['guest_name'],$d['guest_email'],$due/100,$method,$reference,$receipt ? 'storage/receipts/' . $receipt : null]);
        $pid = (int)$db->insert_id;
        $purpose = $d['state'] === 'Confirmed' ? 'balance' : 'deposit';
        $refKey = $method === 'PayMongo Online' ? null : hash('sha256', $method . ':' . strtolower(preg_replace('/\s+/', '', $reference)));
        pm_query($db, 'INSERT INTO discount_payments (payment_id,booking_id,purpose,reference_key) VALUES (?,?,?,?)', [$pid,$id,$purpose,$refKey]);
        $url = null;
        if ($method === 'PayMongo Online') {
            require_once __DIR__ . '/paymongo.php';
            $return = discount_link($d);
            $result = PayMongoService::createCheckoutSession($due/100, ucfirst($purpose) . ' for REF-' . $id, $id,$d['guest_name'],$d['guest_email'],$d['guest_phone'] ?? '',$return,$return,ucfirst($purpose) . ' — Santa Fe Beach Club');
            if (empty($result['success'])) throw new RuntimeException('Could not create checkout. Please try again later.');
            $sid = $result['session_id'];
            if (!preg_match('/^cs_[A-Za-z0-9]+$/D', $sid)) throw new RuntimeException('Invalid checkout response.');
            $url = $result['checkout_url'];
            pm_query($db, "INSERT INTO paymongo_checkouts (session_id,payment_id,booking_id,amount_centavos,currency) VALUES (?,?,?,?,'PHP')", [$sid,$pid,$id,$due]);
            pm_query($db, 'UPDATE payments SET transaction_id=? WHERE id=?', [$sid,$pid]);
            pm_query($db, 'UPDATE discount_payments SET checkout_url=? WHERE payment_id=?', [$url,$pid]);
        } else {
            if ($purpose === 'deposit') pm_query($db, "UPDATE booking_discounts SET state='Payment Under Review' WHERE booking_id=?", [$id]);
            pm_query($db, "INSERT INTO notifications (title,message,type,booking_id) VALUES ('Discount payment review',?,'payment',?)", ['Verify the actual account transaction for REF-' . $id . ' in Discount Requests.', $id]);
        }
        discount_audit($db, $id, 'Guest', 'Payment initiated', $method . ' ' . $purpose);
        $db->commit();
        return $url;
    } catch (Throwable $e) {
        $db->rollback();
        if ($receipt) @unlink(private_storage_directory('receipts') . '/' . $receipt);
        throw $e;
    }
}

function discount_manual_review(mysqli $db, int $id, int $pid, bool $approve, string $actor, string $note): void {
    $db->begin_transaction();
    try {
        $d = discount_locked($db, $id);
        $p = pm_query($db, 'SELECT p.*, x.purpose FROM payments p JOIN discount_payments x ON x.payment_id=p.id WHERE p.id=? AND x.booking_id=? FOR UPDATE', [$pid,$id])->get_result()->fetch_assoc();
        if (!$p || $p['status'] !== 'pending' || !in_array($p['payment_method'], ['GCash QR','Bank Deposit'], true)) throw new RuntimeException('No pending manual payment found.');
        if (!in_array($d['state'], ['Payment Under Review','Confirmed'], true)) throw new RuntimeException('This booking is not awaiting payment verification.');
        if (!$approve && trim($note) === '') throw new RuntimeException('Explain why the payment was rejected.');
        if ((int)round($p['amount']*100) !== discount_due($db, $d)) throw new RuntimeException('Payment does not match the outstanding amount.');
        if ($approve && pm_query($db, "SELECT id FROM payments WHERE id<>? AND transaction_id=? AND payment_method=? AND status='verified' LIMIT 1", [$pid,$p['transaction_id'],$p['payment_method']])->get_result()->fetch_assoc()) throw new RuntimeException('This account transaction has already been verified for another payment.');
        pm_query($db, 'UPDATE payments SET status=? WHERE id=?', [$approve ? 'verified':'rejected',$pid]);
        if ($approve) {
            pm_query($db, "UPDATE bookings SET status='Confirmed' WHERE id=?", [$id]);
            pm_query($db, "UPDATE booking_discounts SET state='Confirmed' WHERE booking_id=?", [$id]);
        } elseif ($p['purpose'] === 'deposit') {
            pm_query($db, "UPDATE booking_discounts SET state='Awaiting Deposit',note=? WHERE booking_id=?", [$note,$id]);
        }
        if (!$approve) pm_query($db, 'UPDATE discount_payments SET reference_key=NULL WHERE payment_id=?', [$pid]);
        discount_audit($db, $id, $actor, $approve ? 'Payment verified' : 'Payment rejected', $note);
        pm_query($db, 'INSERT INTO payment_action_history (payment_id,action,performed_by,details) VALUES (?,?,?,?)', [$pid,$approve?'verified':'rejected',$actor,$note]);
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
