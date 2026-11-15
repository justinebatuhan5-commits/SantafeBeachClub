    <section class="rooms-hero">
        <div class="rooms-hero-inner">
            <div class="rooms-hero-copy">
                <p class="section-kicker">Coastal collection</p>
                <h1>Luxury stays curated for every shoreline mood</h1>
                <p>Compare sea-facing suites, family rooms, and tropical villas with live availability and transparent pricing.</p>
                <div class="rooms-hero-meta" aria-label="Highlights">
                    <span>Live availability</span>
                    <span>Flexible dates</span>
                    <span>Direct resort rates</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Search Bar -->
    <div class="be-bar-wrapper">
        <form method="GET" action="rooms" class="be-bar-form">
            <div class="be-bar-inner">
                <!-- Date range -->
                <div class="be-field be-field--dates">
                    <div class="be-date-segment">
                        <span class="be-date-label">Check-in</span>
                        <input type="date" id="be-checkin" name="checkin" value="<?php echo htmlspecialchars($filter_checkin); ?>" min="<?php echo date('Y-m-d'); ?>" class="be-date-input">
                    </div>
                    <div class="be-date-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </div>
                    <div class="be-date-segment">
                        <span class="be-date-label">Check-out</span>
                        <input type="date" id="be-checkout" name="checkout" value="<?php echo htmlspecialchars($filter_checkout); ?>" min="<?php echo date('Y-m-d', strtotime($filter_checkin . ' +1 day')); ?>" class="be-date-input">
                    </div>
                </div>
                <div class="be-divider"></div>
                <!-- Guests -->
                <div class="be-field be-field--guests">
                    <span class="be-field-label">Guests</span>
                    <select name="guests" class="be-select">
                        <option value="1" <?php if($filter_guests==1) echo 'selected'; ?>>1 adult &middot; 1 room</option>
                        <option value="2" <?php if($filter_guests==2) echo 'selected'; ?>>2 adults &middot; 1 room</option>
                        <option value="3" <?php if($filter_guests==3) echo 'selected'; ?>>3 adults &middot; 1 room</option>
                        <option value="4" <?php if($filter_guests==4) echo 'selected'; ?>>4 adults &middot; 1 room</option>
                        <option value="5" <?php if($filter_guests>=5) echo 'selected'; ?>>5+ adults &middot; 1 room</option>
                    </select>
                </div>
                <div class="be-divider"></div>
                <!-- Room type (as promo-code-style field) -->
                <div class="be-field be-field--type">
                    <span class="be-field-label">Room Type</span>
                    <select name="room_type" class="be-select be-select--type">
                        <option value="any"              <?php if($filter_type=='any')              echo 'selected'; ?>>All Types</option>
                        <option value="beachview_duplex" <?php if($filter_type=='beachview_duplex') echo 'selected'; ?>>Beachview Duplex</option>
                        <option value="seaview_duplex"   <?php if($filter_type=='seaview_duplex')   echo 'selected'; ?>>Seaview Duplex</option>
                        <option value="beach_villa"      <?php if($filter_type=='beach_villa')      echo 'selected'; ?>>Beach Villa</option>
                        <option value="standard_room"    <?php if($filter_type=='standard_room')    echo 'selected'; ?>>Standard Room</option>
                        <option value="standard_king"    <?php if($filter_type=='standard_king')    echo 'selected'; ?>>Standard Family Room</option>
                        <option value="cottage"          <?php if($filter_type=='cottage')          echo 'selected'; ?>>Beach Cottage</option>
                        <option value="villa"            <?php if($filter_type=='villa')            echo 'selected'; ?>>Luxury Villa</option>
                    </select>
                </div>
                <!-- Search button -->
                <button type="submit" class="be-search-btn" aria-label="Search">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <span>Check Rates</span>
                </button>
            </div>
        </form>
    </div>
