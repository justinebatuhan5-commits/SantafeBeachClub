<!-- ═══ SECURITY STATUS / THREAT ALERT BANNER ═══ -->
<div id="security-alert-banner" style="display:none; margin-bottom:20px; border-radius:12px; padding:14px 18px; align-items:center; justify-content:space-between; gap:12px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
    <div style="display:flex; align-items:center; gap:12px;">
        <div id="sec-banner-icon-box" style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg id="sec-banner-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div>
            <div id="sec-banner-title" style="font-size:14px; font-weight:700;"></div>
            <div id="sec-banner-desc" style="font-size:12.5px; margin-top:2px;"></div>
        </div>
    </div>
    <a href="admin_logs?tab=security" id="sec-banner-link" style="padding:6px 14px; border-radius:8px; font-size:12.5px; font-weight:700; text-decoration:none; white-space:nowrap; transition:all 0.2s ease;">
        Review Threat Logs &rarr;
    </a>
</div>

<!-- ═══ KPI METRICS WITH SPARKLINE TRENDLINES ═══ -->
<div class="stats-grid">
    <div class="stat-card" style="flex-direction:column; padding-bottom:12px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; width:100%;">
            <div>
                <div class="stat-card-label">Daily Revenue</div>
                <div class="stat-card-value" id="kpi-daily-rev">...</div>
            </div>
            <div class="stat-icon green">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </div>
        </div>
    </div>

    <div class="stat-card" style="flex-direction:column; padding-bottom:12px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; width:100%;">
            <div>
                <div class="stat-card-label">Weekly Revenue</div>
                <div class="stat-card-value" id="kpi-weekly-rev">...</div>
            </div>
            <div class="stat-icon brown">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 21V3h7.5a4.5 4.5 0 1 1 0 9H7" />
                    <line x1="4" y1="8" x2="17" y2="8" />
                    <line x1="4" y1="12" x2="17" y2="12" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card" style="flex-direction:column; padding-bottom:12px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; width:100%;">
            <div>
                <div class="stat-card-label">Checked-In Now</div>
                <div class="stat-card-value"><span id="kpi-occupancy-rate">...</span></div>
                <div class="stat-card-sub"><span id="kpi-occupied-rooms">0</span> of <span id="kpi-total-rooms">0</span> rooms checked in</div>
            </div>
            <div class="stat-icon blue">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
        </div>
    </div>

    <div class="stat-card" style="flex-direction:column; padding-bottom:12px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; width:100%;">
            <div>
                <div class="stat-card-label">Total Bookings</div>
                <div class="stat-card-value" id="kpi-total-bookings">...</div>
            </div>
            <div class="stat-icon purple">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
        </div>
    </div>
</div>

<!-- ═══ TODAY'S LIVE ROOM & FRONT DESK OCCUPANCY STRIP ═══ -->
<?php $kpi_extra_class = 'cal-kpi-grid--dashboard'; include __DIR__ . '/../../partials/_occupancy_kpi_cards.php'; ?>

<!-- Actionable counters not already represented above or in Daily Activity -->
<div class="operations-snapshot">
    <div class="stat-card">
        <div>
            <div class="stat-card-label">Reserved Rooms</div>
            <div class="stat-card-value" id="kpi-reserved-rooms">...</div>
            <div class="stat-card-sub">Upcoming &amp; active room holds</div>
        </div>
        <div class="stat-icon blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-card-label">Pending Payments</div>
            <div class="stat-card-value" id="kpi-pending-payments">...</div>
            <div class="stat-card-sub" style="color:var(--orange);">Awaiting payment verification</div>
        </div>
        <div class="stat-icon purple">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
    </div>
</div>

<!-- ═══ VISUAL ANALYTICS CHARTS ═══ -->
<div class="charts-grid" style="grid-template-columns: 1fr;">
    <div class="chart-card">
        <div class="chart-card-header">
            <div>
                <h3>Revenue Trajectory (Last 7 Days)</h3>
                <p>Verified daily receipts &amp; guest settlements &bull; Click any day to view details</p>
            </div>
            <a href="admin_reports" class="btn-view-all">Full Report &rarr;</a>
        </div>
        <div class="chart-container" style="height:230px;">
            <div class="loading" id="loading-rev">Loading chart data...</div>
            <canvas id="revenueChart" style="display:none;"></canvas>
        </div>
    </div>
