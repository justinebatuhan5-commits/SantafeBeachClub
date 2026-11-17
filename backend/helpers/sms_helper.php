<?php
declare(strict_types=1);

function sf_normalize_e164(string $number, string $dialCode = '+63'): ?string
{
    $number = trim($number);
    if ($number === '') return null;
    $number = preg_replace('/[^0-9+]/', '', $number);
    if (str_starts_with($number, '00')) $number = '+' . substr($number, 2);
    if (!str_starts_with($number, '+')) {
        $dialDigits = ltrim(preg_replace('/\D+/', '', $dialCode), '0');
        if ($dialDigits === '') return null;
        $dialCode = '+' . $dialDigits;
        $number = $dialCode . ltrim($number, '0');
    }
    return preg_match('/^\+[1-9]\d{7,14}$/', $number) ? $number : null;
}

function sf_get_phone_length_bounds(): array
{
    return [
        '+1'   => [10, 10],
        '+7'   => [10, 10],
        '+20'  => [10, 10],
        '+27'  => [9, 9],
        '+30'  => [10, 10],
        '+31'  => [9, 9],
        '+32'  => [8, 9],
        '+33'  => [9, 9],
        '+34'  => [9, 9],
        '+39'  => [9, 10],
        '+41'  => [9, 9],
        '+44'  => [10, 10],
        '+49'  => [6, 13],
        '+55'  => [10, 11],
        '+60'  => [9, 10],
        '+61'  => [9, 9],
        '+62'  => [8, 12],
        '+64'  => [8, 10],
        '+65'  => [8, 8],
        '+66'  => [9, 9],
        '+81'  => [9, 10],
        '+82'  => [9, 10],
        '+84'  => [9, 10],
        '+86'  => [11, 11],
        '+91'  => [10, 10],
        '+852' => [8, 8],
        '+853' => [8, 8],
        '+855' => [8, 9],
        '+880' => [10, 10],
        '+886' => [9, 9],
        '+966' => [9, 9],
        '+971' => [9, 9],
        '+974' => [8, 8],
        '+996' => [9, 9],
    ];
}

function sf_get_phone_prefix_patterns(): array
{
    return [
        '+880' => '/^1[3-9]\d{8}$/',
        '+91'  => '/^[6-9]\d{9}$/',
        '+65'  => '/^[689]\d{7}$/',
        '+852' => '/^[2-9]\d{7}$/',
        '+853' => '/^[26]\d{7}$/',
    ];
}

function sf_validate_booking_phone(string $number, string $dialCode = '+63', string $country = 'Philippines'): ?string
{
    $trimmed = trim($number);
    $country = trim($country);
    $isForeign = ($country !== '' && strcasecmp($country, 'Philippines') !== 0);

    if ($trimmed === '') {
        return $isForeign ? null : 'Contact Number is required.';
    }

    $clean = preg_replace('/[\s()-]/', '', $trimmed);
    $dialCode = trim($dialCode);
    if (!preg_match('/^\+[1-9]\d{0,3}$/', $dialCode)) {
        return 'Please select a valid country calling code.';
    }

    if ($country !== '' && function_exists('sf_get_country_calling_codes')) {
        $codes = sf_get_country_calling_codes();
        $expectedDial = $codes[$country]['code'] ?? null;
        if (strcasecmp($country, 'Philippines') === 0 && $dialCode !== '+63') {
            return 'Please use PH +63 for Philippines contact numbers.';
        }
        if ($expectedDial !== null && $dialCode !== $expectedDial && $dialCode !== '+63') {
            return "Dial code {$dialCode} does not match {$country} ({$expectedDial}). Please use {$expectedDial} or PH +63.";
        }
    }

    if (str_starts_with($clean, '+')) {
        if (!str_starts_with($clean, $dialCode)) {
            return "Country code mismatch. Enter digits without a country code or switch the selector from {$dialCode}.";
        }
        $nationalRaw = substr($clean, strlen($dialCode));
    } else {
        $nationalRaw = $clean;
    }

    if ($dialCode === '+63') {
        if (preg_match('/^(?:\+?63|0)?9\d{9}$/', $clean) !== 1) {
            return 'Please enter a valid PH mobile number (e.g. 09171234567 or 9171234567).';
        }
        return null;
    }

    if (!preg_match('/^\d+$/', $nationalRaw)) {
        return 'Please enter digits only for the phone number.';
    }

    $nationalDigits = ltrim($nationalRaw, '0');
    $boundsMap = sf_get_phone_length_bounds();
    [$minLen, $maxLen] = $boundsMap[$dialCode] ?? [6, 12];
    $nationalLen = strlen($nationalDigits);

    if ($nationalLen < $minLen || $nationalLen > $maxLen) {
        $expectedLabel = ($minLen === $maxLen)
            ? "{$minLen} digits"
            : "{$minLen} to {$maxLen} digits";
        return "Please enter a valid phone number ({$expectedLabel} for {$dialCode}).";
    }

    $prefixMap = sf_get_phone_prefix_patterns();
    if (isset($prefixMap[$dialCode]) && preg_match($prefixMap[$dialCode], $nationalDigits) !== 1) {
        return "Please enter a valid mobile number for {$dialCode}.";
    }

    if (sf_normalize_e164($trimmed, $dialCode) === null) {
        return 'Please enter a valid international phone number (8 to 15 digits total).';
    }

    return null;
}

function sf_mask_phone(string $number): string
{
    $normalized = sf_normalize_e164($number);
    if (!$normalized) return 'unavailable';
    $visible = substr($normalized, -3);
    return substr($normalized, 0, min(3, strlen($normalized) - 3)) . str_repeat('•', max(4, strlen($normalized) - strlen($visible) - 3)) . $visible;
}

function sf_sms_provider_configured(): bool
{
    $provider = strtolower((string)getenv('SMS_PROVIDER'));
    if ($provider === 'twilio') return getenv('TWILIO_ACCOUNT_SID') !== false && getenv('TWILIO_AUTH_TOKEN') !== false && getenv('TWILIO_FROM_NUMBER') !== false;
    if ($provider === 'semaphore') return getenv('SEMAPHORE_API_KEY') !== false;
    return false;
}

/** @return array{success:bool,error:?string} */
function sf_send_sms(string $to, string $message): array
{
    $to = sf_normalize_e164($to) ?? '';
    if ($to === '' || !sf_sms_provider_configured() || !function_exists('curl_init')) return ['success' => false, 'error' => 'SMS delivery is not configured.'];
    $provider = strtolower((string)getenv('SMS_PROVIDER'));
    if ($provider === 'twilio') {
        $sid = (string)getenv('TWILIO_ACCOUNT_SID');
        $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => (string)getenv('TWILIO_FROM_NUMBER'), 'Body' => $message]), CURLOPT_USERPWD => $sid . ':' . (string)getenv('TWILIO_AUTH_TOKEN'), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    } elseif ($provider === 'semaphore' && str_starts_with($to, '+63')) {
        $ch = curl_init('https://api.semaphore.co/api/v4/messages');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['apikey' => (string)getenv('SEMAPHORE_API_KEY'), 'number' => $to, 'message' => $message, 'sendername' => (string)(getenv('SEMAPHORE_SENDER_NAME') ?: 'SantaFe')]), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    } else {
        return ['success' => false, 'error' => 'The configured SMS provider does not support this destination.'];
    }
    $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) return ['success' => false, 'error' => $error !== '' ? $error : 'SMS provider rejected the message.'];
    return ['success' => true, 'error' => null];
}
