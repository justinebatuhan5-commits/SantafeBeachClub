<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';
require_once __DIR__ . '/../backend/services/chatbot_tools.php';

// Force local grounded engine during CLI test for fast deterministic assertions
putenv('GEMINI_API_KEY=');
$_ENV['GEMINI_API_KEY'] = '';

function assertTrue(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "=== Phase 4 · Advanced (Intelligent Assistance) Verification ===\n";

$widgetContent = (string)file_get_contents(__DIR__ . '/../frontend/partials/_chatbot_widget.php')
    . "\n"
    . (string)file_get_contents(__DIR__ . '/../frontend/assets/js/components/chatbot-widget.js');
$adminContent  = (string)file_get_contents(__DIR__ . '/../frontend/admin_chatbot.php')
    . "\n" . (string)file_get_contents(__DIR__ . '/../frontend/views/admin_chatbot/_tab_analytics.php');
$apiContent    = (string)file_get_contents(__DIR__ . '/../backend/api/chatbot_api.php');

// 1. Multilingual Support Verification
$tlToken = bin2hex(random_bytes(32));
$_SESSION['chatbot_session_token'] = $tlToken;
$_SESSION['chatbot_history'] = [];

$tlRes = sf_chatbot_send_guest_message($conn, 'Magkano ang mga kwarto at paano magbayad ng deposit?');
assertTrue(($tlRes['language'] ?? '') === 'Tagalog', 'Auto-detected Tagalog language from guest query');
assertTrue(str_contains((string)$tlRes['reply'], 'Santa Fe Beach Club'), 'Tagalog response generated');

// Follow-up with only dates (no Tagalog words) should preserve Tagalog from conversation context
$tlFollow = sf_chatbot_send_guest_message($conn, '2026-12-20 to 2026-12-22');
assertTrue(($tlFollow['language'] ?? '') === 'Tagalog', 'Preserved Tagalog language on short date-only follow-up turn');
assertTrue(str_contains((string)$tlFollow['reply'], 'Live room availability mula'), 'Localized Tagalog availability response returned');

$cebToken = bin2hex(random_bytes(32));
$_SESSION['chatbot_session_token'] = $cebToken;
$_SESSION['chatbot_history'] = [];

$cebRes = sf_chatbot_send_guest_message($conn, 'Pila ang presyo sa inyong mga kwarto?');
assertTrue(($cebRes['language'] ?? '') === 'Cebuano / Bisaya', 'Auto-detected Cebuano / Bisaya language from guest query');
assertTrue(str_contains((string)$cebRes['reply'], 'Ania ang among live accommodation rates'), 'Localized Cebuano / Bisaya rates response returned');
assertTrue(strpos($widgetContent, 'SFBC_UI_I18N') !== false && strpos($widgetContent, 'sfbcOnLangChange') !== false, 'Frontend widget includes live multilingual UI chip & placeholder localization');

// International All-Language Auto-Detection & Reply Verification (Korean, Japanese, Chinese, Spanish, French, German)
$intlTests = [
    ['query' => '객실 요금과 정원은 어떻게 되나요?', 'expected_lang' => 'Korean', 'expected_sub' => '한국어 안내'],
    ['query' => '客室料金と定員を教えてください。', 'expected_lang' => 'Japanese', 'expected_sub' => '日本語のご案内'],
    ['query' => '你们的房价和可住人数是多少？', 'expected_lang' => 'Chinese', 'expected_sub' => '中文指南'],
    ['query' => '¿Cuáles son las tarifas de las habitaciones?', 'expected_lang' => 'Spanish', 'expected_sub' => 'Guía en Español'],
    ['query' => 'Quels sont les tarifs des chambres ?', 'expected_lang' => 'French', 'expected_sub' => 'Guide en Français'],
    ['query' => 'Wie hoch sind die Zimmerpreise?', 'expected_lang' => 'German', 'expected_sub' => 'Information auf Deutsch'],
];
$intlTokens = [];
foreach ($intlTests as $it) {
    $tok = bin2hex(random_bytes(32));
    $intlTokens[] = $tok;
    $_SESSION['chatbot_session_token'] = $tok;
    $_SESSION['chatbot_history'] = [];
    $res = sf_chatbot_send_guest_message($conn, $it['query'], 'auto');
    assertTrue(($res['language'] ?? '') === $it['expected_lang'], "Auto-detected {$it['expected_lang']} from international guest query (got: " . ($res['language'] ?? 'null') . " | reply: " . substr((string)($res['reply'] ?? ''), 0, 80) . ")");
    assertTrue(str_contains((string)$res['reply'], $it['expected_sub']), "Localized {$it['expected_lang']} header/reply generated (got: " . substr((string)($res['reply'] ?? ''), 0, 80) . ")");
}
assertTrue(count(sf_chatbot_supported_languages()) >= 11, 'sf_chatbot_supported_languages() exposes all 11+ global & local languages');
assertTrue(strpos($widgetContent, 'sfbcRecommendPanel') !== false && strpos($widgetContent, 'sfbcSubmitRecommendQuery') !== false, 'Frontend widget includes interactive Smart Room Recommender & Reservation Helper drawer');

// 2. Context-Aware Multi-Turn Conversations Verification
$ctxToken = bin2hex(random_bytes(32));
$_SESSION['chatbot_session_token'] = $ctxToken;
$_SESSION['chatbot_history'] = [];

// Turn 1: Guest provides dates and guest count
$turn1 = sf_chatbot_send_guest_message($conn, 'Check availability from 2026-12-20 to 2026-12-22 for 4 guests', 'English');
assertTrue(($turn1['context_memory']['dates'] ?? null) === ['2026-12-20', '2026-12-22'], 'Turn 1 stored travel dates in Context Memory');
assertTrue(($turn1['context_memory']['guests'] ?? 0) === 4, 'Turn 1 stored 4 guests in Context Memory');

// Turn 2: Guest asks about a specific room WITHOUT repeating dates or guest count
$turn2 = sf_chatbot_send_guest_message($conn, 'How much for the Beach Villa?', 'English');
assertTrue(($turn2['tool_used'] ?? '') === 'estimate_price', 'Turn 2 automatically upgraded to estimate_price using remembered dates');
assertTrue(str_contains((string)$turn2['reply'], '2026-12-20 to 2026-12-22') && str_contains((string)$turn2['reply'], '4 guest(s)'), 'Turn 2 carried over remembered dates (2026-12-20 to 2026-12-22) and guest count (4) for Beach Villa');

// Turn 3: Guest asks about cancellation policy WITHOUT repeating dates
$turn3 = sf_chatbot_send_guest_message($conn, 'What is the cancellation deadline?', 'English');
assertTrue(($turn3['tool_used'] ?? '') === 'get_cancellation_policy', 'Turn 3 automatically computed exact cancellation deadline from remembered dates');
assertTrue(str_contains((string)$turn3['reply'], '**2-night stay** (2026-12-20 to 2026-12-22)'), 'Turn 3 reply includes exact 2-night stay cancellation window');
assertTrue(strpos($widgetContent, 'sfbcContextBar') !== false && strpos($apiContent, 'reset_context') !== false, 'Frontend widget displays Active Context Memory Bar with Reset Context support');

// 3. Unanswered Question Detection Verification
$uaToken = bin2hex(random_bytes(32));
$_SESSION['chatbot_session_token'] = $uaToken;
$_SESSION['chatbot_history'] = [];

$greetRes = sf_chatbot_send_guest_message($conn, 'Hello good morning!', 'English');
assertTrue(empty($greetRes['unanswered_detected']) && ($greetRes['intent'] ?? '') === 'general', 'Simple greeting is NOT flagged as an unanswered question');

$unansRes = sf_chatbot_send_guest_message($conn, 'Do you have a submarine helipad and scuba tank refilling station?', 'English');
assertTrue(!empty($unansRes['unanswered_detected']) && ($unansRes['intent'] ?? '') === 'unanswered', 'Unindexed specific question is detected and classified as intent=unanswered');
assertTrue(!empty($unansRes['suggest_handoff']), 'Unanswered question automatically sets suggest_handoff = true');
assertTrue(strpos($adminContent, 'Unanswered Guest Questions (Knowledge Gap Detector)') !== false, 'Admin Analytics includes Unanswered Guest Questions (Knowledge Gap Detector) table');

// 4. Personalized Recommendations Verification
$recToken = bin2hex(random_bytes(32));
$_SESSION['chatbot_session_token'] = $recToken;
$_SESSION['chatbot_history'] = [];

// Budget + Honeymoon preference test
$honeymoonRec = sf_chatbot_tool_recommend_accommodation($conn, 2, 'romantic honeymoon beachfront', 7000.0, '2026-12-20', '2026-12-22');
assertTrue(!empty($honeymoonRec['recommendations']), 'Personalized recommendation returned matches for honeymoon under ₱7,000');
$topHoneymoon = $honeymoonRec['recommendations'][0];
assertTrue($topHoneymoon['slug'] === 'beachview_duplex' && (float)$topHoneymoon['price_per_night'] <= 7000.0, 'Honeymoon under ₱7,000/night recommends Beachview Duplex (₱6,900) and filters out ₱7,900 rooms');
assertTrue(!empty($topHoneymoon['stay_total']) && str_contains((string)$topHoneymoon['booking_url'], 'checkin=2026-12-20'), 'Personalized recommendation includes live stay total and date-prefilled booking link');

// Senior / Ground floor preference test
$seniorChat = sf_chatbot_send_guest_message($conn, 'Which room do you recommend for 2 guests with senior citizens who need ground floor access under ₱7000?', 'English');
assertTrue(($seniorChat['intent'] ?? '') === 'recommendation' && ($seniorChat['tool_used'] ?? '') === 'recommend_accommodation', 'Natural language senior + budget query routed to recommend_accommodation');
assertTrue(str_contains((string)$seniorChat['reply'], 'Senior-Friendly Ground Floor'), 'Personalized reply highlights Senior-Friendly Ground Floor badge');

// Clean up test conversations
$allTokens = array_merge([$tlToken, $cebToken, $ctxToken, $uaToken, $recToken], $intlTokens);
$inList = "'" . implode("','", $allTokens) . "'";
$conn->query("DELETE FROM chatbot_conversations WHERE session_token IN ({$inList})");

echo "\nALL PHASE 4 INTELLIGENT ASSISTANCE CHECKS PASSED!\n";
