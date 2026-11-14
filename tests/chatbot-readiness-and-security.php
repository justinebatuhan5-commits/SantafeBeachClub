<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('session.save_path', sys_get_temp_dir());

function assert_chatbot(bool $cond, string $label): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}

$root = dirname(__DIR__);

// Load functions without requiring a live MySQL socket for pure unit checks
require_once $root . '/backend/helpers/date_helper.php';
require_once $root . '/backend/helpers/business_time_helper.php';
require_once $root . '/backend/helpers/guest_auth_helper.php';
require_once $root . '/backend/helpers/pii_masker.php';

// 1. Verify cancellation policy accuracy (48h / 5d / 7d, never 72h)
$p2Nights = sf_get_cancellation_policy('2027-05-10', '2027-05-12');
assert_chatbot($p2Nights['deadline_hours'] === 48 && $p2Nights['window_label'] === '48 hours', '1-3 night stay uses 48-hour cancellation policy');

$p5Nights = sf_get_cancellation_policy('2027-05-10', '2027-05-15');
assert_chatbot($p5Nights['deadline_hours'] === 120 && $p5Nights['window_label'] === '5 days', '4-6 night stay uses 5-day cancellation policy');

$p7Nights = sf_get_cancellation_policy('2027-05-10', '2027-05-18');
assert_chatbot($p7Nights['deadline_hours'] === 168 && $p7Nights['window_label'] === '7 days', '7+ night stay uses 7-day cancellation policy');

// 2. Verify chatbot_service.php and chatbot_tools.php source guarantees
$serviceSource = file_get_contents($root . '/backend/services/chatbot_service.php');
$toolsSource = file_get_contents($root . '/backend/services/chatbot_tools.php');
$widgetSource = file_get_contents($root . '/frontend/partials/_chatbot_widget.php');
$footerSource = file_get_contents($root . '/frontend/partials/_footer.php');
$termsSource = file_get_contents($root . '/terms.php');

assert_chatbot(!str_contains($serviceSource, '?key='), 'Gemini API key is never passed in URL query string');
assert_chatbot(str_contains($serviceSource, 'x-goog-api-key:'), 'Gemini API key is passed via x-goog-api-key HTTP header');
assert_chatbot(!str_contains($serviceSource, '3,450') && !str_contains($serviceSource, '4,500'), 'Retired fake room prices (3,450 / 4,500) are not present');
assert_chatbot(!str_contains($serviceSource, 'up to 72 hours'), 'Retired inaccurate 72-hour cancellation claim is not present');
assert_chatbot(str_contains($serviceSource, '24 hours before'), 'Balance due 24 hours before arrival is stated accurately');

// 3. Verify widget XSS protection & global inclusion
assert_chatbot(str_contains($widgetSource, 'sfbcFormatSafeReply'), 'Widget uses XSS-safe reply formatter');
assert_chatbot(!str_contains($widgetSource, '/laravel_core/public/api/v1/chatbot/message'), 'Widget no longer points to hardcoded local Laravel URL');
assert_chatbot(str_contains($widgetSource, 'backend/api/chatbot_api.php'), 'Widget points to native PHP chatbot API');
assert_chatbot(str_contains($footerSource, '_chatbot_widget.php'), 'Footer includes the chatbot widget across all public pages');
assert_chatbot(str_contains($termsSource, 'Virtual Guest Assistant (AI Concierge) Transcripts'), 'Terms & Privacy Policy includes RA 10173 AI Concierge disclosure');

// 4. Verify Laravel files were NOT deleted
assert_chatbot(is_file($root . '/laravel_core/app/Services/GeminiChatbotService.php'), 'laravel_core files remain untouched');

echo "All chatbot readiness, grounding, and security checks passed.\n";
