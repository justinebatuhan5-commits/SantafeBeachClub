<?php

/** Verify the exact request bytes before opening a database connection. */
function verify_paymongo_signature(string $body, string $header, string $secret, bool $liveMode): bool
{
    if (trim($secret) === '' || $body === '' || trim($header) === '') {
        return false;
    }

    $values = [];
    foreach (explode(',', $header) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) !== 2) {
            return false;
        }
        $key = trim($pair[0]);
        if (!in_array($key, ['t', 'te', 'li'], true) || array_key_exists($key, $values)) {
            return false;
        }
        $values[$key] = trim($pair[1]);
    }

    $timestamp = $values['t'] ?? '';
    $signature = $values[$liveMode ? 'li' : 'te'] ?? '';
    if (!preg_match('/^[1-9][0-9]{0,11}$/D', $timestamp)
        || !preg_match('/^[a-fA-F0-9]{64}$/D', $signature)) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    return hash_equals($expected, strtolower($signature));
}
