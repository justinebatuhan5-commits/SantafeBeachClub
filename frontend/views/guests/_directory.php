<div class="admin-body">
    <!-- KPI Stats Row -->
    <div class="guest-stats-grid">
        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">Total Guests</div>
                <div class="guest-stat-value"><?php echo (int)$guestsData['totalGuests']; ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#E8F0FE; color:#1A73E8;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>

        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">VIP Members</div>
                <div class="guest-stat-value"><?php echo (int)$guestsData['vipCount']; ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#FFF3E8; color:#84563C;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
        </div>

        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">Corporate</div>
                <div class="guest-stat-value"><?php echo (int)$guestsData['corporateCount']; ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#E3F2FD; color:#1565C0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
            </div>
        </div>

        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">First Visit</div>
                <div class="guest-stat-value"><?php echo (int)$guestsData['firstVisitCount']; ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#E6F4EA; color:#0D652D;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
        </div>

        <div class="guest-stat-card">
            <div class="guest-stat-info">
                <div class="guest-stat-label">Returning</div>
                <div class="guest-stat-value"><?php echo (int)$guestsData['returningCount']; ?></div>
            </div>
            <div class="guest-stat-icon" style="background:#F3E8FF; color:#7C3AED;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
    </div>

    <!-- Guest Directory Card -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:20px 24px 16px;">
            <div>
                <h2 style="font-size:17px; font-weight:800; color:var(--text-main); margin:0; font-family:var(--font-heading);">Guest Directory</h2>
                <p style="font-size:13px; color:var(--text-muted); margin-top:3px;">
                    <span id="visibleCount"><?php echo (int)$guestsData['totalGuests']; ?></span> guest<?php echo $guestsData['totalGuests'] !== 1 ? 's' : ''; ?> total
                </p>
            </div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <div class="filter-tabs">
                    <button type="button" class="filter-tab active" data-filter="all">All</button>
                    <button type="button" class="filter-tab" data-filter="VIP Member">VIP</button>
                    <button type="button" class="filter-tab" data-filter="Returning Guest">Returning</button>
                    <button type="button" class="filter-tab" data-filter="Corporate">Corporate</button>
                    <button type="button" class="filter-tab" data-filter="First Visit">First Visit</button>
                </div>
                <button type="button" id="exportCsvBtn" class="btn-export">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export CSV
                </button>
            </div>
        </div>

        <div style="overflow-x:auto; padding: 0 8px 16px;">
            <table class="guests-table" id="guestTable">
                <thead>
                    <tr>
                        <th style="padding-left:24px;">Guest Profile</th>
                        <th>Email</th>
                        <th>Tier</th>
                        <th>Bookings</th>
                        <th>Last Visit</th>
                        <th style="text-align:right; padding-right:24px;">Action</th>
                    </tr>
                </thead>
                <tbody id="guestTableBody">
                    <?php if (empty($guestsData['guestRows'])): ?>
                        <tr class="guest-row">
                            <td colspan="6">
                                <div class="no-results-state">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                    <p>No guests found in the directory.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($guestsData['guestRows'] as $row): ?>
                            <tr class="guest-row"
                                data-type="<?php echo htmlspecialchars($row['type']); ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($row['name'])); ?>"
                                data-email="<?php echo htmlspecialchars(strtolower($row['email'])); ?>">
                                <td>
                                    <div class="guest-profile-small">
                                        <div class="avatar-small" style="background:<?php echo $row['avatarBg']; ?>;color:<?php echo $row['avatarText']; ?>;"><?php echo htmlspecialchars($row['initials']); ?></div>
                                        <span class="guest-name-main"><?php echo htmlspecialchars($row['name']); ?></span>
                                    </div>
                                </td>
                                <td style="color:#555;font-size:14px; font-family:var(--font-mono, monospace);"><?php echo htmlspecialchars($row['maskedEmail']); ?></td>
                                <td><?php echo $row['typeBadge']; ?></td>
                                <td><span class="booking-count-badge"><?php echo (int)$row['total']; ?></span></td>
                                <td style="color:#555;font-size:14px;"><?php echo htmlspecialchars($row['lastVisit']); ?></td>
                                <td>
                                    <button type="button" class="btn-view"
                                        data-action="open-guest-profile"
                                        data-profile="<?php echo htmlspecialchars($row['profileJson'], ENT_QUOTES, 'UTF-8'); ?>">
                                        View Profile
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="noResultsMsg" style="display:none;" class="no-results">No guests match your search.</div>
        </div>
        <div style="height:8px;"></div>
    </div>
</div>
