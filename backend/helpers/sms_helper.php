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
