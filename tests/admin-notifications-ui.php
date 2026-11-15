<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function assertNotifUi(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$root = dirname(__DIR__);

$sampleNotifications = [
    [
        'id'         => 101,
        'title'      => 'Booking Needs Cancellation Review <script>alert(1)</script>',
        'message'    => 'REF-120001 remains unpaid after the grace period.',
        'type'       => 'alert',
        'booking_id' => 120001,
        'is_read'    => false,
        'time'       => 'Nov 15, 2026 · 5:28 PM',
        'badgeClass' => 'badge-alert',
        'detailJson' => json_encode([
            'id' => 101,
            'title' => 'Booking Needs Cancellation Review',
            'message' => 'REF-120001 remains unpaid after the grace period.',
            'type' => 'alert',
            'booking_id' => 120001,
            'time' => 'Nov 15, 2026 · 5:28 PM',
        ]),
    ],
    [
        'id'         => 102,
        'title'      => 'Pre-Arrival Reminder Sent',
        'message'    => 'Reminder sent to Justine Batuhan.',
        'type'       => 'info',
        'booking_id' => 0,
        'is_read'    => true,
        'time'       => 'Nov 15, 2026 · 5:28 PM',
        'badgeClass' => 'badge-info',
        'detailJson' => json_encode([
            'id' => 102,
            'title' => 'Pre-Arrival Reminder Sent',
            'message' => 'Reminder sent to Justine Batuhan.',
            'type' => 'info',
            'booking_id' => 0,
            'time' => 'Nov 15, 2026 · 5:28 PM',
        ]),
    ],
];

$renderAdminContent = static function (array $notifData) use ($root): string {
    ob_start();
    include $root . '/frontend/views/admin_notifications/_notifications_content.php';
    return (string) ob_get_clean();
};

$renderReceptionContent = static function (array $notifData) use ($root): string {
    ob_start();
    include $root . '/frontend/views/notifications/_notifications_list.php';
    return (string) ob_get_clean();
};

$notifData = [
    'notifications'     => $sampleNotifications,
    'totalCount'        => 2,
    'unreadCount'       => 1,
    'urgentCount'       => 1,
    'urgentUnreadCount' => 1,
    'pendingCount'      => 3,
    'maintCount'        => 1,
    'notifFlash'        => 'Broadcast alert posted to the team feed.',
    'csrfField'         => '<input type="hidden" name="csrf_token" value="test-csrf">',
];

$adminHtml = $renderAdminContent($notifData);
assertNotifUi(!str_contains($adminHtml, '<script>alert(1)</script>'), 'Notification titles escape raw HTML/script tags');
assertNotifUi(str_contains($adminHtml, '<polyline points="10 17 15 12 10 7"/>'), 'Pending Check-ins KPI uses full check-in door+arrow SVG icon');
assertNotifUi(str_contains($adminHtml, 'data-notif-filter="unread"') && str_contains($adminHtml, 'data-notif-filter="urgent"'), 'Notification feed includes filter pills for unread and urgent items');
assertNotifUi(str_contains($adminHtml, 'id="notifSearchInput"'), 'Notification feed includes instant search bar');
assertNotifUi(str_contains($adminHtml, 'notif-unread-pill') && str_contains($adminHtml, 'REF-120001'), 'Unread alert renders inline unread indicator and booking ref chip');
assertNotifUi(str_contains($adminHtml, 'class="broadcast-type-grid"') && str_contains($adminHtml, 'id="broadcastPreview"'), 'Broadcast composer renders severity pills and live preview');

$receptionHtml = $renderReceptionContent($notifData);
assertNotifUi(str_contains($receptionHtml, 'action="notifications"') && !str_contains($receptionHtml, 'Send Alert Broadcast'), 'Reception notifications view reuses feed partial without admin broadcast form');

$css = file_get_contents($root . '/frontend/assets/css/pages/admin-notifications.css');
assertNotifUi(is_string($css) && !str_contains($css, '.notif-item.unread::after'), 'Removed overlapping top-right corner unread pseudo-element dot');
assertNotifUi(str_contains($css, '[data-theme="dark"] .badge-alert'), 'Dark theme styles cover redesigned notification badges');

echo "ALL ADMIN NOTIFICATIONS UI TESTS PASSED\n";
