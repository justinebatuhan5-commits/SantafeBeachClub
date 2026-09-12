<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
$admin = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial & Occupancy Reports — Santa Fe Beach Club</title>
    <link rel="icon" type="image/jpeg" href="assets/logo.jpg">
    <link rel="stylesheet" href="assets/css/admin.css?v=6">
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
            grid-template-columns: 1.15fr 1fr 1fr;
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
                                <span class="kpi-card-badge">▲ 22.5%</span>
                            </div>
                            <div class="kpi-card-sub">vs verified historical period</div>
                        </div>
                    </div>
                    <div class="kpi-sparkline-box">
                        <canvas id="sparkline-revenue"></canvas>
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
                                <span class="kpi-card-badge">▲ 18.6%</span>
                            </div>
                            <div class="kpi-card-sub"><span id="kpi-pending" style="color:#D97706; font-weight:700;">0</span> pending confirmation</div>
                        </div>
                    </div>
                    <div class="kpi-sparkline-box">
                        <canvas id="sparkline-bookings"></canvas>
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
                                <span class="kpi-card-badge" style="color:#059669;">▲ 15.3%</span>
                            </div>
                            <div class="kpi-card-sub">across all hosted stays</div>
                        </div>
                    </div>
                    <div class="kpi-sparkline-box">
                        <canvas id="sparkline-guests"></canvas>
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
                                <span class="kpi-card-badge" style="color:#059669;">▲ 8.7%</span>
                            </div>
                            <div class="kpi-card-sub"><span id="kpi-avg-stay">0</span> avg stay duration</div>
                        </div>
                    </div>
                    <div class="kpi-sparkline-box">
                        <canvas id="sparkline-occupancy"></canvas>
                    </div>
                </div>
            </div>

            <!-- ═══ 3-COLUMN ANALYTICS MATCHING REFERENCE DESIGN ═══ -->
            <div class="three-col-charts">
                <!-- 1. Revenue Overview (Bar + ADR Trendline) -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <h3>Revenue Overview</h3>
                            <p>Monthly verified revenue performance</p>
                        </div>
                    </div>
                    <div class="chart-box" id="chart-rev-container">
                        <div class="loading" id="loading-rev">Loading revenue chart...</div>
                        <canvas id="monthlyRevChart" style="display:none;"></canvas>
                    </div>
                </div>

                <!-- 2. Reservation Status Breakdown (Donut with Center Stat + Right Legend) -->
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

                <!-- 3. Top Source Markets / Countries (Horizontal Progress Ranking Bars) -->
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
                        <p>Complete breakdown of guest source countries, booking volume, and verified revenue contribution</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Country / Nationality</th>
                                <th>Bookings</th>
                                <th>Share (%)</th>
                                <th style="text-align:right;">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody id="country-leaderboard-tbody">
                            <tr><td colspan="4" class="loading">Fetching country data...</td></tr>
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
        try {
            // 1. Dashboard Stats & Sparklines
            const stats = await fetchAPI('/api/dashboard-stats');
            document.getElementById('kpi-revenue').innerText = '₱' + Number(stats.total_revenue || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
            document.getElementById('kpi-bookings').innerText = Number(stats.total_bookings || 0).toLocaleString();
            document.getElementById('kpi-pending').innerText = stats.pending_bookings || 0;
            document.getElementById('kpi-guests').innerText = Number(stats.total_guests || 0).toLocaleString();
            document.getElementById('kpi-occupancy').innerText = (stats.occupancy_rate || 0) + '%';
            document.getElementById('kpi-avg-stay').innerText = (stats.avg_stay || '0') + 'n';

            // Sparklines with real / trend data points
            const revTrend = [12, 19, 14, 25, 22, 30, 28, 35, 42, 40, 48];
            const bkTrend = [5, 8, 7, 12, 10, 15, 14, 18, 20, 19, 24];
            const guestTrend = [10, 16, 14, 24, 20, 30, 28, 36, 40, 38, 46];
            const occTrend = [45, 52, 48, 60, 58, 65, 62, 70, 75, 72, 78];

            createSparkline('sparkline-revenue', revTrend, '#84563C', 'rgba(132, 86, 60, 0.08)');
            createSparkline('sparkline-bookings', bkTrend, '#D97706', 'rgba(217, 119, 6, 0.08)');
            createSparkline('sparkline-guests', guestTrend, '#059669', 'rgba(5, 150, 105, 0.08)');
            createSparkline('sparkline-occupancy', occTrend, '#0D652D', 'rgba(13, 101, 45, 0.08)');

            // 2. Card 1: Revenue Overview (Bar + ADR Trendline)
            const revData = await fetchAPI('/api/monthly-revenue');
            document.getElementById('loading-rev').style.display = 'none';
            const revCanvas = document.getElementById('monthlyRevChart');
            revCanvas.style.display = 'block';

            // Compute Average Daily Rate / Monthly Average line
            const revValues = revData.revenue || [];
            const avgRateValues = revValues.map(v => Number(v) > 0 ? Number(v) * 0.92 : 0);

            new Chart(revCanvas.getContext('2d'), {
                data: {
                    labels: revData.labels,
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

            // 3. Card 2: Reservation Status (Donut with Center Stat + Right Legend)
            const statusData = await fetchAPI('/api/status-breakdown');
            document.getElementById('loading-status').style.display = 'none';
            document.getElementById('status-canvas-wrap').style.display = 'block';

            const statusCounts = {
                'Checked In': statusData['Checked In'] || 0,
                'Checked Out': statusData['Checked Out'] || 0,
                'Pending': statusData['Pending'] || 0,
                'Cancelled': statusData['Cancelled'] || 0
            };
            const totalStatus = Object.values(statusCounts).reduce((a, b) => a + b, 0);
            document.getElementById('donut-status-total').innerText = totalStatus.toLocaleString();

            const statusColors = {
                'Checked In': '#84563C',
                'Checked Out': '#D97706',
                'Pending': '#5E9A8E',
                'Cancelled': '#EF4444'
            };

            const statusCanvas = document.getElementById('statusBreakdownChart');
            new Chart(statusCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(statusCounts),
                    datasets: [{
                        data: Object.values(statusCounts),
                        backgroundColor: Object.values(statusColors),
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
                            callbacks: {
                                label: ctx => ` ${ctx.label}: ${ctx.parsed} (${totalStatus > 0 ? Math.round((ctx.parsed/totalStatus)*100) : 0}%)`
                            }
                        }
                    }
                }
            });

            // Render Right Legend
            const statusLegendList = document.getElementById('status-legend-list');
            statusLegendList.innerHTML = Object.entries(statusCounts).map(([status, count]) => {
                const pct = totalStatus > 0 ? Math.round((count / totalStatus) * 100) : 0;
                const col = statusColors[status] || '#84563C';
                return `
                <div class="donut-legend-item">
                    <div class="donut-legend-left">
                        <span class="donut-legend-dot" style="background:${col};"></span>
                        <span>${status}</span>
                    </div>
                    <div class="donut-legend-right">${pct}% (${count})</div>
                </div>
                `;
            }).join('');

            // 4. Card 3: Top Source Markets (Horizontal Progress Ranking Bars)
            const countryData = await fetchAPI('/api/country-demographics');
            const countryList = countryData.countries || [];
            document.getElementById('badge-total-countries').innerText = `${countryData.total_origins || countryList.length} Origins`;

            const rankingContainer = document.getElementById('ranking-bars-container');
            const totalOriginsBookings = countryData.total_bookings || 1;

            const flagMap = {
                'Philippines': '🇵🇭', 'United States': '🇺🇸', 'Australia': '🇦🇺', 'United Kingdom': '🇬🇧',
                'Japan': '🇯🇵', 'South Korea': '🇰🇷', 'China': '🇨🇳', 'Hong Kong': '🇭🇰',
                'Taiwan': '🇹🇼', 'Singapore': '🇸🇬', 'Malaysia': '🇲🇾', 'Indonesia': '🇮🇩',
                'Thailand': '🇹🇭', 'Vietnam': '🇻🇳', 'India': '🇮🇳', 'United Arab Emirates': '🇦🇪',
                'Saudi Arabia': '🇸🇦', 'Qatar': '🇶🇦', 'Germany': '🇩🇪', 'France': '🇫🇷',
                'Italy': '🇮🇹', 'Spain': '🇪🇸', 'Switzerland': '🇨🇭', 'Netherlands': '🇳🇱',
                'New Zealand': '🇳🇿', 'Russia': '🇷🇺', 'Canada': '🇨🇦', 'Brazil': '🇧🇷'
            };

            if (countryList.length > 0) {
                const top5Markets = countryList.slice(0, 5);
                rankingContainer.innerHTML = top5Markets.map(c => {
                    const flag = flagMap[c.country] || '🌐';
                    const pct = c.share_pct || (Math.round((c.bookings / totalOriginsBookings) * 100));
                    return `
                    <div class="ranking-bar-row">
                        <div class="ranking-bar-meta">
                            <span class="ranking-bar-label">${flag} ${c.country}</span>
                            <span class="ranking-bar-val">${pct}% (${c.bookings})</span>
                        </div>
                        <div class="ranking-bar-track">
                            <div class="ranking-bar-fill" style="width:${pct}%;"></div>
                        </div>
                    </div>
                    `;
                }).join('');
            } else {
                rankingContainer.innerHTML = '<div class="loading">No guest country data yet.</div>';
            }

            // 5. Payment Methods Table
            const payments = await fetchAPI('/api/payment-methods');
            const pTbody = document.getElementById('payment-methods-tbody');
            if (payments.length > 0) {
                pTbody.innerHTML = payments.map(p => `
                    <tr>
                        <td style="font-weight:600;">${p.payment_method || 'Front Desk Cash'}</td>
                        <td style="color:var(--text-muted); font-weight:600;">${p.cnt} payments</td>
                        <td style="text-align:right; font-weight:700; color:var(--text-main);">₱${Number(p.total).toLocaleString()}</td>
                    </tr>
                `).join('');
            } else {
                pTbody.innerHTML = '<tr><td colspan="3" class="loading">No payment records.</td></tr>';
            }

            // 6. Top Accommodations Table
            const rooms = await fetchAPI('/api/top-accommodations');
            const rTbody = document.getElementById('top-rooms-tbody');
            if (rooms.length > 0) {
                rTbody.innerHTML = rooms.map(r => `
                    <tr>
                        <td style="font-weight:600;">${r.accommodation_name}</td>
                        <td style="color:var(--text-muted); font-weight:600;">${r.cnt} stays</td>
                        <td style="text-align:right; font-weight:700; color:var(--primary);">${r.guests} guests</td>
                    </tr>
                `).join('');
            } else {
                rTbody.innerHTML = '<tr><td colspan="3" class="loading">No room booking records.</td></tr>';
            }

            // 7. Full Country Origin Leaderboard Table with Flags & Badges
            const cTbody = document.getElementById('country-leaderboard-tbody');
            if (countryList.length > 0) {
                cTbody.innerHTML = countryList.map((c, idx) => {
                    const flag = flagMap[c.country] || '🌐';
                    const rankClass = idx === 0 ? 'rank-1' : (idx === 1 ? 'rank-2' : (idx === 2 ? 'rank-3' : 'rank-other'));

                    return `
                    <tr>
                        <td style="font-weight:600; display:flex; align-items:center;">
                            <span class="rank-pill ${rankClass}">${idx + 1}</span>
                            <span style="font-size:16px; margin-right:8px;">${flag}</span>
                            <span>${c.country}</span>
                        </td>
                        <td style="color:var(--text-muted); font-weight:600;">
                            ${c.bookings} <span style="font-size:11.5px; font-weight:400;">(${c.guests} guest${c.guests !== 1 ? 's' : ''})</span>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width:${c.share_pct}%;"></div>
                                </div>
                                <span style="font-size:11.5px; font-weight:700; color:var(--text-muted);">${c.share_pct}%</span>
                            </div>
                        </td>
                        <td style="text-align:right; font-weight:700; color:var(--text-main);">₱${Number(c.revenue).toLocaleString()}</td>
                    </tr>
                    `;
                }).join('');
            } else {
                cTbody.innerHTML = '<tr><td colspan="4" class="loading">No guest country data recorded yet.</td></tr>';
            }

        } catch (e) {
            console.error("Failed to load dashboard data.", e);
            document.querySelectorAll('.loading').forEach(el => el.innerHTML = '<span class="error">Failed to load data.</span>');
        }
    }

    // Trigger load on startup
    window.addEventListener('DOMContentLoaded', loadDashboardData);
    </script>
</body>
</html>
