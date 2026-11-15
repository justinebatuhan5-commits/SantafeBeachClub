<?php
declare(strict_types=1);

/**
 * Regression checks for Admin & Reception sidebar priority ordering.
 *
 * Verifies that high-frequency pages (Payments, Notifications, Live Support
 * Chats, Reports & Analytics) appear immediately after Launch QR Scanner /
 * Front Desk, while content/setup pages appear lower without dropping routes.
 */

$root = dirname(__DIR__);

$assert = static function (bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$label}\n");
};

$workspaceJs = file_get_contents($root . '/frontend/assets/js/sidebar-workspace.js');
$adminNav = file_get_contents($root . '/frontend/partials/sidebar/_admin_nav.php');
$receptionNav = file_get_contents($root . '/frontend/partials/sidebar/_reception_nav.php');

$assert(is_string($workspaceJs) && $workspaceJs !== '', 'sidebar-workspace.js is readable');
$assert(is_string($adminNav) && $adminNav !== '', '_admin_nav.php is readable');
$assert(is_string($receptionNav) && $receptionNav !== '', '_reception_nav.php is readable');

$assert(
    preg_match(
        "/name:\s*'Front Desk'.*?name:\s*'Daily Priority',\s*routes:\s*\['payments',\s*'notifications',\s*'live_support',\s*'reports'\]/s",
        $workspaceJs
    ) === 1,
    'sidebar-workspace.js places Payments, Notifications, Live Support Chats, Reports & Analytics right after Front Desk'
);

$priorityPos = strpos($workspaceJs, "name: 'Daily Priority'");
$propertyPos = strpos($workspaceJs, "name: 'Property & Guests'");
$contentPos = strpos($workspaceJs, "name: 'Content & Offers'");
$systemPos = strpos($workspaceJs, "name: 'System'");

$assert(
    $priorityPos !== false && $propertyPos !== false && $contentPos !== false && $systemPos !== false
    && $priorityPos < $propertyPos && $propertyPos < $contentPos && $contentPos < $systemPos,
    'sidebar-workspace.js orders sections Front Desk -> Daily Priority -> Finance -> Property & Guests -> Content & Offers -> System'
);

$assert(
    preg_match("/name:\s*'Content & Offers',\s*routes:\s*\['promotions',\s*'gallery',\s*'room_types'\]/", $workspaceJs) === 1,
    'sidebar-workspace.js moves promotions, gallery, and room_types down to Content & Offers'
);

// Verify all href links present in _admin_nav.php and _reception_nav.php match at least one route token in workspaceJs.
preg_match_all('/href="([^"]+)"/', $adminNav . "\n" . $receptionNav, $hrefMatches);
$uniqueHrefs = array_values(array_unique($hrefMatches[1] ?? []));
$assert(count($uniqueHrefs) === 27, 'All 27 unique Admin/Reception sidebar href targets remain present');

preg_match("/var groups = \[(.*?)\];/s", $workspaceJs, $groupMatch);
$groupsBlock = $groupMatch[1] ?? '';
$routeTokens = [];
if ($groupsBlock !== '') {
    preg_match_all("/routes:\s*\[([^\]]+)\]/", $groupsBlock, $routesLists);
    foreach ($routesLists[1] ?? [] as $list) {
        preg_match_all("/'([^']+)'/", $list, $tokens);
        foreach ($tokens[1] ?? [] as $token) {
            $routeTokens[] = $token;
        }
    }
}

foreach ($uniqueHrefs as $href) {
    $matched = false;
    foreach ($routeTokens as $token) {
        if (str_contains($href, $token)) {
            $matched = true;
            break;
        }
    }
    $assert($matched, "Sidebar route token covers href={$href}");
}

// Verify SSR markup in _admin_nav.php also puts priority items right below Launch QR Scanner.
$qrPos = strpos($adminNav, 'Launch QR Scanner');
$payPos = strpos($adminNav, 'href="payments"');
$notifPos = strpos($adminNav, 'href="admin_notifications"');
$livePos = strpos($adminNav, 'href="admin_live_support"');
$repPos = strpos($adminNav, 'href="admin_reports"');
$promoPos = strpos($adminNav, 'href="admin_promotions"');
$galPos = strpos($adminNav, 'href="admin_gallery"');
$roomPos = strpos($adminNav, 'href="admin_room_types"');

$assert(
    $qrPos !== false && $payPos !== false && $notifPos !== false && $livePos !== false && $repPos !== false
    && $promoPos !== false && $galPos !== false && $roomPos !== false
    && $qrPos < $payPos && $payPos < $notifPos && $notifPos < $livePos && $livePos < $repPos
    && $repPos < $promoPos && $promoPos < $galPos && $galPos < $roomPos,
    'SSR _admin_nav.php places Payments, Notifications, Live Support Chats, and Reports right below Launch QR Scanner and above Content & Offers'
);

fwrite(STDOUT, "ALL SIDEBAR PRIORITY ORDER TESTS PASSED\n");
