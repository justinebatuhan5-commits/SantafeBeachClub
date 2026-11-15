<div class="bk-card bk-discount-card">
    <h2 class="bk-card-title">Senior Citizen / PWD discount</h2>
    <p class="bk-card-sub">Applicable to valid Senior Citizen or PWD ID holders upon front desk verification.</p>
    <input type="hidden" name="discount_request_nonce" value="<?php echo htmlspecialchars($_SESSION['discount_request_nonce'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <label for="discountCategory">Booking type</label>
    <select name="discount_category" id="discountCategory" class="bk-input">
        <option value="regular">Regular booking</option>
        <option value="senior">Senior Citizen — request 20% discount</option>
        <option value="pwd">PWD — request 20% discount</option>
    </select>
    <div id="discountFields" hidden>
        <p>20% applies to the entire room booking after staff verifies eligibility. Promo codes cannot be combined. Estimated total: <strong>₱ <?php echo number_format(round($pricing['subtotal'] * .8, 2), 2); ?></strong>. No payment is collected now.</p>
        <p>Your room is held for up to <?php echo max(1, min(72, (int) (getenv('DISCOUNT_HOLD_HOURS') ?: 24))); ?> hours, or until arrival time if earlier. Staff approval and a verified 50% deposit are required to confirm your booking.</p>
        <label for="eligibleGuest">Full name of eligible staying guest</label>
        <input class="bk-input" id="eligibleGuest" name="eligible_guest" maxlength="150" disabled>
        <label for="discountId">Valid Senior Citizen / PWD ID (JPG or PNG, maximum 2 MB)</label>
        <details class="bk-id-guide" id="discountIdGuide">
            <summary>View sample <span id="sampleIdCategory">Senior Citizen</span> ID</summary>
            <img id="sampleIdImage" class="bk-id-sample-image" src="assets/images/senior-id-sample.png" alt="Senior Citizen ID illustration with fictional name, photo placeholder and ID number. SAMPLE — NOT VALID." width="1536" height="1024" decoding="async">
            <p>Actual ID designs may vary by issuing city or municipality. This illustration only shows what should be readable.</p>
        </details>
        <p id="discountIdHelp" class="bk-id-help">Upload a clear photo of your own valid ID. Keep your name, photo, and ID number readable, and include all four corners without glare. Do not upload this sample.</p>
        <input id="discountId" type="file" name="discount_id" accept="image/jpeg,image/png" aria-describedby="discountIdHelp" disabled>
        <label><input type="checkbox" name="discount_consent" value="1" id="discountConsent" disabled> I agree to the booking policies and authorize staff to review this ID for discount verification. ID images are privately stored and deleted after the configured retention period (<?php echo max(1, min(365, (int) (getenv('DISCOUNT_ID_RETENTION_DAYS') ?: 30))); ?> days after review, or submission if unreviewed).</label>
    </div>
