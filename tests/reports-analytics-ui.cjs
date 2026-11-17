const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const reportsJs = fs.readFileSync(path.join(root, 'frontend/assets/js/pages/admin-reports.js'), 'utf8');
const reportsHtml = fs.readFileSync(path.join(root, 'frontend/views/admin_reports/_report_sections.php'), 'utf8');
const adminReportsPage = fs.readFileSync(path.join(root, 'frontend/admin_reports.php'), 'utf8');
const dashboardKpiPhp = fs.readFileSync(path.join(root, 'frontend/views/admin_dashboard/_kpi_and_charts.php'), 'utf8');
const paymentsJs = fs.readFileSync(path.join(root, 'frontend/assets/js/pages/payments.js'), 'utf8');
const analyticsApiPhp = fs.readFileSync(path.join(root, 'backend/api/analytics_api.php'), 'utf8');
const analyticsProxyPhp = fs.readFileSync(path.join(root, 'backend/api/analytics_proxy.php'), 'utf8');
const availabilityPhp = fs.readFileSync(path.join(root, 'backend/api/availability.php'), 'utf8');

// 1. Synthetic 0.92 Average Rate line and unused endpoints must be removed
assert(!reportsJs.includes('0.92'), 'Synthetic 0.92 Average Rate multiplier must be removed from admin-reports.js');
assert(!analyticsApiPhp.includes("case 'room-type-occupancy':"), 'Dead room-type-occupancy endpoint must be removed');
assert(!analyticsApiPhp.includes("case 'accommodation-popularity':"), 'Dead accommodation-popularity endpoint must be removed');
console.log('PASS: Synthetic 0.92 multiplier and dead endpoints are removed');

// 2. Dashboard occupancy & reserved room labels clarified
assert(dashboardKpiPhp.includes('Checked-In Now'), 'Dashboard top card is clearly labeled Checked-In Now');
assert(dashboardKpiPhp.includes('id="kpi-occupied-rooms"'), 'Dashboard top card shows checked-in room ratio');
assert(dashboardKpiPhp.includes('Upcoming &amp; active room holds'), 'Reserved Rooms subtitle clarifies active/upcoming holds');
console.log('PASS: Admin Dashboard occupancy and reserved room labels are clarified');

// 3. Proxy query forwarding & scoped CSV export support
assert(analyticsProxyPhp.includes('http_build_query($forwardParams)'), 'analytics_proxy.php forwards full query parameters');
assert(analyticsProxyPhp.includes('$pythonSupportedActions'), 'analytics_proxy.php restricts Python forwarding to supported legacy endpoints so Reports v3 endpoints use native PHP');
assert(!analyticsApiPhp.includes('GROUP BY country\n'), 'country-demographics avoids TiDB-incompatible GROUP BY alias');
assert(!analyticsApiPhp.includes('GROUP BY reason\n'), 'cancellation-insights avoids TiDB-incompatible GROUP BY alias');
assert(availabilityPhp.includes('WHERE DATE(p.paid_at) BETWEEN ? AND ?'), 'Payments CSV export supports date range filtering');
assert(availabilityPhp.includes('WHERE DATE(b.created_at) BETWEEN ? AND ?'), 'Bookings CSV export supports date range filtering');
assert(adminReportsPage.includes('id="exportBookingsCsvLink"'), 'admin_reports.php exposes #exportBookingsCsvLink');
assert(adminReportsPage.includes('id="exportPaymentsCsvLink"'), 'admin_reports.php exposes #exportPaymentsCsvLink');
console.log('PASS: Proxy query forwarding and date-scoped CSV exports are wired');

// 4. Reports HTML sections and modal elements exist
for (const requiredId of [
    'reportsDateFilterBar',
    'reportStartDate',
    'reportEndDate',
    'applyCustomDateBtn',
    'fin-earned-revenue',
    'fin-deferred-revenue',
    'fin-refunded-amount',
    'fin-pending-verification-amount',
    'fin-outstanding-balances-amount',
    'room-type-performance-tbody',
    'discounts-summary-container',
    'cancellations-summary-container',
    'reviews-summary-container',
    'monthlyRevenueDrilldownModal',
    'mrdmBodyContent'
]) {
    assert(reportsHtml.includes(`id="${requiredId}"`), `_report_sections.php must contain #${requiredId}`);
}
console.log('PASS: All financial health, room-type, discount, cancellation, review, and modal sections exist in _report_sections.php');

// 5. Payments page supports both ?status= and ?search= deep links
assert(paymentsJs.includes("urlParams.get('status')"), 'payments.js reads ?status= URL parameter');
assert(paymentsJs.includes("urlParams.get('search')"), 'payments.js reads ?search= URL parameter');
console.log('PASS: payments.js supports deep-linked status and search filters');

