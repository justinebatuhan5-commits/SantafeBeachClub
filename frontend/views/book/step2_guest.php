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
            <label>Country / Region <span class="req">*</span></label>
            <select name="country" required data-label="Country / Region">
                <option value="">Select country / region</option>
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
            <?php
                $renderEtaOptions = (!empty($eta_options) && is_array($eta_options))
                    ? $eta_options
                    : (function_exists('sf_booking_eta_catalog') ? sf_booking_eta_catalog() : [
                        '14:00' => '02:00 PM (Standard Check-in)',
                        '15:00' => '03:00 PM',
                        '16:00' => '04:00 PM',
                        '17:00' => '05:00 PM',
                        '18:00' => '06:00 PM',
                        '20:00' => '08:00 PM (Late Arrival)',
                        '22:00' => '10:00 PM+ (Late Night Arrival)',
                    ]);
                $renderSelectedEta = (isset($selected_eta) && isset($renderEtaOptions[$selected_eta]))
                    ? (string) $selected_eta
                    : (string) array_key_first($renderEtaOptions);
            ?>
            <select name="eta" required data-label="Estimated Arrival Time">
                <?php foreach ($renderEtaOptions as $etaValue => $etaLabel): ?>
                    <option value="<?php echo htmlspecialchars((string) $etaValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($renderSelectedEta === (string) $etaValue) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $etaLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($is_same_day_checkin)): ?>
                <small style="display:block; margin-top:0.35rem; color:#6b7280; font-size:0.78rem;">Same-day check-in: past arrival times for today are hidden.</small>
            <?php endif; ?>
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
