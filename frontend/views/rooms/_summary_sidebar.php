        <!-- Booking Summary Sidebar -->
        <aside class="booking-sidebar" id="booking-sidebar">
            <div class="bs-header">
                <span class="bs-kicker">Reservation Summary</span>
                <h3 class="bs-title">Booking Summary</h3>
            </div>

            <div class="bs-body">
                <div class="bs-dates">
                    <div class="bs-date-col">
                        <div class="bs-date-main"><?php echo $checkin_fmt; ?></div>
                        <div class="bs-date-time">Check-in &middot; 13:30</div>
                    </div>
                    <div class="bs-date-arrow" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </div>
                    <div class="bs-date-col bs-date-col--right">
                        <div class="bs-date-main"><?php echo $checkout_fmt; ?></div>
                        <div class="bs-date-time">Check-out &middot; 11:00</div>
                    </div>
                </div>
                <div class="bs-meta-strip">
                    <span class="bs-nights" id="bs-nights" data-base-nights="<?php echo (int)$nights; ?>"><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                </div>

                <div class="bs-room-info">
                    <div class="bs-room-info-header">
                        <span>Selected rooms</span>
                        <a href="#" class="bs-clear-link" id="bs-clear-link" onclick="clearSelection(event)">Clear selection</a>
                    </div>
                    <div class="bs-room-placeholder" id="bs-placeholder">Choose a room to preview your stay total and deposit.</div>
                    <div class="bs-selected-rooms" id="bs-selected-rooms" style="display:none;"></div>
                </div>

                <div class="bs-total-block">
                    <div class="bs-total-row">
                        <span class="bs-total-label">Total Stay Cost</span>
                        <span class="bs-total-amount" id="bs-total">&#8369; 0.00</span>
                    </div>
                    <div class="bs-deposit-pill" id="bs-deposit-pill" style="display:none;">
                        <span>50% Deposit Due</span>
                        <span id="bs-deposit-amount">&#8369; 0.00</span>
                    </div>
                    <div class="bs-tax-note">Price includes all taxes and fees</div>
                </div>

                <button class="btn-book-now" id="btn-book-now" disabled onclick="proceedToBook()">BOOK NOW</button>
            </div>
        </aside>

    </div><!-- /.rooms-layout -->

    <!-- Mobile Sticky Booking Bar -->
    <div class="mobile-book-bar" id="mobile-book-bar">
        <div class="mobile-book-bar-info">
            <div class="mobile-book-bar-count" id="mobile-bar-count">1 room selected</div>
            <div class="mobile-book-bar-total" id="mobile-bar-total">&#8369; 0.00</div>
        </div>
        <button class="mobile-book-bar-btn" onclick="proceedToBook()">Book Now &rarr;</button>
    </div>
