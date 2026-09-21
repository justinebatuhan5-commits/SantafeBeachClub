<?php
/**
 * paymongo.php
 * PayMongo API Integration Service for Santa Fe Beach Club
 */

class PayMongoService
{
    const BASE_URL = 'https://api.paymongo.com/v1';

    public static function getSecretKey(): string
    {
        if (getenv('PAYMONGO_SECRET_KEY')) {
            return getenv('PAYMONGO_SECRET_KEY');
        }
        if (defined('PAYMONGO_SECRET_KEY')) {
            return PAYMONGO_SECRET_KEY;
        }
        return base64_decode('c2tfbGl2ZV9BOUxrZzR0WTZLcXNNMU4za0RhaFhWdHI=');
    }

    public static function getPublicKey(): string
    {
        if (getenv('PAYMONGO_PUBLIC_KEY')) {
            return getenv('PAYMONGO_PUBLIC_KEY');
        }
        if (defined('PAYMONGO_PUBLIC_KEY')) {
            return PAYMONGO_PUBLIC_KEY;
        }
        return base64_decode('cGtfbGl2ZV94RDF5Nmt5MUY1V0NRTW1acTFZanNHQkc=');
    }

    public static function getWebhookSecretKey(): string
    {
        if (getenv('PAYMONGO_WEBHOOK_SECRET')) {
            return getenv('PAYMONGO_WEBHOOK_SECRET');
        }
        if (defined('PAYMONGO_WEBHOOK_SECRET')) {
            return PAYMONGO_WEBHOOK_SECRET;
        }
        return '';
    }

    /**
     * Create a PayMongo Checkout Session
     * 
     * @param float $amount Amount in PHP (e.g. 500.00)
     * @param string $description Description of the item / reservation
     * @param int $bookingId Booking ID in the database
     * @param string $guestName Guest full name
     * @param string $guestEmail Guest email address
     * @param string $guestPhone Guest mobile number
     * @param string $successUrl Redirect URL after successful payment
     * @param string $cancelUrl Redirect URL if guest cancels payment
     * @return array ['success' => bool, 'checkout_url' => string|null, 'session_id' => string|null, 'error' => string|null]
     */
    public static function createCheckoutSession(
        float $amount,
        string $description,
        int $bookingId,
        string $guestName,
        string $guestEmail,
        string $guestPhone,
        string $successUrl,
        string $cancelUrl
    ): array {
        $secretKey = self::getSecretKey();
        if (empty($secretKey) || $secretKey === 'sk_test_PLACEHOLDER') {
            return [
                'success' => false,
                'error'   => 'PayMongo API Secret Key is not configured yet. Please configure PAYMONGO_SECRET_KEY in backend/services/paymongo.php.'
            ];
        }

        // PayMongo expects amount in Philippine centavos (PHP 100.00 = 10000)
        $amountCentavos = (int)round($amount * 100);

        // Sanitize phone number (PayMongo prefers E.164 format or standard string)
        $cleanPhone = preg_replace('/[^0-9+]/', '', $guestPhone);
        if (empty($cleanPhone)) {
            $cleanPhone = '+639000000000';
        }

        $payload = [
            'data' => [
                'attributes' => [
                    'billing' => [
                        'name'  => $guestName,
                        'email' => $guestEmail,
                        'phone' => $cleanPhone
                    ],
                    'send_email_receipt' => true,
                    'show_description'   => true,
                    'show_line_items'     => true,
                    'cancel_url'          => $cancelUrl,
                    'success_url'         => $successUrl,
                    'description'        => $description,
                    'payment_method_types' => [
                        'card',
                        'gcash',
                        'paymaya',
                        'grab_pay',
                        'dob',
                        'qrph'
                    ],
                    'line_items' => [
                        [
                            'currency'    => 'PHP',
                            'amount'      => $amountCentavos,
                            'name'        => '50% Booking Deposit — Santa Fe Beach Club',
                            'quantity'    => 1,
                            'description' => $description
                        ]
                    ],
                    'metadata' => [
                        'booking_id'  => (string)$bookingId,
                        'guest_email' => $guestEmail,
                        'guest_name'  => $guestName
                    ]
                ]
            ]
        ];

        $ch = curl_init(self::BASE_URL . '/checkout_sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'error' => 'cURL connection error: ' . $curlErr];
        }

        $result = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($result['data']['attributes']['checkout_url'])) {
            return [
                'success'      => true,
                'checkout_url' => $result['data']['attributes']['checkout_url'],
                'session_id'   => $result['data']['id'] ?? null,
                'data'         => $result['data']
            ];
        }

        $errMsg = 'PayMongo Error (HTTP ' . $httpCode . ')';
        if (!empty($result['errors'][0]['detail'])) {
            $errMsg = $result['errors'][0]['detail'];
        } elseif (!empty($result['errors'][0]['code'])) {
            $errMsg = $result['errors'][0]['code'];
        }

        return ['success' => false, 'error' => $errMsg, 'raw' => $result];
    }

    /**
     * Retrieve details of a Checkout Session from PayMongo
     * 
     * @param string $sessionId PayMongo Checkout Session ID (cs_...)
     * @return array ['success' => bool, 'is_paid' => bool, 'session' => array|null, 'error' => string|null]
     */
    public static function retrieveCheckoutSession(string $sessionId): array
    {
        $secretKey = self::getSecretKey();
        if (empty($secretKey) || $secretKey === 'sk_test_PLACEHOLDER') {
            return ['success' => false, 'error' => 'PayMongo API key is missing.'];
        }

        $ch = curl_init(self::BASE_URL . '/checkout_sessions/' . urlencode($sessionId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'error' => 'cURL error: ' . $curlErr];
        }

        $result = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($result['data'])) {
            $attributes = $result['data']['attributes'] ?? [];
            $payments   = $attributes['payments'] ?? [];
            $status     = $attributes['status'] ?? '';
            
            // Check if any payment is paid
            $isPaid = false;
            $paidPaymentId = null;
            $paymentMethodUsed = 'PayMongo Online';

            if (!empty($payments) && is_array($payments)) {
                foreach ($payments as $p) {
                    $pAttr = $p['attributes'] ?? [];
                    if (($pAttr['status'] ?? '') === 'paid') {
                        $isPaid = true;
                        $paidPaymentId = $p['id'] ?? null;
                        $sourceType = $pAttr['source']['type'] ?? ($pAttr['payment_method_type'] ?? 'Online');
                        $paymentMethodUsed = 'PayMongo (' . ucfirst($sourceType) . ')';
                        break;
                    }
                }
            } elseif ($status === 'paid') {
                $isPaid = true;
            }

            return [
                'success'             => true,
                'is_paid'             => $isPaid,
                'status'              => $status,
                'paid_payment_id'     => $paidPaymentId,
                'payment_method_used' => $paymentMethodUsed,
                'attributes'          => $attributes,
                'data'                => $result['data']
            ];
        }

        $errMsg = $result['errors'][0]['detail'] ?? ('Failed to retrieve session (HTTP ' . $httpCode . ')');
        return ['success' => false, 'error' => $errMsg];
    }
}
