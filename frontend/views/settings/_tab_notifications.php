<?php
$active_tab = $pageData['activeTab'];
?>
<div class="tab-panel <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" id="tab-notifications">
    <div class="settings-card">
        <h3>Notification Sound</h3>
        <p class="card-desc">Control the audible alert that plays when a new notification arrives while you are on any admin page.</p>

        <div class="notif-pref-row">
            <div class="notif-pref-info">
                <strong>Notification Sound</strong>
                <span>Plays a short chime when new alerts arrive. Applies to this browser only.</span>
                <div class="notif-pref-status" id="sound-status">Loading…</div>
            </div>
            <label class="toggle-switch" aria-label="Toggle notification sound">
                <input type="checkbox" id="toggle-sound">
                <span class="toggle-track"></span>
            </label>
        </div>

        <div style="margin-top:20px;">
            <button type="button" class="notif-test-btn" id="test-sound-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                Test Sound
            </button>
        </div>
    </div>

    <div class="settings-card">
        <h3>Desktop Popup Notifications</h3>
        <p class="card-desc">Show a native OS notification popup when a new alert arrives, even if you are on a different browser tab.</p>

        <div class="notif-pref-row">
            <div class="notif-pref-info">
                <strong>Desktop Popups</strong>
                <span>Requires browser permission. Enabling this will prompt the browser to request permission if not already granted.</span>
                <div class="notif-pref-status" id="desktop-status">Loading…</div>
            </div>
            <label class="toggle-switch" aria-label="Toggle desktop notifications">
                <input type="checkbox" id="toggle-desktop">
                <span class="toggle-track"></span>
            </label>
        </div>

        <div class="notif-permission-note" id="permission-note">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <strong>Permission blocked by browser.</strong> To enable desktop popups, you must allow notifications for this site in your browser settings
                (<em>Site Settings &rarr; Notifications &rarr; Allow</em>), then reload this page.
            </div>
        </div>

        <div style="margin-top:20px;">
            <button type="button" class="notif-test-btn" id="test-desktop-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                Send Test Popup
            </button>
        </div>
    </div>

    <div class="settings-card">
        <h3>In-Page Toast Banners</h3>
        <p class="card-desc">The dark slide-up banner at the bottom-right of the screen. This is always shown and cannot be disabled — it is the primary notification indicator when sound is muted.</p>

        <div class="notif-pref-row">
            <div class="notif-pref-info">
                <strong>Toast Banners</strong>
                <span>Always enabled — provides a visual alert when a new notification arrives.</span>
                <div class="notif-pref-status on">Always on</div>
            </div>
            <label class="toggle-switch" aria-label="Toast banners always on">
                <input type="checkbox" checked disabled>
                <span class="toggle-track" style="opacity:.5;cursor:not-allowed;"></span>
            </label>
        </div>
    </div>
</div>
