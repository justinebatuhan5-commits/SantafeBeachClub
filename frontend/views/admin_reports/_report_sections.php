<div id="api-connection-error" style="display: none; background: #FEF2F2; color: #991B1B; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #F87171; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div><strong>Analytics Error:</strong> Could not load reports data. Please verify your database connection.</div>
</div>

<!-- ═══ GLOBAL DATE RANGE FILTER BAR ═══ -->
<div class="reports-filter-bar" id="reportsDateFilterBar">
    <div class="reports-filter-left">
        <span class="reports-filter-label">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Reporting Window:
        </span>
        <div class="reports-preset-group" role="group" aria-label="Date range presets">
            <button type="button" class="filter-preset-btn active" data-range-preset="all">All Time</button>
            <button type="button" class="filter-preset-btn" data-range-preset="7d">Last 7 Days</button>
            <button type="button" class="filter-preset-btn" data-range-preset="30d">Last 30 Days</button>
            <button type="button" class="filter-preset-btn" data-range-preset="month">This Month</button>
            <button type="button" class="filter-preset-btn" data-range-preset="90d">Last 90 Days</button>
        </div>
    </div>
    <div class="reports-filter-right">
        <div class="reports-custom-dates">
            <input type="date" id="reportStartDate" class="reports-date-input" aria-label="Start date">
            <span class="reports-date-sep">to</span>
            <input type="date" id="reportEndDate" class="reports-date-input" aria-label="End date">
            <button type="button" id="applyCustomDateBtn" class="filter-apply-btn">Apply</button>
        </div>
        <span class="reports-active-pill" id="activeRangeLabel">Showing: All Time</span>
    </div>
</div>

<!-- ═══ 4 EXECUTIVE KPI CARDS ═══ -->
<div class="report-kpi-grid">
    <div class="kpi-card">
        <div class="kpi-card-top">
            <div class="kpi-circle-icon" style="background:#FFF3E8; color:#84563C;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
            </div>
            <div class="kpi-card-info">
                <div class="kpi-card-title">Net Collected Revenue</div>
                <div class="kpi-card-val-row">
                    <div class="kpi-card-value" id="kpi-revenue">₱0.00</div>
                    <span class="kpi-card-badge" id="kpi-revenue-trend"></span>
                </div>
                <div class="kpi-card-sub" id="kpi-revenue-sub">Gross verified less approved refunds</div>
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
                    <span class="kpi-card-badge" id="kpi-bookings-trend"></span>
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
                    <span class="kpi-card-badge" id="kpi-guests-trend"></span>
                </div>
                <div class="kpi-card-sub" id="kpi-guests-sub">across non-cancelled stays</div>
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
                    <span class="kpi-card-badge" id="kpi-occupancy-trend"></span>
                </div>
                <div class="kpi-card-sub"><span id="kpi-avg-stay">0</span> avg stay duration</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ FINANCIAL HEALTH & ACCOUNTING BREAKDOWN STRIP ═══ -->
<div class="financial-health-grid">
    <div class="fin-mini-card">
        <div class="fin-mini-label">Earned vs. Deferred Revenue</div>
        <div class="fin-mini-val" id="fin-earned-revenue">₱0</div>
        <div class="fin-mini-sub">Deferred (pre-checkout): <strong id="fin-deferred-revenue">₱0</strong></div>
    </div>
    <div class="fin-mini-card">
        <div class="fin-mini-label">Approved / Completed Refunds</div>
        <div class="fin-mini-val" style="color:#DC2626;" id="fin-refunded-amount">₱0</div>
        <div class="fin-mini-sub"><span id="fin-refund-count">0</span> refunds • Fees retained: <strong id="fin-refund-fees">₱0</strong></div>
    </div>
    <a href="payments.php?status=pending" class="fin-mini-card fin-mini-card--link" title="Open pending payments queue">
        <div class="fin-mini-label">Pending Verification Queue &rarr;</div>
        <div class="fin-mini-val" style="color:#D97706;" id="fin-pending-verification-amount">₱0</div>
        <div class="fin-mini-sub"><strong id="fin-pending-verification-count">0</strong> unverified receipts awaiting review</div>
    </a>
    <a href="admin_reservations.php?status=Confirmed" class="fin-mini-card fin-mini-card--link" title="Inspect active bookings with unpaid balances">
        <div class="fin-mini-label">Outstanding Guest Balances &rarr;</div>
        <div class="fin-mini-val" style="color:#2563EB;" id="fin-outstanding-balances-amount">₱0</div>
        <div class="fin-mini-sub"><strong id="fin-outstanding-balances-count">0</strong> active stays with remaining balance</div>
    </a>
</div>

