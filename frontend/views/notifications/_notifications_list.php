<div class="admin-body notifications-page notifications-page--reception">
    <?php if (!empty($notifData['notifFlash'])): ?>
        <div class="notif-flash" role="status">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span><?php echo htmlspecialchars($notifData['notifFlash'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    <?php endif; ?>

    <?php
    $notifActionRoute = 'notifications';
    include dirname(__DIR__) . '/admin_notifications/_notifications_feed.php';
    ?>
</div>
