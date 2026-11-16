const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const jsSource = fs.readFileSync(path.join(root, 'frontend/assets/js/pages/admin-dashboard.js'), 'utf8');
const modalHtml = fs.readFileSync(path.join(root, 'frontend/views/admin_dashboard/_revenue_modal.php'), 'utf8');
const dashPhp = fs.readFileSync(path.join(root, 'frontend/admin_dashboard.php'), 'utf8');
const kpiPartial = fs.readFileSync(path.join(root, 'frontend/views/admin_dashboard/_kpi_and_charts.php'), 'utf8');

assert(dashPhp.includes('_revenue_modal.php'), 'admin_dashboard.php includes _revenue_modal.php');
assert(modalHtml.includes('id="revenueDrilldownModal"'), 'Revenue drilldown modal markup exists');
assert(kpiPartial.includes('Click any day to view details'), 'Chart subtitle informs staff that days are clickable');

(async () => {
    const elements = {
        adminDashboardPageData: { textContent: JSON.stringify({ overdueGuestNames: [] }) },
        'kpi-daily-rev': { innerText: '' },
        'kpi-weekly-rev': { innerText: '' },
        'kpi-occupancy-rate': { innerText: '' },
        'kpi-total-bookings': { innerText: '' },
        'link-total-bookings': { innerText: '' },
        'kpi-reserved-rooms': { innerText: '' },
        'kpi-pending-payments': { innerText: '' },
        'loading-rev': { style: {} },
        revenueChart: { style: {}, getContext: () => ({}) },
        revenueDrilldownModal: { style: { display: 'none' }, closest: () => null },
        rdmTitle: { textContent: '' },
        rdmDateSubtitle: { textContent: '' },
        rdmTotalAmount: { textContent: '' },
        rdmTxnCount: { textContent: '' },
        rdmBodyContent: { innerHTML: '' },
        'recent-bookings-tbody': { innerHTML: '' },
        'recent-logs-container': { innerHTML: '' },
        'security-alert-banner': { style: {} },
    };

    let domReady = null;
    let keydownHandler = null;
    let clickHandler = null;
    let capturedChart = null;
    const requestedUrls = [];

    const ctx = {
        document: {
            documentElement: { getAttribute: () => 'light' },
            getElementById: id => elements[id] || null,
            querySelectorAll: () => [],
            addEventListener: (ev, fn) => {
                if (ev === 'DOMContentLoaded') domReady = fn;
                if (ev === 'keydown') keydownHandler = fn;
                if (ev === 'click') clickHandler = fn;
            },
        },
        window: {},
        Security: {
            escapeHTML: s => String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;'),
            safeURL: u => u,
        },
        Chart: function (_, cfg) {
            capturedChart = {
                cfg,
                canvas: { style: { cursor: 'default' } },
                getElementsAtEventForMode: () => [{ index: 4 }],
            };
            return capturedChart;
        },
        fetch: async url => {
            requestedUrls.push(url);
            if (url.includes('action=weekly-revenue-trajectory')) {
                return {
                    ok: true,
                    json: async () => ({
                        labels: ['Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Mon', 'Tue'],
                        dates: ['2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-15', '2026-11-16', '2026-11-17'],
                        data: [0, 0, 0, 0, 3950, 0, 0],
                    }),
                };
            }
            if (url.includes('action=daily-revenue-details')) {
                return {
                    ok: true,
                    json: async () => ({
                        date: '2026-11-15',
                        date_display: 'Sun, Nov 15, 2026',
                        total_amount: 3950,
                        transaction_count: 1,
                        transactions: [{
                            payment_id: 7,
                            invoice_code: 'INV-1007',
                            booking_ref: 'REF-007',
                            guest_name: '<script>alert(1)</script>Maria',
                            accommodation_name: 'Beach Villa 103',
                            amount: 3950,
                            payment_method: 'GCash QR',
                            transaction_id: 'GC-9988',
                            purpose_label: '50% Deposit',
                            paid_time_display: '03:45 PM',
                        }],
                    }),
                };
            }
            if (url.includes('action=recent-bookings') || url.includes('action=recent-logs')) {
                return { ok: true, json: async () => [] };
            }
            return { ok: true, json: async () => ({}) };
        },
        setInterval: () => 0,
        setTimeout: fn => fn(),
        URLSearchParams,
        console: { error: (...args) => console.error('VM console.error:', ...args) },
    };

    vm.runInNewContext(jsSource, ctx);
    assert(typeof domReady === 'function', 'DOMContentLoaded registered');
    domReady();
    await new Promise(resolve => setTimeout(resolve, 25));

    assert(capturedChart !== null, 'Revenue chart initialized');
    const opts = capturedChart.cfg.options;
    assert.equal(opts.interaction.mode, 'index');
    assert.equal(opts.interaction.intersect, false);

    // Check Y-axis tick formatting fixes duplicate rounded labels like 3.5k -> 4k
    const yTick = opts.scales.y.ticks.callback;
    assert.equal(yTick(3500), '₱3.5k');
    assert.equal(yTick(4000), '₱4k');
    assert.equal(yTick(500), '₱500');

    // Hover sets pointer cursor
    opts.onHover({}, [{ index: 4 }], capturedChart);
    assert.equal(capturedChart.canvas.style.cursor, 'pointer');
    opts.onHover({}, [], capturedChart);
    assert.equal(capturedChart.canvas.style.cursor, 'default');

    // Click triggers drill-down fetch & renders XSS-escaped modal content
    opts.onClick({}, [{ index: 4 }], capturedChart);
    await new Promise(resolve => setTimeout(resolve, 10));

    assert(requestedUrls.some(u => u.includes('action=daily-revenue-details') && u.includes('date=2026-11-15')), 'Requested daily-revenue-details for clicked Sunday 2026-11-15');
    assert.equal(elements.revenueDrilldownModal.style.display, 'flex');
    assert.equal(elements.rdmDateSubtitle.textContent, 'Sun, Nov 15, 2026');
    assert.equal(elements.rdmTxnCount.textContent, '1');
    assert(elements.rdmBodyContent.innerHTML.includes('INV-1007'));
    assert(elements.rdmBodyContent.innerHTML.includes('&lt;script&gt;alert(1)&lt;/script&gt;Maria'), 'Guest name is HTML-escaped in modal');
    assert(!elements.rdmBodyContent.innerHTML.includes('<script>alert(1)</script>'), 'No unescaped script tag rendered');

    // Escape closes modal
    keydownHandler({ key: 'Escape' });
    assert.equal(elements.revenueDrilldownModal.style.display, 'none');

    // Re-open and close via backdrop click
    elements.revenueDrilldownModal.style.display = 'flex';
    clickHandler({ target: elements.revenueDrilldownModal });
    assert.equal(elements.revenueDrilldownModal.style.display, 'none');

    console.log('ALL REVENUE CHART FRONTEND DRILL-DOWN TESTS PASSED!');
})();
