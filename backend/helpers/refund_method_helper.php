<?php

function sf_refund_method_settings(mysqli $db): array
{
    $settings = ['paymongo' => true, 'manual_gcash' => true];
    $result = $db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('refund_paymongo_enabled','refund_manual_gcash_enabled')");
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_key'] === 'refund_paymongo_enabled') $settings['paymongo'] = $row['setting_value'] === '1';
        if ($row['setting_key'] === 'refund_manual_gcash_enabled') $settings['manual_gcash'] = $row['setting_value'] === '1';
    }
    return $settings;
}

function sf_require_refund_method(mysqli $db, string $method): void
{
    $settings = sf_refund_method_settings($db);
    if (!array_key_exists($method, $settings) || !$settings[$method]) {
        $label = $method === 'paymongo' ? 'PayMongo refunds' : 'Manual GCash refunds';
        throw new RuntimeException($label . ' are temporarily disabled by the administrator.');
    }
}
