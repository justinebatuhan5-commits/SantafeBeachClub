const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');

const root = path.resolve(__dirname, '..');
const reportsJs = fs.readFileSync(path.join(root, 'frontend/assets/js/pages/admin-reports.js'), 'utf8');
const analyticsPhp = fs.readFileSync(path.join(root, 'backend/api/analytics_api.php'), 'utf8');

// Extract allocateExactPercentages from admin-reports.js and test in isolation
const fnMatch = reportsJs.match(/function allocateExactPercentages\([\s\S]*?\n    \}/);
assert(fnMatch, 'allocateExactPercentages helper exists in admin-reports.js');
const sandbox = {};
vm.runInNewContext(fnMatch[0], sandbox);
const allocateJS = sandbox.allocateExactPercentages;

// Extract sf_analytics_allocate_percentages from analytics_api.php
const phpFnMatch = analyticsPhp.match(/function sf_analytics_allocate_percentages[\s\S]*?return array_map\([^\n]+\);\r?\n\s*\}/);
assert(phpFnMatch, 'sf_analytics_allocate_percentages helper exists in analytics_api.php');
function allocatePHP(counts, decimals = 1) {
    const script = `${phpFnMatch[0]} echo json_encode(sf_analytics_allocate_percentages(json_decode($argv[1], true), (int)$argv[2]));`;
    const out = execFileSync('php', ['-r', script, JSON.stringify(counts), String(decimals)], { encoding: 'utf8' });
    return JSON.parse(out);
}

// 1. Screenshot case: [Confirmed=1, Checked In=0, Checked Out=4, Pending=3, Cancelled=0] (Total = 8)
{
    const counts = [1, 0, 4, 3, 0];
    const jsPcts = allocateJS(counts, 1);
    const phpPcts = allocatePHP(counts, 1);
    assert.deepEqual(jsPcts, [12.5, 0, 50, 37.5, 0]);
    assert.deepEqual(phpPcts, [12.5, 0, 50, 37.5, 0]);
    assert.equal(Math.round(jsPcts.reduce((a, b) => a + b, 0) * 10), 1000);
    console.log('PASS: [1, 0, 4, 3, 0] produces [12.5, 0, 50, 37.5, 0] summing to 100%');
}

// 2. Repeating decimal & edge cases must always sum to exactly 100%
const distributions = [
    [1, 1, 1],
    [2, 3, 2],
    [1, 1, 1, 1, 1, 1, 1],
    [1, 2, 3, 4, 5],
    [100, 1, 1],
    [7, 11, 13, 17, 19],
    [5, 0, 0, 0, 0],
];
for (const counts of distributions) {
    const jsPcts = allocateJS(counts, 1);
    const phpPcts = allocatePHP(counts, 1);
    assert.deepEqual(jsPcts, phpPcts, `JS and PHP match for [${counts.join(',')}]`);
    const jsSumTenths = Math.round(jsPcts.reduce((a, b) => a + b, 0) * 10);
    assert.equal(jsSumTenths, 1000, `Sum for [${counts.join(',')}] is 100.0% (got ${jsSumTenths / 10}%)`);
}
console.log('PASS: All repeating-decimal distributions sum to exactly 100.0% in both JS and PHP');

// 3. Zero-total case
assert.deepEqual(allocateJS([0, 0, 0, 0, 0], 1), [0, 0, 0, 0, 0]);
assert.deepEqual(allocatePHP([0, 0, 0, 0, 0], 1), [0, 0, 0, 0, 0]);
console.log('PASS: Zero-total distribution safely returns all 0%');

// 4. End-to-end simulation of admin-reports.js status legend & doughnut tooltip
(async () => {
    let capturedChartConfig = null;
    const legendEl = { innerHTML: '' };
    const totalEl = { innerText: '' };
    const statusCanvas = { getContext: () => ({}) };
    const elements = {
        'loading-status': { style: {} },
        'status-canvas-wrap': { style: {} },
        'donut-status-total': totalEl,
        'statusBreakdownChart': statusCanvas,
        'status-legend-list': legendEl,
    };

    let domReadyHandler = null;
    const ctx = {
        document: {
            documentElement: { getAttribute: () => 'light' },
            getElementById: id => elements[id] || null,
            addEventListener: (ev, fn) => { if (ev === 'DOMContentLoaded') domReadyHandler = fn; },
        },
        Chart: function (_, cfg) { capturedChartConfig = cfg; },
        Security: { escapeHTML: s => String(s) },
        fetch: async url => ({
            ok: true,
            json: async () => {
                if (url.includes('status-breakdown')) {
                    return { Confirmed: 1, 'Checked In': 0, 'Checked Out': 4, Pending: 3, Cancelled: 0 };
                }
                if (url.includes('country-demographics')) {
                    return { countries: [], total_bookings: 0, total_origins: 0 };
                }
                return {};
            },
        }),
        console: { error: () => {} },
        window: { print: () => {} },
    };

    vm.runInNewContext(reportsJs, ctx);
    assert(typeof domReadyHandler === 'function', 'DOMContentLoaded handler registered');
    await domReadyHandler();

    assert.equal(totalEl.innerText, '8');
    assert(legendEl.innerHTML.includes('12.5% (1)'), 'Legend shows 12.5% (1) for Confirmed');
    assert(legendEl.innerHTML.includes('50% (4)'), 'Legend shows 50% (4) for Checked Out');
    assert(legendEl.innerHTML.includes('37.5% (3)'), 'Legend shows 37.5% (3) for Pending');
    assert(!legendEl.innerHTML.includes('13% (1)'), 'Rounded 13% bug is gone');
    assert(!legendEl.innerHTML.includes('38% (3)'), 'Rounded 38% bug is gone');

    const tooltipCb = capturedChartConfig.options.plugins.tooltip.callbacks.label;
    assert.equal(tooltipCb({ label: 'Confirmed', parsed: 1, dataIndex: 0 }), ' Confirmed: 1 (12.5%)');
    assert.equal(tooltipCb({ label: 'Pending', parsed: 3, dataIndex: 3 }), ' Pending: 3 (37.5%)');
    console.log('PASS: End-to-end admin-reports.js status legend & chart tooltips match and sum to 100%');
})();
