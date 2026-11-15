    <!-- Room Details Modal -->
    <div class="room-modal" id="room-modal" aria-hidden="true">
        <div class="room-modal-backdrop" onclick="closeRoomModal()"></div>
        <div class="room-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="room-modal-title">
            <div class="room-modal-header">
                <h3>Room details</h3>
                <button type="button" class="room-modal-close" aria-label="Close room details" onclick="closeRoomModal()">&times;</button>
            </div>
            <div class="room-modal-body">
                <div class="room-modal-gallery">
                    <div class="room-modal-main-wrap">
                        <img src="" alt="" class="room-modal-main-image" id="room-modal-main-image">
                        <span class="room-modal-counter" id="room-modal-counter">1/1</span>
                    </div>
                    <div class="room-modal-thumbs" id="room-modal-thumbs"></div>
                </div>
                <div class="room-modal-content">
                    <h4 class="room-modal-title" id="room-modal-title"></h4>
                    <div class="room-modal-bed" id="room-modal-bed"></div>
                    <p class="room-modal-description" id="room-modal-description"></p>
                    <div class="room-modal-amenities-wrap" id="room-modal-amenities-wrap" style="display:none;">
                        <h5>Amenities</h5>
                        <div class="room-modal-amenities" id="room-modal-amenities"></div>
                    </div>
                </div>
            </div>
            <div class="room-modal-footer">
                <button type="button" class="room-modal-close-btn" onclick="closeRoomModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Availability Calendar Modal -->
    <div class="avail-modal" id="avail-modal" aria-hidden="true" role="dialog">
        <div class="avail-modal-backdrop" onclick="closeAvailabilityCalendar()"></div>
        <div class="avail-modal-dialog">
            <button type="button" class="avail-modal-close" onclick="closeAvailabilityCalendar()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <div class="avail-modal-header">
                <div class="avail-modal-header-info">
                    <span class="avail-modal-kicker">Availability Calendar</span>
                    <h3 class="avail-modal-title" id="avail-modal-title">Standard Room</h3>
                    <div class="avail-modal-price" id="avail-modal-price">From &#8369; 2,900.00 / night</div>
                </div>
                <div class="avail-modal-month-nav">
                    <button type="button" class="btn-cal-month" onclick="changeCalMonth(-1)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Prev</span>
                    </button>
                    <span class="cal-current-month" id="cal-current-month">September 2026</span>
                    <button type="button" class="btn-cal-month" onclick="changeCalMonth(1)">
                        <span>Next</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>

            <div class="avail-modal-grid-wrap">
                <div class="avail-modal-weekdays">
                    <span>SUN</span><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
                </div>
                <div class="avail-modal-days" id="avail-modal-days">
                    <!-- Populated via JavaScript -->
                </div>
            </div>

            <div class="avail-modal-footer">
                <p class="avail-modal-hint">Select any green date to switch your stay and continue booking this room type.</p>
                <div class="avail-legend">
                    <div class="avail-legend-item"><span class="legend-dot legend-dot--avail"></span> Available (Click to select)</div>
                    <div class="avail-legend-item"><span class="legend-dot legend-dot--sold"></span> Sold Out</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Direct Resort Assurances -->
    <section class="rooms-assurances" aria-label="Direct Booking Advantages">
        <div class="rooms-shell">
            <div class="rooms-assurances-head">
                <span class="rooms-assurances-kicker">Direct Resort Booking</span>
                <h2>Why reserve directly with Santa Fe Beach Club</h2>
            </div>
            <div class="rooms-assurances-grid">
                <article class="rooms-assurance-card">
                    <span class="rooms-assurance-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
                    </span>
                    <h3>Live Resort Inventory</h3>
                    <p>Every room type and unit status syncs directly with our front desk schedule for your selected check-in and check-out dates.</p>
                </article>
                <article class="rooms-assurance-card">
                    <span class="rooms-assurance-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <h3>50% Deposit Holds Your Stay</h3>
                    <p>Lock in your beachfront accommodation with a clear 50% booking deposit and settle the remaining balance at the resort.</p>
                </article>
                <article class="rooms-assurance-card">
                    <span class="rooms-assurance-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <h3>Check-in 13:30 &middot; Check-out 11:00</h3>
                    <p>Smooth coastal arrivals in Bantayan Island with complimentary children allowances and transparent extra-person rates.</p>
                </article>
                <article class="rooms-assurance-card">
                    <span class="rooms-assurance-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <h3>Direct Resort Support</h3>
                    <p>Need multiple rooms or transfer guidance from Hagnaya to Santa Fe Port? Our team responds straight through the resort desk.</p>
                </article>
            </div>
        </div>
    </section>
