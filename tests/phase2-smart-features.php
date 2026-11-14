<?php
declare(strict_types=1);

/**
 * tests/phase2-smart-features.php
 * Automated verification of Phase 2 · Smart Features:
 * 1. Room availability checker (database integration with getAvailableRooms)
 * 2. Accommodation recommendations (smart recommendation engine with scoring & badges)
 * 3. Reservation assistance (stay pricing breakdowns & pre-filled booking URLs)
 * 4. Booking status checker (verified booking retrieval with payment & cancellation deadlines)
 */

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/services/chatbot_tools.php';
require_once __DIR__ . '/../backend/services/chatbot_service.php';

putenv('GEMINI_API_KEY=');
$_ENV['GEMINI_API_KEY'] = '';

function test_assert(bool $condition, string $msg): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running Phase 2 · Smart Features Verification Suite...\n\n";

$checkIn = date('Y-m-d', strtotime('+7 days'));
$checkOut = date('Y-m-d', strtotime('+9 days'));

// 1. Room Availability Checker
echo "--- Testing Feature 1: Room Availability Checker ---\n";
$avail = sf_chatbot_tool_check_availability($conn, $checkIn, $checkOut, 2);
test_assert(!empty($avail['success']), "Availability check returned success");
test_assert(count($avail['rooms']) >= 5, "Availability checked all 5 room types");
foreach ($avail['rooms'] as $r) {
    test_assert(isset($r['available_units']), "Room {$r['room_type']} includes available_units count");
    test_assert(isset($r['is_available']), "Room {$r['room_type']} includes boolean is_available");
    if ($r['is_available']) {
        test_assert(!empty($r['booking_url']), "Available room includes pre-filled booking_url");
        test_assert((float)$r['pricing']['total_amount'] > 0, "Available room includes calculated total_amount");
        test_assert((float)$r['pricing']['deposit_amount'] > 0, "Available room includes 50% deposit_amount");
    }
}

// Natural language date extraction test
$parsedDates1 = sf_chatbot_extract_dates("Check rooms from 2026-12-20 to 2026-12-22 for 2 guests");
test_assert($parsedDates1 !== null && $parsedDates1[0] === '2026-12-20' && $parsedDates1[1] === '2026-12-22', "ISO dates extracted accurately");

$parsedDates2 = sf_chatbot_extract_dates("Do you have vacancy for tomorrow?");
test_assert($parsedDates2 !== null && $parsedDates2[0] === date('Y-m-d', strtotime('+1 day')), "Tomorrow relative date extracted");

$parsedDates3 = sf_chatbot_extract_dates("Available ba ngayong this weekend?");
test_assert($parsedDates3 !== null, "This weekend date range extracted");

// 2. Accommodation Recommendations
echo "\n--- Testing Feature 2: Accommodation Recommendations ---\n";
// Case A: 5 guests family trip
$recFamily = sf_chatbot_tool_recommend_accommodation($conn, 5, "family vacation");
test_assert(!empty($recFamily['success']), "Recommendation for 5 guests returned success");
test_assert(!empty($recFamily['recommendations']), "Candidates found for 5 guests");
$topFamily = $recFamily['recommendations'][0];
test_assert($topFamily['slug'] === 'beach_villa' || $topFamily['slug'] === 'standard_room', "Top pick for 5 guests fits capacity (chosen: {$topFamily['slug']})");
test_assert(!empty($topFamily['badge']), "Recommendation includes badge (found: {$topFamily['badge']})");
test_assert(!empty($topFamily['reason']), "Recommendation includes tailored reasoning");

// Case B: Couple seeking romantic ocean view
$recCouple = sf_chatbot_tool_recommend_accommodation($conn, 2, "romantic ocean view for couple");
$topCouple = $recCouple['recommendations'][0];
test_assert($topCouple['slug'] === 'seaview_duplex' || $topCouple['slug'] === 'beachview_duplex', "Top pick for ocean view couple is Duplex (chosen: {$topCouple['slug']})");

