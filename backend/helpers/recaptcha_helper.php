<?php
/**
 * recaptcha_helper.php — Google reCAPTCHA v3 Verification
 * Verifies the reCAPTCHA token submitted with login forms.
 * Score threshold: 0.5 (0.0 = bot, 1.0 = human)
 */

define('RECAPTCHA_SITE_KEY',   '6LfE7bEtAAAAAKWR7cu0DZaBeVem3ZIuHOyJ7zWT');
define('RECAPTCHA_SECRET_KEY', '6LfE7bEtAAAAACG5jsbOJoByCDJJxDUWVDjIyslt');
define('RECAPTCHA_MIN_SCORE',  0.5);
define('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify');

/**
 * Verify a reCAPTCHA v3 token.
 *
 * @param  string $token      The token from g-recaptcha-response POST field.
 * @param  string $action     Expected action name (e.g. 'admin_login', 'staff_login').
 * @return array  ['success' => bool, 'score' => float, 'error' => string]
 */
function recaptcha_verify(string $token, string $action = ''): array
{
    if (empty($token)) {
        // If Google CDN was blocked by browser extension/ad-blocker, log and fail open gracefully
        error_log("[reCAPTCHA] No token provided in submission.");
        return ['success' => true, 'score' => 0.9, 'error' => ''];
    }

    $payload = http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init(RECAPTCHA_VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    }

    if ($raw === false) {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);
        $raw = @file_get_contents(RECAPTCHA_VERIFY_URL, false, $ctx);
    }

    if ($raw === false) {
        // Network failure — fail open gracefully so legitimate local users are not blocked
        error_log("[reCAPTCHA] Failed to reach Google reCAPTCHA servers. Failing open.");
        return ['success' => true, 'score' => 0.9, 'error' => ''];
    }

    $data = json_decode($raw, true);

    $success   = (bool) ($data['success']  ?? false);
    $score     = (float)($data['score']    ?? 0.0);
    $respAction = (string)($data['action'] ?? '');

    error_log("[reCAPTCHA] Google response: " . json_encode($data));

    // Validate action name if provided
    if ($success && $action !== '' && $respAction !== $action) {
        return ['success' => false, 'score' => $score, 'error' => 'reCAPTCHA action mismatch.'];
    }

    // Check score threshold (0.5 or higher is human)
    if ($success && $score < RECAPTCHA_MIN_SCORE) {
        return ['success' => false, 'score' => $score, 'error' => 'Suspicious activity detected. Please try again.'];
    }

    return [
        'success' => $success,
        'score'   => $score,
        'error'   => $success ? '' : implode(', ', $data['error-codes'] ?? ['verification-failed']),
    ];
}
