<div id="revenueDrilldownModal" role="dialog" aria-modal="true" aria-labelledby="rdmTitle">
    <div class="rdm-card">
        <div class="rdm-header">
            <div class="rdm-icon-wrap" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </div>
            <div class="rdm-header-text">
                <div class="rdm-eyebrow">Revenue Trajectory Drill-Down</div>
                <h3 id="rdmTitle">Verified Receipts</h3>
                <p id="rdmDateSubtitle">Selected day summary</p>
            </div>
            <button type="button" class="ocm-close-x" data-close-revenue-modal="1" title="Close" aria-label="Close">&times;</button>
        </div>

        <div class="rdm-summary-strip">
            <div class="rdm-summary-item">
                <span class="rdm-summary-label">Verified Revenue</span>
                <strong class="rdm-summary-value" id="rdmTotalAmount">₱0.00</strong>
            </div>
            <div class="rdm-summary-item">
                <span class="rdm-summary-label">Verified Receipts</span>
                <strong class="rdm-summary-value" id="rdmTxnCount">0</strong>
            </div>
        </div>

        <div class="rdm-body" id="rdmBodyContent">
            <div class="rdm-state">Loading daily receipts...</div>
        </div>

        <div class="rdm-footer">
            <button type="button" class="ocm-btn-dismiss" data-close-revenue-modal="1">Close</button>
            <div class="rdm-footer-actions">
                <a class="rdm-btn-secondary" href="admin_reports">Full Report &rarr;</a>
                <a class="rdm-btn-primary" href="payments">Open Payments &rarr;</a>
            </div>
        </div>
    </div>
</div>
