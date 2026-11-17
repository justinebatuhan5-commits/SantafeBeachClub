<?php
require_once __DIR__ . '/../../../backend/helpers/country_codes.php';
$walkinCountries = array_keys(sf_get_country_calling_codes());
?>
<!-- MODAL 1: INTERACTIVE ROOM QUICK-DETAIL -->
<div class="modal-overlay" id="roomDetailModal">
    <div class="room-detail-modal-box">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
            <div>
                <h2 id="rm-modal-title" style="font-family:var(--font-heading); font-size:20px; font-weight:800; color:var(--text-main); margin:0;">Room 101</h2>
                <p id="rm-modal-type" style="font-size:13px; color:var(--text-muted); margin:3px 0 0;">Standard Room</p>
            </div>
            <span id="rm-modal-badge" style="padding:4px 10px; border-radius:99px; font-size:11.5px; font-weight:700; text-transform:uppercase;">Ready</span>
        </div>

        <div id="rm-modal-body" style="background:var(--input-bg, #F8FAFC); border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:20px; font-size:13.5px;">
            <!-- Filled dynamically via JS -->
        </div>

        <div id="rm-modal-actions" style="display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" class="btn-secondary" data-action="close-room-detail" style="height:38px; padding:0 16px; font-size:13px;">Close</button>
            <div id="rm-modal-btn-slot"></div>
        </div>
    </div>
</div>

<!-- MODAL 2: QUICK RESERVATION MODAL -->
<div class="modal-overlay" id="newReservationModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2>New Walk-in Reservation</h2>
            <button type="button" class="modal-close" data-action="close-reservation-modal">&times;</button>
        </div>
        <form id="newReservationForm">
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label for="newGuestName">Full Guest Name <span class="req">*</span></label>
                    <input type="text" id="newGuestName" required placeholder="e.g. Maria Santos">
                </div>
                <div class="admin-form-group">
                    <label for="newGuestCountry">Country / Region</label>
                    <select id="newGuestCountry">
                        <?php foreach ($walkinCountries as $wc): ?>
                            <option value="<?php echo htmlspecialchars($wc, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $wc === 'Philippines' ? ' selected' : ''; ?>><?php echo htmlspecialchars($wc, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                        <?php if (!in_array('Other', $walkinCountries, true)): ?>
                            <option value="Other">Other</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label for="newGuestEmail">Email Address</label>
                    <input type="email" id="newGuestEmail" placeholder="guest@example.com">
                </div>
                <div class="admin-form-group">
                    <label for="newGuestPhone">Contact Number <span style="font-weight:500; opacity:0.65;">(Optional)</span></label>
                    <input type="tel" id="newGuestPhone" placeholder="0917 123 4567 or +CountryCode (Optional)">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label for="newGuestType">Guest Tier</label>
                    <select id="newGuestType">
                        <option value="First Visit">First Visit</option>
                        <option value="Returning Guest">Returning Guest</option>
                        <option value="VIP Member">VIP Member</option>
                        <option value="Corporate">Corporate</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label for="newRoomType">Accommodation Type</label>
                    <select id="newRoomType">
                        <?php foreach ($dashboardData['roomTypes'] as $rt): ?>
                            <option value="<?php echo (int)$rt['id']; ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$rt['name']))); ?> (₱<?php echo number_format((float)($rt['base_price'] ?? 3000), 0); ?>/night)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Stay Duration Quick Pick -->
            <div class="admin-form-group">
                <label>Stay Duration Quick-Set</label>
                <div style="display:flex; gap:8px; margin-bottom:6px;">
                    <button type="button" class="stay-pill-btn" data-action="set-stay-duration" data-nights="1">+ 1 Night</button>
                    <button type="button" class="stay-pill-btn" data-action="set-stay-duration" data-nights="2">+ 2 Nights</button>
                    <button type="button" class="stay-pill-btn" data-action="set-stay-duration" data-nights="3">+ 3 Nights</button>
                    <button type="button" class="stay-pill-btn" data-action="set-stay-duration" data-nights="7">+ 1 Week</button>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label for="newCheckin">Check-in Date</label>
                    <input type="date" id="newCheckin" required value="<?php echo htmlspecialchars($dashboardData['defaultCheckin']); ?>">
                </div>
                <div class="admin-form-group">
                    <label for="newCheckout">Check-out Date</label>
                    <input type="date" id="newCheckout" required value="<?php echo htmlspecialchars($dashboardData['defaultCheckout']); ?>">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label for="newGuestsCount">Guests</label>
                    <input type="number" id="newGuestsCount" required min="1" max="12" value="2">
                </div>
                <div class="admin-form-group">
                    <label for="newETA">Expected Arrival (ETA)</label>
                    <input type="time" id="newETA" required value="14:00">
                </div>
            </div>

            <div class="mobile-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px;">
                <button type="button" class="btn-secondary" data-action="submit-reservation" data-status="Pending">
                    Save as Pending
                </button>
                <button type="button" class="btn-primary" data-action="submit-reservation" data-status="Checked In" style="justify-content:center;">
                    Direct Check-in
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Overdue Check-out Warning Modal -->
<div id="overdueCheckoutModal" role="dialog" aria-modal="true" aria-labelledby="ocmTitle">
    <div class="ocm-card">
        <div class="ocm-header">
            <div class="ocm-logo-wrap">
                <img src="assets/logo.png" alt="Santa Fe Beach Club Logo" onerror="this.src='assets/logo.jpg'">
            </div>
            <div class="ocm-header-text">
                <div class="ocm-brand-badge">
                    <span><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="vertical-align:-1px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span> Santa Fe Beach Club
                </div>
                <h3 id="ocmTitle">Overdue Check-out Alert</h3>
                <p>Action required &bull; Scheduled departure time has passed</p>
            </div>
            <button type="button" class="ocm-close-x" data-action="dismiss-overdue-modal" title="Close">&times;</button>
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
                As of <?php echo htmlspecialchars($dashboardData['overdueNowDisplay']); ?> &bull; <?php echo htmlspecialchars($dashboardData['overdueTzName']); ?>
            </p>
        </div>
        <div class="ocm-footer">
            <button type="button" class="ocm-btn-dismiss" data-action="dismiss-overdue-modal">
                Dismiss
            </button>
            <a class="ocm-btn-go" href="<?php echo htmlspecialchars($dashboardData['checkoutTargetUrl']); ?>">
                <span>Go to Check-out Page</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</div>