</div>
<fieldset id="regularPaymentFields" style="border:0;padding:0;margin:0;min-width:0;">
<!-- STEP 3: PAYMENT -->
<div class="bk-card">
    <div class="bk-card-header-flex">
        <div>
            <h2 class="bk-card-title" style="margin:0;">Pay 50% Deposit With</h2>
            <p class="bk-card-sub" style="margin:6px 0 0;">Select your preferred method to settle the 50% reservation deposit.</p>
        </div>
    </div>

    <!-- 1. INSTANT ONLINE PAYMONGO (AUTO CONFIRMED) -->
    <div class="bk-pay-option bk-pay-option--active" id="opt-paymongo">
        <label class="bk-pay-radio">
            <span class="bk-pay-radio-left">
                <input type="radio" name="payment_method" value="PayMongo Online" checked>
                <span class="bk-pay-icon-box" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                </span>
                <span class="bk-pay-title-wrap">
                    <strong class="bk-pay-title">Instant Online (GCash / Maya / Cards / QR Ph)</strong>
                    <span class="bk-pay-subtitle">Fastest confirmation &bull; Powered by PayMongo</span>
                </span>
            </span>
            <span class="bk-pay-badge bk-pay-badge--auto">Auto Confirmed</span>
        </label>
        <div id="paymongo-form" class="bk-card-form">
            <div class="bk-pay-infobox bk-pay-infobox--emerald">
                <div style="font-size:13.5px; color:#166534; font-weight:600; line-height:1.55;">
                    Upon clicking <strong>"Confirm and book"</strong>, you will be redirected to the secure PayMongo checkout screen to settle <strong>₱ <?php echo number_format($deposit_amount, 2); ?></strong>.
                </div>
                <div style="font-size:12px; color:#15803D; margin-top:8px; display:flex; flex-wrap:wrap; align-items:center; gap:6px;">
                    <span>Visa / Mastercard</span> &bull; <span>GCash</span> &bull; <span>Maya</span> &bull; <span>QR Ph</span>
                </div>
            </div>
            <p style="font-size:12px; color:#166534; font-weight:600; margin:0;">
                No receipt upload required. Your reservation is confirmed immediately upon payment.
            </p>
        </div>
    </div>

    <!-- 2. BANK TRANSFER (MANUAL VERIFICATION) -->
    <div class="bk-pay-option" id="opt-bank">
        <label class="bk-pay-radio">
            <span class="bk-pay-radio-left">
                <input type="radio" name="payment_method" value="Bank Deposit">
                <span class="bk-pay-icon-box" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M3 10h18"/><path d="M5 6l7-3 7 3"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M8 14v3"/><path d="M12 14v3"/><path d="M16 14v3"/></svg>
                </span>
                <span class="bk-pay-title-wrap">
                    <strong class="bk-pay-title">Bank Deposit (BDO)</strong>
                    <span class="bk-pay-subtitle">Direct bank transfer with receipt upload</span>
                </span>
            </span>
            <span class="bk-pay-badge bk-pay-badge--manual">Manual Verification</span>
        </label>
        <div id="bank-form" class="bk-card-form" style="display:none;">
            <p style="font-size:13.5px; color:#4A3E36; margin:0; line-height:1.55;">Please transfer the 50% deposit amount (<strong>₱ <?php echo number_format($deposit_amount, 2); ?></strong>) to our BDO account below.</p>
            <div class="bk-pay-infobox">
                <div style="font-size:13px; color:#6E6259; margin-bottom:5px;">Bank Name: <strong style="color:#2B2420;">BDO (Banco de Oro)</strong></div>
                <div style="font-size:13px; color:#6E6259; margin-bottom:5px;">Account Name: <strong style="color:#2B2420;">Santa Fe Beach Club</strong></div>
                <div style="font-size:13px; color:#6E6259;">Account Number: <strong style="color:#7C533C; letter-spacing:0.5px;">0012 3456 7890</strong></div>
            </div>
            <div class="bk-form-group" style="width:100%; text-align:left;">
                <label for="bankReferenceInput">Bank Reference / Transaction Number <span class="req">*</span></label>
                <input type="text" name="bank_reference" id="bankReferenceInput" placeholder="Enter reference number" value="<?php echo htmlspecialchars($_POST['bank_reference'] ?? ''); ?>">
                <div id="bankRefError" style="display:none; color:#B91C1C; font-size:12px; margin-top:6px; font-weight:500;">Please enter the reference number.</div>
            </div>
            <div class="bk-form-group" style="width:100%; text-align:left;">
                <label for="bankReceiptInput">Upload Payment Receipt (JPG, PNG, PDF &mdash; Max 2MB) <span class="req">*</span></label>
                <input type="file" name="bank_receipt" id="bankReceiptInput" accept="image/jpeg,image/png,image/webp,application/pdf" data-max-size="2" data-label="Bank receipt" style="width:100%; padding:12px 15px; border:1.5px dashed #C8996F; border-radius:10px; font-size:13.5px; outline:none; background:#FAF7F2; color:#6E6259; box-sizing:border-box;" onchange="validateReceiptFile(this, ['jpg','jpeg','png','pdf'], 'bankReceiptError')">
                <div id="bankReceiptError" style="display:none; color:#B91C1C; font-size:12px; margin-top:6px; font-weight:500; background:#FEF2F2; border:1px solid #FECACA; border-radius:6px; padding:8px 12px; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Invalid file. Please upload an actual JPG, PNG, or PDF file — renaming other file types (e.g. .exe renamed to .png) is not allowed.</span>
                </div>
            </div>
            <p style="font-size:12px; color:#6E6259; margin:0;">Your booking remains pending until our front desk verifies your transfer receipt.</p>
        </div>
    </div>

    <!-- 3. GCASH QR CODE UPLOAD (MANUAL VERIFICATION) -->
    <div class="bk-pay-option" id="opt-online">
        <label class="bk-pay-radio">
            <span class="bk-pay-radio-left">
                <input type="radio" name="payment_method" value="GCash QR">
                <span class="bk-pay-icon-box" style="background:#FFFDF9;">
                    <img src="assets/images/gcash_logo.png?v=<?php echo time(); ?>" alt="GCash" style="width:20px; height:20px; border-radius:4px; object-fit:contain; display:block;">
                </span>
                <span class="bk-pay-title-wrap">
                    <strong class="bk-pay-title">GCash QR Screenshot</strong>
                    <span class="bk-pay-subtitle">Scan QR code and upload proof of payment</span>
                </span>
            </span>
            <span class="bk-pay-badge bk-pay-badge--manual">Manual Verification</span>
        </label>
        <div id="online-form" class="bk-card-form" style="display:none; flex-direction:column; align-items:center;">
            <div style="display:flex; flex-direction:column; align-items:center; width:100%; max-width:420px; background:#FFFDF9; border:1px solid #EAE0D5; border-radius:14px; padding:22px; box-shadow:0 6px 24px rgba(43,36,32,0.04);">
                <div style="font-size:15px; color:#2B2420; font-weight:700; text-align:center; margin-bottom:4px; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <img src="assets/images/gcash_logo.png?v=<?php echo time(); ?>" alt="GCash" style="width:20px; height:20px; border-radius:4px; vertical-align:middle; object-fit:contain;">
                    Scan to Pay via <span style="color:#7C533C;">GCash</span>
                </div>
                <div style="font-size:13px; color:#6E6259; text-align:center; margin-bottom:16px;">
                    Amount: <strong style="color:#2B2420;">₱ <?php echo number_format($deposit_amount, 2); ?></strong>
                </div>
                <div style="background:#FFFFFF; border:1.5px solid #EAE0D5; border-radius:12px; padding:12px; margin-bottom:16px;">
                    <img src="assets/images/gcash_qr.png?v=<?php echo time(); ?>"
                         onerror="this.style.display='none'; document.getElementById('gcash-qr-placeholder').style.display='flex';"
                         alt="GCash / InstaPay QR Code"
                         style="width:180px; height:180px; object-fit:contain; display:block;">
                    <div id="gcash-qr-placeholder" style="display:none; width:180px; height:180px; background:#FAF7F2; border-radius:8px; align-items:center; justify-content:center; flex-direction:column; gap:8px;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9C8F84" stroke-width="1.2">
                            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                            <rect x="3" y="14" width="7" height="7" rx="1"/>
                            <rect x="14" y="14" width="2" height="2"/><rect x="18" y="14" width="3" height="2"/>
                            <rect x="14" y="18" width="2" height="3"/><rect x="19" y="19" width="2" height="2"/>
                        </svg>
                        <span style="font-size:11px; color:#6E6259; font-weight:600; text-align:center;">GCash QR placeholder<br><em>Upload your QR to assets/images/gcash_qr.png</em></span>
                    </div>
                </div>
                <div class="bk-pay-infobox" style="margin-bottom:16px; text-align:center; width:100%; box-sizing:border-box;">
                    <div style="font-size:10.5px; color:#7C533C; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:4px;">
                        GCash Account Details
                    </div>
                    <div style="font-size:16px; font-weight:800; color:#5C3D2B; display:flex; align-items:center; justify-content:center; gap:6px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> <?php echo htmlspecialchars($gcash_number); ?>
                    </div>
                    <div style="font-size:12px; color:#6E6259; margin-top:3px;">
                        Account Name: <strong style="color:#2B2420;"><?php echo htmlspecialchars($gcash_name); ?></strong>
                    </div>
                </div>

                <div style="font-size:12px; color:#6E6259; text-align:center; margin-bottom:16px; line-height:1.75;">
                    1. Open your <strong>GCash app</strong><br>
                    2. Tap <strong>Pay QR</strong> and scan the code above<br>
                    3. Enter <strong>₱ <?php echo number_format($deposit_amount, 2); ?></strong> and complete payment<br>
                    4. Enter the <strong>GCash Reference No.</strong> &amp; upload receipt screenshot below
                </div>
                <div class="bk-form-group" style="width:100%; text-align:left; margin-bottom:14px;">
                    <label for="gcashReferenceInput">
                        GCash Reference Number <span class="req">*</span>
                    </label>
                    <input type="text" name="gcash_reference" id="gcashReferenceInput" placeholder="e.g. 1001 2345 6789 or 13-digit Ref No."
                        value="<?php echo htmlspecialchars($_POST['gcash_reference'] ?? ''); ?>">
                    <div id="gcashRefError" style="display:none; color:#B91C1C; font-size:12px; margin-top:6px; font-weight:500;">
                        Please enter your GCash reference number.
                    </div>
                </div>
                <div class="bk-form-group" style="width:100%; text-align:left;">
                    <label for="gcashReceiptInput">Upload GCash Receipt Screenshot (JPG or PNG &mdash; Max 2MB) <span class="req">*</span></label>
                    <input type="file" name="gcash_receipt" id="gcashReceiptInput" accept="image/jpeg,image/png" data-max-size="2" data-label="GCash receipt" style="width:100%; padding:12px 15px; border:1.5px dashed #C8996F; border-radius:10px; font-size:13.5px; outline:none; background:#FAF7F2; color:#6E6259; box-sizing:border-box;" onchange="validateReceiptFile(this, ['jpg','jpeg','png'], 'gcashReceiptError')">
                    <div id="gcashReceiptError" style="display:none; color:#B91C1C; font-size:12px; margin-top:6px; font-weight:500; background:#FEF2F2; border:1px solid #FECACA; border-radius:6px; padding:8px 12px; align-items:center; gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>Invalid file. Please upload an actual JPG or PNG screenshot — renaming other file types is not allowed.</span>
                    </div>
                </div>
                <p style="font-size:11.5px; color:#6E6259; text-align:center; margin-top:12px; margin-bottom:0;">Our front desk will verify your GCash screenshot and confirm your booking.</p>
            </div>
        </div>
    </div>
