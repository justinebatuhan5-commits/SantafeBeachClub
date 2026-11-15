<!-- Availability Results Modal -->
<div class="cal-avail-modal-overlay" id="availModalOverlay">
    <div class="cal-avail-modal" id="availModal" role="dialog" aria-modal="true" aria-labelledby="availModalTitle">
        <div class="cal-avail-modal-header">
            <div>
                <h3 id="availModalTitle">Available Accommodations</h3>
                <div class="cal-avail-modal-sub" id="availModalSubtitle">&mdash;</div>
            </div>
            <button type="button" class="cal-detail-close" id="availModalClose" title="Close" aria-label="Close availability modal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="cal-avail-modal-body" id="availModalBody"></div>
    </div>
</div>

<!-- Booking Detail Slide-Over Panel -->
<div class="cal-detail-overlay" id="calDetailOverlay"></div>
<aside class="cal-detail-panel" id="calDetailPanel" aria-label="Booking details">
    <div class="cal-detail-header">
        <h3>Reservation Details</h3>
        <button type="button" class="cal-detail-close" id="calDetailClose" title="Close" aria-label="Close booking details">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="cal-detail-body" id="calDetailBody"></div>
    <div class="cal-detail-footer">
        <a class="cal-detail-link" id="calDetailLink" href="#">
            <span>Open Full Reservation</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
    </div>
</aside>

<!-- Live Drag Preview & Toast -->
<div class="cal-drag-pill" id="calDragPill"></div>
<div class="cal-toast" id="calToast" role="status" aria-live="polite"></div>

<script src="assets/js/occupancy_kpi.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/occupancy_kpi.js'); ?>"></script>
<script src="assets/js/components/admin-calendar-overlays.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/components/admin-calendar-overlays.js'); ?>"></script>
