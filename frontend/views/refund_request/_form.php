    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Request Refund</h1>
                <p>Submit a refund request for your booking</p>
            </div>

            <div class="body">
                <?php if ($success_message): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
                    <div class="success-message">
                        <div class="success-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                        <h2>Request Submitted</h2>
                        <p>Thank you for submitting your refund request. Our management team will review your request and contact you within 24 hours with a decision.</p>
                        <div class="reference-box">
                            <div class="label">Booking Reference</div>
                            <div class="value"><?php echo htmlspecialchars($submitted_ref ?? ''); ?></div>
                        </div>
                        <p style="color: #94a3b8; font-size: 13px;">
                            We've notified our management team. You'll receive a decision within 24 hours.
                        </p>
                        <a href="/" class="btn btn-primary" style="margin-top: 16px;">Back to Home</a>
                    </div>
                <?php elseif ($error_message): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
                    <div style="text-align: center; padding: 20px 0;">
                        <a href="/" class="btn btn-secondary" style="max-width: 200px;">Back to Home</a>
                    </div>
                <?php elseif ($booking): ?>
                    <form method="POST">
                        <!-- Booking Info -->
                        <div class="info-box">
                            <div class="info-row">
                                <span class="info-label">Accommodation</span>
                                <span class="info-value"><?php echo htmlspecialchars((string)($booking['accommodation_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Check-in Date</span>
                                <span class="info-value"><?php echo date('M j, Y', strtotime((string)($booking['check_in'] ?? 'now'))); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Check-out Date</span>
                                <span class="info-value"><?php echo date('M j, Y', strtotime((string)($booking['check_out'] ?? 'now'))); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Booking Reference</span>
                                <span class="info-value" style="font-family: 'Courier New', monospace;">REF-<?php echo str_pad((string)($booking['id'] ?? ''), 3, '0', STR_PAD_LEFT); ?></span>
                            </div>
                        </div>

                        <!-- Policy Info -->
                        <div class="policy-info">
                            <strong>Refund Policy:</strong> Your refund amount is based on our cancellation policy and when the refund is requested relative to your check-in date.
                        </div>

                        <!-- Refund Reason -->
                        <div class="form-group">
                            <label for="reason">Why are you requesting a refund? *</label>
                            <textarea
                                id="reason"
                                name="reason"
                                placeholder="Please tell us the reason for your refund request. This helps us understand your needs and improve our service..."
                                required></textarea>
                            <div class="char-count">
                                <span id="charCount">0</span> / 500 characters
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="preferredRefundMethod">Preferred refund method</label>
                            <select id="preferredRefundMethod" name="preferred_refund_method" style="width:100%;padding:12px;border:1px solid #d7dde7;border-radius:8px;font-size:14px;background:#fff" <?php echo !$hasAvailableRefundMethod ? 'disabled' : ''; ?>>
                                <option value="original_source" <?php echo !$originalRefundAvailable ? 'disabled' : ''; ?>>Return to original payment method<?php echo !$originalRefundAvailable ? ' (temporarily unavailable)' : ''; ?></option>
                                <?php if ($refund_preference_ready && $manualGcashAllowed): ?>
                                <option value="manual_gcash" <?php echo !$manualRefundAvailable ? 'disabled' : ''; ?> <?php echo !$originalRefundAvailable && $manualRefundAvailable ? 'selected' : ''; ?>>Receive through GCash<?php echo !$manualRefundAvailable ? ' (temporarily unavailable)' : ''; ?></option>
                                <?php endif; ?>
                            </select>
                            <small style="display:block;margin-top:6px;color:<?php echo $hasAvailableRefundMethod ? '#64748b' : '#991b1b'; ?>"><?php echo $hasAvailableRefundMethod ? 'Your selection is a preference. Management makes the final choice based on the original payment channel.' : 'No eligible refund method is currently available. Please contact the resort.'; ?></small>
                        </div>

                        <!-- Buttons -->
                        <div class="button-group">
                            <a href="/" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="submit_refund_request" class="btn btn-primary" <?php echo !$hasAvailableRefundMethod ? 'disabled title="Refund requests are temporarily unavailable"' : ''; ?>>
                                Submit Request
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert error">
                        <?php echo htmlspecialchars((string)($error_message ?: 'Unable to load booking information. Please check your booking confirmation email for the correct link.'), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div style="text-align: center; padding: 20px 0;">
                        <a href="/" class="btn btn-secondary" style="max-width: 200px;">Back to Home</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
