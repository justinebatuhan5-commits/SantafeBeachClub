<?php
$formatRoomTypeLabel = function ($raw) {
    $clean = trim(str_replace(['_', '-'], ' ', (string)$raw));
    return $clean === '' ? 'Room' : ucwords($clean);
};
?>
<!-- Unified Command Bar -->
<section class="cal-command-deck" aria-label="Calendar controls and filters">
    <div class="cal-toolbar">
        <div class="cal-toolbar-left">
            <div class="cal-nav-group" role="group" aria-label="Date navigation">
                <button type="button" class="cal-nav-btn cal-nav-btn-icon" id="calPrev" title="Previous 7 days" aria-label="Previous 7 days">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button type="button" class="cal-nav-btn cal-nav-btn-today" id="calToday">Today</button>
                <button type="button" class="cal-nav-btn cal-nav-btn-icon" id="calNext" title="Next 7 days" aria-label="Next 7 days">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>

            <div class="cal-range-pill">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span class="cal-range-label" id="calRangeLabel">&mdash;</span>
            </div>

            <div class="cal-horizon-switch" id="calHorizonSwitch" role="group" aria-label="Visible days horizon">
                <button type="button" class="cal-horizon-btn" data-days="14">14D</button>
                <button type="button" class="cal-horizon-btn active" data-days="30">30D</button>
                <button type="button" class="cal-horizon-btn" data-days="45">45D</button>
            </div>
        </div>

        <div class="cal-toolbar-right">
            <div class="cal-search-wrap">
                <svg class="cal-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="cal-search-input" id="calRoomSearch" placeholder="Search room or guest..." autocomplete="off">
                <button type="button" class="cal-search-clear" id="calSearchClear" title="Clear search" aria-label="Clear search">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <select class="cal-filter-select" id="calFilter" aria-label="Filter by room type">
                <option value="all">All Room Types</option>
                <?php foreach ($room_types as $type): ?>
                <option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($formatRoomTypeLabel($type), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>

            <select class="cal-filter-select" id="calStatusFilter" aria-label="Filter bookings by status">
                <option value="all">All Statuses</option>
                <option value="active">Active Only</option>
                <option value="Pending">Pending</option>
                <option value="Confirmed">Confirmed</option>
                <option value="Checked In">Checked In</option>
                <option value="Checked Out">Checked Out</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>
    </div>

    <div class="cal-subbar">
        <div class="cal-availability-search">
            <span class="cal-avail-badge">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
                Quick Check
            </span>
            <div class="cal-date-group">
                <label for="availabilityCheckIn">In</label>
                <input type="date" id="availabilityCheckIn">
            </div>
            <div class="cal-date-group">
                <label for="availabilityCheckOut">Out</label>
                <input type="date" id="availabilityCheckOut">
            </div>
            <button type="button" class="cal-search-btn" id="availabilitySearchBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span>Check Availability</span>
            </button>
        </div>

        <div class="cal-legend" aria-label="Booking status legend">
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#D97706;"></span> Pending</div>
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#2563EB;"></span> Confirmed</div>
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#059669;"></span> Checked In</div>
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#64748B;"></span> Checked Out</div>
            <div class="cal-legend-item"><span class="cal-legend-dot" style="background:#DC2626;"></span> Cancelled</div>
            <div class="cal-legend-item"><span class="cal-legend-today"></span> Today</div>
        </div>
    </div>
</section>

<!-- Calendar Timeline Grid -->
<section class="cal-container" aria-label="Room occupancy timeline">
    <div class="cal-grid-meta">
        <span class="cal-grid-hint">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Drag active bars horizontally to reschedule. Click any bar for booking details, or click an open date cell to check availability.
        </span>
        <span class="cal-visible-count" id="calVisibleSummary">Loading schedule...</span>
    </div>
    <div class="cal-scroll-wrapper" id="calScrollWrapper">
        <div class="cal-grid" id="calGrid"></div>
    </div>
</section>
