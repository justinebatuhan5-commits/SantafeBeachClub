<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';
require_once __DIR__ . '/../backend/services/chatbot_tools.php';

// Force local grounded engine during CLI test for deterministic offline verification
putenv('GEMINI_API_KEY=');
$_ENV['GEMINI_API_KEY'] = '';

function assertTrue(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "=== Intelligent NLU with Grammar & Typo Tolerance Verification ===\n";

// 1. Text Normalization Unit Tests
echo "\n--- Section 1: Preprocessor & Typo Normalization Tests ---\n";
$norm1 = sf_chatbot_normalize_nlu_text('u have room tomorow?');
assertTrue(str_contains($norm1, 'you') && str_contains($norm1, 'tomorrow'), "Normalized 'u have room tomorow?' -> '{$norm1}'");

$norm2 = sf_chatbot_normalize_nlu_text('hm is ur villa?');
assertTrue(str_contains($norm2, 'how much') && str_contains($norm2, 'your'), "Normalized 'hm is ur villa?' -> '{$norm2}'");

$norm3 = sf_chatbot_normalize_nlu_text('wanna book tom?');
assertTrue(str_contains($norm3, 'want to book') && str_contains($norm3, 'tomorrow'), "Normalized 'wanna book tom?' -> '{$norm3}'");

$norm4 = sf_chatbot_normalize_nlu_text('is room avail?');
assertTrue(str_contains($norm4, 'available'), "Normalized 'is room avail?' -> '{$norm4}'");

// 2. Room Typo & Fuzzy Matching Tests
echo "\n--- Section 2: Accommodation Typo Tolerance Tests ---\n";
assertTrue(sf_chatbot_normalize_room_slug('vila') === 'beach_villa', "Misspelled 'vila' mapped to 'beach_villa'");
assertTrue(sf_chatbot_normalize_room_slug('cottage') === 'beach_villa', "Synonym 'cottage' mapped to 'beach_villa'");
assertTrue(sf_chatbot_normalize_room_slug('bakante cottage') === 'beach_villa', "'bakante cottage' mapped to 'beach_villa'");
assertTrue(sf_chatbot_normalize_room_slug('duplx') === 'beachview_duplex', "Misspelled 'duplx' mapped to 'beachview_duplex'");
assertTrue(sf_chatbot_normalize_room_slug('seaveiw') === 'seaview_duplex', "Misspelled 'seaveiw' mapped to 'seaview_duplex'");
assertTrue(sf_chatbot_normalize_room_slug('standrd') === 'standard_room', "Misspelled 'standrd' mapped to 'standard_room'");

// 3. Multilingual Detection Tests
echo "\n--- Section 3: Multilingual Understanding Tests ---\n";
assertTrue(sf_chatbot_detect_language('Naa moy available room?') === 'Cebuano / Bisaya', "Detected Cebuano / Bisaya from 'Naa moy available room?'");
assertTrue(sf_chatbot_detect_language('Pila inyo cottage?') === 'Cebuano / Bisaya', "Detected Cebuano / Bisaya from 'Pila inyo cottage?'");
assertTrue(sf_chatbot_detect_language('Pwede magbook ugma?') === 'Cebuano / Bisaya', "Detected Cebuano / Bisaya from 'Pwede magbook ugma?'");
assertTrue(sf_chatbot_detect_language('Magkano po ang villa para sa 5 tao?') === 'Tagalog', "Detected Tagalog from 'Magkano po ang villa...'");
assertTrue(sf_chatbot_detect_language('How much ang villa good for 5 persons?') === 'English', "Detected English/Taglish from 'How much ang villa good for 5 persons?'");

// 4. Intent Recognition Tests
echo "\n--- Section 4: Intent Recognition with Grammar & Typo Tolerance ---\n";
// Table Examples from User Prompt:
// | "u have room tomorow?" | Check room availability for tomorrow |
// | "hm villa for 5 person?" | Ask or retrieve the villa price for five guests |
// | "naa moy bakante cottage?" | Cottage availability inquiry |
// | "i want book tom" | Reservation assistance for tomorrow |
// | "pila inyo rooms?" | Accommodation price inquiry |
// | "can i cancel my book?" | Cancellation policy inquiry |

assertTrue(sf_chatbot_detect_intent('u have room tomorow?') === 'availability', "Intent 'availability' recognized for 'u have room tomorow?'");
assertTrue(sf_chatbot_detect_intent('hm villa for 5 person?') === 'rates_rooms', "Intent 'rates_rooms' recognized for 'hm villa for 5 person?'");
assertTrue(sf_chatbot_detect_intent('naa moy bakante cottage?') === 'availability', "Intent 'availability' recognized for 'naa moy bakante cottage?'");
assertTrue(sf_chatbot_detect_intent('i want book tom') === 'reservation_assistance', "Intent 'reservation_assistance' recognized for 'i want book tom'");
assertTrue(sf_chatbot_detect_intent('pila inyo rooms?') === 'rates_rooms', "Intent 'rates_rooms' recognized for 'pila inyo rooms?'");
assertTrue(sf_chatbot_detect_intent('can i cancel my book?') === 'cancellation_refund', "Intent 'cancellation_refund' recognized for 'can i cancel my book?'");

// Additional informal expressions
assertTrue(sf_chatbot_detect_intent('how many person can stay?') === 'rates_rooms', "Intent 'rates_rooms' recognized for 'how many person can stay?'");
assertTrue(sf_chatbot_detect_intent('is room avail?') === 'availability', "Intent 'availability' recognized for 'is room avail?'");
assertTrue(sf_chatbot_detect_intent('Pwede magbook ugma?') === 'reservation_assistance', "Intent 'reservation_assistance' recognized for 'Pwede magbook ugma?'");
assertTrue(sf_chatbot_detect_intent('where is resort located?') === 'location_directions', "Intent 'location_directions' recognized for 'where is resort located?'");
assertTrue(sf_chatbot_detect_intent('u have wifi?') === 'amenities', "Intent 'amenities' recognized for 'u have wifi?'");
assertTrue(sf_chatbot_detect_intent('how to pay gcash?') === 'payment_deposit', "Intent 'payment_deposit' recognized for 'how to pay gcash?'");
assertTrue(sf_chatbot_detect_intent('checkin time?') === 'checkin_checkout', "Intent 'checkin_checkout' recognized for 'checkin time?'");

// 5. Context-Aware Multi-turn Conversation Test
echo "\n--- Section 5: Context-Aware Multi-turn Conversation Tests ---\n";
// Example from user request:
// Guest: "Do you have available rooms?"
// Chatbot: "Yes, I can help you check availability. What date will you arrive?"
// Guest: "tomorrow" -> Understand tomorrow is the arrival date
// Guest: "5 persons" -> Understand 5 is the guest count

$token = bin2hex(random_bytes(16));
$_SESSION['chatbot_session_token'] = $token;
$_SESSION['chatbot_history'] = [];

// Turn 1: "Do you have available rooms?"
$r1 = sf_chatbot_send_guest_message($conn, 'Do you have available rooms?', 'English');
assertTrue($r1['success'] === true, 'Turn 1 processed successfully');
assertTrue($r1['intent'] === 'availability', 'Turn 1 detected availability intent');
assertTrue(str_contains(strtolower($r1['reply']), 'check') || str_contains(strtolower($r1['reply']), 'availability'), 'Turn 1 asks for dates politely');

// Turn 2: "tomorrow" (or "ugma")
$r2 = sf_chatbot_send_guest_message($conn, 'tomorrow', 'English');
assertTrue($r2['success'] === true, 'Turn 2 processed successfully');
assertTrue(!empty($r2['context_memory']['dates']), 'Turn 2 context memory captured arrival date for tomorrow');
assertTrue($r2['intent'] === 'availability', 'Turn 2 contextual intent upgraded to availability');

// Turn 3: "5 persons"
$r3 = sf_chatbot_send_guest_message($conn, '5 persons', 'English');
assertTrue($r3['success'] === true, 'Turn 3 processed successfully');
assertTrue(($r3['context_memory']['guests'] ?? 0) === 5, 'Turn 3 context memory captured 5 persons as guest count');
assertTrue(!empty($r3['context_memory']['dates']), 'Turn 3 retained travel dates from Turn 2');
assertTrue($r3['intent'] === 'availability', 'Turn 3 retained availability intent with dates and 5 guests');

// Clean up test conversation from DB
$conn->query("DELETE FROM chatbot_conversations WHERE session_token = '{$token}'");

echo "\nALL NLU & GRAMMAR/TYPO TOLERANCE TESTS PASSED!\n";