// 6. Simulate admin-reports.js end-to-end with XSS payloads, date filter, and drill-downs
(async () => {
    const createdCharts = [];
    const clickHandlers = [];
    const elements = {};
    const makeEl = id => ({
        id,
        innerText: '',
        innerHTML: '',
        value: '',
        style: {},
        className: '',
        attributes: {},
        setAttribute(k, v) { this.attributes[k] = v; },
        getAttribute(k) { return this.attributes[k]; },
        classList: { toggle() {}, remove() {}, add() {} },
        getContext() { return {}; }
    });

    for (const id of [
        'kpi-revenue', 'kpi-revenue-sub', 'kpi-revenue-trend',
        'kpi-bookings', 'kpi-pending', 'kpi-bookings-trend',
        'kpi-guests', 'kpi-guests-trend',
        'kpi-occupancy', 'kpi-avg-stay', 'kpi-occupancy-trend',
        'fin-earned-revenue', 'fin-deferred-revenue',
        'fin-refunded-amount', 'fin-refund-count', 'fin-refund-fees',
        'fin-pending-verification-amount', 'fin-pending-verification-count',
        'fin-outstanding-balances-amount', 'fin-outstanding-balances-count',
        'loading-rev', 'monthlyRevChart',
        'loading-status', 'status-canvas-wrap', 'donut-status-total', 'statusBreakdownChart', 'status-legend-list',
        'badge-total-countries', 'ranking-bars-container', 'country-leaderboard-tbody',
        'payment-methods-tbody', 'top-rooms-tbody', 'room-type-performance-tbody',
        'discounts-summary-container', 'cancellations-summary-container', 'reviews-summary-container',
        'exportBookingsCsvLink', 'exportPaymentsCsvLink', 'activeRangeLabel',
        'reportStartDate', 'reportEndDate',
        'api-connection-error',
        'monthlyRevenueDrilldownModal', 'mrdmTitle', 'mrdmMonthSubtitle',
        'mrdmGrossAmount', 'mrdmRefundedAmount', 'mrdmNetAmount', 'mrdmTxnCount', 'mrdmBodyContent'
    ]) {
        elements[id] = makeEl(id);
    }

    let domReadyHandler = null;
    const requestedUrls = [];
    const windowObj = {
        location: { href: 'admin_reports.php' },
        print() {}
    };

    const ctx = {
        document: {
            documentElement: { getAttribute: () => 'light' },
            getElementById: id => elements[id] || null,
            querySelectorAll: () => [],
            addEventListener: (ev, fn) => {
                if (ev === 'DOMContentLoaded') domReadyHandler = fn;
                if (ev === 'click') clickHandlers.push(fn);
            }
        },
        Chart: function (canvasCtx, cfg) {
            this.cfg = cfg;
            this.destroy = () => {};
            createdCharts.push(cfg);
        },
        Security: {
            escapeHTML: s => String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
        },
        fetch: async url => {
            requestedUrls.push(url);
            return {
                ok: true,
                json: async () => {
                    if (url.includes('dashboard-stats')) {
                        return {
                            gross_revenue: 15000,
                            refunded_amount: 2000,
                            refund_fees: 200,
                            refund_count: 1,
                            net_revenue: 13000,
                            earned_revenue: 9000,
                            deferred_revenue: 6000,
                            pending_verification_count: 2,
                            pending_verification_amount: 3500,
                            outstanding_balances_count: 1,
                            outstanding_balances_amount: 2900,
                            total_bookings: 8,
                            pending_bookings: 3,
                            total_guests: 18,
                            avg_stay: 2.5,
                            occupancy_rate: 45.5,
                            trend: {
                                comparison_label: 'vs previous 30d',
                                net_revenue_pct: 18.4,
                                bookings_pct: 12.0,
                                guests_pct: -5.0,
                                occupancy_pct: 0.0
                            }
                        };
                    }
                    if (url.includes('monthly-revenue-details')) {
                        return {
                            month: '2026-11',
                            month_display: 'November 2026',
                            gross_amount: 15000,
                            refunded_amount: 2000,
                            net_amount: 13000,
                            transaction_count: 1,
                            refund_count: 1,
                            transactions: [{
                                payment_id: 9,
                                invoice_code: 'INV-1009',
                                booking_ref: 'REF-009',
                                guest_name: '<script>alert("xss")</script>',
                                accommodation_name: 'Beach Villa 103',
                                amount: 15000,
                                payment_method: 'GCash QR',
                                transaction_id: 'TXN-999',
                                purpose_label: 'Full Payment',
                                accounting_status: 'earned',
                                paid_time_display: 'Nov 16, 2026 • 02:00 PM'
                            }]
                        };
                    }
                    if (url.includes('monthly-revenue')) {
                        return {
                            months: ['2026-10', '2026-11'],
                            labels: ['Oct 2026', 'Nov 2026'],
                            gross_revenue: [8000, 15000],
                            refunds: [0, 2000],
                            net_revenue: [8000, 13000]
                        };
                    }
                    if (url.includes('status-breakdown')) {
                        return { Confirmed: 1, 'Checked In': 0, 'Checked Out': 4, Pending: 3, Cancelled: 0 };
                    }
                    if (url.includes('country-demographics')) {
                        return {
                            countries: [{ country: '<img src=x onerror=alert(1)>', bookings: 8, unique_customers: 6, booked_guests: 18, revenue: 15000, share_pct: 100 }],
                            total_bookings: 8,
                            total_origins: 1
                        };
                    }
                    if (url.includes('payment-methods')) {
                        return [{ payment_method: 'GCash QR', cnt: 3, total: 15000 }];
                    }
                    if (url.includes('top-accommodations')) {
                        return [{ accommodation_name: 'Beach Villa 103', cnt: 4, guests: 10 }];
                    }
                    if (url.includes('room-type-performance')) {
                        return {
                            period_days: 30,
                            room_types: [{
                                room_type: 'beach_villa',
                                display_name: 'Beach Villa',
                                units: 3,
                                active_units: 3,
                                base_rate: 7900,
                                stays_count: 4,
                                nights_sold: 8,
                                occupancy_pct: 26.7,
                                adr: 7900,
                                revpar: 2106,
                                verified_revenue: 15000
                            }]
                        };
                    }
                    if (url.includes('discounts-and-promos')) {
                        return {
                            active_promotions_count: 2,
                            promo_redemptions_count: 1,
                            combined_discount_total: 1580,
                            promo_breakdown: [{ code: 'SUMMER20', uses_count: 1, discount_total: 790 }],
                            statutory_breakdown: [{ label: 'Senior Citizen (20%)', approved_count: 1, requests_count: 1, discount_total: 790 }]
                        };
                    }
                    if (url.includes('cancellation-insights')) {
                        return {
                            total_bookings: 8,
                            cancelled_bookings: 1,
                            cancellation_rate_pct: 12.5,
                            nonpayment_auto_cancelled: 1,
                            avg_lead_time_days: 5.4,
                            reasons: [{ reason: 'Unpaid Balance / Expired Hold', count: 1, share_pct: 100 }]
                        };
                    }
                    if (url.includes('reviews-summary')) {
                        return {
                            approved_count: 4,
                            pending_count: 1,
                            avg_rating: 4.75,
                            distribution: [
                                { stars: 5, count: 3, share_pct: 75 },
                                { stars: 4, count: 1, share_pct: 25 },
                                { stars: 3, count: 0, share_pct: 0 },
                                { stars: 2, count: 0, share_pct: 0 },
                                { stars: 1, count: 0, share_pct: 0 }
                            ]
                        };
                    }
                    return {};
                }
            };
        },
        console: { error() {} },
        window: windowObj
    };

    vm.runInNewContext(reportsJs, ctx);
    await domReadyHandler();

    assert.equal(elements['kpi-revenue'].innerText, '₱13,000', 'Net revenue populates #kpi-revenue');
    assert.equal(elements['api-connection-error'].style.display, 'none', '#api-connection-error stays hidden when primary analytics data loads');
    assert(elements['kpi-revenue-sub'].innerText.includes('Gross: ₱15,000'), 'Gross & Refunded breakdown populates #kpi-revenue-sub');
    assert.equal(elements['kpi-revenue-trend'].innerText, '▲ +18.4%', 'Trend badge populates #kpi-revenue-trend');
    assert.equal(elements['fin-earned-revenue'].innerText, '₱9,000', 'Earned revenue populates #fin-earned-revenue');
    assert.equal(elements['fin-deferred-revenue'].innerText, '₱6,000', 'Deferred revenue populates #fin-deferred-revenue');
    assert(!elements['country-leaderboard-tbody'].innerHTML.includes('<img'), 'XSS payloads in country names are escaped');

    // Trigger monthly chart bar click -> opens #monthlyRevenueDrilldownModal
    const revChart = createdCharts[0];
    assert.equal(revChart.data.datasets[0].label, 'Gross Verified Revenue (₱)');
    assert.equal(revChart.data.datasets[1].label, 'Net Revenue After Refunds (₱)');
    await revChart.options.onClick({}, [{ index: 1 }]);
    assert.equal(elements['monthlyRevenueDrilldownModal'].style.display, 'flex', 'Clicking a month bar opens #monthlyRevenueDrilldownModal');
    assert(!elements['mrdmBodyContent'].innerHTML.includes('<script>'), 'XSS payloads in monthly drill-down modal are escaped');

    // Trigger status doughnut click -> navigates to admin_reservations.php?status=Checked%20Out
    const statusChart = createdCharts[1];
    statusChart.options.onClick({}, [{ index: 2 }]);
    assert.equal(windowObj.location.href, 'admin_reservations.php?status=Checked%20Out', 'Clicking status slice navigates to filtered reservations');

    console.log('PASS: End-to-end Reports UI, Net/Gross chart, XSS sanitization, and clickable drill-downs verified');
})();
