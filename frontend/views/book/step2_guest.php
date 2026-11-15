<!-- STEP 2: GUEST DETAILS -->
<div class="bk-card">
    <h2 class="bk-card-title">Guest Details</h2>

    <div class="bk-form-grid">
        <div class="bk-form-group">
            <label>First name <span class="req">*</span></label>
            <input type="text" name="first_name" placeholder="First name" required data-validate="name" data-label="First name" style="text-transform: capitalize;" value="<?php echo htmlspecialchars($_SESSION['guest_first_name'] ?? ''); ?>">
        </div>
        <div class="bk-form-group">
            <label>Last name <span class="req">*</span></label>
            <input type="text" name="last_name" placeholder="Last name" required data-validate="name" data-label="Last name" style="text-transform: capitalize;" value="<?php echo htmlspecialchars($_SESSION['guest_last_name'] ?? ''); ?>">
        </div>

        <div class="bk-form-group">
            <label>Email <span class="req">*</span></label>
            <input type="email" name="email" placeholder="Email" required data-validate="email" data-label="Email address" value="<?php echo htmlspecialchars($_SESSION['guest_email'] ?? ''); ?>">
        </div>
        <div class="bk-form-group">
            <label>Retype email <span class="req">*</span></label>
            <input type="email" name="email_confirm" placeholder="Retype email" required data-validate="email" data-label="Email confirmation" value="<?php echo htmlspecialchars($_SESSION['guest_email'] ?? ''); ?>">
        </div>

        <div class="bk-form-group">
            <label>Contact phone <span class="req">*</span></label>
            <div class="bk-input-combo">
                <select name="phone_code" class="bk-select-phone" aria-label="Country Code">
                    <?php
                    $country_calling_codes = sf_get_country_calling_codes();
                    $phone_codes           = sf_get_distinct_calling_codes();
                    $selected_country      = $_SESSION['guest_country'] ?? '';
                    $selected_phone_code   = $_SESSION['guest_phone_code'] ?? ($selected_country && isset($country_calling_codes[$selected_country]) ? $country_calling_codes[$selected_country]['code'] : '+63');
                    foreach ($phone_codes as $item):
                        $sel = ($selected_phone_code === $item['code']) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($item['code']) . "\" data-flag=\"" . htmlspecialchars($item['flag']) . "\" {$sel}>" . htmlspecialchars($item['display']) . "</option>\n";
                    endforeach;
                    ?>
                </select>
                <input type="tel" name="phone" placeholder="<?php echo ($selected_phone_code === '+63') ? '09171234567' : 'e.g. 12345678'; ?>" required data-validate="phone" data-label="Phone number" value="<?php echo htmlspecialchars($_SESSION['guest_phone'] ?? ''); ?>">
            </div>
        </div>
        <div class="bk-form-group">
            <label>Country <span class="req">*</span></label>
            <select name="country" required data-label="Country">
                <option value="">--Select--</option>
                <?php
                foreach (sfbc_get_booking_countries() as $country):
                    $sel  = ($selected_country === $country) ? 'selected' : '';
                    $info = $country_calling_codes[$country] ?? ['code' => '+63', 'flag' => 'PH'];
                    echo "<option value=\"" . htmlspecialchars($country) . "\" data-dial-code=\"" . htmlspecialchars($info['code']) . "\" data-flag=\"" . htmlspecialchars($info['flag']) . "\" {$sel}>" . htmlspecialchars($country) . "</option>\n";
                endforeach;
                ?>
            </select>
        </div>

        <div class="bk-form-group">
            <label>Estimated Arrival Time (ETA) <span class="req">*</span></label>
            <select name="eta" required data-label="Estimated Arrival Time">
                <option value="14:00" <?php echo (($_SESSION['guest_eta'] ?? '14:00') === '14:00') ? 'selected' : ''; ?>>02:00 PM (Standard Check-in)</option>
                <option value="15:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '15:00') ? 'selected' : ''; ?>>03:00 PM</option>
                <option value="16:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '16:00') ? 'selected' : ''; ?>>04:00 PM</option>
                <option value="17:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '17:00') ? 'selected' : ''; ?>>05:00 PM</option>
                <option value="18:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '18:00') ? 'selected' : ''; ?>>06:00 PM</option>
                <option value="20:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '20:00') ? 'selected' : ''; ?>>08:00 PM (Late Arrival)</option>
                <option value="22:00" <?php echo (($_SESSION['guest_eta'] ?? '') === '22:00') ? 'selected' : ''; ?>>10:00 PM+ (Late Night Arrival)</option>
            </select>
        </div>

        <div class="bk-form-group" style="grid-column: 1 / -1;">
            <label>Additional comments</label>
            <textarea name="comments" placeholder="Additional comments" rows="3"><?php echo htmlspecialchars($_SESSION['guest_comments'] ?? ''); ?></textarea>
        </div>
    </div>
</div>
<div class="bk-actions">
    <a href="book?step=1" class="btn-bk-back">&larr; Back</a>
    <input type="hidden" name="action" value="go_step_3">
    <button type="submit" class="btn-bk-next">Next &rarr;</button>
</div>
