<div class="admin-body">
    <!-- METRICS ROW (6 KPIs) -->
    <section class="metrics-row">
        <div class="metric-card">
            <div class="metric-info">
                <h3>Today's Check-ins</h3>
                <div class="metric-number" id="arrivalsCount"><?php echo (int)$dashboardData['checkinsToday']; ?></div>
            </div>
            <div class="metric-icon-wrapper brown">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Today's Check-outs</h3>
                <div class="metric-number"><?php echo (int)$dashboardData['checkoutsToday']; ?></div>
            </div>
            <div class="metric-icon-wrapper" style="background:#F1F5F9; color:#475569;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Pending RSVPs</h3>
                <div class="metric-number" style="color:var(--orange);"><?php echo (int)$dashboardData['pendingRsvps']; ?></div>
            </div>
            <div class="metric-icon-wrapper orange">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Occupied Units</h3>
                <div class="metric-number" id="occupiedRoomsCount" style="color:var(--blue);"><?php echo (int)$dashboardData['occupiedRooms']; ?></div>
            </div>
            <div class="metric-icon-wrapper blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><circle cx="8" cy="14" r="2"/><circle cx="16" cy="14" r="2"/></svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Ready / Available</h3>
                <div class="metric-number" id="availableRoomsCount" style="color:var(--green);"><?php echo (int)$dashboardData['availableRooms']; ?></div>
            </div>
            <div class="metric-icon-wrapper green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>In-House Guests</h3>
                <div class="metric-number"><?php echo (int)$dashboardData['totalGuests']; ?></div>
            </div>
            <div class="metric-icon-wrapper purple">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
    </section>

    <!-- LOWER GRID: ARRIVALS TABLE + LIVE ROOM MATRIX -->
    <section class="dashboard-grid">
        <!-- Today's Arrivals -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2>Expected Arrivals Today</h2>
                    <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Pending arrivals scheduled for today</p>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <select id="arrivalTypeFilter" style="padding:6px 12px; border:1px solid var(--border); border-radius:var(--radius-sm); font-size:12.5px; background:var(--input-bg); color:var(--text-main);">
                        <option value="">All Guest Tiers</option>
                        <option value="First Visit">First Visit</option>
                        <option value="Returning Guest">Returning Guest</option>
                        <option value="VIP Member">VIP Member</option>
                        <option value="Corporate">Corporate</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="arrivals-table" id="arrivalsTable">
                    <thead>
                        <tr>
                            <th>Guest Details</th>
                            <th>Accommodation</th>
                            <th>ETA</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($dashboardData['arrivals'])): ?>
                            <?php foreach ($dashboardData['arrivals'] as $idx => $row):
                                $rowId = 'row-' . (int)$row['id'];
                                $nameRaw = (string)$row['guest_name'];
                                $typeRaw = (string)$row['guest_type'];
                                $accommodationRaw = (string)$row['accommodation_name'];
                                $eta = htmlspecialchars((string)($row['eta'] ?? '14:00'));
                                $name = htmlspecialchars($nameRaw);
                                $type = htmlspecialchars($typeRaw);
                                $accommodation = htmlspecialchars($accommodationRaw);
                                $phone = htmlspecialchars((string)($row['guest_phone'] ?? ''));
                                $email = htmlspecialchars((string)($row['guest_email'] ?? ''));
                                $refId = 'REF-' . str_pad((string)$row['id'], 4, '0', STR_PAD_LEFT);
                                $roomNo = htmlspecialchars((string)($row['room_number'] ?? ''));

                                $initials = '';
                                foreach (explode(' ', $nameRaw) as $part) {
                                    $initials .= strtoupper($part[0] ?? '');
                                }
                                $initials = htmlspecialchars(substr($initials, 0, 2));
                                $btnClass = ($idx === 0) ? 'primary' : 'secondary';
                                $searchText = strtolower(htmlspecialchars("{$nameRaw} {$phone} {$email} {$refId} {$accommodationRaw} {$roomNo}", ENT_QUOTES));
                            ?>
                                <tr id="<?php echo htmlspecialchars($rowId); ?>" data-guest-type="<?php echo htmlspecialchars($typeRaw, ENT_QUOTES); ?>" data-search-text="<?php echo $searchText; ?>">
                                    <td>
                                        <div class="guest-profile">
                                            <div class="avatar-letter"><?php echo $initials; ?></div>
                                            <div class="guest-info">
                                                <h4><?php echo $name; ?></h4>
                                                <p><?php echo $type; ?> &bull; <span style="font-family:monospace; color:var(--primary);"><?php echo $refId; ?></span></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color:var(--text-muted); font-size:13px;">
                                        <strong><?php echo $accommodation; ?></strong><?php echo $roomNo ? " <span style='color:var(--text-main); font-weight:700;'>(Room {$roomNo})</span>" : ''; ?>
                                    </td>
                                    <td class="eta-cell" style="font-weight:600; color:var(--text-main);"><?php echo $eta; ?></td>
                                    <td style="text-align: right;">
                                        <button type="button" class="btn-table-action <?php echo $btnClass; ?>" data-action="checkin-guest" data-row-id="<?php echo htmlspecialchars($rowId, ENT_QUOTES); ?>">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Express Check-in
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 36px 20px;">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4; margin-bottom:8px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                                    <p>No pending arrivals scheduled for today</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Live Accommodation Status Grid -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2>Live Unit Availability</h2>
                    <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Click any room for quick actions</p>
                </div>
                <a href="admin_calendar" class="btn-view-all">Full Matrix &rarr;</a>
            </div>

            <div class="room-grid" id="roomStatusGrid">
                <?php foreach ($dashboardData['roomStatusRows'] as $room):
                    $num = htmlspecialchars((string)$room['room_number']);
                    $status = htmlspecialchars((string)$room['resolved_status']);
                    $roomJson = htmlspecialchars(json_encode($room), ENT_QUOTES, 'UTF-8');
                ?>
                    <div class="room-block <?php echo $status; ?>" data-room="<?php echo $roomJson; ?>" data-status="<?php echo $status; ?>" data-action="open-room-detail" title="Room <?php echo $num; ?> • <?php echo ucfirst($status); ?>"><?php echo $num; ?></div>
                <?php endforeach; ?>
            </div>

            <div class="legend-container">
                <div class="legend-item">
                    <span class="legend-dot ready"></span>
                    Ready (Available)
                </div>
                <div class="legend-item">
                    <span class="legend-dot occupied"></span>
                    Occupied (In-House)
                </div>
                <div class="legend-item">
                    <span class="legend-dot reserved"></span>
                    Reserved (Booked)
                </div>
                <div class="legend-item">
                    <span class="legend-dot maintenance"></span>
                    Turnover / Maintenance
                </div>
            </div>
        </div>
    </section>
</div>
