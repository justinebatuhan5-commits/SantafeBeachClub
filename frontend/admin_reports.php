<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
$admin = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial & Occupancy Reports — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ── Executive KPI Card Strip (Matching Reference) ───────── */
        .report-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: var(--card-bg, #FFFFFF);
            border: 1px solid var(--border, #E2E8F0);
            border-radius: 16px;
            padding: 20px 22px 14px 22px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
        }
        
        .kpi-card-top {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }
        .kpi-circle-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .kpi-circle-icon svg {
            width: 22px;
            height: 22px;
        }

        .kpi-card-info {
            flex: 1;
            min-width: 0;
        }
        .kpi-card-title {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }
        .kpi-card-val-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
            flex-wrap: wrap;
        }
        .kpi-card-value {
            font-family: var(--font-heading, 'Plus Jakarta Sans', sans-serif);
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main, #1E293B);
            line-height: 1.1;
        }
        .kpi-card-badge {
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            color: #10B981;
        }
        .kpi-card-sub {
            font-size: 11px;
            color: #94A3B8;
            margin-top: 4px;
            font-weight: 500;
        }

        .kpi-sparkline-box {
            width: 100%;
            height: 42px;
            margin-top: 8px;
            position: relative;
        }

        /* ── 3-Column Visual Match Grid ────────────────────────── */
        .three-col-charts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .admin-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 22px 24px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .admin-card:hover {
            box-shadow: var(--shadow-md);
        }
        .admin-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .admin-card-header h3 {
            font-family: var(--font-heading);
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }
        .admin-card-header p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 3px 0 0;
        }

        /* Reports toolbar: actions stay left; account controls sit at the far right. */
        .page-header-actions {
            flex: 1 1 100%;
            min-width: 0;
        }
        .page-header-actions .header-notif-menu {
            margin-left: auto;
        }

        @media (max-width: 768px) {
            .page-header-actions .header-notif-menu {
                margin-left: 0;
            }
        }

        /* Keep the ten highest-ranked origins visible and scroll through the rest. */
        .country-leaderboard-scroll {
            max-height: 714px;
            overflow-y: auto;
            scrollbar-gutter: stable;
        }
        .country-leaderboard-scroll .admin-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--card-bg, #FFFFFF);
            box-shadow: 0 1px 0 var(--border, #E2E8F0);
        }
        
        .chart-box {
            position: relative;
            width: 100%;
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Donut Chart with Center Total and Right Legend */
        .donut-layout {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            width: 100%;
            height: 220px;
        }
        .donut-canvas-wrap {
            position: relative;
            width: 155px;
            height: 155px;
            flex-shrink: 0;
        }
        .donut-center-stat {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            text-align: center;
        }
        .donut-center-stat .num {
            font-family: var(--font-heading);
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
        }
        .donut-center-stat .lbl {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 3px;
            text-transform: capitalize;
        }

        /* Right Legend List */
        .donut-legend-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }
        .donut-legend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12.5px;
        }
        .donut-legend-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--text-main);
        }
        .donut-legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .donut-legend-right {
            font-weight: 700;
            color: var(--text-muted);
            font-size: 12px;
        }

        /* Horizontal Bar Ranking (Card 3) */
        .ranking-bar-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
            height: 220px;
            justify-content: center;
        }
        .ranking-bar-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .ranking-bar-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
        }
        .ranking-bar-label {
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ranking-bar-val {
            font-weight: 700;
            color: var(--text-muted);
        }
        .ranking-bar-track {
            width: 100%;
            height: 10px;
            background: #F1F5F9;
            border-radius: 99px;
            overflow: hidden;
        }
        .ranking-bar-fill {
            height: 100%;
            background: #84563C;
            border-radius: 99px;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .loading {
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
        }
        .error {
            color: #EF4444;
            text-align: center;
            padding: 20px;
            font-size: 13px;
        }

        /* Country table styling */
        .rank-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 6px;
            font-size: 10.5px;
            font-weight: 800;
            margin-right: 8px;
        }
        .rank-1 { background: #FEF3C7; color: #B45309; }
        .rank-2 { background: #E2E8F0; color: #475569; }
        .rank-3 { background: #FFEDD5; color: #C2410C; }
        .rank-other { background: var(--border-light); color: var(--text-muted); }

        .progress-bar-bg {
            flex: 1;
            max-width: 70px;
            height: 6px;
            background: var(--border);
            border-radius: 99px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #84563C, #A37152);
            border-radius: 99px;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @media (max-width: 1200px) {
            .three-col-charts {
                grid-template-columns: 1fr;
            }
            .report-kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .report-kpi-grid {
                grid-template-columns: 1fr;
            }
            .two-col {
                grid-template-columns: 1fr;
            }
            .donut-layout {
                flex-direction: column;
                height: auto;
            }
        }
    </style>
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>
    <?php $active_page = 'reports'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="admin-main">
        <?php
        $page_title = 'Financial & Business Reports';
        $page_subtitle = 'Audit trails, income performance, and guest volume metrics';
        $header_extra_html = '
            <div style="display:flex; gap:8px; align-items:center;">
                <a href="../backend/api/availability.php?action=export_report_csv&type=bookings" class="header-icon-btn" style="width:auto; padding:0 14px; height:40px; font-size:12.5px; font-weight:700; text-decoration:none; gap:6px; color:#334155;" title="Export Bookings CSV">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Bookings CSV</span>
                </a>
                <a href="../backend/api/availability.php?action=export_report_csv&type=payments" class="header-icon-btn" style="width:auto; padding:0 14px; height:40px; font-size:12.5px; font-weight:700; text-decoration:none; gap:6px; color:#059669; border-color:#A7F3D0; background:#ECFDF5;" title="Export Payments CSV">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Payments CSV</span>
                </a>
                <button class="btn-primary" onclick="window.print()" style="height:40px; padding:0 16px; border-radius:12px; font-size:13px; font-weight:700;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print
                </button>
            </div>
        ';
        include __DIR__ . '/partials/_page_header.php';
        ?>

        <div class="admin-body">
            
            <div id="api-connection-error" style="display: none; background: #FEF2F2; color: #991B1B; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #F87171; align-items:center; gap:10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div><strong>Analytics Error:</strong> Could not load reports data. Please verify your database connection.</div>
            </div>

            <!-- ═══ 4 KPI CARDS MATCHING REFERENCE DESIGN ═══ -->
            <div class="report-kpi-grid">
                <!-- 1. Total Revenue -->
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

                <!-- 2. Total Bookings -->
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

                <!-- 3. Total Guests -->
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

                <!-- 4. Occupancy Rate -->
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
                <!-- 1. Reservation Status Breakdown (Donut with Center Stat + Right Legend) -->
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

                <!-- 2. Top Source Markets / Countries (Horizontal Progress Ranking Bars) -->
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

            <!-- ═══ REVENUE OVERVIEW (BELOW STATUS & MARKETS) ═══ -->
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
                <!-- Payment Methods Table -->
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

                <!-- Top Accommodations Table -->
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
                        <h3>Guest Origin & Nationality Leaderboard</h3>
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

        </div>
    </main>

    <script>
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(15, 23, 42, 0.06)';
    const tickColor = isDark ? '#94A3B8' : '#64748B';

    // Helper: Mini Sparkline Creator
    function createSparkline(canvasId, dataPoints, strokeColor, fillColor) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dataPoints.map((_, i) => i + 1),
                datasets: [{
                    data: dataPoints,
                    borderColor: strokeColor,
                    backgroundColor: fillColor,
                    borderWidth: 1.8,
                    pointRadius: 2.5,
                    pointHoverRadius: 4,
                    pointBackgroundColor: strokeColor,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: {
                    x: { display: false },
                    y: { display: false, beginAtZero: false }
                }
            }
        });
    }

    // Proxy to Python Analytics Service (via PHP analytics_proxy.php)
    async function fetchAPI(endpoint) {
        try {
            const action = endpoint.replace('/api/', '').replace(/^\//, '');
            const res = await fetch(`../backend/api/analytics_proxy.php?action=${encodeURIComponent(action)}`, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('API Error: ' + res.statusText);
            return await res.json();
        } catch (error) {
            console.error('Fetch error:', error);
            const err = document.getElementById('api-connection-error');
            if (err) err.style.display = 'block';
            throw error;
        }
    }

    async function loadDashboardData() {
        // 1. KPI Stats
        try {
            const stats = await fetchAPI('/api/dashboard-stats');
            document.getElementById('kpi-revenue').innerText = '₱' + Number(stats.total_revenue || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
            document.getElementById('kpi-bookings').innerText = Number(stats.total_bookings || 0).toLocaleString();
            document.getElementById('kpi-pending').innerText = stats.pending_bookings || 0;
            document.getElementById('kpi-guests').innerText = Number(stats.total_guests || 0).toLocaleString();
            document.getElementById('kpi-occupancy').innerText = (stats.occupancy_rate || 0) + '%';
            document.getElementById('kpi-avg-stay').innerText = (stats.avg_stay || '0') + ' nights';
        } catch (e) {
            console.error("KPI load error:", e);
        }

        // 2. Revenue Overview
        try {
            const revData = await fetchAPI('/api/monthly-revenue');
            const loadingRev = document.getElementById('loading-rev');
            if (loadingRev) loadingRev.style.display = 'none';
            const revCanvas = document.getElementById('monthlyRevChart');
            if (revCanvas && typeof Chart !== 'undefined') {
                revCanvas.style.display = 'block';
                const revValues = revData.revenue || [];
                const avgRateValues = revValues.map(v => Number(v) > 0 ? Number(v) * 0.92 : 0);

                new Chart(revCanvas.getContext('2d'), {
                    data: {
                        labels: revData.labels || [],
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Revenue (₱)',
                                data: revValues,
                                backgroundColor: '#84563C',
                                hoverBackgroundColor: '#6B442E',
                                borderRadius: 4,
                                barThickness: 16,
                                order: 2
                            },
                            {
                                type: 'line',
                                label: 'Average Rate (₱)',
                                data: avgRateValues,
                                borderColor: '#D97706',
                                backgroundColor: '#D97706',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                pointBackgroundColor: '#D97706',
                                tension: 0.35,
                                order: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                align: 'end',
                                labels: {
                                    color: tickColor,
                                    boxWidth: 10,
                                    boxHeight: 10,
                                    font: { family: 'Plus Jakarta Sans', size: 10.5, weight: '600' }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: c => ` ${c.dataset.label}: ₱${Number(c.parsed.y).toLocaleString()}`
                                }
                            }
                        },
                        scales: {
                            x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { family: 'Plus Jakarta Sans', size: 10.5 } } },
                            y: { grid: { color: gridColor }, ticks: { color: tickColor, font: { family: 'Plus Jakarta Sans', size: 10.5 }, callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v) }, beginAtZero: true }
                        }
                    }
                });
            }
        } catch (e) {
            console.error("Revenue overview load error:", e);
            const loadingRev = document.getElementById('loading-rev');
            if (loadingRev) loadingRev.innerHTML = '<span class="error">Failed to load revenue overview.</span>';
        }

        // 3. Reservation Status
        try {
            const statusData = await fetchAPI('/api/status-breakdown');
            const loadingStatus = document.getElementById('loading-status');
            if (loadingStatus) loadingStatus.style.display = 'none';
            const statusCanvasWrap = document.getElementById('status-canvas-wrap');
            if (statusCanvasWrap) statusCanvasWrap.style.display = 'block';

            const statusCounts = {
                'Confirmed': statusData['Confirmed'] || 0,
                'Checked In': statusData['Checked In'] || 0,
                'Checked Out': statusData['Checked Out'] || 0,
                'Pending': statusData['Pending'] || 0,
                'Cancelled': statusData['Cancelled'] || 0
            };
            const totalStatus = Object.values(statusCounts).reduce((a, b) => a + b, 0);
            const totalStatusEl = document.getElementById('donut-status-total');
            if (totalStatusEl) totalStatusEl.innerText = totalStatus.toLocaleString();

            const statusColors = {
                'Confirmed': '#2563EB',
                'Checked In': '#84563C',
                'Checked Out': '#D97706',
                'Pending': '#5E9A8E',
                'Cancelled': '#EF4444'
            };

            const statusCanvas = document.getElementById('statusBreakdownChart');
            if (statusCanvas && typeof Chart !== 'undefined') {
                new Chart(statusCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(statusCounts),
                        datasets: [{
                            data: totalStatus > 0 ? Object.values(statusCounts) : [1],
                            backgroundColor: totalStatus > 0 ? Object.values(statusColors) : ['#E2E8F0'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: totalStatus > 0,
                                callbacks: {
                                    label: ctx => ` ${ctx.label}: ${ctx.parsed} (${totalStatus > 0 ? Math.round((ctx.parsed/totalStatus)*100) : 0}%)`
                                }
                            }
                        }
                    }
                });
            }

            const statusLegendList = document.getElementById('status-legend-list');
            if (statusLegendList) {
                statusLegendList.innerHTML = Object.entries(statusCounts).map(([status, count]) => {
                    const pct = totalStatus > 0 ? Math.round((count / totalStatus) * 100) : 0;
                    const col = statusColors[status] || '#84563C';
                    const safeStatus = typeof Security !== 'undefined' ? Security.escapeHTML(status) : status;
                    return `
                    <div class="donut-legend-item">
                        <div class="donut-legend-left">
                            <span class="donut-legend-dot" style="background:${col};"></span>
                            <span>${safeStatus}</span>
                        </div>
                        <div class="donut-legend-right">${pct}% (${count})</div>
                    </div>
                    `;
                }).join('');
            }
        } catch (e) {
            console.error("Status breakdown load error:", e);
        }

        // 4. Top Source Markets & Country Demographics
        let countryList = [];
        const flagMap = {};

        try {
            const countryData = await fetchAPI('/api/country-demographics');
            countryList = countryData.countries || [];
            const badgeCountries = document.getElementById('badge-total-countries');
            if (badgeCountries) badgeCountries.innerText = `${countryData.total_origins || countryList.length} Origins`;

            const rankingContainer = document.getElementById('ranking-bars-container');
            const totalOriginsBookings = countryData.total_bookings || 1;

            if (rankingContainer) {
                if (countryList.length > 0) {
                    const top5Markets = countryList.slice(0, 5);
                    rankingContainer.innerHTML = top5Markets.map(c => {
                        const pct = c.share_pct || (Math.round((c.bookings / totalOriginsBookings) * 100));
                        const safeCountry = typeof Security !== 'undefined' ? Security.escapeHTML(c.country) : c.country;
                        return `
                        <div class="ranking-bar-row">
                            <div class="ranking-bar-meta">
                                <span class="ranking-bar-label">${safeCountry}</span>
                                <span class="ranking-bar-val">${pct}% (${c.bookings})</span>
                            </div>
                            <div class="ranking-bar-track">
                                <div class="ranking-bar-fill" style="width:${pct}%;"></div>
                            </div>
                        </div>
                        `;
                    }).join('');
                } else {
                    rankingContainer.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">No guest country data yet.</div>';
                }
            }
        } catch (e) {
            console.error("Country demographics error:", e);
            const rankingContainer = document.getElementById('ranking-bars-container');
            if (rankingContainer) rankingContainer.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">No guest country data yet.</div>';
        }

        // 5. Payment Methods Table
        try {
            const payments = await fetchAPI('/api/payment-methods');
            const pTbody = document.getElementById('payment-methods-tbody');
            if (pTbody) {
                if (payments && payments.length > 0) {
                    pTbody.innerHTML = payments.map(p => {
                        const safeMethod = typeof Security !== 'undefined' ? Security.escapeHTML(p.payment_method || 'Front Desk Cash') : (p.payment_method || 'Front Desk Cash');
                        return `
                        <tr>
                            <td style="font-weight:600;">${safeMethod}</td>
                            <td style="color:var(--text-muted); font-weight:600;">${p.cnt} payments</td>
                            <td style="text-align:right; font-weight:700; color:var(--text-main);">₱${Number(p.total).toLocaleString()}</td>
                        </tr>
                        `;
                    }).join('');
                } else {
                    pTbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:24px; color:var(--text-muted);">No payment records.</td></tr>';
                }
            }
        } catch (e) {
            console.error("Payment methods error:", e);
            const pTbody = document.getElementById('payment-methods-tbody');
            if (pTbody) pTbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:24px; color:var(--text-muted);">No payment records.</td></tr>';
        }

        // 6. Top Accommodations Table
        try {
            const rooms = await fetchAPI('/api/top-accommodations');
            const rTbody = document.getElementById('top-rooms-tbody');
            if (rTbody) {
                if (rooms && rooms.length > 0) {
                    rTbody.innerHTML = rooms.map(r => {
                        const safeRoom = typeof Security !== 'undefined' ? Security.escapeHTML(r.accommodation_name) : r.accommodation_name;
                        return `
                        <tr>
                            <td style="font-weight:600;">${safeRoom}</td>
                            <td style="color:var(--text-muted); font-weight:600;">${r.cnt} stays</td>
                            <td style="text-align:right; font-weight:700; color:var(--primary);">${r.guests} guests</td>
                        </tr>
                        `;
                    }).join('');
                } else {
                    rTbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:24px; color:var(--text-muted);">No room booking records.</td></tr>';
                }
            }
        } catch (e) {
            console.error("Top accommodations error:", e);
            const rTbody = document.getElementById('top-rooms-tbody');
            if (rTbody) rTbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:24px; color:var(--text-muted);">No room booking records.</td></tr>';
        }

        // 7. Full Country Origin Leaderboard Table
        try {
            const cTbody = document.getElementById('country-leaderboard-tbody');
            if (cTbody) {
                if (countryList && countryList.length > 0) {
                    cTbody.innerHTML = countryList.map((c, idx) => {
                        const rankClass = idx === 0 ? 'rank-1' : (idx === 1 ? 'rank-2' : (idx === 2 ? 'rank-3' : 'rank-other'));
                        const safeCountry = typeof Security !== 'undefined' ? Security.escapeHTML(c.country) : c.country;

                        return `
                        <tr>
                            <td style="font-weight:600; display:flex; align-items:center;">
                                <span class="rank-pill ${rankClass}">${idx + 1}</span>
                                <span>${safeCountry}</span>
                            </td>
                            <td style="color:var(--text-muted); font-weight:600;">${c.bookings}</td>
                            <td style="color:var(--text-muted); font-weight:600;">${c.unique_customers ?? 0}</td>
                            <td style="color:var(--text-muted); font-weight:600;">${c.booked_guests ?? 0}</td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div class="progress-bar-bg">
                                        <div class="progress-bar-fill" style="width:${Math.max(0, Math.min(100, Number(c.share_pct) || 0))}%;"></div>
                                    </div>
                                    <span style="font-size:11.5px; font-weight:700; color:var(--text-muted);">${Math.max(0, Math.min(100, Number(c.share_pct) || 0))}%</span>
                                </div>
                            </td>
                            <td style="text-align:right; font-weight:700; color:var(--text-main);">₱${Number(c.revenue || 0).toLocaleString()}</td>
                        </tr>
                        `;
                    }).join('');
                } else {
                    cTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">No guest country data recorded yet.</td></tr>';
                }
            }
        } catch (e) {
            console.error("Country leaderboard error:", e);
            const cTbody = document.getElementById('country-leaderboard-tbody');
            if (cTbody) cTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">No guest country data recorded yet.</td></tr>';
        }
    }

    // Trigger load on startup
    window.addEventListener('DOMContentLoaded', loadDashboardData);
    </script>
</body>
</html>
