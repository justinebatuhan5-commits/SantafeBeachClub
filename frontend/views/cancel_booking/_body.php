    <div class="cancel-wrap">
        <div class="cancel-card">
            <div class="cancel-head">
                <h1>Reservation Cancellation</h1>
                <p>Santa Fe Beach Club Self-Service Guest Portal</p>
            </div>
            <div class="cancel-body">

                <?php if ($success_message): ?>
                    <!-- Success View -->
                    <div class="notice success">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <div>
                            <strong><?php echo htmlspecialchars($success_message); ?></strong>
                            <?php if ($email_notice): ?>
                                <div style="margin-top:4px; font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($email_notice); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($booking): ?>
                        <div class="details-grid">
                            <div class="detail"><div class="label">Booking Ref</div><div class="value"><?php echo htmlspecialchars($booking_ref); ?></div></div>
                            <div class="detail"><div class="label">Guest</div><div class="value"><?php echo htmlspecialchars($booking['guest_name']); ?></div></div>
                            <div class="detail"><div class="label">Room</div><div class="value"><?php echo htmlspecialchars($booking['accommodation_name']); ?></div></div>
                            <div class="detail"><div class="label">Status</div><div class="value" style="color:var(--danger);">Cancelled</div></div>
                        </div>

                        <?php if (!empty($booking['cancellation_reason'])): ?>
                            <div style="background:#F8FAFC; border:1px solid var(--border); border-radius:10px; padding:12px 16px; margin-bottom:20px; font-size:13px;">
                                <strong style="color:var(--text-muted); text-transform:uppercase; font-size:11px; display:block; margin-bottom:3px;">Reason Provided</strong>
                                <span><?php echo htmlspecialchars($booking['cancellation_reason']); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p style="font-size:13.5px; color:var(--text-muted); line-height:1.5;">
                        We are sorry to see you cancel your stay. If your schedule clears or you wish to plan another beach getaway, we would be delighted to host you.
                    </p>

                    <div class="btn-row">
                        <a href="book.php" class="btn btn-secondary" style="background:var(--primary); color:white;">Book Another Date</a>
                        <a href="index.php" class="btn btn-secondary">Return to Home</a>
                    </div>

                <?php elseif ($error && !$booking): ?>
                    <!-- Invalid Link Error -->
                    <div class="notice error">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div>
                            <strong>Unable to process cancellation</strong>
                            <div style="margin-top:4px; font-size:13px;"><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    </div>
                    <div class="btn-row">
                        <a href="index.php" class="btn btn-secondary">Return to Home</a>
                    </div>

                <?php elseif ($booking): ?>

                    <!-- Active Booking Details -->
                    <div class="details-grid">
                        <div class="detail"><div class="label">Booking Ref</div><div class="value"><?php echo htmlspecialchars($booking_ref); ?></div></div>
                        <div class="detail"><div class="label">Guest Name</div><div class="value"><?php echo htmlspecialchars($booking['guest_name']); ?></div></div>
                        <div class="detail"><div class="label">Room</div><div class="value"><?php echo htmlspecialchars($booking['accommodation_name']); ?></div></div>
                        <div class="detail"><div class="label">Stay Dates</div><div class="value"><?php echo htmlspecialchars($checkin_fmt); ?> &rarr; <?php echo htmlspecialchars($checkout_fmt); ?></div></div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <!-- Booking Already Cancelled / Checked In Error -->
                        <div class="notice error">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                        <div class="btn-row">
                            <a href="index.php" class="btn btn-secondary">Return to Home</a>
                        </div>

                    <?php elseif ($is_past_deadline): ?>
                        <!-- Past Deadline View -->
                        <div class="notice warning">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div>
                                <strong style="font-size:15px;">Online Cancellation Deadline Has Passed</strong>
                                <p style="margin:6px 0 0; font-size:13px; line-height:1.5;">
                                    Self-service online cancellation for this <?php echo (int)$policy_nights; ?>-night stay closed on <strong><?php echo htmlspecialchars($deadline_str); ?></strong> (<?php echo htmlspecialchars($policy_window_label); ?> prior to arrival).
                                </p>
                            </div>
                        </div>

                        <div class="contact-box">
                            <h4>Need to cancel or reschedule your arrival?</h4>
                            <p>
                                Because your arrival date is coming up soon, please contact our Front Desk team directly. We are happy to assist you with special requests or date changes.
                            </p>
                            <div class="contact-links">
                                <a href="tel:+639505223146" class="contact-btn">Call Front Desk (+63 950 522 3146)</a>
                                <a href="mailto:Justinebatuhan017@gmail.com?subject=Cancellation%20Request%20<?php echo urlencode($booking_ref); ?>" class="contact-btn">Email Front Desk</a>
                            </div>
                        </div>

                        <div class="btn-row" style="margin-top:22px;">
                            <a href="index.php" class="btn btn-secondary">Return to Home</a>
                        </div>

                    <?php else: ?>
                        <!-- Within Cancellation Window: Form -->
                        <div class="policy-badge-box">
                            <div class="policy-badge-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                            <div class="policy-badge-text">
                                <h4>Free Cancellation Open (<?php echo $hours_remaining >= 24 ? $days_remaining . ' days left' : $hours_remaining . ' hrs left'; ?>)</h4>
                                <p>Self-service cancellation for your <?php echo (int)$policy_nights; ?>-night stay closes on <strong><?php echo htmlspecialchars($deadline_str); ?></strong> (<?php echo htmlspecialchars($policy_window_label); ?> before check-in).</p>
                            </div>
                        </div>

                        <?php if (!empty($form_error)): ?>
                            <div class="notice error">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <div><?php echo htmlspecialchars($form_error); ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="cancel_booking" id="cancelForm" data-site-title="Cancel reservation?" data-site-button="Cancel reservation" data-site-confirm="Are you sure you want to cancel this reservation? This cannot be undone.">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="confirm_cancel" value="1">

                            <div class="form-group">
                                <label class="form-label">Please select your reason for cancellation <span class="req">*</span></label>
                                <div class="reason-options">
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Change of travel plans" required>
                                        <span>Change of travel plans / scheduling conflict</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Personal or medical emergency">
                                        <span>Personal or medical emergency</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Transportation / flight issues">
                                        <span>Transportation, ferry, or flight disruption</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Found alternative accommodation">
                                        <span>Found alternative accommodation</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Booked wrong dates by mistake">
                                        <span>Booked wrong dates / accidental reservation</span>
                                    </label>
                                    <label class="reason-item">
                                        <input type="radio" name="reason_choice" value="Other" id="reasonOtherRadio">
                                        <span>Other reason</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Additional details / note <span style="font-weight:400; color:var(--text-muted);">(Optional)</span></label>
                                <textarea name="custom_details" class="form-textarea" placeholder="Help us improve our service with any extra details or feedback..."></textarea>
                            </div>

                            <p style="font-size:12.5px; color:var(--text-muted); margin:18px 0; line-height:1.5; display:flex; align-items:center; gap:6px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span><strong>Note:</strong> Once confirmed, this reservation will be released immediately and a cancellation confirmation will be emailed to your inbox.</span>
                            </p>

                            <div class="btn-row">
                                <button type="submit" class="btn btn-danger">
                                    Confirm Cancellation
                                </button>
                                <a href="index.php" class="btn btn-secondary">Keep Reservation</a>
                            </div>
                        </form>

                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </div>
    </div>
