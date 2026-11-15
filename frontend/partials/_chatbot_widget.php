<?php
if (defined('SFBC_CHATBOT_WIDGET_LOADED')) {
    return;
}
define('SFBC_CHATBOT_WIDGET_LOADED', true);

require_once __DIR__ . '/../../backend/helpers/csrf_helper.php';
$sfbc_chat_csrf = get_csrf_token();

$sfbc_script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$sfbc_asset_prefix = (strpos($sfbc_script_dir, '/frontend') !== false || is_file('assets/logo.jpg')) ? '' : 'frontend/';
$sfbc_logo_url = $sfbc_asset_prefix . 'assets/logo.jpg';
$sfbc_widget_css = $sfbc_asset_prefix . 'assets/css/components/chatbot-widget.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/components/chatbot-widget.css');
$sfbc_widget_js  = $sfbc_asset_prefix . 'assets/js/components/chatbot-widget.js?v=' . (int) @filemtime(__DIR__ . '/../assets/js/components/chatbot-widget.js');
$sfbc_widget_cfg = [
    'csrfToken' => $sfbc_chat_csrf,
    'logoUrl'   => $sfbc_logo_url,
];
?>
<!-- Santa Fe Beach Club - Intelligent Virtual Guest Assistant Widget -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($sfbc_widget_css, ENT_QUOTES, 'UTF-8'); ?>">

