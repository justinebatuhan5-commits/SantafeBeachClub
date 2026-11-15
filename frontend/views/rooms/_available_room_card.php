<?php
/**
 * Renders one available room type card (shared between Recommended and Other Available tabs).
 * Expects $type_key and $type_rooms in scope alongside room catalog maps from rooms_controller.php.
 */
$label         = $type_labels[$type_key]           ?? ucfirst(str_replace('_', ' ', $type_key));
$gradient      = $type_gradients[$type_key]        ?? 'linear-gradient(135deg,#374151,#6b7280)';
$desc          = $type_descriptions[$type_key]     ?? '';
$long_desc     = $type_long_descriptions[$type_key] ?? $desc;
$bed           = $type_beds[$type_key]             ?? '1 bed';
$photo         = $type_photos[$type_key]           ?? 'assets/hero-slide-4-hd.png';
$modal_photos  = $type_modal_photos[$type_key]     ?? [$photo];
$amenities     = $type_amenities[$type_key]        ?? [];
$has_breakfast = !empty($breakfast_included_types[$type_key]);
$extra_rate    = $extra_person_rates[$type_key]    ?? 0;
$avail_count   = count($type_rooms);
$sample_room   = $type_rooms[0];
$price         = $sample_room['price_per_night'];
$capacity      = $sample_room['capacity'];
$type_id       = 'room-' . str_replace('_', '-', $type_key);
$book_url      = 'book?' . http_build_query([
    'checkin'   => $filter_checkin,
    'checkout'  => $filter_checkout,
    'guests'    => max(1, $filter_guests),
    'room_type' => $type_key,
]);
?>
<div class="room-type-section" id="<?php echo $type_id; ?>">
    <div class="rts-header">
        <h3 class="rts-title"><?php echo htmlspecialchars($label); ?></h3>
        <div class="rts-alerts">
            <?php if ($avail_count === 1): ?>
            <span class="alert-urgency">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                Only 1 left
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="rate-card">
        <div class="rate-card-left">
            <div class="rate-photo rate-photo-clickable" role="button" tabindex="0" aria-label="View photos for <?php echo htmlspecialchars($label, ENT_QUOTES); ?>" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openRoomModal(this); }"
                 style="background-color: #e7dfd4; background-image: url('<?php echo htmlspecialchars($photo); ?>'); cursor: pointer;"
                 data-room-name="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
                 data-room-bed="<?php echo htmlspecialchars($bed, ENT_QUOTES); ?>"
                 data-room-desc="<?php echo htmlspecialchars($long_desc, ENT_QUOTES); ?>"
                 data-room-photos="<?php echo htmlspecialchars(json_encode($modal_photos), ENT_QUOTES); ?>"
                 data-room-amenities="<?php echo htmlspecialchars(json_encode($amenities), ENT_QUOTES); ?>"
                 onclick="openRoomModal(this)"
                 title="Click to view photos & amenities">
                <div class="rate-photo-overlay">
                    <span class="rate-photo-badge">Explore this room</span>
                    <div class="rate-photo-copy">
                        <span class="rate-photo-kicker"><?php echo htmlspecialchars($label); ?></span>
                        <strong class="rate-photo-title"><?php echo $capacity; ?> guests &middot; <?php echo htmlspecialchars($bed); ?></strong>
                    </div>
                </div>
                <span class="rate-photo-count">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <?php echo count($modal_photos); ?>
                </span>
            </div>
            <div class="rate-info">
                <span class="rate-details-label">Your room at a glance</span>
                <dl class="rate-comparison">
                    <div><dt>Capacity</dt><dd>Up to <?php echo (int)$capacity; ?> guests</dd></div>
                    <div><dt>Sleeping arrangements</dt><dd><?php echo htmlspecialchars($bed); ?></dd></div>
                    <div><dt>Breakfast</dt><dd><?php echo $has_breakfast ? 'Included' : 'Not included'; ?></dd></div>
                    <div><dt>Extra person</dt><dd>&#8369;<?php echo number_format($extra_rate); ?></dd></div>
                </dl>
                <div class="rate-bed">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><line x1="12" y1="10" x2="12" y2="20"/></svg>
                    <?php echo htmlspecialchars($bed); ?>
                </div>
                <p class="rate-desc"><?php echo htmlspecialchars($desc); ?></p>
                <?php if (!empty($amenities)): ?>
                <div class="rate-amenity-chips">
                    <?php foreach (array_slice($amenities, 0, 4) as $chip): ?>
                    <span class="rate-amenity-chip"><?php echo htmlspecialchars($chip); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <button
                    class="btn-view-room-info"
                    type="button"
                    data-room-name="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
                    data-room-bed="<?php echo htmlspecialchars($bed, ENT_QUOTES); ?>"
                    data-room-desc="<?php echo htmlspecialchars($long_desc, ENT_QUOTES); ?>"
                    data-room-photos="<?php echo htmlspecialchars(json_encode($modal_photos), ENT_QUOTES); ?>"
                    data-room-amenities="<?php echo htmlspecialchars(json_encode($amenities), ENT_QUOTES); ?>"
                    onclick="openRoomModal(this)">
                    View Room Details &amp; Amenities
                </button>
            </div>
        </div>

        <div class="rate-card-right">
            <div class="rate-name-wrap">
                <span class="rate-plan-tag">Best Flexible Rate</span>
                <h4 class="rate-name"><?php echo htmlspecialchars($label); ?></h4>
            </div>
            <div class="rate-features">
                <div class="rate-feature">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1F6E4A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Flexible cancellation
                </div>
                <div class="rate-feature">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9A5518" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2h18l-2 7H5L3 2z"/><path d="M5 9l-1 11h16l-1-11"/><line x1="12" y1="13" x2="12" y2="18"/><line x1="9.5" y1="13" x2="9.5" y2="18"/><line x1="14.5" y1="13" x2="14.5" y2="18"/></svg>
                    <?php echo $has_breakfast ? 'Breakfast included' : 'No breakfast included'; ?>
                </div>
            </div>
            <div class="rate-pricing">
                <div class="rate-from-label">From</div>
                <div class="rate-price-main">&#8369;<?php echo number_format($price, 2); ?></div>
                <div class="rate-price-sub">per night &middot; includes up to <?php echo (int)$capacity; ?> adults</div>
                <div class="rate-price-note">All taxes &amp; fees included</div>
            </div>
            <div class="rate-actions-box">
                <div class="rate-selector-row">
                    <select class="rate-room-select"
                            data-type="<?php echo htmlspecialchars($type_key); ?>"
                            data-label="<?php echo htmlspecialchars($label); ?>"
                            data-price="<?php echo $price; ?>"
                            data-extra-price="<?php echo $extra_rate; ?>"
                            data-nights="<?php echo $nights; ?>"
                            data-base-book-url="<?php echo htmlspecialchars($book_url); ?>"
                            data-book-url="<?php echo htmlspecialchars($book_url); ?>"
                            onchange="roomSelectChanged(this)">
                        <option value="">Choose a room</option>
                        <?php foreach ($rooms_by_type[$type_key] as $room_unit):
                            $unit_avail = (int)$room_unit['active_bookings'] === 0 && in_array(strtolower((string)$room_unit['status']), ['ready', 'available'], true);
                            $unit_label = 'Room ' . $room_unit['room_number'];
                            if ($room_unit['status'] === 'maintenance') {
                                $unit_label .= ' - Under Maintenance';
                            } elseif ($room_unit['status'] === 'occupied') {
                                $unit_label .= ' - Occupied';
                            } elseif (!$unit_avail) {
                                $unit_label .= ' - Booked';
                            }
                        ?>
                        <option value="<?php echo $room_unit['id']; ?>"
                                data-room-number="<?php echo htmlspecialchars($room_unit['room_number']); ?>"
                                <?php echo $unit_avail ? '' : 'disabled'; ?>>
                            <?php echo htmlspecialchars($unit_label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="select-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="rate-extra-status" id="extra-status-<?php echo htmlspecialchars($type_key); ?>" style="display:none;"></div>
                <button class="rate-extra-persons" type="button" data-type="<?php echo htmlspecialchars($type_key); ?>" onclick="toggleExtraPersons(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
                    <span class="rate-extra-persons-label">add extra persons (0)</span>
                    <svg class="extra-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
                </button>
                <div class="rate-extra-panel" id="extra-panel-<?php echo htmlspecialchars($type_key); ?>">
                    <p class="rate-extra-note">** Please note that infants 0 - 3 years old stay free when using existing bedding</p>
                    <div class="rate-extra-card">
                        <div class="rate-extra-rows" id="extra-rows-<?php echo htmlspecialchars($type_key); ?>"></div>
                        <div class="rate-extra-breakdown" id="extra-breakdown-<?php echo htmlspecialchars($type_key); ?>"></div>
                        <div class="rate-extra-total">
                            <span>Total</span>
                            <span id="extra-total-<?php echo htmlspecialchars($type_key); ?>">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
