<!-- STEP 1: CONFIRMATION & EXTRAS -->
<div class="bk-card">
    <h2 class="bk-card-title">Room</h2>
    <div class="bk-room-box">
        <div class="bk-room-left">
            <h3 class="bk-room-name">1 &times; <?php echo htmlspecialchars($accommodation_name); ?></h3>
            <div class="bk-room-badge" style="background:<?php echo ($available_rooms === 0) ? '#FEE2E2' : (($available_rooms !== null && $available_rooms <= 3) ? '#FFF7ED' : '#ECFDF5'); ?>; color:<?php echo ($available_rooms === 0) ? '#B91C1C' : (($available_rooms !== null && $available_rooms <= 3) ? '#B45309' : '#15803D'); ?>;">
                <?php if ($available_rooms === null): ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                    Checking availability...
                <?php elseif ($available_rooms === 0): ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    Sold out for selected dates
                <?php elseif ($available_rooms <= 3): ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                    Only <?php echo $available_rooms; ?> room<?php echo $available_rooms > 1 ? 's' : ''; ?> left!
                <?php else: ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Available (<?php echo $available_rooms; ?> rooms left)
                <?php endif; ?>
            </div>
            <ul class="bk-room-perks">
                <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Flexible cancellation <span class="info-icon">i</span></li>
                <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> <?php echo $has_breakfast ? 'Breakfast included' : 'No breakfast included'; ?></li>
            </ul>
            <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" id="openAvailabilityCalBtn" style="background:#FFF; border:1.5px solid #84563C; color:#84563C; border-radius:8px; padding:7px 14px; font-size:12.5px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s;" onmouseover="this.style.background='#84563C'; this.style.color='#FFF';" onmouseout="this.style.background='#FFF'; this.style.color='#84563C';">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Change Dates & Check Availability
                </button>
            </div>
        </div>
        <div class="bk-room-right">
            <button type="button" class="bk-edit-icon" id="editRoomBtn" aria-label="Edit room" title="Change Room or Guests">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <div class="bk-room-price">₱ <?php echo number_format($total_amount, 2); ?></div>
            <div class="bk-room-subtext">
                <span class="stay-facts" aria-label="Stay details">
                    <span><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                    <span>1 room</span>
                    <span><?php echo $guests_total; ?> adult<?php echo $guests_total > 1 ? 's' : ''; ?></span>
                </span>
                <?php if (($pricing['extra_person_total'] ?? 0) > 0): ?>
                    <span style="color:#7C533C; font-weight:600;">(includes <?php echo $pricing['extra_adults']; ?> extra adult<?php echo $pricing['extra_adults'] > 1 ? 's' : ''; ?>)</span>
                <?php endif; ?>
            </div>
            <?php if ($discount_amount > 0): ?>
                <div style="font-size:11px; color:#15803D; font-weight:700; margin-top:4px;">Coupon applied: -₱<?php echo number_format($discount_amount, 2); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- PROMO CODE INPUT BOX -->
<div class="bk-card" style="padding:18px 22px;">
    <h3 style="font-size:14px; font-weight:700; margin-bottom:10px; color:var(--ink); display:flex; align-items:center; gap:8px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#7C533C" stroke-width="2.2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        Have a Promo / Coupon Code?
    </h3>
    <div style="display:flex; gap:10px; align-items:center;">
        <input type="text" name="promo_code" value="<?php echo htmlspecialchars($applied_promo_code ?? ''); ?>" placeholder="Enter code (e.g. SUMMER2026)" style="flex:1; padding:10px 14px; border:1.5px solid #EAE0D5; border-radius:10px; font-size:13px; text-transform:uppercase; background:#FAF7F2; color:#2B2420;">
        <button type="submit" name="apply_promo" value="1" class="btn-secondary" style="padding:10px 18px; font-size:12.5px; font-weight:700; cursor:pointer; background:#5C3D2B; color:#FFF; border-radius:10px; border:none;">Apply</button>
        <?php if ($applied_promo_code): ?>
            <button type="submit" name="remove_promo" value="1" class="btn-secondary" style="padding:10px 12px; font-size:12px; cursor:pointer; background:#FEE2E2; color:#B91C1C; border-radius:8px; border:none;">Remove</button>
        <?php endif; ?>
    </div>
    <?php if ($applied_promo_code && $discount_amount > 0): ?>
        <div style="margin-top:8px; font-size:12px; color:#15803D; font-weight:600;">
            Code <strong><?php echo htmlspecialchars($applied_promo_code); ?></strong> applied! You save ₱<?php echo number_format($discount_amount, 2); ?>.
        </div>
    <?php endif; ?>
</div>

<div class="bk-actions">
    <a href="rooms" class="btn-bk-back">&larr; Back</a>
    <input type="hidden" name="action" value="go_step_2">
    <button type="submit" class="btn-bk-next" <?php echo ($available_rooms === 0) ? 'disabled' : ''; ?>>Next &rarr;</button>
</div>
