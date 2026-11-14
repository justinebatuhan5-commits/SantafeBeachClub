<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';
/** @var mysqli $conn */
$admin = $_SESSION['admin_username'] ?? 'Admin';

// ── Overdue check-out alert (simple, no helper dependencies) ─────────────────
$_overdue_guest_names = [];
$_overdue_now_display = date('M d, Y h:i A');
$_overdue_tz_name     = 'Asia/Manila';
try {
    $_ov_res = $conn->query(
        "SELECT guest_name FROM bookings
         WHERE status = 'Checked In'
           AND check_out < CURDATE()
         ORDER BY check_out ASC"
    );
    if ($_ov_res) {
        while ($_ov_row = $_ov_res->fetch_assoc()) {
            $_overdue_guest_names[] = (string)$_ov_row['guest_name'];
        }
    }
} catch (Throwable $_ov_err) {
    $_overdue_guest_names = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=1794418047058">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="assets/css/admin_calendar.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/admin_calendar.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/occupancy_kpi.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/js/occupancy_kpi.js'); ?>"></script>
    <style>
        .loading { text-align: center; padding: 20px; color: var(--text-muted); font-size: 14px; }
        .error { color: #EF4444; text-align: center; padding: 20px; }

        /* ── Daily Activity Summary ───────────────────────────────── */
        .daily-summary-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
            margin-bottom: 28px;
            overflow: hidden;
        }
        .daily-summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px 14px;
            border-bottom: 1px solid var(--border);
        }
        .daily-summary-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .daily-summary-header h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }
        .daily-summary-live-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            background: rgba(16,185,129,.12);
            color: #059669;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .3px;
        }
        .daily-summary-live-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10B981;
            animation: ds-pulse 1.8s ease-in-out infinite;
        }
        @keyframes ds-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: .4; transform: scale(.8); }
        }
        .daily-summary-date {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }
        .daily-summary-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0;
        }
        .ds-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 22px 12px 18px;
            position: relative;
            transition: background .15s;
            border-right: 1px solid var(--border);
        }
        .ds-tile:last-child { border-right: none; }
        .ds-tile:hover { background: var(--primary-light); }
        .ds-tile-accent {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 0 0 3px 3px;
        }
        .ds-tile-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        .ds-tile-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
            margin-bottom: 5px;
        }
        .ds-tile-label {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .4px;
            text-align: center;
        }
        .ds-tile-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 3px;
            opacity: .75;
        }
        .daily-summary-footer {
            padding: 8px 22px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        @media (max-width: 768px) {
            .daily-summary-strip { grid-template-columns: repeat(3, 1fr); }
            .ds-tile:nth-child(3) { border-right: none; }
            .ds-tile:nth-child(4) { border-top: 1px solid var(--border); }
            .ds-tile:nth-child(5) { border-top: 1px solid var(--border); border-right: none; }
        }
</style>
    <!-- PWA Configuration -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#ff6b6b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Beach Club">
    <meta name="description" content="Admin Dashboard for Santa Fe Beach Club Booking System">
<?php require __DIR__ . '/partials/_responsive.php'; ?>
</head>
<body>

<?php
$active_page = 'dashboard';
include __DIR__ . '/partials/_sidebar.php';
?>

<div class="admin-main executive-dashboard">
    <?php
    $page_title = 'Admin Dashboard';
    $page_subtitle = "Welcome back, " . htmlspecialchars($admin) . " • Resort operations and financial summary";
    $header_extra_html = '
        <div class="admin-search-wrapper">
            <svg class="admin-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="adminGlobalSearch" class="admin-search-input" placeholder="Search bookings, rooms, guests…" oninput="adminSearch(this.value)" autocomplete="off">
            <div id="adminSearchResults" class="admin-search-dropdown"></div>
        </div>
        <a href="admin_reservations" class="btn-primary" style="height:38px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Booking
        </a>
    ';
    include __DIR__ . '/partials/_page_header.php';
    ?>

    <div class="admin-body">

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
                        <div class="stat-card-label">Occupancy Rate</div>
                        <div class="stat-card-value"><span id="kpi-occupancy-rate">...</span></div>
                        
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
        <?php $kpi_extra_class = 'cal-kpi-grid--dashboard'; include __DIR__ . '/partials/_occupancy_kpi_cards.php'; ?>

        <!-- Actionable counters not already represented above or in Daily Activity -->
        <div class="operations-snapshot">
            <div class="stat-card">
                <div>
                    <div class="stat-card-label">Reserved Rooms</div>
                    <div class="stat-card-value" id="kpi-reserved-rooms">...</div>
                    <div class="stat-card-sub">Rooms held by active bookings</div>
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
            <!-- Revenue Line Chart -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <h3>Revenue Trajectory (Last 7 Days)</h3>
                        <p>Verified daily receipts & guest settlements</p>
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
                <!-- New Bookings -->
                <div class="ds-tile">
                    <div class="ds-tile-accent" style="background:#84563C;"></div>
                    <div class="ds-tile-icon" style="background:rgba(132,86,60,.12);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#84563C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
                    </div>
                    <div class="ds-tile-value" id="ds-bookings">—</div>
                    <div class="ds-tile-label">New Bookings</div>
                    <div class="ds-tile-sub">Created today</div>
                </div>
                <!-- Payments -->
                <div class="ds-tile">
                    <div class="ds-tile-accent" style="background:#10B981;"></div>
                    <div class="ds-tile-icon" style="background:rgba(16,185,129,.1);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    </div>
                    <div class="ds-tile-value" id="ds-payments-count">—</div>
                    <div class="ds-tile-label">Payments Verified</div>
                    <div class="ds-tile-sub" id="ds-payments-amount" style="color:#059669;font-weight:600;">₱0</div>
                </div>
                <!-- Check-ins -->
                <div class="ds-tile">
                    <div class="ds-tile-accent" style="background:#3B82F6;"></div>
                    <div class="ds-tile-icon" style="background:rgba(59,130,246,.1);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    </div>
                    <div class="ds-tile-value" id="ds-checkins">—</div>
                    <div class="ds-tile-label">Check-ins</div>
                    <div class="ds-tile-sub">Arrived today</div>
                </div>
                <!-- Check-outs -->
                <div class="ds-tile">
                    <div class="ds-tile-accent" style="background:#64748B;"></div>
                    <div class="ds-tile-icon" style="background:rgba(100,116,139,.1);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </div>
                    <div class="ds-tile-value" id="ds-checkouts">—</div>
                    <div class="ds-tile-label">Check-outs</div>
                    <div class="ds-tile-sub">Departed today</div>
                </div>
                <!-- Cancellations -->
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
            <!-- Recent Bookings Table -->
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

            <!-- Activity Logs Feed -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h3>Live Audit Feed</h3>
                        <p style="font-size:12px; color:var(--text-muted); margin-top:2px;">Recent administrator and staff activity</p>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <button type="button" onclick="refreshAuditFeed(this)" title="Refresh Live Feed" style="background:none; border:1px solid var(--border); border-radius:6px; padding:4px 8px; font-size:12px; cursor:pointer; color:var(--text-muted); display:inline-flex; align-items:center; gap:4px;">
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
    </div>
</div>

<script>
// ── Charts Configuration ──────────────────────────────────────────────────────
const primaryColor = '#84563C';
const primaryGradColor = 'rgba(132, 86, 60, 0.12)';
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
                pointRadius: 2.2,
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

// Helper to make API calls to the local native PHP Analytics API
async function fetchAPI(endpoint) {
    try {
        const action = endpoint.replace('/api/', '').replace('/', '');
        const res = await fetch(`../backend/api/analytics_proxy.php?action=${encodeURIComponent(action)}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) throw new Error('API Error: ' + res.statusText);
        return await res.json();
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}

async function loadDashboardData() {
    // 1. KPI Metrics
    try {
        const stats = await fetchAPI('/api/executive-stats');
        document.getElementById('kpi-daily-rev').innerText = '₱' + Number(stats.daily_revenue || 0).toLocaleString();
        document.getElementById('kpi-weekly-rev').innerText = '₱' + Number(stats.weekly_revenue || 0).toLocaleString();
        document.getElementById('kpi-occupancy-rate').innerText = stats.occupancy_rate || '0%';
        const occEl = document.getElementById('kpi-occupied-rooms'); if (occEl) occEl.innerText = stats.occupied_rooms ?? 0;
        const totRoomsEl = document.getElementById('kpi-total-rooms'); if (totRoomsEl) totRoomsEl.innerText = stats.total_rooms ?? 0;
        document.getElementById('kpi-total-bookings').innerText = Number(stats.total_bookings || 0).toLocaleString();
        const pbEl = document.getElementById('kpi-pending-bookings'); if (pbEl) pbEl.innerText = stats.pending_bookings ?? 0;
        document.getElementById('link-total-bookings').innerText = stats.total_bookings ?? 0;
        document.getElementById('kpi-reserved-rooms').innerText    = stats.reserved_rooms  ?? '—';
        document.getElementById('kpi-pending-payments').innerText  = stats.pending_payments ?? '—';
    } catch (e) {
        console.error("KPI Metrics error:", e);
    }

    // 1b. Live Room Occupancy & Front Desk Strip (shared with Availability Calendar)
    if (window.SantaFeOccupancyKPI) {
        window.SantaFeOccupancyKPI.fetchAndRenderToday();
    }

    // 2. Revenue Trajectory Line Chart
    try {
        const revData = await fetchAPI('/api/weekly-revenue-trajectory');
        const loadingRev = document.getElementById('loading-rev');
        if (loadingRev) loadingRev.style.display = 'none';
        const rCanvas = document.getElementById('revenueChart');
        if (rCanvas && typeof Chart !== 'undefined') {
            rCanvas.style.display = 'block';
            new Chart(rCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: revData.labels || [],
                    datasets: [{
                        label: 'Verified Revenue (₱)',
                        data: revData.data || [],
                        borderColor: primaryColor,
                        backgroundColor: primaryGradColor,
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true,
                        pointBackgroundColor: primaryColor,
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                        pointRadius: 4.5,
                        pointHoverRadius: 6.5,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            titleFont: { family: 'Plus Jakarta Sans', size: 12, weight: '600' },
                            bodyFont: { family: 'Plus Jakarta Sans', size: 13, weight: '700' },
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: context => ' ₱' + Number(context.parsed.y).toLocaleString()
                            }
                        }
                    },
                    scales: {
                        x: { grid: { color: gridColor }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11.5 }, color: tickColor } },
                        y: { grid: { color: gridColor }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11.5 }, color: tickColor, callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v) }, beginAtZero: true }
                    }
                }
            });
        }
    } catch (e) {
        console.error("Revenue trajectory error:", e);
        const loadingRev = document.getElementById('loading-rev');
        if (loadingRev) loadingRev.innerHTML = '<span class="error">Failed to load revenue trajectory.</span>';
    }

    // 3. Daily Activity Summary
    try {
        const ds = await fetchAPI('/api/daily-summary');
        const fmt = ds.date ? new Date(ds.date + 'T00:00:00').toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        }) : 'Today';
        const dsDate = document.getElementById('ds-date');
        if (dsDate) dsDate.textContent = fmt;
        const dsBk = document.getElementById('ds-bookings');
        if (dsBk) dsBk.textContent = ds.bookings_today ?? 0;
        const dsPc = document.getElementById('ds-payments-count');
        if (dsPc) dsPc.textContent = ds.payments_today_count ?? 0;
        const dsPa = document.getElementById('ds-payments-amount');
        if (dsPa) dsPa.textContent = '₱' + Number(ds.payments_today_amount || 0).toLocaleString();
        const dsCi = document.getElementById('ds-checkins');
        if (dsCi) dsCi.textContent = ds.checkins_today ?? 0;
        const dsCo = document.getElementById('ds-checkouts');
        if (dsCo) dsCo.textContent = ds.checkouts_today ?? 0;
        const dsCn = document.getElementById('ds-cancellations');
        if (dsCn) dsCn.textContent = ds.cancellations_today ?? 0;
        const dsRef = document.getElementById('ds-refreshed');
        if (dsRef) dsRef.textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch (e) {
        console.error("Daily summary error:", e);
        const dsDate = document.getElementById('ds-date');
        if (dsDate) dsDate.textContent = 'Unavailable';
    }

    // 4. Recent Bookings Table
    try {
        const bookings = await fetchAPI('/api/recent-bookings');
        const bTbody = document.getElementById('recent-bookings-tbody');
        if (bTbody) {
            if (bookings && bookings.length > 0) {
                bTbody.innerHTML = bookings.map(b => {
                    const sc = { 'Pending': 'badge-pending', 'Checked In': 'badge-checkedin', 'Checked Out': 'badge-checkedout', 'Cancelled': 'badge-cancelled' };
                    const cls = sc[b.status] || 'badge-pending';
                    const initial = b.guest_name ? b.guest_name.charAt(0).toUpperCase() : '?';
                    
                    return `
                    <tr>
                        <td style="color:var(--text-muted); font-weight:600;">#${Security.escapeHTML(b.id)}</td>
                        <td>
                            <div class="guest-profile">
                                <div class="avatar-letter">${Security.escapeHTML(initial)}</div>
                                <div class="guest-info">
                                    <h4>${Security.escapeHTML(b.guest_name)}</h4>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--text-muted);">${Security.escapeHTML(b.accommodation_name)}</td>
                        <td style="color:var(--text-muted); font-weight:500;">${Security.escapeHTML(b.check_in)}</td>
                        <td><span class="badge ${cls}">${Security.escapeHTML(b.status)}</span></td>
                    </tr>
                    `;
                }).join('');
            } else {
                bTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No recent reservations found.</td></tr>';
            }
        }
    } catch (e) {
        console.error("Recent bookings error:", e);
        const bTbody = document.getElementById('recent-bookings-tbody');
        if (bTbody) bTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);"><span class="error">Failed to load reservations.</span></td></tr>';
    }

    // 5. Recent Logs Feed
    try {
        await renderAuditLogs();
    } catch (e) {
        console.error("Audit logs error:", e);
        const lContainer = document.getElementById('recent-logs-container');
        if (lContainer) lContainer.innerHTML = '<span class="error">Failed to load activity logs.</span>';
    }

    // 6. Security Threats / Warning Banner Check
    try {
        const threats = await fetchAPI('/api/security-threats-summary');
        const banner = document.getElementById('security-alert-banner');
        if (banner) {
            if (threats.status === 'critical' || threats.status === 'warning') {
                const isCrit = threats.status === 'critical';
                banner.style.display = 'flex';
                banner.style.background = isCrit ? '#FEF2F2' : '#FFFBEB';
                banner.style.border = isCrit ? '1px solid #FCA5A5' : '1px solid #FCD34D';

                const iconBox = document.getElementById('sec-banner-icon-box');
                if (iconBox) {
                    iconBox.style.background = isCrit ? '#FEE2E2' : '#FEF3C7';
                    iconBox.style.color = isCrit ? '#DC2626' : '#D97706';
                }

                const title = document.getElementById('sec-banner-title');
                if (title) {
                    title.style.color = isCrit ? '#991B1B' : '#92400E';
                    title.textContent = isCrit 
                        ? `Security Alert: ${threats.critical_count} critical incident(s) detected in the last 24h`
                        : `Security Warning: ${threats.warning_count} security warning(s) detected in the last 24h`;
                }

                const desc = document.getElementById('sec-banner-desc');
                if (desc) {
                    desc.style.color = isCrit ? '#B91C1C' : '#B45309';
                    const latestEvent = threats.recent_events && threats.recent_events[0] ? threats.recent_events[0].event_type : 'Suspicious activity';
                    desc.textContent = `Latest: ${latestEvent} from IP ${threats.recent_events[0]?.ip_address || 'unknown'}. Please review your security log.`;
                }

                const link = document.getElementById('sec-banner-link');
                if (link) {
                    link.style.background = isCrit ? '#DC2626' : '#D97706';
                    link.style.color = '#FFFFFF';
                }
            } else {
                banner.style.display = 'none';
            }
        }
    } catch (e) {
        console.error("Security threats check error:", e);
    }
}

// Render Audit Logs Helper
async function renderAuditLogs() {
    const logs = await fetchAPI('/api/recent-logs');
    const lContainer = document.getElementById('recent-logs-container');
    if (!lContainer) return;

    if (logs && logs.length > 0) {
        let html = '<ul class="activity-list">';
        logs.forEach(log => {
            let dot = 'default';
            let tag = 'System';
            let tagBg = '#F1F5F9';
            let tagColor = '#475569';

            const actionLower = (log.action || '').toLowerCase();
            if (actionLower.includes('login') || actionLower.includes('auth') || actionLower.includes('otp')) {
                dot = 'login';
                tag = 'Auth';
                tagBg = '#ECFDF5';
                tagColor = '#047857';
            } else if (actionLower.includes('booking') || actionLower.includes('reserve') || actionLower.includes('check')) {
                dot = 'booking';
                tag = 'Booking';
                tagBg = '#EFF6FF';
                tagColor = '#1D4ED8';
            } else if (actionLower.includes('payment') || actionLower.includes('paid') || actionLower.includes('receipt')) {
                dot = 'payment';
                tag = 'Payment';
                tagBg = '#FEF3C7';
                tagColor = '#B45309';
            }
            
            let detailsHtml = log.details ? `<span style="color:var(--text-muted); font-size:12.5px;"> · ${Security.escapeHTML(log.details)}</span>` : '';
            
            html += `
            <li class="activity-item" style="display:flex; align-items:flex-start; gap:12px; padding:10px 0; border-bottom:1px solid var(--border);">
                <div class="activity-dot ${dot}" style="margin-top:5px;"></div>
                <div style="flex:1; min-width:0;">
                    <div class="activity-text" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:13px;">
                        <span style="display:inline-block; font-size:10.5px; font-weight:700; padding:2px 6px; border-radius:4px; background:${tagBg}; color:${tagColor}; text-transform:uppercase;">${tag}</span>
                        <strong style="color:var(--primary); font-weight:700;">${Security.escapeHTML(log.admin_username)}</strong>
                        <span>${Security.escapeHTML(log.action)}</span>
                        ${detailsHtml}
                    </div>
                    <div class="activity-meta" style="font-size:11.5px; color:var(--text-muted); margin-top:3px;">${Security.escapeHTML(log.created_at)}</div>
                </div>
            </li>`;
        });
        html += '</ul>';
        lContainer.innerHTML = html;
    } else {
        lContainer.innerHTML = `
        <div class="empty-state">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <p>No recent activity recorded.</p>
        </div>`;
    }
}

// Instant Refresh Button handler for Audit Feed
async function refreshAuditFeed(btn) {
    const icon = document.getElementById('audit-refresh-icon');
    if (icon) {
        icon.style.transition = 'transform 0.5s ease';
        icon.style.transform = 'rotate(360deg)';
    }
    if (btn) btn.disabled = true;

    try {
        await renderAuditLogs();
    } finally {
        setTimeout(() => {
            if (icon) icon.style.transform = 'none';
            if (btn) btn.disabled = false;
        }, 500);
    }
}

// Auto-refresh daily summary every 60 s without reloading the whole dashboard
setInterval(async () => {
    try {
        const ds = await fetchAPI('/api/daily-summary');
        document.getElementById('ds-bookings').textContent        = ds.bookings_today;
        document.getElementById('ds-payments-count').textContent  = ds.payments_today_count;
        document.getElementById('ds-payments-amount').textContent = '₱' + Number(ds.payments_today_amount).toLocaleString();
        document.getElementById('ds-checkins').textContent        = ds.checkins_today;
        document.getElementById('ds-checkouts').textContent       = ds.checkouts_today;
        document.getElementById('ds-cancellations').textContent   = ds.cancellations_today;
        document.getElementById('ds-refreshed').textContent       = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch (_) {}
}, 60000);

// ── Live Global Search ────────────────────────────────────────────────────────
let searchTimeout = null;
function adminSearch(q) {
    const dropdown = document.getElementById('adminSearchResults');
    q = q.trim();
    if (!q) { dropdown.classList.remove('open'); dropdown.innerHTML = ''; return; }

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetch('../backend/api/admin_search_api.php?q=' + encodeURIComponent(q))
        .then(res => res.json())
        .then(results => {
            if (!results.length) {
                dropdown.innerHTML = '<div class="search-no-results">No records found for "<strong>' + q + '</strong>"</div>';
                dropdown.classList.add('open');
                return;
            }

            let html = '';
            results.forEach(item => {
                const initials = item.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
                html += `<a class="search-result-item" href="${Security.escapeHTML(Security.safeURL(item.url))}">
                    <div class="search-result-avatar">${Security.escapeHTML(initials)}</div>
                    <div style="min-width:0;">
                        <div class="search-result-name">${Security.escapeHTML(item.name)}</div>
                        <div class="search-result-meta">${Security.escapeHTML(item.sub)}</div>
                    </div>
                    <span class="badge badge-pending" style="margin-left:auto;">${Security.escapeHTML(item.status)}</span>
                </a>`;
            });

            dropdown.innerHTML = html;
            dropdown.classList.add('open');
        })
        .catch(err => console.error('Search error:', err));
    }, 200);
}

document.addEventListener('click', e => {
    if (!e.target.closest('.admin-search-wrapper')) {
        const dd = document.getElementById('adminSearchResults');
        if (dd) dd.classList.remove('open');
    }
});

// Trigger load on startup
window.addEventListener('DOMContentLoaded', loadDashboardData);
</script>

<!-- ── Overdue Check-out Warning Modal ────────────────────────────────────── -->
<style>
    #overdueCheckoutModal {
        display: none;
        position: fixed;
        inset: 0;
        top: 0; left: 0; right: 0; bottom: 0;
        width: 100vw; height: 100vh; height: 100dvh;
        margin: 0;
        box-sizing: border-box;
        background: rgba(15, 23, 42, 0.72);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        z-index: 99999;
        padding: 20px;
        animation: ocm-fadein .28s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes ocm-fadein { from { opacity:0; } to { opacity:1; } }
    .ocm-card {
        background: #FFFFFF;
        width: min(100%, 500px);
        margin: auto;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        border-radius: 20px;
        box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(226, 232, 240, 0.8);
        overflow: hidden;
        animation: ocm-slidein .32s cubic-bezier(.34, 1.25, .64, 1);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    @keyframes ocm-slidein { from { transform: translateY(24px) scale(.95); } to { transform: translateY(0) scale(1); } }
    .ocm-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 22px 26px;
        background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
        border-bottom: 3px solid #D97706;
        color: #fff;
        position: relative;
    }
    .ocm-logo-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #FFFFFF;
        padding: 3px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .ocm-logo-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 9px;
    }
    .ocm-header-text {
        flex: 1;
        min-width: 0;
    }
    .ocm-brand-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #F59E0B;
        margin-bottom: 2px;
    }
    .ocm-header-text h3 {
        margin: 0;
        font-size: 17px;
        font-weight: 700;
        color: #FFFFFF;
        letter-spacing: -0.2px;
    }
    .ocm-header-text p {
        margin: 3px 0 0;
        font-size: 12px;
        color: #94A3B8;
    }
    .ocm-close-x {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: #94A3B8;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
        font-size: 16px;
        line-height: 1;
    }
    .ocm-close-x:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #FFFFFF;
    }
    .ocm-body {
        padding: 22px 26px 14px;
        background: #FAFAFA;
    }
    .ocm-banner {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #FEF3C7;
        border: 1px solid #FCD34D;
        border-radius: 12px;
        padding: 11px 14px;
        margin-bottom: 16px;
    }
    .ocm-banner-icon {
        font-size: 17px;
        line-height: 1.2;
    }
    .ocm-banner-text {
        font-size: 12.5px;
        color: #92400E;
        line-height: 1.5;
        font-weight: 500;
    }
    .ocm-list-label {
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #64748B;
        margin-bottom: 8px;
    }
    .ocm-guest-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 180px;
        overflow-y: auto;
    }
    .ocm-guest-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-left: 4px solid #EA580C;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        color: #1E293B;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .ocm-guest-name {
        display: flex;
        align-items: center;
        gap: 9px;
    }
    .ocm-guest-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        background: #FEE2E2;
        color: #DC2626;
        border-radius: 6px;
        letter-spacing: 0.2px;
    }
    .ocm-timestamp {
        font-size: 11.5px;
        color: #94A3B8;
        margin: 14px 0 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .ocm-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 26px 20px;
        background: #FFFFFF;
        border-top: 1px solid #F1F5F9;
    }
    .ocm-btn-dismiss {
        padding: 10px 18px;
        border: 1px solid #E2E8F0;
        background: #F8FAFC;
        color: #475569;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.15s ease;
    }
    .ocm-btn-dismiss:hover {
        background: #F1F5F9;
        color: #1E293B;
        border-color: #CBD5E1;
    }
    .ocm-btn-go {
        padding: 10px 22px;
        border: none;
        background: linear-gradient(135deg, #D97706 0%, #B45309 100%);
        color: #FFFFFF;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
        font-size: 13px;
        box-shadow: 0 4px 14px rgba(217, 119, 6, 0.35);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.15s ease;
    }
    .ocm-btn-go:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(217, 119, 6, 0.45);
        filter: brightness(1.05);
    }
    [data-theme="dark"] .ocm-card { background: #131826 !important; border: 1px solid #1E293B !important; }
    [data-theme="dark"] .ocm-body { background: #0F1422 !important; }
    [data-theme="dark"] .ocm-guest-list li { background: #131826 !important; border-color: #1E293B !important; color: #F8FAFC !important; }
    [data-theme="dark"] .ocm-footer { background: #131826 !important; border-color: #1E293B !important; }
    [data-theme="dark"] .ocm-btn-dismiss { background: #1E293B !important; border-color: #334155 !important; color: #94A3B8 !important; }
</style>

<div id="overdueCheckoutModal" role="dialog" aria-modal="true" aria-labelledby="ocmTitle">
    <div class="ocm-card">
        <div class="ocm-header">
            <div class="ocm-logo-wrap">
                <img src="assets/logo.png" alt="Santa Fe Beach Club Logo" onerror="this.src='assets/logo.jpg'">
            </div>
            <div class="ocm-header-text">
                <div class="ocm-brand-badge">
                    <span><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true" style="vertical-align:-1px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span> Santa Fe Beach Club
                </div>
                <h3 id="ocmTitle">Overdue Check-out Alert</h3>
                <p>Action required &bull; Scheduled departure time has passed</p>
            </div>
            <button class="ocm-close-x" onclick="document.getElementById('overdueCheckoutModal').style.display='none'" title="Close">&times;</button>
        </div>
        <div class="ocm-body">
            <div class="ocm-banner">
                <span class="ocm-banner-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                <span class="ocm-banner-text">The following guest(s) have passed their scheduled check-out date and are still marked as <strong>Checked In</strong>. Please process their departure to free up accommodations.</span>
            </div>
            <div class="ocm-list-label">Overdue Guest(s)</div>
            <ul class="ocm-guest-list" id="ocmGuestList"></ul>
            <p class="ocm-timestamp">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                As of <?php echo htmlspecialchars($_overdue_now_display); ?> &bull; <?php echo htmlspecialchars($_overdue_tz_name); ?>
            </p>
        </div>
        <div class="ocm-footer">
            <button class="ocm-btn-dismiss" onclick="document.getElementById('overdueCheckoutModal').style.display='none'">
                Dismiss
            </button>
            <a class="ocm-btn-go" href="admin_checkout">
                <span>Go to Check-out Page</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</div>

<script>
(function(){
    var names = <?php echo json_encode($_overdue_guest_names, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    if (names.length > 0) {
        var list = document.getElementById('ocmGuestList');
        list.innerHTML = names.map(function(n){
            return '<li><div class="ocm-guest-name"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> ' + n + '</div><span class="ocm-guest-badge">Overdue</span></li>';
        }).join('');
        document.getElementById('overdueCheckoutModal').style.display = 'flex';
    }
})();
</script>
    <?php include __DIR__ . '/partials/_new_booking_alert.php'; ?>
    <!-- PWA Install Prompt -->
    <?php include __DIR__ . '/assets/pwa-install-prompt.html'; ?>
    <!-- PWA Install Handler Script -->
    <script src="assets/pwa-install.js" defer></script>

</body>
</html>