<div id="sfbcChatWidget">
    <!-- Floating Toggle Button -->
    <button id="sfbcChatToggle" type="button" onclick="if(window.sfbcToggleChat){window.sfbcToggleChat();}else{var b=document.getElementById('sfbcChatBox');if(b){b.style.setProperty('display','flex','important');b.classList.add('sfbc-open');}}" aria-label="Toggle AI Guest Assistant" aria-expanded="false" aria-controls="sfbcChatBox" title="Ask Santa Fe AI Concierge">
        <span id="sfbcIconOpen" style="display:block; width:100%; height:100%; position:relative; pointer-events:none;">
            <span class="sfbc-toggle-logo-wrap">
                <img src="<?php echo htmlspecialchars($sfbc_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Santa Fe Beach Club Concierge" class="sfbc-toggle-logo-img" onerror="if(!this.dataset.fb){this.dataset.fb='1';this.src='frontend/assets/logo.jpg';}">
            </span>
            <span class="sfbc-toggle-status-dot" aria-hidden="true"></span>
        </span>
        <span id="sfbcToggleUnreadBadge" class="sfbc-toggle-unread-badge" aria-hidden="true"></span>
        <svg id="sfbcIconClose" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none; pointer-events:none;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>

    <!-- Chat Window Container -->
    <div id="sfbcChatBox">
        <!-- Header -->
        <div id="sfbcChatHeader">
            <div class="sfbc-header-brand">
                <div class="sfbc-header-logo-ring">
                    <img src="<?php echo htmlspecialchars($sfbc_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Santa Fe Beach Club" class="sfbc-header-logo-img" onerror="if(!this.dataset.fb){this.dataset.fb='1';this.src='frontend/assets/logo.jpg';}">
                    <span class="sfbc-header-online-dot" aria-hidden="true"></span>
                </div>
                <div style="min-width:0;">
                    <strong class="sfbc-header-title">Santa Fe AI Concierge</strong>
                    <span id="sfbcHeaderSubtitle" class="sfbc-header-subtitle">24/7 Resort Concierge</span>
                </div>
            </div>
            <div class="sfbc-header-controls">
                <!-- Language Selector (All Languages Supported) -->
                <div class="sfbc-lang-pill">
                    <select id="sfbcLangSelect" aria-label="Preferred language" onchange="window.sfbcOnLangChange && window.sfbcOnLangChange(this.value)">
                        <option value="" style="color:#0f172a;">All Languages</option>
                        <option value="English" style="color:#0f172a;">English</option>
                        <option value="Tagalog" style="color:#0f172a;">Tagalog</option>
                        <option value="Cebuano / Bisaya" style="color:#0f172a;">Bisaya / Cebuano</option>
                        <option value="Korean" style="color:#0f172a;">한국어 (Korean)</option>
                        <option value="Japanese" style="color:#0f172a;">日本語 (Japanese)</option>
                        <option value="Chinese" style="color:#0f172a;">中文 (Chinese)</option>
                        <option value="Spanish" style="color:#0f172a;">Español (Spanish)</option>
                        <option value="French" style="color:#0f172a;">Français (French)</option>
                        <option value="German" style="color:#0f172a;">Deutsch (German)</option>
                        <option value="Russian" style="color:#0f172a;">Русский (Russian)</option>
                        <option value="Arabic" style="color:#0f172a;">العربية (Arabic)</option>
                    </select>
                </div>
                <!-- Expand / Make Big Button -->
                <button id="sfbcExpandBtn" class="sfbc-header-icon-btn" type="button" onclick="window.sfbcToggleExpand()" aria-label="Expand chat window" title="Make Bigger / Expand">
                    <svg id="sfbcIconExpand" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    <svg id="sfbcIconRestore" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:none; pointer-events:none;"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                </button>
                <!-- Close Window Button -->
                <button class="sfbc-header-icon-btn" type="button" onclick="if(window.sfbcToggleChat){window.sfbcToggleChat(false);}else{var b=document.getElementById('sfbcChatBox');if(b){b.style.setProperty('display','none','important');b.classList.remove('sfbc-open');}}" aria-label="Close chat window" title="Close">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="pointer-events:none;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Quick-Reply Chips Bar -->
        <div id="sfbcQuickChipsBar">
            <button type="button" id="sfbcChipFaq" onclick="sfbcTogglePanel('faq')" class="sfbc-quick-chip" style="background:#7C533C !important; color:#ffffff !important; border-color:#7C533C !important;">Browse FAQs &amp; Rooms</button>
            <button type="button" id="sfbcChipRecommend" onclick="sfbcTogglePanel('recommend')" class="sfbc-quick-chip" style="background:#EFE2D0 !important; color:#5C3824 !important; border-color:#D6B48C !important;">Recommend a Room</button>
            <button type="button" id="sfbcChipRates" onclick="sfbcQuickAsk('What are your room rates and capacities?')" class="sfbc-quick-chip">Room Rates</button>
            <button type="button" id="sfbcChipAvail" onclick="sfbcOpenDatePrompt()" class="sfbc-quick-chip">Check Availability</button>
            <button type="button" id="sfbcChipPay" onclick="sfbcQuickAsk('How does the 50% deposit and balance payment work?')" class="sfbc-quick-chip">How to Pay</button>
            <button type="button" id="sfbcChipCancel" onclick="sfbcQuickAsk('What is your cancellation and refund policy?')" class="sfbc-quick-chip">Cancellation</button>
            <button type="button" id="sfbcChipDir" onclick="sfbcQuickAsk('How do I get to Santa Fe Beach Club?')" class="sfbc-quick-chip">Directions</button>
            <button type="button" id="sfbcChipOtp" onclick="sfbcTogglePanel('otp')" class="sfbc-quick-chip">My Booking (OTP)</button>
            <button type="button" id="sfbcChipStaff" onclick="sfbcTogglePanel('handoff')" class="sfbc-quick-chip">Talk to Staff</button>
        </div>

        <!-- Active Conversation Context Memory Bar (Phase 4 Intelligent Assistance) -->
        <div id="sfbcContextBar" style="display:none; padding:6px 12px; background:#EFF6FF; border-bottom:1px solid #BFDBFE; font-size:11px; color:#1E40AF; align-items:center; justify-content:space-between; gap:8px; flex-shrink:0; flex-wrap:wrap;">
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <strong style="font-size:10.5px; color:#1E3A8A;">Remembered Context:</strong>
                <span id="sfbcContextBadges" style="display:inline-flex; gap:5px; flex-wrap:wrap;"></span>
            </div>
            <button type="button" onclick="window.sfbcResetContext()" style="background:none; border:1px solid #93C5FD; color:#1D4ED8; border-radius:4px; padding:1px 6px; font-size:10px; font-weight:600; cursor:pointer;" title="Clear remembered dates, guests, and preferences">× Reset</button>
        </div>

        <!-- Active Live Reception Desk Banner -->
        <div id="sfbcLiveStaffBanner" style="display:none; padding:7px 14px; background:linear-gradient(90deg, #ECFEFF 0%, #F0FDF4 100%); border-bottom:1px solid #99F6E4; font-size:11px; color:#134E4A; align-items:center; justify-content:space-between; gap:8px; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:7px; min-width:0;">
                <span style="width:8px; height:8px; border-radius:50%; background:#10B981; flex-shrink:0; box-shadow:0 0 0 3px rgba(16,185,129,0.2);"></span>
                <span style="font-weight:700; white-space:nowrap;">Live Staff Chat</span>
                <span style="color:#0F766E; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Replies appear here &amp; by email</span>
            </div>
            <button type="button" onclick="window.sfbcResumeAiMode()" style="background:#ffffff; border:1px solid #5EEAD4; color:#0F766E; border-radius:6px; padding:3px 8px; font-size:10.5px; font-weight:700; cursor:pointer; flex-shrink:0;">Switch to AI</button>
        </div>

        <!-- Inline FAQ & Accommodation Knowledge Drawer -->
        <div id="sfbcFaqPanel" style="display:none; max-height:360px; overflow-y:auto; padding:12px 14px; background:#FAF7F2; border-bottom:1px solid #e2e8f0; font-size:12px; color:#334155; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div>
                    <strong style="color:#5C3824; font-size:12.5px;">Resort FAQs &amp; Accommodation Guide</strong>
                    <span style="font-size:10.5px; color:#64748B; display:block;">Tap a question for instant answers or to ask AI Concierge</span>
                </div>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#64748B; font-size:14px; font-weight:700; cursor:pointer;" title="Close drawer">&times;</button>
            </div>
            
            <!-- Category Tabs -->
            <div style="display:flex; gap:5px; margin-bottom:10px; overflow-x:auto; padding-bottom:4px;" id="sfbcFaqTabs">
                <button type="button" class="sfbc-faq-tab active" onclick="sfbcSwitchFaqTab('rooms', this)">Rooms &amp; Rates</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('payment', this)">Booking &amp; 50% Deposit</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('cancellation', this)">Cancellation &amp; Refund</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('amenities', this)">Amenities &amp; Wi-Fi</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('policies', this)">Early Arrival &amp; Pets</button>
                <button type="button" class="sfbc-faq-tab" onclick="sfbcSwitchFaqTab('directions', this)">Directions &amp; Port</button>
            </div>

            <!-- Tab 1: Rooms & Rates Guide -->
            <div id="sfbcFaqSection_rooms" class="sfbc-faq-section" style="display:block;">
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Beachview Duplex</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱6,900<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 2 guests · Max extra: 1 adult (+₱1,000/stay) · Direct beachfront view</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Beachview Duplex and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=beachview_duplex" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Seaview Duplex</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱7,900<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 2 guests · Max extra: 1 adult (+₱1,000/stay) · Panoramic ocean views</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Seaview Duplex and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=seaview_duplex" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Beach Villa</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱7,900<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 4 guests · Max extra: 3 adults (+₱1,000 each/stay) · Standalone luxury villa</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Beach Villa and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=beach_villa" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Standard Family Room</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱4,300<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 4 guests · Max extra: 1 adult (+₱700/stay) · Spacious family value</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Standard Family Room and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=standard_king" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
                <div class="sfbc-room-card">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <strong style="color:#1e293b; font-size:12px;">Standard Room</strong>
                        <span style="color:#84563C; font-weight:700; font-size:12px;">₱3,450<span style="font-size:10px; font-weight:normal; color:#64748b;">/night</span></span>
                    </div>
                    <div style="font-size:11px; color:#64748b;">Base: 2 guests · Max extra: 3 adults (+₱700 each/stay) · Clean &amp; comfortable</div>
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <button type="button" onclick="sfbcQuickAsk('Tell me more about Standard Room and check availability')" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:3px 8px; font-size:10.5px; cursor:pointer; color:#334155;">Ask AI Concierge</button>
                        <a href="book?room_type=standard_room" style="background:#84563C; color:#fff; border-radius:4px; padding:3px 8px; font-size:10.5px; text-decoration:none; font-weight:600;">Book Now &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Payment & 50% Deposit -->
            <div id="sfbcFaqSection_payment" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>How does the 50% deposit and remaining balance work?</summary>
                    <div class="sfbc-faq-item-ans">A 50% deposit confirms your reservation. The remaining 50% balance is due 24 hours before your scheduled arrival time (with a 6-hour grace period). Pay online via PayMongo, GCash (0950 522 3146), or Bank Transfer.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>What payment methods do you accept?</summary>
                    <div class="sfbc-faq-item-ans">We accept GCash, Maya, credit/debit cards via PayMongo, BDO/BPI Bank Deposit, and Cash upon check-in.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>Do you offer Senior Citizen or PWD discounts?</summary>
                    <div class="sfbc-faq-item-ans">Yes! 20% statutory discount + VAT exemption is granted for eligible Senior Citizen and PWD guests via our <a href="discount_booking.php" style="color:#84563C; font-weight:600;">Discount Booking</a> page.</div>
                </details>
            </div>

            <!-- Tab 3: Cancellation & Refund -->
            <div id="sfbcFaqSection_cancellation" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>What is your official cancellation and refund policy?</summary>
                    <div class="sfbc-faq-item-ans">
                        - <strong>1 to 3 nights</strong>: Cancel at least 48 hours (2 days) before 2:00 PM check-in for a 100% refund.<br>
                        - <strong>4 to 6 nights</strong>: Cancel at least 5 days (120 hours) before check-in for a 100% refund.<br>
                        - <strong>7+ nights</strong>: Cancel at least 7 days (168 hours) before check-in for a 100% refund.<br>
                        Cancellations after the deadline are non-refundable.
                    </div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>What happens if my ferry is cancelled due to a typhoon or sea gale?</summary>
                    <div class="sfbc-faq-item-ans">If the Philippine Coast Guard cancels sea travel due to a typhoon warning or sea weather advisory, we gladly reschedule your reservation with no penalty. Contact our front desk at 0950 522 3146.</div>
                </details>
            </div>

            <!-- Tab 4: Amenities & Wi-Fi -->
            <div id="sfbcFaqSection_amenities" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>Is high-speed Wi-Fi included in rooms and beachfront?</summary>
                    <div class="sfbc-faq-item-ans">Yes! Free high-speed Wi-Fi covers all guest rooms, beachfront loungers, lobby, and dining areas.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>Do you offer island hopping, motorcycle rental, and dining?</summary>
                    <div class="sfbc-faq-item-ans">Yes! Our reception assists with motorcycle/scooter rentals, Virgin Island boat tours, and our on-site beachfront restaurant serves daily breakfast and fresh seafood.</div>
                </details>
            </div>

            <!-- Tab 5: Policies & Pets -->
            <div id="sfbcFaqSection_policies" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>Can we store luggage if we arrive before 2:00 PM?</summary>
                    <div class="sfbc-faq-item-ans">Yes! Guests arriving early before the 2:00 PM check-in may store their luggage for free at reception and enjoy the beachfront while waiting.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>Are pets allowed at the resort?</summary>
                    <div class="sfbc-faq-item-ans">Pets are welcome in our ground-level Beach Villas upon prior notice. Please bring pet bedding and keep pets leashed in common areas.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>What are the check-in and check-out times?</summary>
                    <div class="sfbc-faq-item-ans">Standard check-in is at 2:00 PM and check-out is at 12:00 PM noon. Quiet hours are observed from 10:00 PM to 7:00 AM.</div>
                </details>
            </div>

            <!-- Tab 6: Directions & Port -->
            <div id="sfbcFaqSection_directions" class="sfbc-faq-section" style="display:none;">
                <details class="sfbc-faq-item" open>
                    <summary>How far is the resort from Santa Fe Port?</summary>
                    <div class="sfbc-faq-item-ans">We are located along the Pantalan shoreline in Santa Fe, Bantayan Island — only 3 to 5 minutes by tricycle (₱25–₱50/pax) from Santa Fe Port.</div>
                </details>
                <details class="sfbc-faq-item">
                    <summary>How do I get to Bantayan Island from Cebu City?</summary>
                    <div class="sfbc-faq-item-ans">Take a bus or van from Cebu North Bus Terminal to Hagnaya Port (San Remigio), then take the 1-hour ferry to Santa Fe Port.</div>
                </details>
            </div>
        </div>

        <!-- Inline Live Room Availability & Guided Reservation Assistance Drawer -->
        <div id="sfbcAvailabilityPanel" style="display:none; padding:12px 14px; background:#F0FDF4; border-bottom:1px solid #BBF7D0; font-size:12px; color:#166534; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div>
                    <strong style="color:#14532D; font-size:12.5px;">Reservation Assistance &amp; Live Availability</strong>
                    <span style="font-size:10.5px; color:#15803D; display:block;">Step 1: Collect Details &rarr; Step 2: Check Requirements &rarr; Step 3: Confirm Booking</span>
                </div>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#15803D; font-size:14px; font-weight:700; cursor:pointer;" title="Close drawer">&times;</button>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px; margin-bottom:6px;">
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#14532D; display:block; margin-bottom:2px;">Check-In Date</label>
                    <input type="date" id="sfbcAvailCin" style="width:100%; padding:5px 8px; border:1px solid #86EFAC; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                </div>
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#14532D; display:block; margin-bottom:2px;">Check-Out Date</label>
                    <input type="date" id="sfbcAvailCout" style="width:100%; padding:5px 8px; border:1px solid #86EFAC; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px; margin-bottom:8px;">
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#14532D; display:block; margin-bottom:2px;">Total Guests</label>
                    <select id="sfbcAvailGuests" style="width:100%; padding:5px 8px; border:1px solid #86EFAC; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                        <option value="1">1 Guest</option>
                        <option value="2" selected>2 Guests</option>
                        <option value="3">3 Guests</option>
                        <option value="4">4 Guests</option>
                        <option value="5">5 Guests</option>
                        <option value="6">6 Guests</option>
                        <option value="7">7 Guests</option>
                        <option value="8">8+ Guests</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#14532D; display:block; margin-bottom:2px;">Room Preference</label>
                    <select id="sfbcAvailRoomType" style="width:100%; padding:5px 8px; border:1px solid #86EFAC; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                        <option value="">All / Any Accommodation</option>
                        <option value="beachview_duplex">Beachview Duplex (₱6,900)</option>
                        <option value="seaview_duplex">Seaview Duplex (₱7,900)</option>
                        <option value="beach_villa">Beach Villa (₱7,900)</option>
                        <option value="standard_king">Standard Family (₱4,300)</option>
                        <option value="standard_room">Standard Room (₱3,450)</option>
                    </select>
                </div>
            </div>
            <div style="background:#DCFCE7; border:1px solid #86EFAC; border-radius:6px; padding:6px 8px; margin-bottom:8px; font-size:10.5px; color:#14532D; line-height:1.35;">
                <strong>Reservation Requirements:</strong> <strong>50% deposit</strong> required upon booking (PayMongo / GCash / Bank); <strong>50% balance</strong> due 24h before arrival; bring a <strong>valid ID &amp; booking QR code</strong> at 2:00 PM check-in.
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <button type="button" onclick="sfbcSubmitAvailabilityQuery()" style="width:100%; padding:8px 6px; background:#16A34A; color:#fff; border:none; border-radius:6px; font-weight:700; font-size:11.5px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px;">
                    <span>Show Room Cards</span>
                </button>
                <button type="button" onclick="sfbcDirectToConfirmBooking()" style="width:100%; padding:8px 6px; background:#0E9AAD; color:#fff; border:none; border-radius:6px; font-weight:700; font-size:11.5px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px;">
                    <span>Confirm Booking &rarr;</span>
                </button>
            </div>
        </div>

        <!-- Inline Smart Accommodation Recommender & Reservation Assistance Drawer -->
        <div id="sfbcRecommendPanel" style="display:none; padding:12px 14px; background:#FDF4FF; border-bottom:1px solid #F0ABFC; font-size:12px; color:#334155; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="color:#701A75; font-size:12px;">Smart Room Recommender &amp; Reservation Helper</strong>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#701A75; font-size:12px; font-weight:700; cursor:pointer;">Close ×</button>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#701A75; display:block; margin-bottom:2px;">Number of Guests</label>
                    <select id="sfbcRecGuests" style="width:100%; padding:5px 8px; border:1px solid #E879F9; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                        <option value="2" selected>2 Guests (Couple / Pair)</option>
                        <option value="1">1 Guest (Solo Traveler)</option>
                        <option value="3">3 Guests</option>
                        <option value="4">4 Guests (Small Family)</option>
                        <option value="5">5 Guests</option>
                        <option value="6">6 Guests (Family / Group)</option>
                        <option value="8">8+ Guests (Barkada / Multi-Room)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:10.5px; font-weight:600; color:#701A75; display:block; margin-bottom:2px;">Max Nightly Budget</label>
                    <select id="sfbcRecBudget" style="width:100%; padding:5px 8px; border:1px solid #E879F9; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                        <option value="">Any Budget</option>
                        <option value="4000">Up to ₱4,000 / night</option>
                        <option value="5000">Up to ₱5,000 / night</option>
                        <option value="7000">Up to ₱7,000 / night</option>
                        <option value="8000">Up to ₱8,000 / night</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:8px;">
                <label style="font-size:10.5px; font-weight:600; color:#701A75; display:block; margin-bottom:2px;">Trip Style / Priority</label>
                <select id="sfbcRecStyle" style="width:100%; padding:5px 8px; border:1px solid #E879F9; border-radius:6px; font-size:12px; background:#fff; color:#0f172a;">
                    <option value="beachfront">Direct Beachfront &amp; Ocean View</option>
                    <option value="honeymoon">Romantic / Honeymoon / Anniversary</option>
                    <option value="family">Family / Barkada Getaway</option>
                    <option value="budget">Best Value / Budget-Friendly</option>
                    <option value="senior">Senior / Ground-Floor Easy Access</option>
                    <option value="workation">Quiet Workation / Remote Work</option>
                </select>
            </div>
            <button type="button" onclick="sfbcSubmitRecommendQuery()" style="width:100%; padding:8px; background:#86198F; color:#fff; border:none; border-radius:6px; font-weight:700; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:background 0.15s ease;">
                <span>Find Best Room &amp; Pre-Filled Booking Link</span> &rarr;
            </button>
        </div>

        <!-- Inline OTP Booking Verification Drawer -->
        <div id="sfbcOtpPanel" style="display:none; padding:12px 14px; background:#FFFBEB; border-bottom:1px solid #FDE68A; font-size:12px; color:#334155; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="color:#5C3824; font-size:12px;">Verify My Booking (Email OTP)</strong>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#64748B; font-size:12px; cursor:pointer;">Close ×</button>
            </div>
            <div id="sfbcOtpStep1" style="display:flex; flex-direction:column; gap:6px;">
                <input type="text" id="sfbcOtpRef" placeholder="Booking Reference (e.g. REF-001)" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <input type="email" id="sfbcOtpEmail" placeholder="Booking Email Address" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <button type="button" onclick="sfbcRequestBookingOtp()" style="width:100%; padding:7px; background:#84563C; color:#fff; border:none; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer;">Send 6-Digit Verification Code</button>
            </div>
            <div id="sfbcOtpStep2" style="display:none; flex-direction:column; gap:6px;">
                <p id="sfbcOtpHintText" style="font-size:11px; color:#475569; margin:0;"></p>
                <input type="text" id="sfbcOtpCode" maxlength="6" placeholder="Enter 6-digit OTP code" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#fff; letter-spacing:4px; text-align:center; font-weight:700;">
                <button type="button" onclick="sfbcVerifyBookingOtp()" style="width:100%; padding:7px; background:#16A34A; color:#fff; border:none; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer;">Verify &amp; Show Booking</button>
            </div>
        </div>

        <!-- Inline Human Staff Handoff Drawer -->
        <div id="sfbcHandoffPanel" style="display:none; padding:12px 14px; background:#F1F5F9; border-bottom:1px solid #e2e8f0; font-size:12px; color:#334155; flex-shrink:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="color:#5C3824; font-size:12px;">Forward Chat to Reception Staff</strong>
                <button type="button" onclick="sfbcTogglePanel(null)" style="background:none; border:none; color:#64748B; font-size:12px; cursor:pointer;">Close ×</button>
            </div>
            <div style="display:flex; flex-direction:column; gap:6px;">
                <input type="text" id="sfbcHandoffName" placeholder="Your Full Name" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <input type="email" id="sfbcHandoffEmail" placeholder="Your Email Address" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <input type="text" id="sfbcHandoffNote" placeholder="Short note for reception (optional)" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px; background:#fff;">
                <button type="button" onclick="sfbcSubmitHandoff()" style="width:100%; padding:7px; background:#84563C; color:#fff; border:none; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer;">Send Transcript to Staff</button>
            </div>
        </div>

        <!-- Messages Area -->
        <div id="sfbcChatMessages">
            <div style="display:flex; flex-direction:column; align-items:flex-start; width:100%;">
                <div class="sfbc-bot-label">
                    <img src="<?php echo htmlspecialchars($sfbc_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Santa Fe Beach Club" onerror="if(!this.dataset.fb){this.dataset.fb='1';this.src='frontend/assets/logo.jpg';}">
                    <span>Santa Fe Concierge</span>
                </div>
                <div class="sfbc-msg-bubble-model">
                    Hello! Welcome to <strong>Santa Fe Beach Club</strong>. I can check live room availability &amp; prices, recommend the best accommodation for your group, prepare pre-filled booking links, verify your reservation via OTP, or answer resort questions in <strong>any language</strong> (English, Tagalog, Bisaya, 한국어, 日本語, 中文, Español, Français, Deutsch, and more). How may I assist you today?
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; max-width:100%;">
                    <span style="font-size:10.5px; color:#7C533C; font-weight:700; width:100%; margin-bottom:1px;">Popular questions to get started:</span>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('Hi! Can I book a stay on February 1-11?')">Book a Stay (Feb 1–11)</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('What are your room rates and capacities?')">Room Rates &amp; Capacities</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcOpenDatePrompt()">Check Dates Availability</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('How do I book and what are the reservation requirements?')">Reservation Requirements</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('What is your cancellation and refund policy?')">Cancellation Policy</button>
                    <button type="button" class="sfbc-suggested-q-pill" onclick="sfbcQuickAsk('How do I get to Santa Fe Beach Club?')">Directions from Port</button>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="sfbc-composer-dock">
            <div class="sfbc-input-pill">
                <input type="text" id="sfbcChatInput" maxlength="500" placeholder="Ask about rooms, dates, rates, policies..." onkeypress="if(event.key==='Enter') sfbcSendMessage();">
                <button type="button" class="sfbc-send-btn" onclick="sfbcSendMessage()">
                    <span>Send</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px; color:#8B7366; padding:0 4px;">
                <span>Chats stored to improve service (<a href="../terms.php#privacy" style="text-decoration:underline; color:#6B4F42;">Privacy Policy</a>)</span>
                <button type="button" onclick="sfbcTogglePanel('handoff')" style="background:none; border:none; color:#84563C; font-weight:700; text-decoration:underline; cursor:pointer; font-size:10.5px; padding:0;">Need a human?</button>
            </div>
        </div>
    </div>
</div>

<script>window.SFBC_CHATBOT_CONFIG = <?php echo json_encode($sfbc_widget_cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="<?php echo htmlspecialchars($sfbc_widget_js, ENT_QUOTES, 'UTF-8'); ?>"></script>
