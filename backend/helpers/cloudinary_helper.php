<?php
/**
 * cloudinary_helper.php — Cloudinary Cloud Storage Upload Helper
 *
 * Provides standalone image upload and delete functions using Cloudinary REST API.
 * Uses native PHP cURL (no external SDK required).
 */

require_once __DIR__ . '/../config/env_loader.php';
if (!defined('_ENV_LOADED')) {
    load_env(__DIR__ . '/../../.env');
    define('_ENV_LOADED', true);
}

if (!defined('CLOUDINARY_CLOUD_NAME')) {
    define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: 'pyvbsoq9');
}
if (!defined('CLOUDINARY_API_KEY')) {
    define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY') ?: '868384347948186');
}
if (!defined('CLOUDINARY_API_SECRET')) {
    define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: 'YtDo7N6MMenfNOFCWy5a7RTlMHI');
}

/**
 * Upload a local image file to Cloudinary.
 *
 * @param string $filePath Absolute path or temporary upload path of the image.
 * @param string $folder Optional subfolder in Cloudinary (e.g. 'gallery', 'rooms', 'staff').
 * @return array ['success' => bool, 'url' => string|null, 'public_id' => string|null, 'error' => string|null]
 */
function cloudinary_upload(string $filePath, string $folder = 'sfbc_uploads'): array
{
    if (!file_exists($filePath)) {
        return ['success' => false, 'url' => null, 'public_id' => null, 'error' => 'Source file does not exist.'];
    }

    $cloudName = CLOUDINARY_CLOUD_NAME;
    $apiKey    = CLOUDINARY_API_KEY;
    $apiSecret = CLOUDINARY_API_SECRET;

    if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
        return ['success' => false, 'url' => null, 'public_id' => null, 'error' => 'Cloudinary credentials are not configured.'];
    }

    $timestamp = time();

    // Prepare parameters for signature calculation (sorted alphabetically)
    $paramsToSign = [];
    if (!empty($folder)) {
        $paramsToSign['folder'] = $folder;
    }
    $paramsToSign['timestamp'] = $timestamp;
    ksort($paramsToSign);

    // Build signature string: <key1>=<val1>&<key2>=<val2><api_secret>
    $sigParts = [];
    foreach ($paramsToSign as $k => $v) {
        $sigParts[] = "{$k}={$v}";
    }
    $toSign = implode('&', $sigParts) . $apiSecret;
    $signature = sha1($toSign);

    // Build multipart POST fields
    $postFields = [
        'file'      => curl_file_create($filePath),
        'api_key'   => $apiKey,
        'timestamp' => $timestamp,
        'signature' => $signature,
    ];
    if (!empty($folder)) {
        $postFields['folder'] = $folder;
    }

    $uploadUrl = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $uploadUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'url' => null, 'public_id' => null, 'error' => "cURL error: {$curlError}"];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['secure_url'])) {
        return [
            'success'   => true,
            'url'       => $data['secure_url'],
            'public_id' => $data['public_id'] ?? null,
            'error'     => null,
        ];
    }

    $errMsg = $data['error']['message'] ?? "Cloudinary upload failed with HTTP {$httpCode}";
    return ['success' => false, 'url' => null, 'public_id' => null, 'error' => $errMsg];
}

/**
 * Helper to check if an image path is a remote URL or local path.
 */
function is_remote_image(?string $path): bool
{
    if (empty($path)) {
        return false;
    }
    return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
}

/**
 * Returns the proper image src for rendering in HTML.
 * Handles both remote Cloudinary URLs and local asset paths.
 */
function get_image_url(?string $path, string $defaultFallback = 'assets/logo.jpg', string $localPrefix = ''): string
{
    if (empty($path)) {
        return $defaultFallback;
    }
    if (is_remote_image($path)) {
        return $path;
    }
    return $localPrefix . $path;
}