// Case C: Budget conscious
$recBudget = sf_chatbot_tool_recommend_accommodation($conn, 2, "cheap budget friendly");
$topBudget = $recBudget['recommendations'][0];
test_assert($topBudget['slug'] === 'standard_room', "Top pick for budget is Standard Room (chosen: {$topBudget['slug']})");

// 3. Reservation Assistance & Stay Pricing
echo "\n--- Testing Feature 3: Reservation Assistance & Stay Pricing ---\n";
$quote = sf_chatbot_tool_estimate_price($conn, 'beach_villa', $checkIn, $checkOut, 4);
test_assert(!empty($quote['success']), "Price quotation returned success");
test_assert($quote['nights'] === 2, "Calculated 2 nights stay");
test_assert((float)$quote['total_amount'] > 0, "Calculated non-zero total amount");
test_assert((float)$quote['deposit_required_now'] === round((float)$quote['total_amount'] * 0.5, 2), "Deposit required is exactly 50%");
test_assert((float)$quote['remaining_balance_due_24h_before_arrival'] === round((float)$quote['total_amount'] - (float)$quote['deposit_required_now'], 2), "Remaining balance is 50%");
test_assert(!empty($quote['free_cancellation_deadline']), "Quotation includes free cancellation deadline");
test_assert(strpos($quote['booking_url'], 'book?room_type=beach_villa') !== false, "Booking URL is pre-filled with beach_villa");

// 4. Booking Status Checker
echo "\n--- Testing Feature 4: Booking Status Checker ---\n";
// Unverified session must be blocked
$unverified = sf_chatbot_tool_lookup_verified_booking($conn);
test_assert(empty($unverified['verified']), "Unverified session cannot view booking details (RA 10173 compliance)");
test_assert(!empty($unverified['requires_otp']), "Lookup instructs OTP verification is required");

// Check chatbot intent detection for Phase 2
test_assert(sf_chatbot_detect_intent("Can you check room availability for Dec 20 to 22?") === 'availability', "Detects availability intent");
test_assert(sf_chatbot_detect_intent("Which room do you recommend for 5 people?") === 'recommendation', "Detects recommendation intent");
test_assert(sf_chatbot_detect_intent("How much for 3 nights in Beach Villa?") === 'price_estimate', "Detects price estimate intent");
test_assert(sf_chatbot_detect_intent("Check status of REF-001") === 'booking_status', "Detects booking status intent");

// End-to-end chat message response for recommendation
$msgRec = sf_chatbot_send_guest_message($conn, "Which room do you recommend for a family of 5 guests?", "English");
test_assert(!empty($msgRec['success']), "Chatbot answered recommendation inquiry successfully");
test_assert(strpos($msgRec['reply'], 'Beach Villa') !== false || strpos($msgRec['reply'], 'Standard Room') !== false, "Reply recommends suitable high-capacity rooms");
test_assert(!empty($msgRec['suggested_actions']), "Reply provides direct booking actions");
test_assert(!empty($msgRec['room_cards']) && count($msgRec['room_cards']) >= 1, "Recommendation response includes visual room_cards carousel");

// End-to-end Reservation Assistance test ("Hi! Can I book a stay on February 1-11?" + requirements guide)
$msgBook = sf_chatbot_send_guest_message($conn, "Hi! Can I book a stay on February 1-11?", "English");
test_assert(!empty($msgBook['success']), "Reservation assistance query succeeded");
test_assert(!empty($msgBook['room_cards']) && count($msgBook['room_cards']) >= 3, "Reservation assistance returns visual room_cards with photos and BOOK NOW links");
$firstCard = $msgBook['room_cards'][0];
test_assert(!empty($firstCard['image_url']) && !empty($firstCard['price_label']) && strpos((string)$firstCard['booking_url'], 'book?room_type=') === 0, "Each visual room card contains image_url, price_label, and pre-filled booking_url");
test_assert(strpos((string)$msgBook['reply'], '50% Deposit') !== false || strpos((string)$msgBook['reply'], '50% deposit') !== false, "Reservation assistance explains 50% deposit and reservation requirements");

echo "\nAll Phase 2 · Smart Features automated tests passed successfully!\n";
