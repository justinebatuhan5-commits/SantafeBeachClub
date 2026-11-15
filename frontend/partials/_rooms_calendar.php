<?php
/**
 * Luxury Live Availability & Rates Calendar partial for frontend/rooms.php
 * Expects $filter_checkin, $filter_checkout, $filter_type, and $nights from rooms.php.
 */
$cal_filter_options = [
    'any'              => 'All Rooms',
    'standard_room'    => 'Standard Room',
    'standard_king'    => 'Standard Family',
    'cottage'          => 'Beach Cottage',
    'beachview_duplex' => 'Beachview Duplex',
    'seaview_duplex'   => 'Seaview Duplex',
    'beach_villa'      => 'Beach Villa',
    'villa'            => 'Luxury Villa',
];
?>
    <!-- Interactive Guest Live Availability & Rates Calendar -->
    <section class="sf-cal-shell" aria-label="Live Availability and Rates Calendar">
        <div class="sf-cal-card">
            <div class="sf-cal-header">
                <div class="sf-cal-heading-group">
                    <span class="sf-cal-kicker">Santa Fe Live Inventory &amp; Nightly Rates</span>
                    <div class="sf-cal-title-row">
                        <span class="sf-cal-icon-badge" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </span>
                        <h3 class="sf-cal-title">Live Availability &amp; Rates Calendar</h3>
                    </div>
                    <p class="sf-cal-subtitle">Compare real-time nightly rates and open inventory by date. Click check-in and check-out dates to plan your stay.</p>
                </div>

                <div class="sf-cal-header-actions">
                    <div class="sf-cal-active-pill" id="sf-cal-header-pill">
                        <span class="sf-cal-active-dot" aria-hidden="true"></span>
                        <span><?php echo htmlspecialchars(date('M j', strtotime($filter_checkin)) . ' - ' . date('M j', strtotime($filter_checkout))); ?> &middot; <?php echo (int)$nights . ' ' . ((int)$nights === 1 ? 'night' : 'nights'); ?></span>
                    </div>
                    <button type="button" class="rooms-cal-toggle" id="rooms-cal-toggle" aria-expanded="false" aria-controls="rooms-calendar-body">
                        <span>View availability calendar</span>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>
            </div>

            <div class="rooms-calendar-body" id="rooms-calendar-body" hidden>
                <div class="sf-cal-controls">
                    <div class="sf-cal-type-pills" id="sf-cal-type-pills" role="group" aria-label="Filter calendar by accommodation type">
                        <?php foreach ($cal_filter_options as $opt_key => $opt_label):
                            $is_opt_active = ($filter_type === $opt_key);
                        ?>
                        <button type="button"
                                class="sf-cal-type-pill<?php echo $is_opt_active ? ' is-active' : ''; ?>"
                                data-cal-room-type="<?php echo htmlspecialchars($opt_key); ?>"
                                aria-pressed="<?php echo $is_opt_active ? 'true' : 'false'; ?>">
                            <?php echo htmlspecialchars($opt_label); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="sf-cal-nav-wrap">
                        <button type="button" id="sf-cal-today-btn" class="sf-cal-today-btn">Today</button>
                        <div class="rooms-calendar-nav">
                            <button type="button" id="cal-prev-month" class="sf-cal-nav-btn" aria-label="Previous month">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </button>
                            <span id="cal-month-title" class="sf-cal-month-label">...</span>
                            <button type="button" id="cal-next-month" class="sf-cal-nav-btn" aria-label="Next month">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="sf-cal-status-strip">
                    <div id="sf-cal-pick-hint" class="sf-cal-pick-hint">
                        <span>Click any available date to select your check-in.</span>
                    </div>
                    <div class="sf-cal-legend">
                        <span class="sf-cal-legend-item"><span class="sf-cal-swatch sf-cal-swatch--avail"></span> Available</span>
                        <span class="sf-cal-legend-item"><span class="sf-cal-swatch sf-cal-swatch--low"></span> Limited (1-2)</span>
                        <span class="sf-cal-legend-item"><span class="sf-cal-swatch sf-cal-swatch--sold"></span> Sold Out</span>
                        <span class="sf-cal-legend-item"><span class="sf-cal-swatch sf-cal-swatch--selected"></span> Selected Stay</span>
                    </div>
                </div>

                <div id="sf-cal-weekdays">
                    <div class="sf-cal-weekday sf-cal-weekday--wknd">SUN</div>
                    <div class="sf-cal-weekday">MON</div>
                    <div class="sf-cal-weekday">TUE</div>
                    <div class="sf-cal-weekday">WED</div>
                    <div class="sf-cal-weekday">THU</div>
                    <div class="sf-cal-weekday">FRI</div>
                    <div class="sf-cal-weekday sf-cal-weekday--wknd">SAT</div>
                </div>
                <div id="sf-cal-days"></div>

                <div class="sf-cal-footer">
                    <div class="sf-cal-summary" id="sf-cal-summary">
                        <span class="sf-cal-summary-dates" id="sf-cal-summary-dates"></span>
                        <span class="sf-cal-summary-nights" id="sf-cal-summary-nights"></span>
                        <span class="sf-cal-summary-est" id="sf-cal-summary-est"></span>
                    </div>
                    <div class="sf-cal-footer-actions">
                        <button type="button" id="sf-cal-reset-btn" class="sf-cal-btn-reset">Reset</button>
                        <button type="button" id="sf-cal-apply-btn" class="sf-cal-btn-apply">
                            <span>Apply Dates &amp; Update Rooms</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
$sf_cal_js_cfg = [
    'initialCheckin'  => $filter_checkin,
    'initialCheckout' => $filter_checkout,
    'todayIso'        => date('Y-m-d'),
    'currentCalYear'  => (int)date('Y', strtotime($filter_checkin)),
    'currentCalMonth' => (int)date('n', strtotime($filter_checkin)),
    'currentRoomType' => $filter_type,
];
$sf_cal_js_ver = (int) @filemtime(__DIR__ . '/../assets/js/components/rooms-calendar.js');
?>
    <script>window.SF_ROOMS_CAL_CONFIG = <?php echo json_encode($sf_cal_js_cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script src="assets/js/components/rooms-calendar.js?v=<?php echo $sf_cal_js_ver; ?>"></script>