</div>

<!-- ═══ DAILY ACTIVITY SUMMARY ═══ -->
<div class="daily-summary-card">
    <div class="daily-summary-header">
        <div class="daily-summary-header-left">
            <h3>Daily Activity Summary</h3>
            <span class="daily-summary-live-badge">
                <span class="daily-summary-live-dot"></span>
                LIVE
            </span>
        </div>
        <span class="daily-summary-date" id="ds-date">Loading…</span>
    </div>
    <div class="daily-summary-strip">
        <div class="ds-tile">
            <div class="ds-tile-accent" style="background:#84563C;"></div>
            <div class="ds-tile-icon" style="background:rgba(132,86,60,.12);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#84563C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
            </div>
            <div class="ds-tile-value" id="ds-bookings">—</div>
            <div class="ds-tile-label">New Bookings</div>
            <div class="ds-tile-sub">Created today</div>
        </div>
        <div class="ds-tile">
            <div class="ds-tile-accent" style="background:#10B981;"></div>
            <div class="ds-tile-icon" style="background:rgba(16,185,129,.1);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </div>
            <div class="ds-tile-value" id="ds-payments-count">—</div>
            <div class="ds-tile-label">Payments Verified</div>
            <div class="ds-tile-sub" id="ds-payments-amount" style="color:#059669;font-weight:600;">₱0</div>
        </div>
        <div class="ds-tile">
            <div class="ds-tile-accent" style="background:#3B82F6;"></div>
            <div class="ds-tile-icon" style="background:rgba(59,130,246,.1);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </div>
            <div class="ds-tile-value" id="ds-checkins">—</div>
            <div class="ds-tile-label">Check-ins</div>
            <div class="ds-tile-sub">Arrived today</div>
        </div>
        <div class="ds-tile">
            <div class="ds-tile-accent" style="background:#64748B;"></div>
            <div class="ds-tile-icon" style="background:rgba(100,116,139,.1);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </div>
            <div class="ds-tile-value" id="ds-checkouts">—</div>
            <div class="ds-tile-label">Check-outs</div>
            <div class="ds-tile-sub">Departed today</div>
        </div>
        <div class="ds-tile">
            <div class="ds-tile-accent" style="background:#EF4444;"></div>
            <div class="ds-tile-icon" style="background:rgba(239,68,68,.1);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <div class="ds-tile-value" id="ds-cancellations">—</div>
            <div class="ds-tile-label">Cancellations</div>
            <div class="ds-tile-sub">Cancelled today</div>
        </div>
    </div>
    <div class="daily-summary-footer">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Last refreshed: <span id="ds-refreshed">—</span>
    </div>
</div>

<!-- ═══ RECENT BOOKINGS & LIVE AUDIT FEED ═══ -->
<div class="lower-grid">
    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Recent Reservations</h3>
                <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Latest guest booking transactions</p>
            </div>
            <a href="admin_reservations">View All (<span id="link-total-bookings">...</span>) &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Guest</th>
                        <th>Accommodation</th>
                        <th>Check-in</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="recent-bookings-tbody">
                    <tr><td colspan="5" class="loading">Loading reservations...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Live Audit Feed</h3>
                <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Recent administrator and staff activity</p>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <button type="button" data-action="refresh-audit" title="Refresh Live Feed" style="background:none; border:1px solid var(--border); border-radius:6px; padding:4px 8px; font-size:12px; cursor:pointer; color:var(--text-muted); display:inline-flex; align-items:center; gap:4px;">
                    <svg id="audit-refresh-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    Refresh
                </button>
                <a href="admin_logs">Full Log &rarr;</a>
            </div>
        </div>
        <div id="recent-logs-container">
            <div class="loading">Loading activity logs...</div>
        </div>
    </div>
</div>
