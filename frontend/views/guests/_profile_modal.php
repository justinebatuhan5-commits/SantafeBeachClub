<div class="modal-overlay" id="guestModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-avatar-lg" id="modalAvatar"></div>
            <div style="flex:1; min-width:0;">
                <div class="modal-guest-name" id="modalName"></div>
                <div class="modal-guest-email" id="modalEmail"></div>
                <div id="modalTypeBadge"></div>
            </div>
            <div class="modal-header-actions">
                <a id="modalBookAgainBtn" class="btn-book-again" href="book?step=2">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                    Book Again
                </a>
                <button type="button" class="modal-close" data-action="close-guest-modal" title="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div class="modal-body">
            <div class="modal-kpi-row">
                <div class="modal-kpi">
                    <div class="modal-kpi-val" id="modalTotalBookings">—</div>
                    <div class="modal-kpi-lbl">Total Bookings</div>
                </div>
                <div class="modal-kpi">
                    <div class="modal-kpi-val" id="modalFirstVisit">—</div>
                    <div class="modal-kpi-lbl">First Visit</div>
                </div>
                <div class="modal-kpi">
                    <div class="modal-kpi-val" id="modalTotalSpend">—</div>
                    <div class="modal-kpi-lbl">Est. Total Spend</div>
                </div>
            </div>

            <div class="modal-section-title">Contact Information</div>
            <div class="contact-grid">
                <div>
                    <div class="contact-label">Contact Number</div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="contact-value" id="modalPhone" style="font-family:var(--font-mono, monospace);">—</div>
                        <button type="button" id="btnTogglePhone" data-action="toggle-phone" style="display:none; background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:12px; padding:3px 8px; border-radius:5px; border:1px solid var(--border); align-items:center; gap:5px;" title="Toggle Phone Mask">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span>Show</span>
                        </button>
                    </div>
                </div>
                <div>
                    <div class="contact-label">Country / Region</div>
                    <div class="contact-value" id="modalCountry">—</div>
                </div>
            </div>

            <div id="modalSpecialRequestsSection" style="display:none; margin-bottom:22px;">
                <div class="modal-section-title">Special Requests / Notes</div>
                <div style="padding:14px 16px; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.25); border-radius:10px; font-size:13.5px; color:var(--text-main); line-height:1.6;" id="modalSpecialRequests"></div>
            </div>

            <div class="modal-section-title">Booking History</div>
            <div id="modalBookingHistory" style="overflow-x:auto; border-radius:10px; border:1px solid var(--border);"></div>
        </div>
    </div>
</div>
