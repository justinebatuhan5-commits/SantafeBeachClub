<section class="dashboard-grid" style="grid-template-columns: 1fr;">
    <?php if (isset($_GET['success'])): ?>
        <?php if ($_GET['success'] === 'walkin'): ?>
            <div class="alert-success" style="display:flex; align-items:center; gap:8px;">
                <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
                <span>Walk-in guest successfully registered and checked in! Room is now marked occupied.</span>
            </div>
        <?php else: ?>
            <div class="alert-success">Guest successfully checked in!</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['cancelled'])): ?>
        <div class="alert-warning" style="display:flex; align-items:center; gap:8px;">
            <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
            <span>Reservation #<?php echo htmlspecialchars($_GET['bid'] ?? ''); ?> has been marked as <strong>Cancelled / No-Show</strong>. Room availability has been released.</span>
            <?php if (($_SESSION['admin_role'] ?? '') === 'receptionist'
                && !empty($_SESSION['noshow_refund_handoff'])
                && $_SESSION['noshow_refund_handoff']['booking_id'] === (int)($_GET['bid'] ?? 0)): ?>
                <a href="admin_refunds?noshow_handoff=1">Open refund request</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($specific_booking): ?>
        <?php
        $spec_id = htmlspecialchars((string)$specific_booking['id']);
        $spec_name = htmlspecialchars((string)$specific_booking['guest_name']);
        $spec_acc = htmlspecialchars((string)$specific_booking['accommodation_name']);
        $spec_total = floatval($specific_booking['total_cost']);
        $spec_paid = floatval($specific_booking['amount_paid']);
        $spec_balance = max(0, $spec_total - $spec_paid);
        $spec_checkin = date('Y-m-d', strtotime((string)$specific_booking['check_in']));
        $spec_discount_category = in_array($specific_booking['discount_category'] ?? '', ['senior', 'pwd'], true)
            ? (string)$specific_booking['discount_category']
            : '';
        ?>
        <div class="specific-checkin-overlay">
            <div class="specific-checkin-card">
                <a href="admin_checkin" class="sc-close-link" title="Close">&times;</a>

                <div class="sc-logo-ring">
                    <img src="assets/logo.jpg" alt="Santa Fe Beach Club" style="width: 100%; height: 100%; object-fit: cover;">
                </div>

                <h2 style="margin: 0 0 6px; font-size: 21px; font-weight: 800; color: #0F172A; letter-spacing: -0.01em;">Guest Scanned!</h2>
                <p style="margin: 0 0 20px; color: #64748B; font-size: 13.5px;">Reservation identified and verified successfully.</p>

                <div style="background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 14px; padding: 14px 16px; margin-bottom: 18px; text-align: left;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; margin-bottom: 10px; border-bottom: 1px dashed #E2E8F0;">
                        <span style="font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Guest</span>
                        <strong style="font-size: 14px; color: #0F172A; font-weight: 800;"><?php echo $spec_name; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; margin-bottom: 10px; border-bottom: 1px dashed #E2E8F0;">
                        <span style="font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Room</span>
                        <strong style="font-size: 13.5px; color: #0F172A; font-weight: 700;"><?php echo $spec_acc; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Booking Ref</span>
                        <span style="background: #EFF6FF; color: #1D4ED8; font-weight: 800; font-size: 12px; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.5px; font-family: monospace;"><?php echo htmlspecialchars($ref); ?></span>
                    </div>
                    <?php if ($spec_discount_category): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; padding-top:10px; border-top:1px dashed #E2E8F0;">
                        <span style="font-size:12px; color:#64748B; font-weight:700; text-transform:uppercase; letter-spacing:.5px;">Discount</span>
                        <span style="background:#F3E8FF; color:#7E22CE; font-weight:800; font-size:11px; padding:4px 9px; border-radius:999px;"><?php echo $spec_discount_category === 'senior' ? 'SENIOR CITIZEN · 20%' : 'PWD · 20%'; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($spec_balance > 0): ?>
                    <div style="background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); border: 1.5px solid #FDBA74; border-radius: 14px; padding: 14px 16px; margin-bottom: 22px; text-align: left; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 11px; font-weight: 800; color: #9A3412; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Balance Due</div>
                            <div style="font-size: 12px; color: #C2410C; font-weight: 500;">Collect before room turnover</div>
                        </div>
                        <div style="font-size: 20px; font-weight: 900; color: #C2410C; letter-spacing: -0.02em;">&#8369;<?php echo number_format($spec_balance, 2); ?></div>
                    </div>
                <?php else: ?>
                    <div style="background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%); border: 1.5px solid #86EFAC; border-radius: 14px; padding: 14px 16px; margin-bottom: 22px; text-align: left; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <div style="font-size: 11px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Payment Status</div>
                            <div style="font-size: 12px; color: #15803D; font-weight: 500;">All charges fully settled</div>
                        </div>
                        <span style="background: #15803D; color: #fff; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.5px;">PAID IN FULL</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="admin_checkin" style="margin: 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="checkin">
                    <input type="hidden" name="booking_id" value="<?php echo $spec_id; ?>">
                    <button
                        type="submit"
                        class="sc-confirm-btn js-checkin-btn"
                        data-id="<?php echo $spec_id; ?>"
                        data-guest="<?php echo htmlspecialchars((string)$specific_booking['guest_name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-room="<?php echo htmlspecialchars((string)$specific_booking['accommodation_name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-total="<?php echo htmlspecialchars((string)$spec_total, ENT_QUOTES, 'UTF-8'); ?>"
                        data-balance="<?php echo htmlspecialchars((string)$spec_balance, ENT_QUOTES, 'UTF-8'); ?>"
                        data-discount="<?php echo htmlspecialchars($spec_discount_category, ENT_QUOTES, 'UTF-8'); ?>"
                        data-checkin="<?php echo htmlspecialchars($spec_checkin, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Check In Guest Now
                    </button>
                </form>
            </div>
        </div>
    <?php elseif (!empty($ref)): ?>
        <div class="alert-success" style="background-color: #FFEBEE; color: #C62828;">
            Invalid or already processed QR code token.
        </div>
    <?php endif; ?>

    <div class="card" style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-top:20px;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px; border-bottom:1px solid #F3F4F6; padding-bottom:16px;">
            <div>
                <h2 style="font-size:20px; font-weight:800; color:#1F2937; margin:0;">Pending Arrivals</h2>
                <p style="color:#6B7280; font-size:13px; margin:4px 0 0;"><?php echo count($available_rooms); ?> room(s) currently ready for walk-in guests</p>
            </div>
            <button type="button" class="btn-walkin-primary" data-action="open-walkin">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                <span>Express Walk-in Check-in</span>
            </button>
        </div>

        <table class="reservations-table">
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>Stay Details</th>
                    <th>Balance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pending_bookings)): ?>
                    <?php
                    $today_date = date('Y-m-d');
                    foreach ($pending_bookings as $row):
                        $id = htmlspecialchars((string)$row['id']);
                        $name = htmlspecialchars((string)$row['guest_name']);
                        $email = htmlspecialchars((string)$row['guest_email']);
                        $type = htmlspecialchars((string)$row['guest_type']);
                        $accommodation = htmlspecialchars((string)$row['accommodation_name']);
                        $total_cost = floatval($row['total_cost']);
                        $amount_paid = floatval($row['amount_paid']);
                        $balance_due = max(0, $total_cost - $amount_paid);
                        $discount_category = in_array($row['discount_category'] ?? '', ['senior', 'pwd'], true)
                            ? (string)$row['discount_category']
                            : '';
                        $checkin_date_only = date('Y-m-d', strtotime((string)$row['check_in']));
                        $is_overdue = ($checkin_date_only < $today_date);
                        $is_today = ($checkin_date_only === $today_date);
                    ?>
                    <tr>
                        <td>
                            <div class="arrival-guest-name"><strong><?php echo $name; ?></strong><span class="arrival-booking-id">#<?php echo $id; ?></span></div>
                            <span class="arrival-email"><?php echo $email; ?></span>
                            <div class="arrival-meta-row">
                                <span class="arrival-meta-pill"><?php echo $type; ?></span>
                                <?php if ($discount_category): ?>
                                    <span class="arrival-meta-pill discount"><?php echo $discount_category === 'senior' ? 'SENIOR · 20%' : 'PWD · 20%'; ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="arrival-room"><?php echo $accommodation; ?></div>
                            <div class="arrival-date"><?php echo date('M j, Y', strtotime((string)$row['check_in'])); ?></div>
                            <?php if (!empty($row['eta']) && preg_match('/^([01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', (string)$row['eta'])): ?>
                                <div class="arrival-time">Expected arrival: <?php echo date('g:i A', strtotime((string)$row['eta'])); ?></div>
                            <?php else: ?>
                                <div class="arrival-time">Arrival time not provided</div>
                            <?php endif; ?>
                            <?php if ($is_overdue): ?>
                                <?php $days_late = (int)ceil((strtotime($today_date) - strtotime($checkin_date_only)) / 86400); ?>
                                <span class="badge-overdue" title="Check-in date has passed by <?php echo $days_late; ?> day(s)">Overdue (<?php echo $days_late; ?>d)</span>
                            <?php elseif ($is_today): ?>
                                <span class="badge-today">Arriving Today</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($balance_due > 0):
                                $balanceState = htmlspecialchars((string)($row['balance_status'] ?? 'balance_pending'));
                                $deadlineText = !empty($row['balance_due_at']) ? date('M j, g:i A', strtotime((string)$row['balance_due_at'])) : 'Not set';
                                $stateColor = $balanceState === 'cancellation_review' ? '#991B1B' : ($balanceState === 'overdue' ? '#B45309' : '#E65100');
                            ?>
                                <div class="arrival-balance">
                                    <span class="arrival-balance-amount" style="color:<?php echo $stateColor; ?>;">&#8369;<?php echo number_format($balance_due, 2); ?> due</span>
                                    <span class="arrival-balance-state" style="color:<?php echo $stateColor; ?>;"><?php echo str_replace('_', ' ', $balanceState); ?></span>
                                    <span class="arrival-balance-deadline">Deadline: <?php echo $deadlineText; ?></span>
                                </div>
                            <?php else: ?>
                                <div class="arrival-balance paid">
                                    <span class="arrival-balance-amount" style="color:#166534;">Paid in full</span>
                                    <span class="arrival-balance-state" style="color:#15803D;">Ready for check-in</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="arrival-actions">
                                <form method="POST" action="admin_checkin" style="display:inline; margin:0;">
                                    <input type="hidden" name="action" value="checkin">
                                    <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
                                    <button
                                        type="submit"
                                        class="btn-checkin js-checkin-btn"
                                        data-id="<?php echo $id; ?>"
                                        data-guest="<?php echo htmlspecialchars((string)$row['guest_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-room="<?php echo htmlspecialchars((string)$row['accommodation_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-total="<?php echo htmlspecialchars((string)$total_cost, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-balance="<?php echo htmlspecialchars((string)$balance_due, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-discount="<?php echo htmlspecialchars($discount_category, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-checkin="<?php echo htmlspecialchars($checkin_date_only, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        Check-in
                                    </button>
                                </form>
                                <button
                                    type="button"
                                    class="btn-noshow js-noshow-btn"
                                    data-id="<?php echo $id; ?>"
                                    data-guest="<?php echo htmlspecialchars((string)$row['guest_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-room="<?php echo htmlspecialchars((string)$row['accommodation_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-checkin="<?php echo htmlspecialchars($checkin_date_only, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Mark this booking as No-Show / Cancelled"
                                >
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                                    No-Show
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; color: #888; padding: 30px 20px;">No pending arrivals</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