<!-- ═══ ANALYTICS ROW: RESERVATION STATUS & TOP SOURCE MARKETS ═══ -->
<div class="three-col-charts">
    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Reservation Status</h3>
                <p>Bookings outcome distribution • Click any status to view reservations</p>
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
                <p>Non-cancelled guest origins by booking volume</p>
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
            <p>Monthly Gross Verified Receipts vs. Net Revenue (after refunds) • Click any month bar to inspect receipts</p>
        </div>
    </div>
    <div class="chart-box" id="chart-rev-container" style="height: 250px;">
        <div class="loading" id="loading-rev">Loading revenue chart...</div>
        <canvas id="monthlyRevChart" style="display:none;"></canvas>
    </div>
</div>

<!-- ═══ ROOM-TYPE PERFORMANCE (ADR, REVPAR & OCCUPANCY) ═══ -->
<div class="admin-card" style="margin-bottom: 24px;">
    <div class="admin-card-header">
        <div>
            <h3>Room-Type Performance (ADR &amp; RevPAR)</h3>
            <p>Unit inventory utilization, sold room-nights, Average Daily Rate (ADR), and Revenue Per Available Room</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Room Category</th>
                    <th>Active Units</th>
                    <th>Stays / Nights Sold</th>
                    <th>Occupancy</th>
                    <th>ADR</th>
                    <th>RevPAR</th>
                    <th style="text-align:right;">Verified Revenue</th>
                </tr>
            </thead>
            <tbody id="room-type-performance-tbody">
                <tr><td colspan="7" class="loading">Fetching room-type performance...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ LOWER SECTION: LEADERBOARDS & TABLES ═══ -->
<div class="two-col">
    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Payment Methods</h3>
                <p>Settlement volume by gateway / method • Click any row to filter payments</p>
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
                <p>Most booked individual units (excluding cancelled bookings)</p>
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
<div class="admin-card" style="margin-bottom:24px;">
    <div class="admin-card-header">
        <div>
            <h3>Guest Origin &amp; Nationality Leaderboard</h3>
            <p>All recorded non-cancelled origins included; top 10 shown first and additional countries are scrollable</p>
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

<!-- ═══ RESORT INSIGHTS: PROMOS/DISCOUNTS, CANCELLATIONS & GUEST REVIEWS ═══ -->
<div class="insights-three-col">
    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Promotions &amp; Statutory Discounts</h3>
                <p>Promo code redemptions &amp; Senior Citizen / PWD 20% discount impact</p>
            </div>
        </div>
        <div id="discounts-summary-container">
            <div class="loading">Loading discount metrics...</div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Cancellations &amp; Lead Time</h3>
                <p>Cancellation rate, non-payment auto-expiries, and booking lead window</p>
            </div>
        </div>
        <div id="cancellations-summary-container">
            <div class="loading">Loading cancellation insights...</div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <h3>Guest Satisfaction &amp; Reviews</h3>
                <p>Approved guest rating distribution and moderation queue</p>
            </div>
        </div>
        <div id="reviews-summary-container">
            <div class="loading">Loading review ratings...</div>
        </div>
    </div>
</div>

<!-- ═══ MONTHLY REVENUE DRILL-DOWN MODAL ═══ -->
<div id="monthlyRevenueDrilldownModal" role="dialog" aria-modal="true" aria-labelledby="mrdmTitle">
    <div class="rdm-card">
        <div class="rdm-header">
            <div class="rdm-icon-wrap" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </div>
            <div class="rdm-header-text">
                <div class="rdm-eyebrow">Monthly Revenue Drill-Down</div>
                <h3 id="mrdmTitle">Verified Monthly Receipts</h3>
                <p id="mrdmMonthSubtitle">Selected month summary</p>
            </div>
            <button type="button" class="ocm-close-x" data-close-monthly-modal="1" title="Close" aria-label="Close">&times;</button>
        </div>

        <div class="rdm-summary-strip">
            <div class="rdm-summary-item">
                <span class="rdm-summary-label">Gross Verified</span>
                <strong class="rdm-summary-value" id="mrdmGrossAmount">₱0.00</strong>
            </div>
            <div class="rdm-summary-item">
                <span class="rdm-summary-label">Refunded</span>
                <strong class="rdm-summary-value" style="color:#DC2626;" id="mrdmRefundedAmount">₱0.00</strong>
            </div>
            <div class="rdm-summary-item">
                <span class="rdm-summary-label">Net Revenue</span>
                <strong class="rdm-summary-value" style="color:#0D652D;" id="mrdmNetAmount">₱0.00</strong>
            </div>
            <div class="rdm-summary-item">
                <span class="rdm-summary-label">Receipts</span>
                <strong class="rdm-summary-value" id="mrdmTxnCount">0</strong>
            </div>
        </div>

        <div class="rdm-body" id="mrdmBodyContent">
            <div class="rdm-state">Loading monthly receipts...</div>
        </div>

        <div class="rdm-footer">
            <button type="button" class="ocm-btn-dismiss" data-close-monthly-modal="1">Close</button>
            <div class="rdm-footer-actions">
                <a class="rdm-btn-primary" href="payments.php?status=verified">Open Payments &rarr;</a>
            </div>
        </div>
    </div>
</div>
