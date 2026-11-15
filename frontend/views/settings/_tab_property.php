<?php
$settings                  = $pageData['settings'];
$current_property_timezone = $pageData['currentPropertyTimezone'];
$property_timezone_options = $pageData['propertyTimezoneOptions'];
$active_tab                = $pageData['activeTab'];
?>
<div class="tab-panel <?php echo $active_tab === 'property' ? 'active' : ''; ?>" id="tab-property">
    <div class="settings-card">
        <h3>Homepage Highlights</h3>
        <p class="card-desc">The guest rating is calculated automatically from all approved reviews. With no approved reviews, the homepage shows “No reviews yet.” Edit the other two highlights below. Leave either field blank to hide that highlight. Enter 24/7 only if support is available around the clock.</p>
        <form method="POST" class="settings-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_highlights">
            <?php foreach (['stay' => 'Stay', 'support' => 'Support'] as $kind => $label): ?>
                <div class="form-row">
                    <?php foreach (['title' => 'Headline', 'description' => 'Description'] as $part => $field_label):
                        $field_key = 'hero_' . $kind . '_' . $part;
                    ?>
                        <div>
                            <label for="<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label . ' ' . $field_label, ENT_QUOTES, 'UTF-8'); ?></label>
                            <input id="<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>" type="text" maxlength="60" value="<?php echo htmlspecialchars($settings[$field_key] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <div class="form-actions"><button type="submit" class="btn-save">Save Homepage Highlights</button></div>
        </form>
    </div>

    <div class="settings-card">
        <h3>Property Information</h3>
        <p class="card-desc">These details appear on receipts, emails, and throughout the dashboard.</p>

        <form method="POST" class="settings-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_property">

            <p class="subsection-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                Venue Details
            </p>

            <div class="form-row single">
                <div>
                    <label>Property Name</label>
                    <input type="text" name="property_name" value="<?php echo htmlspecialchars($settings['property_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Santa Fe Beach Club">
                </div>
            </div>
            <div class="form-row single">
                <div>
                    <label>Address</label>
                    <input type="text" name="property_address" value="<?php echo htmlspecialchars($settings['property_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Full property address">
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label>Phone</label>
                    <input type="text" name="property_phone" value="<?php echo htmlspecialchars($settings['property_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="+63 32 000 0000">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="property_email" value="<?php echo htmlspecialchars($settings['property_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="info@yourproperty.com">
                </div>
            </div>

            <hr class="section-divider">

            <p class="subsection-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Default Times, Timezone &amp; Currency
            </p>

            <div class="form-row">
                <div>
                    <label>Default Check-in Time</label>
                    <input type="time" name="checkin_time" value="<?php echo htmlspecialchars($settings['checkin_time'] ?? '14:00', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label>Default Check-out Time</label>
                    <input type="time" name="checkout_time" value="<?php echo htmlspecialchars($settings['checkout_time'] ?? '12:00', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="form-row single">
                <div>
                    <label>Business Timezone</label>
                    <select name="property_timezone">
                        <?php foreach ($property_timezone_options as $timezone_value => $timezone_label): ?>
                            <option value="<?php echo htmlspecialchars($timezone_value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($current_property_timezone === $timezone_value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($timezone_label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display:block; margin-top:6px; color:#6B7280; font-size:12px;">Check-out due notifications use this timezone.</small>
                </div>
            </div>
            <div class="form-row single">
                <div>
                    <label>Currency</label>
                    <select name="currency">
                        <?php
                        $currencies = ['PHP' => 'PHP – Philippine Peso', 'USD' => 'USD – US Dollar', 'EUR' => 'EUR – Euro', 'SGD' => 'SGD – Singapore Dollar'];
                        $current_currency = $settings['currency'] ?? 'PHP';
                        foreach ($currencies as $code => $label):
                        ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($current_currency === $code) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">Save Property Settings</button>
            </div>
        </form>
    </div>

    <div class="settings-card">
        <h3>Manual Payment Instructions</h3>
        <p class="card-desc">Only configure accounts authorized to receive hotel payments. A method stays hidden until all required fields are supplied.</p>
        <form method="POST" class="settings-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_payment_instructions">
            <div class="form-row">
                <div>
                    <label for="gcash_number">GCash Number</label>
                    <input id="gcash_number" name="gcash_number" type="text" maxlength="150" value="<?php echo htmlspecialchars($settings['gcash_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter GCash number">
                </div>
                <div>
                    <label for="gcash_name">GCash Account Name</label>
                    <input id="gcash_name" name="gcash_name" type="text" maxlength="150" value="<?php echo htmlspecialchars($settings['gcash_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter GCash account name">
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="discount_bank_name">Bank Name</label>
                    <input id="discount_bank_name" name="discount_bank_name" type="text" maxlength="150" value="<?php echo htmlspecialchars($settings['discount_bank_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter bank name">
                </div>
                <div>
                    <label for="discount_bank_account">Bank Account Number</label>
                    <input id="discount_bank_account" name="discount_bank_account" type="text" maxlength="150" value="<?php echo htmlspecialchars($settings['discount_bank_account'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter bank account number">
                </div>
            </div>
            <div class="form-row single">
                <div>
                    <label for="discount_bank_holder">Bank Account Holder</label>
                    <input id="discount_bank_holder" name="discount_bank_holder" type="text" maxlength="150" value="<?php echo htmlspecialchars($settings['discount_bank_holder'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter bank account holder">
                </div>
            </div>
            <div class="form-actions"><button type="submit" class="btn-save">Save Payment Instructions</button></div>
        </form>
    </div>

    <div class="settings-card">
        <h3>Refund Method Availability</h3>
        <p class="card-desc">Temporarily disable a channel when its provider or manual process has an error. Existing requests remain recorded.</p>
        <form method="POST" action="?tab=property" class="settings-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_refund_methods">
            <input type="hidden" name="refund_paymongo_enabled" value="0">
            <input type="hidden" name="refund_manual_gcash_enabled" value="0">
            <div class="form-row">
                <label style="display:flex;align-items:center;gap:9px;font-weight:700;cursor:pointer">
                    <input type="checkbox" name="refund_paymongo_enabled" value="1" <?php echo ($settings['refund_paymongo_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    PayMongo refunds
                </label>
                <label style="display:flex;align-items:center;gap:9px;font-weight:700;cursor:pointer">
                    <input type="checkbox" name="refund_manual_gcash_enabled" value="1" <?php echo ($settings['refund_manual_gcash_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    Manual GCash
                </label>
            </div>
            <div class="form-actions"><button type="submit" class="btn-save">Save Availability</button></div>
        </form>
    </div>
</div>
