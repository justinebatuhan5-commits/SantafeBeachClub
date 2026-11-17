<?php
/** Contact-form checks. No third-party service or browser JavaScript required. */
function inquiry_validate(array $input, array $tokens, int $now): string {
    foreach (['guest_name', 'guest_email', 'guest_phone', 'subject', 'message', 'website', 'inquiry_token'] as $field) {
        if (isset($input[$field]) && !is_string($input[$field])) {
            return 'Invalid form submission. Please refresh and try again.';
        }
    }
    if (trim($input['website'] ?? '') !== '') {
        return 'Unable to send this message. Please refresh and try again.';
    }
    $issued = $tokens[$input['inquiry_token'] ?? ''] ?? 0;
    if (!$issued || $now - $issued > 7200) {
        return 'This form has expired or was already submitted. Please try again using the refreshed form below.';
    }
    if ($now - $issued < 3) {
        return 'Please take a moment to review your message, then send it again.';
    }
    foreach (['guest_name' => 150, 'guest_email' => 150, 'subject' => 200, 'message' => 5000] as $field => $max) {
        $value = trim($input[$field] ?? '');
        $length = preg_match_all('/./us', $value);
        if ($value === '' || $length === false || $length > $max) {
            return 'Please complete all fields within the displayed length limits.';
        }
    }
    if (!filter_var(trim($input['guest_email']), FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    $guestPhone = trim($input['guest_phone'] ?? '');
    if ($guestPhone !== '' && preg_match('/\A[0-9+\-\s()]{7,30}\z/', $guestPhone) !== 1) {
        return 'Please enter a valid contact number.';
    }
    return '';
}

/** Atomically reserve quota across IP, email and repeated content, even across sessions. */
function inquiry_reserve(mysqli $conn, string $ip, string $email, string $subject, string $message): bool {
    $conn->query("CREATE TABLE IF NOT EXISTS inquiry_throttles (
        bucket_key CHAR(64) PRIMARY KEY,
        window_start BIGINT NOT NULL,
        attempts INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $normalize = static function (string $value): string {
        $value = preg_replace('/\s+/u', ' ', trim($value));
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    };
    $email = $normalize($email);
    $rules = [
        hash('sha256', 'ip:' . $ip) => [3, 3600],
        hash('sha256', 'email:' . $email) => [3, 3600],
        hash('sha256', 'duplicate:' . $normalize($subject) . ':' . $normalize($message)) => [1, 86400],
    ];
    ksort($rules); // Consistent lock order for concurrent submissions.
    $now = time();
    $conn->begin_transaction();
    try {
        foreach ($rules as $key => [$limit, $window]) {
            $stmt = $conn->prepare('INSERT IGNORE INTO inquiry_throttles (bucket_key, window_start, attempts) VALUES (?, ?, 0)');
            $stmt->bind_param('si', $key, $now);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('SELECT window_start, attempts FROM inquiry_throttles WHERE bucket_key = ? FOR UPDATE');
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $start = (int)$row['window_start'];
            $count = (int)$row['attempts'];
            if ($now - $start >= $window) { $start = $now; $count = 0; }
            if ($count >= $limit) { $conn->rollback(); return false; }
            $count++;
            $stmt = $conn->prepare('UPDATE inquiry_throttles SET window_start = ?, attempts = ? WHERE bucket_key = ?');
            $stmt->bind_param('iis', $start, $count, $key);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    // All windows expire within a day; bounded retention of hashed identifiers.
    $cutoff = $now - 86400;
    $stmt = $conn->prepare('DELETE FROM inquiry_throttles WHERE window_start < ? LIMIT 100');
    $stmt->bind_param('i', $cutoff);
    $stmt->execute();
    $stmt->close();
    return true;
}
