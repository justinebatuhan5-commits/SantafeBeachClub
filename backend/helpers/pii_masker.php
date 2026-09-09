<?php
/**
 * pii_masker.php — Personally Identifiable Information (PII) Masking Helper
 *
 * Provides data protection and privacy compliance utilities pursuant to
 * Republic Act No. 10173 (Philippine Data Privacy Act of 2012).
 *
 * Rules:
 * - Phone numbers: Shows leading country/network code and last 3-4 digits, masks middle digits.
 * - Emails: Shows first character and domain suffix, masks username body.
 * - Names: Leaves initials or first name intact for staff recognition.
 */

if (!function_exists('mask_phone')) {
    /**
     * Mask a mobile or phone number.
     * e.g. "09505223146"      -> "0950 •••• 146"
     * e.g. "+639505223146"    -> "+63 950 •••• 146"
     * e.g. "9505223146"       -> "950 •••• 146"
     *
     * @param string|null $phone
     * @return string
     */
    function mask_phone(?string $phone): string {
        if (!$phone) {
            return '—';
        }

        // Clean white spaces and dashes
        $clean = preg_replace('/[^\d+]/', '', trim($phone));
        $len = strlen($clean);

        if ($len < 7) {
            return htmlspecialchars($clean);
        }

        // Format Philippine 11-digit or 10-digit mobile
        if (preg_match('/^(09\d{2})(\d{3,4})(\d{3})$/', $clean, $m)) {
            return $m[1] . ' •••• ' . $m[3];
        }

        if (preg_match('/^(\+639\d{2})(\d{3,4})(\d{3})$/', $clean, $m)) {
            return $m[1] . ' •••• ' . $m[3];
        }

        // Generic fallback: keep first 3 and last 3 digits
        $start = substr($clean, 0, 3);
        $end   = substr($clean, -3);
        return $start . ' •••• ' . $end;
    }
}

if (!function_exists('mask_email')) {
    /**
     * Mask an email address.
     * e.g. "justinebatuhan017@gmail.com" -> "j••••••••••••17@gmail.com"
     * e.g. "admin@beachclub.com"         -> "a•••n@beachclub.com"
     *
     * @param string|null $email
     * @return string
     */
    function mask_email(?string $email): string {
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email ? htmlspecialchars($email) : '—';
        }

        $parts = explode('@', $email, 2);
        $user = $parts[0];
        $domain = $parts[1] ?? '';
        $uLen = strlen($user);

        if ($uLen <= 2) {
            $maskedUser = substr($user, 0, 1) . '•';
        } elseif ($uLen <= 4) {
            $maskedUser = $user[0] . str_repeat('•', $uLen - 2) . $user[$uLen - 1];
        } else {
            // Keep first 1 char and last 2 chars
            $maskedUser = $user[0] . str_repeat('•', min(8, $uLen - 3)) . substr($user, -2);
        }

        return $maskedUser . '@' . $domain;
    }
}
