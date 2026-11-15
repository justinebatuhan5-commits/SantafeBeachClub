<?php
declare(strict_types=1);

/**
 * Regression checks verifying Live Support Chats is a dedicated standalone page,
 * separate from the Guest Feedback tabs (Reviews / Inquiries / AI Concierge).
 */

require_once __DIR__ . '/../backend/helpers/page_header_helper.php';

$root = dirname(__DIR__);

$assert = static function (bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$label}\n");
};

$liveSupportPage = file_get_contents($root . '/frontend/admin_live_support.php');
$tabsPartial = file_get_contents($root . '/frontend/partials/_guest_communications_tabs.php');
$inquiriesPage = file_get_contents($root . '/frontend/admin_inquiries.php');
$adminNav = file_get_contents($root . '/frontend/partials/sidebar/_admin_nav.php');
$receptionNav = file_get_contents($root . '/frontend/partials/sidebar/_reception_nav.php');

$assert(is_string($liveSupportPage) && $liveSupportPage !== '', 'admin_live_support.php is readable');
$assert(is_string($tabsPartial) && $tabsPartial !== '', '_guest_communications_tabs.php is readable');
$assert(is_string($inquiriesPage) && $inquiriesPage !== '', 'admin_inquiries.php is readable');
$assert(is_string($adminNav) && $adminNav !== '', '_admin_nav.php is readable');
$assert(is_string($receptionNav) && $receptionNav !== '', '_reception_nav.php is readable');

$assert(
    !str_contains($liveSupportPage, '_guest_communications_tabs.php'),
    'admin_live_support.php does not embed the shared Guest Feedback tabs strip'
);
$assert(
    str_contains($liveSupportPage, "'channel' => 'live_chat'"),
    'admin_live_support.php renders only the live_chat channel queue'
);

$assert(
    !str_contains($tabsPartial, 'admin_live_support') && !str_contains($tabsPartial, 'Live Chats'),
    '_guest_communications_tabs.php omits Live Chats tab'
);
$assert(
    str_contains($tabsPartial, 'admin_reviews')
    && str_contains($tabsPartial, 'admin_inquiries')
    && str_contains($tabsPartial, 'admin_chatbot'),
    '_guest_communications_tabs.php retains Reviews, Inquiries, and AI Concierge tabs'
);

$assert(
    !str_contains($inquiriesPage, 'appear in Live Chats'),
    'admin_inquiries.php subtitle references standalone Live Support Chats page cleanly'
);

$assert(
    str_contains($adminNav, 'href="admin_live_support"') && str_contains($receptionNav, 'href="admin_live_support"'),
    'Both Admin and Reception sidebars keep dedicated Live Support Chats links'
);

$context = sf_header_context('live_support', 'Live Support Chats', true);
$assert(
    ($context['section'] ?? '') === 'Live Support',
    'Header context assigns Live Support Chats its own Live Support section breadcrumb'
);

$feedbackContext = sf_header_context('reviews', 'Reviews & Inquiries', true);
$assert(
    ($feedbackContext['section'] ?? '') === 'Guest Feedback',
    'Reviews retains the Guest Feedback section breadcrumb'
);

fwrite(STDOUT, "ALL LIVE SUPPORT STANDALONE TESTS PASSED\n");
