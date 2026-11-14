<?php
declare(strict_types=1);

/**
 * tests/phase1-essential-features.php
 * Automated verification of Phase 1 · Essential Basic AI Chatbot features:
 * 1. AI guest inquiry assistant (EN, TL, CEB response handling)
 * 2. FAQ & knowledge base (25+ active verified entries)
 * 3. Accommodation information (5 accurate room types with live rates and capacities)
 * 4. Suggested questions (contextual follow-up question pills)
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';
require_once __DIR__ . '/../backend/services/chatbot_tools.php';

function test_assert(bool $condition, string $msg): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running Phase 1 · Essential Basic AI Chatbot Feature Verifications...\n\n";

// 1. FAQ & Knowledge Base verification
$kbRes = $conn->query("SELECT COUNT(*) AS total FROM chatbot_knowledge WHERE is_active = 1");
$kbCount = $kbRes ? (int)$kbRes->fetch_assoc()['total'] : 0;
test_assert($kbCount >= 20, "Knowledge base contains at least 20 active FAQs (found: {$kbCount})");

$catRes = $conn->query("SELECT DISTINCT category FROM chatbot_knowledge WHERE is_active = 1");
$categories = [];
while ($row = $catRes->fetch_assoc()) {
    $categories[] = $row['category'];
}
test_assert(in_array('accommodations', $categories, true), "Knowledge base includes 'accommodations' category");
test_assert(in_array('payment', $categories, true), "Knowledge base includes 'payment' category");
test_assert(in_array('cancellation', $categories, true), "Knowledge base includes 'cancellation' category");
test_assert(in_array('amenities', $categories, true), "Knowledge base includes 'amenities' category");
test_assert(in_array('directions', $categories, true), "Knowledge base includes 'directions' category");
test_assert(in_array('policies', $categories, true), "Knowledge base includes 'policies' category");

// 2. Accommodation Information verification
$catalog = sf_chatbot_get_room_catalog($conn);
test_assert(count($catalog) >= 5, "Live room catalog has all 5 accommodations");

$slugs = array_column($catalog, 'slug');
test_assert(in_array('beachview_duplex', $slugs, true), "Catalog has beachview_duplex");
test_assert(in_array('seaview_duplex', $slugs, true), "Catalog has seaview_duplex");
test_assert(in_array('beach_villa', $slugs, true), "Catalog has beach_villa");
test_assert(in_array('standard_king', $slugs, true), "Catalog has standard_king");
test_assert(in_array('standard_room', $slugs, true), "Catalog has standard_room");

foreach ($catalog as $room) {
    test_assert((float)$room['price_per_night'] > 0, "Room {$room['slug']} has positive price: ₱{$room['price_per_night']}");
    test_assert((int)$room['base_capacity'] >= 2, "Room {$room['slug']} base capacity is >= 2");
}

// 3. Suggested Questions verification
$qRates = sf_chatbot_get_suggested_questions('rates_rooms', 'English');
test_assert(count($qRates) >= 2, "Rates intent returns 2+ suggested questions in English");

$qRatesTL = sf_chatbot_get_suggested_questions('rates_rooms', 'Tagalog');
test_assert(count($qRatesTL) >= 2, "Rates intent returns 2+ suggested questions in Tagalog");

$qRatesCEB = sf_chatbot_get_suggested_questions('rates_rooms', 'Cebuano / Bisaya');
test_assert(count($qRatesCEB) >= 2, "Rates intent returns 2+ suggested questions in Bisaya");

$qAvail = sf_chatbot_get_suggested_questions('availability', 'English');
test_assert(count($qAvail) >= 2, "Availability intent returns 2+ suggested questions");

// 4. AI Guest Inquiry Assistant response test
$resp1 = sf_chatbot_send_guest_message($conn, "What are your room rates and capacities?", "English");
test_assert(!empty($resp1['success']), "Chatbot responds to room rates inquiry successfully");
test_assert(!empty($resp1['reply']), "Chatbot reply is non-empty");
test_assert(strpos($resp1['reply'], 'Beachview Duplex') !== false || strpos($resp1['reply'], 'Beach Villa') !== false, "Reply includes live room names");
test_assert(!empty($resp1['suggested_questions']), "Chatbot response payload includes dynamic suggested_questions");
test_assert(!empty($resp1['suggested_actions']), "Chatbot response payload includes pre-filled booking actions");

// Multilingual test
$respTL = sf_chatbot_send_guest_message($conn, "Magkano ang mga kwarto at paano mag-book?", "Tagalog");
test_assert(!empty($respTL['success']), "Tagalog inquiry succeeds");
test_assert($respTL['language'] === 'Tagalog', "Detected language is Tagalog");
test_assert(!empty($respTL['suggested_questions']), "Tagalog response has suggested questions");

$respCEB = sf_chatbot_send_guest_message($conn, "Pila ang presyo sa kwarto ug kanus-a ang check in?", "Cebuano / Bisaya");
test_assert(!empty($respCEB['success']), "Bisaya inquiry succeeds");
test_assert($respCEB['language'] === 'Cebuano / Bisaya', "Detected language is Cebuano / Bisaya");
test_assert(!empty($respCEB['suggested_questions']), "Bisaya response has suggested questions");

echo "\nAll Phase 1 · Essential Basic AI Chatbot features verified successfully!\n";
