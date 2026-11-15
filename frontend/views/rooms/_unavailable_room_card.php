<?php
/**
 * Renders one unavailable room type card.
 * Expects $type_key and $type_rooms in scope alongside catalog maps.
 */
$label       = $type_labels[$type_key]       ?? ucfirst(str_replace('_', ' ', $type_key));
$gradient    = $type_gradients[$type_key]    ?? 'linear-gradient(135deg,#374151,#6b7280)';
$desc        = $type_descriptions[$type_key] ?? '';
$bed         = $type_beds[$type_key]         ?? '1 bed';
$photo       = $type_photos[$type_key]       ?? 'assets/hero-slide-4-hd.png';
$sample_room = $type_rooms[0];
$price       = $sample_room['price_per_night'];
$capacity    = $sample_room['capacity'];
$is_all_maintenance = true;
foreach ($type_rooms as $tr) {
    if ($tr['status'] !== 'maintenance') {
        $is_all_maintenance = false;
        break;
    }
}
$unavail_reason = $is_all_maintenance ? 'Under Maintenance' : 'Fully Booked';
?>
<div class="room-type-section room-type-section--unavailable">
    <div class="rts-header">
        <h3 class="rts-title"><?php echo htmlspecialchars($label); ?></h3>
        <span class="unavail-badge"><?php echo $unavail_reason; ?></span>
    </div>
    <div class="rate-card rate-card--unavailable">
        <div class="rate-card-left">
            <div class="rate-photo" style="background-color: #e7dfd4; background-image: url('<?php echo htmlspecialchars($photo); ?>'); opacity: 0.72;">
                <div class="rate-photo-overlay">
                    <span class="rate-photo-badge"><?php echo $unavail_reason; ?></span>
                    <div class="rate-photo-copy">
                        <span class="rate-photo-kicker"><?php echo htmlspecialchars($label); ?></span>
                        <strong class="rate-photo-title"><?php echo $capacity; ?> guests &middot; <?php echo htmlspecialchars($bed); ?></strong>
                    </div>
                </div>
            </div>
            <div class="rate-info">
                <span class="rate-details-label">Your room at a glance</span>
                <dl class="rate-comparison">
                    <div><dt>Capacity</dt><dd>Up to <?php echo (int)$capacity; ?> guests</dd></div>
                    <div><dt>Sleeping arrangements</dt><dd><?php echo htmlspecialchars($bed); ?></dd></div>
                    <div><dt>Breakfast</dt><dd><?php echo !empty($breakfast_included_types[$type_key]) ? 'Included' : 'Not included'; ?></dd></div>
                    <div><dt>Extra person</dt><dd>&#8369;<?php echo number_format($extra_person_rates[$type_key] ?? 0); ?></dd></div>
                </dl>
                <div class="rate-bed">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><line x1="12" y1="10" x2="12" y2="20"/></svg>
                    <?php echo htmlspecialchars($bed); ?>
                </div>
                <p class="rate-desc"><?php echo htmlspecialchars($desc); ?></p>
            </div>
        </div>
        <div class="rate-card-right rate-card-right--unavailable">
            <h4 class="rate-name"><?php echo htmlspecialchars($label); ?></h4>
            <div class="rate-pricing">
                <div class="rate-price-main">&#8369;<?php echo number_format($price, 2); ?></div>
                <div class="rate-price-sub">per night &middot; includes up to <?php echo (int)$capacity; ?> adults</div>
            </div>

            <?php if (!$is_all_maintenance): ?>
            <div class="rate-date-strip-wrap" id="date-strip-wrap-<?php echo htmlspecialchars($type_key); ?>">
                <button type="button" class="btn-strip-nav btn-strip-prev" onclick="shiftDateStrip(<?php echo xss_attr_json($type_key); ?>, -3)" aria-label="Previous dates">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="rate-date-strip" id="date-strip-<?php echo htmlspecialchars($type_key); ?>" data-type="<?php echo htmlspecialchars($type_key); ?>" data-price="<?php echo $price; ?>">
                    <!-- Populated via JS -->
                </div>
                <button type="button" class="btn-strip-nav btn-strip-next" onclick="shiftDateStrip(<?php echo xss_attr_json($type_key); ?>, 3)" aria-label="Next dates">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>

            <div class="rate-soldout-actions">
                <button type="button" class="btn-sold-out" disabled><?php echo $unavail_reason; ?></button>
                <button type="button" class="btn-check-calendar" onclick="openAvailabilityCalendar(<?php echo xss_attr_json($type_key); ?>, <?php echo xss_attr_json($label); ?>, <?php echo $price; ?>)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Check availability calendar
                </button>
            </div>
            <?php else: ?>
            <div class="rate-soldout-actions">
                <button type="button" class="btn-sold-out" disabled><?php echo $unavail_reason; ?></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
