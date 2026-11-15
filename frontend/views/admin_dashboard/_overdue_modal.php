<div id="overdueCheckoutModal" role="dialog" aria-modal="true" aria-labelledby="ocmTitle">
    <div class="ocm-card">
        <div class="ocm-header">
            <div class="ocm-logo-wrap">
                <img src="assets/logo.png" data-fallback-src="assets/logo.jpg" alt="Santa Fe Beach Club Logo">
            </div>
            <div class="ocm-header-text">
                <div class="ocm-brand-badge">
                    <span><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="vertical-align:-1px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span> Santa Fe Beach Club
                </div>
                <h3 id="ocmTitle">Overdue Check-out Alert</h3>
                <p>Action required &bull; Scheduled departure time has passed</p>
            </div>
            <button type="button" class="ocm-close-x" data-close-overdue-modal="1" title="Close" aria-label="Close">&times;</button>
        </div>
        <div class="ocm-body">
            <div class="ocm-banner">
                <span class="ocm-banner-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                <span class="ocm-banner-text">The following guest(s) have passed their scheduled check-out date and are still marked as <strong>Checked In</strong>. Please process their departure to free up accommodations.</span>
            </div>
            <div class="ocm-list-label">Overdue Guest(s)</div>
            <ul class="ocm-guest-list" id="ocmGuestList"></ul>
            <p class="ocm-timestamp">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                As of <?php echo htmlspecialchars($pageData['overdueNowDisplay'], ENT_QUOTES, 'UTF-8'); ?> &bull; <?php echo htmlspecialchars($pageData['overdueTzName'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <div class="ocm-footer">
            <button type="button" class="ocm-btn-dismiss" data-close-overdue-modal="1">
                Dismiss
            </button>
            <a class="ocm-btn-go" href="admin_checkout">
                <span>Go to Check-out Page</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</div>