</div>
</fieldset>

<div class="bk-card" id="regularPaymentSchedule">
    <h2 class="bk-card-title">Payment schedule</h2>
    <div class="bk-schedule-row">
        <div>
            <span>Deposit due now</span>
            <span class="bk-schedule-sub">50% deposit required to confirm your reservation</span>
        </div>
        <span class="bk-amt">₱ <?php echo number_format($deposit_amount, 2); ?></span>
    </div>
    <div class="bk-schedule-row bk-schedule-row--due">
        <div>
            <span>Balance due on <?php echo date('d M Y', strtotime($checkin . ' -1 day')); ?></span>
            <span class="bk-schedule-sub">Remaining room balance (₱ <?php echo number_format($total_amount - $deposit_amount, 2); ?>) &bull; Payment convenience fee: ₱ 0.00</span>
        </div>
        <span class="bk-amt">₱ <?php echo number_format($total_amount - $deposit_amount, 2); ?></span>
    </div>
</div>

<div class="bk-card">
    <h2 class="bk-card-title">Booking Policies</h2>
    <p class="bk-policy-intro">Please review the stay policies, deposit deadlines, and check-in guidelines before confirming.</p>
    <div class="bk-policy-box">
        <div class="bk-policy-room-title"><?php echo htmlspecialchars($accommodation_name, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="bk-policy-grid">
            <div class="bk-policy-item">
                <strong>Cancellation &amp; No-Show</strong>
                <p>If cancelled, modified, or in case of no-show, the first night will be charged.</p>
            </div>
            <div class="bk-policy-item">
                <strong>Deposit &amp; Balance</strong>
                <p>A 50% deposit confirms your reservation. Remaining balance is due one day before arrival; unpaid bookings enter a grace period before staff review.</p>
            </div>
        </div>
        <div class="bk-policy-notes">
            <strong>Check-In, Dining &amp; Discount Guidelines</strong>
            <ul>
                <li>Check-in time is <strong>1:30 PM</strong> &bull; Check-out time is <strong>11:00 AM</strong></li>
                <li>Restaurant breakfast hours: <strong>7:00 AM &ndash; 1:30 PM</strong></li>
                <li>Additional guest rates: ₱500 (1&ndash;5 pax), ₱1,000 (6&ndash;10 pax), ₱1,000+ &amp; ₱150/pax in excess of 10 pax</li>
                <li>Senior Citizen / PWD ID verification: message <a href="https://www.facebook.com/SantaFeBeachClub" target="_blank" rel="noopener noreferrer">Santa Fe Beach Club Facebook</a> or email <a href="mailto:santafebeachclub@gmail.com">santafebeachclub@gmail.com</a> to submit your ID. Once approved, we will send a payment link for the discounted total. Only one discount applies per booking.</li>
            </ul>
        </div>
    </div>
    <label class="bk-checkbox-label">
        <input type="checkbox" required>
        <span>Please check this box to indicate that you have read and agree to the <a href="terms" target="_blank" rel="noopener noreferrer">Booking Policies, Terms of Service, and Privacy Policy</a>.</span>
    </label>
</div>

<div class="bk-actions">
    <a href="book?step=2" class="btn-bk-back">&larr; Back</a>
    <input type="hidden" name="action" value="confirm_booking">
    <button type="submit" class="btn-bk-next">Confirm and book</button>
</div>
