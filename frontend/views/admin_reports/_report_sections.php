<div id="api-connection-error" style="display: none; background: #FEF2F2; color: #991B1B; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #F87171; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div><strong>Analytics Error:</strong> Could not load reports data. Please verify your database connection.</div>
</div>

<!-- ═══ 4 KPI CARDS ═══ -->
<div class="report-kpi-grid">
    <div class="kpi-card">
        <div class="kpi-card-top">
            <div class="kpi-circle-icon" style="background:#FFF3E8; color:#84563C;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
            </div>
            <div class="kpi-card-info">
                <div class="kpi-card-title">Total Revenue</div>
                <div class="kpi-card-val-row">
                    <div class="kpi-card-value" id="kpi-revenue">₱0.00</div>
                </div>
                <div class="kpi-card-sub">vs previous 30 days</div>
            </div>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-top">
            <div class="kpi-circle-icon" style="background:#FFF8E7; color:#D97706;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/></svg>
            </div>
            <div class="kpi-card-info">
                <div class="kpi-card-title">Total Bookings</div>
                <div class="kpi-card-val-row">
                    <div class="kpi-card-value" id="kpi-bookings">0</div>
                </div>
                <div class="kpi-card-sub"><span id="kpi-pending" style="color:#D97706; font-weight:700;">0</span> pending confirmation</div>
            </div>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-top">
            <div class="kpi-circle-icon" style="background:#F4EBE3; color:#78350F;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="kpi-card-info">
                <div class="kpi-card-title">Total Guests</div>
                <div class="kpi-card-val-row">
                    <div class="kpi-card-value" id="kpi-guests">0</div>
                </div>
                <div class="kpi-card-sub">across all hosted stays</div>
            </div>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-top">
            <div class="kpi-circle-icon" style="background:#E6F4EA; color:#0D652D;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>
            </div>
            <div class="kpi-card-info">
                <div class="kpi-card-title">Occupancy Rate</div>
                <div class="kpi-card-val-row">
                    <div class="kpi-card-value" id="kpi-occupancy">0%</div>
                </div>
                <div class="kpi-card-sub"><span id="kpi-avg-stay">0</span> avg stay duration</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ ANALYTICS ROW: RESERVATION STATUS & TOP SOURCE MARKETS ═══ -->
<div class="three-col-charts">
    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Reservation Status</h3>
                <p>Bookings outcome distribution</p>
            </div>
        </div>
        <div class="donut-layout" id="chart-status-container">
            <div class="loading" id="loading-status">Loading status...</div>
            <div class="donut-canvas-wrap" id="status-canvas-wrap" style="display:none;">
                <canvas id="statusBreakdownChart"></canvas>
                <div class="donut-center-stat">
                    <div class="num" id="donut-status-total">0</div>
                    <div class="lbl">Total</div>
                </div>
            </div>
            <div class="donut-legend-list" id="status-legend-list"></div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <h3>Top Source Markets</h3>
                    <span id="badge-total-countries" style="background:rgba(132,86,60,0.12); color:#84563C; font-size:11px; font-weight:700; padding:2px 8px; border-radius:99px;">0 Origins</span>
                </div>
                <p>Guest origins by booking volume</p>
            </div>
        </div>
        <div class="ranking-bar-list" id="ranking-bars-container">
            <div class="loading" id="loading-country">Loading origin data...</div>
        </div>
    </div>
</div>

<!-- ═══ REVENUE OVERVIEW ═══ -->
<div class="admin-card" style="margin-bottom: 24px;">
    <div class="admin-card-header">
        <div>
            <h3>Revenue Overview</h3>
            <p>Monthly verified revenue performance</p>
        </div>
    </div>
    <div class="chart-box" id="chart-rev-container" style="height: 250px;">
        <div class="loading" id="loading-rev">Loading revenue chart...</div>
        <canvas id="monthlyRevChart" style="display:none;"></canvas>
    </div>
</div>

<!-- ═══ LOWER SECTION: LEADERBOARDS & TABLES ═══ -->
<div class="two-col">
    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Payment Methods</h3>
                <p>Settlement volume by gateway / method</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Channel / Method</th>
                        <th>Transactions</th>
                        <th style="text-align:right;">Total Collected</th>
                    </tr>
                </thead>
                <tbody id="payment-methods-tbody">
                    <tr><td colspan="3" class="loading">Fetching data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Top Accommodations</h3>
                <p>Most booked units by guest volume</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Unit Name</th>
                        <th>Stays</th>
                        <th style="text-align:right;">Guests Hosted</th>
                    </tr>
                </thead>
                <tbody id="top-rooms-tbody">
                    <tr><td colspan="3" class="loading">Fetching data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Country Origin Leaderboard Full Width Table -->
<div class="admin-card" style="margin-bottom:28px;">
    <div class="admin-card-header">
        <div>
            <h3>Guest Origin &amp; Nationality Leaderboard</h3>
            <p>All recorded origins included; top 10 shown first and additional countries are scrollable</p>
        </div>
    </div>
    <div class="table-responsive country-leaderboard-scroll" id="country-leaderboard-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Country / Nationality</th>
                    <th>Bookings</th>
                    <th>Unique Customers</th>
                    <th>Booked Guests</th>
                    <th>Booking Share (%)</th>
                    <th style="text-align:right;">Verified Revenue</th>
                </tr>
            </thead>
            <tbody id="country-leaderboard-tbody">
                <tr><td colspan="6" class="loading">Fetching country data...</td></tr>
            </tbody>
        </table>
    </div>
</div>
