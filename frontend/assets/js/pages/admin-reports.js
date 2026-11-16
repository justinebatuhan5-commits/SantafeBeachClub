(function () {
    'use strict';

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(15, 23, 42, 0.06)';
    const tickColor = isDark ? '#94A3B8' : '#64748B';

    let monthlyRevChartInstance = null;
    let statusBreakdownChartInstance = null;
    let monthlyDrilldownSeq = 0;

    const currentFilter = {
        range: 'all',
        startDate: '',
        endDate: ''
    };

    function safeEscapeHTML(value) {
        const raw = String(value ?? '');
        if (typeof Security !== 'undefined' && Security && typeof Security.escapeHTML === 'function') {
            return Security.escapeHTML(raw);
        }
        return raw
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/`/g, '&#96;');
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.innerText = String(value ?? '');
        }
    }

    function formatPeso(amount, decimals = 0) {
        return '₱' + Number(amount || 0).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function renderTrendBadge(id, pctValue, comparisonLabel) {
        const el = document.getElementById(id);
        if (!el) return;
        if (pctValue === null || pctValue === undefined || Number.isNaN(Number(pctValue))) {
            el.className = 'kpi-card-badge trend-flat';
            el.innerText = 'New';
            el.title = comparisonLabel || 'vs previous period';
            return;
        }
        const num = Number(pctValue);
        if (num > 0) {
            el.className = 'kpi-card-badge trend-up';
            el.innerText = `▲ +${num.toFixed(1)}%`;
        } else if (num < 0) {
            el.className = 'kpi-card-badge trend-down';
            el.innerText = `▼ ${num.toFixed(1)}%`;
        } else {
            el.className = 'kpi-card-badge trend-flat';
            el.innerText = '0.0%';
        }
        el.title = comparisonLabel || 'vs previous period';
    }

    function allocateExactPercentages(counts, decimals = 1) {
        const safeCounts = (counts || []).map(v => Math.max(0, Number(v) || 0));
        const total = safeCounts.reduce((a, b) => a + b, 0);
        if (total <= 0) {
            return safeCounts.map(() => 0);
        }

        const factor = Math.pow(10, decimals);
        const targetUnits = 100 * factor;
        const raw = safeCounts.map((count, idx) => {
            const exact = (count * targetUnits) / total;
            const floored = Math.floor(exact + 1e-9);
            return {
                idx,
                count,
                floored,
                remainder: Math.max(0, exact - floored)
            };
        });

        let remaining = targetUnits - raw.reduce((sum, item) => sum + item.floored, 0);
        if (remaining > 0) {
            const ranked = raw
                .filter(item => item.count > 0)
                .slice()
                .sort((a, b) => (b.remainder - a.remainder) || (b.count - a.count) || (a.idx - b.idx));
            for (let i = 0; i < ranked.length && remaining > 0; i++) {
                ranked[i].floored += 1;
                remaining -= 1;
            }
        } else if (remaining < 0) {
            const ranked = raw
                .filter(item => item.floored > 0)
                .slice()
                .sort((a, b) => (a.remainder - b.remainder) || (a.count - b.count) || (b.idx - a.idx));
            for (let i = 0; i < ranked.length && remaining < 0; i++) {
                ranked[i].floored -= 1;
                remaining += 1;
            }
        }

        return raw.map(item => Number((item.floored / factor).toFixed(decimals)));
    }

    function buildFilterQueryParams() {
        const parts = [];
        if (currentFilter.range && currentFilter.range !== 'all') {
            parts.push('range=' + encodeURIComponent(currentFilter.range));
        }
        if (currentFilter.startDate && currentFilter.endDate && currentFilter.range !== 'all') {
            parts.push('start_date=' + encodeURIComponent(currentFilter.startDate));
            parts.push('end_date=' + encodeURIComponent(currentFilter.endDate));
        }
        return parts.length > 0 ? '&' + parts.join('&') : '';
    }

    function syncCsvExportLinks() {
        const bookingsLink = document.getElementById('exportBookingsCsvLink');
        const paymentsLink = document.getElementById('exportPaymentsCsvLink');
        let dateSuffix = '';
        if (currentFilter.range !== 'all' && currentFilter.startDate && currentFilter.endDate) {
            dateSuffix = `&start_date=${encodeURIComponent(currentFilter.startDate)}&end_date=${encodeURIComponent(currentFilter.endDate)}`;
        }
        if (bookingsLink) {
            bookingsLink.setAttribute('href', `../backend/api/availability.php?action=export_report_csv&type=bookings${dateSuffix}`);
        }
        if (paymentsLink) {
            paymentsLink.setAttribute('href', `../backend/api/availability.php?action=export_report_csv&type=payments${dateSuffix}`);
        }
    }

    async function fetchAPI(endpoint, extraParams = '') {
        try {
            const action = endpoint.replace('/api/', '').replace(/^\//, '');
            const filterQs = buildFilterQueryParams();
            const extraQs = extraParams ? (extraParams.startsWith('&') ? extraParams : '&' + extraParams) : '';
            const res = await fetch(`../backend/api/analytics_proxy.php?action=${encodeURIComponent(action)}${filterQs}${extraQs}`, {
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

    function navigateToReservationsByStatus(statusLabel) {
        const allowed = ['Pending', 'Confirmed', 'Checked In', 'Checked Out', 'Cancelled'];
        const normalized = allowed.includes(statusLabel) ? statusLabel : 'Pending';
        if (typeof window !== 'undefined' && window.location) {
            window.location.href = `admin_reservations.php?status=${encodeURIComponent(normalized)}`;
        }
    }

    function navigateToPaymentsByMethod(methodName) {
        const method = String(methodName || '').trim();
        if (typeof window !== 'undefined' && window.location) {
            window.location.href = `payments.php?status=verified&search=${encodeURIComponent(method)}`;
        }
    }

    function closeMonthlyRevenueModal() {
        const modal = document.getElementById('monthlyRevenueDrilldownModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    async function openMonthlyRevenueModal(monthKey, monthLabel) {
        const modal = document.getElementById('monthlyRevenueDrilldownModal');
        if (!modal || !monthKey) return;

        const seq = ++monthlyDrilldownSeq;
        modal.style.display = 'flex';
        setText('mrdmTitle', `Verified Receipts — ${monthLabel || monthKey}`);
        setText('mrdmMonthSubtitle', `Loading settlement records for ${monthLabel || monthKey}...`);
        setText('mrdmGrossAmount', '₱0.00');
        setText('mrdmRefundedAmount', '₱0.00');
        setText('mrdmNetAmount', '₱0.00');
        setText('mrdmTxnCount', '...');

        const bodyEl = document.getElementById('mrdmBodyContent');
        if (bodyEl) {
            bodyEl.innerHTML = '<div class="rdm-state">Loading monthly receipts...</div>';
        }

        try {
            const details = await fetchAPI('/api/monthly-revenue-details', `month=${encodeURIComponent(monthKey)}`);
            if (seq !== monthlyDrilldownSeq) return;

            setText('mrdmTitle', `Verified Receipts — ${details.month_display || monthLabel || monthKey}`);
            setText('mrdmMonthSubtitle', `${details.transaction_count || 0} verified receipt(s) • ${details.refund_count || 0} approved refund(s)`);
            setText('mrdmGrossAmount', formatPeso(details.gross_amount ?? details.total_amount ?? 0, 2));
            setText('mrdmRefundedAmount', formatPeso(details.refunded_amount ?? 0, 2));
            setText('mrdmNetAmount', formatPeso(details.net_amount ?? details.total_amount ?? 0, 2));
            setText('mrdmTxnCount', details.transaction_count ?? 0);

            const txns = Array.isArray(details.transactions) ? details.transactions : [];
            if (!bodyEl) return;

            if (txns.length === 0) {
                bodyEl.innerHTML = `<div class="rdm-state">No verified payments recorded in ${safeEscapeHTML(details.month_display || monthLabel || monthKey)}.</div>`;
                return;
            }

            const rowsHtml = txns.map(t => {
                const guest = safeEscapeHTML(t.guest_name || 'Guest');
                const ref = t.booking_ref ? `${safeEscapeHTML(t.booking_ref)} • ` : '';
                const acc = safeEscapeHTML(t.accommodation_name || 'Accommodation');
                const purpose = safeEscapeHTML(t.purpose_label || 'Payment');
                const method = safeEscapeHTML(t.payment_method || 'Front Desk Cash');
                const accounting = safeEscapeHTML((t.accounting_status || 'deferred').toUpperCase());
                const inv = safeEscapeHTML(t.invoice_code || '');
                const txnId = safeEscapeHTML(t.transaction_id || '');
                const paidTime = safeEscapeHTML(t.paid_time_display || t.paid_at || '—');
                const amt = formatPeso(t.amount || 0, 2);

                return `
                    <tr>
                        <td>
                            <strong>${guest}</strong>
                            <span class="rdm-sub">${ref}${acc}</span>
                        </td>
                        <td>
                            <span class="rdm-pill">${purpose}</span>
                            <span class="rdm-sub">${method} • ${accounting}</span>
                        </td>
                        <td>
                            <strong>${inv}</strong>
                            <span class="rdm-sub">${txnId}</span>
                        </td>
                        <td>${paidTime}</td>
                        <td style="text-align:right; font-weight:800;">${amt}</td>
                    </tr>
                `;
            }).join('');

            bodyEl.innerHTML = `
                <div class="table-responsive">
                    <table class="rdm-table">
                        <thead>
                            <tr>
                                <th>Guest &amp; Stay</th>
                                <th>Type &amp; Accounting</th>
                                <th>Reference</th>
                                <th>Paid Date</th>
                                <th style="text-align:right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </div>
            `;
        } catch (err) {
            if (seq !== monthlyDrilldownSeq) return;
            console.error('Monthly drill-down error:', err);
            setText('mrdmTxnCount', '0');
            if (bodyEl) {
                bodyEl.innerHTML = '<div class="rdm-state" style="color:#DC2626;">Could not load receipts for this month.</div>';
            }
        }
    }

    async function loadReportsData() {
        syncCsvExportLinks();

        // 1. KPI Stats & Financial Health
        try {
            const stats = await fetchAPI('/api/dashboard-stats');
            const netRev = stats.net_revenue !== undefined ? Number(stats.net_revenue) : Number(stats.total_revenue || 0);
            const grossRev = Number(stats.gross_revenue ?? stats.total_revenue ?? 0);
            const refundedAmt = Number(stats.refunded_amount || 0);

            setText('kpi-revenue', formatPeso(netRev, 0));
            setText('kpi-bookings', Number(stats.total_bookings || 0).toLocaleString());
            setText('kpi-pending', stats.pending_bookings || 0);
            setText('kpi-guests', Number(stats.total_guests || 0).toLocaleString());
            setText('kpi-occupancy', (stats.occupancy_rate || 0) + '%');
            setText('kpi-avg-stay', (stats.avg_stay || '0') + ' nights');

            const revSub = document.getElementById('kpi-revenue-sub');
            if (revSub) {
                revSub.innerText = refundedAmt > 0
                    ? `Gross: ${formatPeso(grossRev, 0)} • Refunded: -${formatPeso(refundedAmt, 0)}`
                    : `Gross verified: ${formatPeso(grossRev, 0)} (₱0 refunded)`;
            }

            if (stats.date_range && stats.date_range.label) {
                setText('activeRangeLabel', `Showing: ${stats.date_range.label}`);
                if (!currentFilter.startDate && stats.date_range.start_date) {
                    const startInput = document.getElementById('reportStartDate');
                    const endInput = document.getElementById('reportEndDate');
                    if (startInput && !startInput.value) startInput.value = stats.date_range.start_date;
                    if (endInput && !endInput.value) endInput.value = stats.date_range.end_date;
                }
            }

            const trend = stats.trend || {};
            const cmpLabel = trend.comparison_label || 'vs previous 30d';
            renderTrendBadge('kpi-revenue-trend', trend.net_revenue_pct ?? trend.revenue_pct, cmpLabel);
            renderTrendBadge('kpi-bookings-trend', trend.bookings_pct, cmpLabel);
            renderTrendBadge('kpi-guests-trend', trend.guests_pct, cmpLabel);
            renderTrendBadge('kpi-occupancy-trend', trend.occupancy_pct, cmpLabel);

            // Financial Health & Accounting Strip
            setText('fin-earned-revenue', formatPeso(stats.earned_revenue || 0, 0));
            setText('fin-deferred-revenue', formatPeso(stats.deferred_revenue || 0, 0));
            setText('fin-refunded-amount', formatPeso(refundedAmt, 0));
            setText('fin-refund-count', stats.refund_count || 0);
            setText('fin-refund-fees', formatPeso(stats.refund_fees || 0, 0));
            setText('fin-pending-verification-amount', formatPeso(stats.pending_verification_amount || 0, 0));
            setText('fin-pending-verification-count', stats.pending_verification_count || 0);
            setText('fin-outstanding-balances-amount', formatPeso(stats.outstanding_balances_amount || 0, 0));
            setText('fin-outstanding-balances-count', stats.outstanding_balances_count || 0);
        } catch (e) {
            console.error('KPI load error:', e);
        }

        // 2. Revenue Overview (Gross Verified Bars vs Net Revenue Line + Clickable Drill-Down)
        try {
            const revData = await fetchAPI('/api/monthly-revenue');
            const loadingRev = document.getElementById('loading-rev');
            if (loadingRev) loadingRev.style.display = 'none';
            const revCanvas = document.getElementById('monthlyRevChart');
            if (revCanvas && typeof Chart !== 'undefined') {
                revCanvas.style.display = 'block';
                const monthKeys = revData.months || [];
                const monthLabels = revData.labels || [];
                const grossValues = (revData.gross_revenue || revData.revenue || []).map(v => Number(v) || 0);
                const refundValues = (revData.refunds || []).map(v => Number(v) || 0);
                const netValues = (revData.net_revenue || grossValues.map((g, idx) => Math.max(0, g - (refundValues[idx] || 0)))).map(v => Number(v) || 0);

                if (monthlyRevChartInstance && typeof monthlyRevChartInstance.destroy === 'function') {
                    monthlyRevChartInstance.destroy();
                }

                monthlyRevChartInstance = new Chart(revCanvas.getContext('2d'), {
                    data: {
                        labels: monthLabels,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Gross Verified Revenue (₱)',
                                data: grossValues,
                                backgroundColor: '#84563C',
                                hoverBackgroundColor: '#6B442E',
                                borderRadius: 4,
                                barThickness: 18,
                                order: 2
                            },
                            {
                                type: 'line',
                                label: 'Net Revenue After Refunds (₱)',
                                data: netValues,
                                borderColor: '#0D652D',
                                backgroundColor: '#0D652D',
                                borderWidth: 2,
                                pointRadius: 3.5,
                                pointHoverRadius: 5.5,
                                pointBackgroundColor: '#0D652D',
                                tension: 0.35,
                                order: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onHover: (event, elements) => {
                            const target = event && event.native ? event.native.target : revCanvas;
                            if (target && target.style) {
                                target.style.cursor = (elements && elements.length > 0) ? 'pointer' : 'default';
                            }
                        },
                        onClick: (event, elements) => {
                            if (elements && elements.length > 0) {
                                const idx = elements[0].index;
                                const mKey = monthKeys[idx];
                                const mLabel = monthLabels[idx];
                                if (mKey) {
                                    openMonthlyRevenueModal(mKey, mLabel);
                                }
                            }
                        },
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
                                    label: c => ` ${c.dataset.label}: ₱${Number(c.parsed.y).toLocaleString()}`,
                                    afterBody: items => {
                                        if (!items || !items.length) return '';
                                        const idx = items[0].dataIndex;
                                        const refAmt = refundValues[idx] || 0;
                                        const lines = [];
                                        if (refAmt > 0) {
                                            lines.push(` Refunded in month: -₱${Number(refAmt).toLocaleString()}`);
                                        }
                                        lines.push(' Click bar to view monthly receipts');
                                        return lines;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { family: 'Plus Jakarta Sans', size: 10.5 } } },
                            y: {
                                grid: { color: gridColor },
                                ticks: {
                                    color: tickColor,
                                    font: { family: 'Plus Jakarta Sans', size: 10.5 },
                                    callback: v => '₱' + (v >= 1000 ? Number((v / 1000).toFixed(1)) + 'k' : v)
                                },
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Revenue overview load error:', e);
            const loadingRev = document.getElementById('loading-rev');
            if (loadingRev) loadingRev.innerHTML = '<span class="error">Failed to load revenue overview.</span>';
        }

        // 3. Reservation Status (Doughnut + Clickable Legend / Slice Drill-Down)
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

            const statusEntries = Object.entries(statusCounts);
            const statusLabels = Object.keys(statusCounts);
            const statusValues = statusEntries.map(([, count]) => count);
            const statusPcts = allocateExactPercentages(statusValues, 1);

            const statusCanvas = document.getElementById('statusBreakdownChart');
            if (statusCanvas && typeof Chart !== 'undefined') {
                if (statusBreakdownChartInstance && typeof statusBreakdownChartInstance.destroy === 'function') {
                    statusBreakdownChartInstance.destroy();
                }

                statusBreakdownChartInstance = new Chart(statusCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: totalStatus > 0 ? statusValues : [1],
                            backgroundColor: totalStatus > 0 ? Object.values(statusColors) : ['#E2E8F0'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        onHover: (event, elements) => {
                            const target = event && event.native ? event.native.target : statusCanvas;
                            if (target && target.style) {
                                target.style.cursor = (totalStatus > 0 && elements && elements.length > 0) ? 'pointer' : 'default';
                            }
                        },
                        onClick: (event, elements) => {
                            if (totalStatus > 0 && elements && elements.length > 0) {
                                const idx = elements[0].index;
                                const clickedStatus = statusLabels[idx];
                                if (clickedStatus) {
                                    navigateToReservationsByStatus(clickedStatus);
                                }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: totalStatus > 0,
                                callbacks: {
                                    label: ctx => ` ${ctx.label}: ${ctx.parsed} (${statusPcts[ctx.dataIndex] ?? 0}%)`
                                }
                            }
                        }
                    }
                });
            }

            const statusLegendList = document.getElementById('status-legend-list');
            if (statusLegendList) {
                statusLegendList.innerHTML = statusEntries.map(([status, count], idx) => {
                    const pct = statusPcts[idx] ?? 0;
                    const col = statusColors[status] || '#84563C';
                    const safeStatus = safeEscapeHTML(status);
                    return `
                    <div class="donut-legend-item clickable-status-item" data-drilldown-status="${safeStatus}" title="View ${safeStatus} reservations">
                        <div class="donut-legend-left">
                            <span class="donut-legend-dot" style="background:${col};"></span>
                            <span>${safeStatus}</span>
                        </div>
                        <div class="donut-legend-right">${pct}% (${count}) &rarr;</div>
                    </div>
                    `;
                }).join('');
            }
        } catch (e) {
            console.error('Status breakdown load error:', e);
        }

        // 4. Top Source Markets & Country Demographics
        let countryList = [];
        try {
            const countryData = await fetchAPI('/api/country-demographics');
            countryList = countryData.countries || [];
            const badgeCountries = document.getElementById('badge-total-countries');
            if (badgeCountries) badgeCountries.innerText = `${countryData.total_origins || countryList.length} Origins`;

            const rankingContainer = document.getElementById('ranking-bars-container');
            const fallbackCountryPcts = allocateExactPercentages(countryList.map(c => c.bookings), 1);

            if (rankingContainer) {
                if (countryList.length > 0) {
                    const top5Markets = countryList.slice(0, 5);
                    rankingContainer.innerHTML = top5Markets.map((c, idx) => {
                        const pct = (c.share_pct !== undefined && c.share_pct !== null)
                            ? Number(c.share_pct)
                            : (fallbackCountryPcts[idx] ?? 0);
                        const safeCountry = safeEscapeHTML(c.country);
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
                    rankingContainer.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">No guest country data in selected range.</div>';
                }
            }
        } catch (e) {
            console.error('Country demographics error:', e);
            const rankingContainer = document.getElementById('ranking-bars-container');
            if (rankingContainer) rankingContainer.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">No guest country data in selected range.</div>';
        }

        // 5. Payment Methods Table (Clickable Drill-Down to Payments)
        try {
            const payments = await fetchAPI('/api/payment-methods');
            const pTbody = document.getElementById('payment-methods-tbody');
            if (pTbody) {
                if (payments && payments.length > 0) {
                    pTbody.innerHTML = payments.map(p => {
                        const safeMethod = safeEscapeHTML(p.payment_method || 'Front Desk Cash');
                        return `
                        <tr class="clickable-report-row" data-drilldown-payment="${safeMethod}" title="Inspect verified ${safeMethod} payments">
                            <td style="font-weight:600;">${safeMethod} <span style="color:var(--text-muted); font-size:11px;">&rarr;</span></td>
                            <td style="color:var(--text-muted); font-weight:600;">${p.cnt} payments</td>
                            <td style="text-align:right; font-weight:700; color:var(--text-main);">₱${Number(p.total).toLocaleString()}</td>
                        </tr>
                        `;
                    }).join('');
                } else {
                    pTbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:24px; color:var(--text-muted);">No verified payment records in selected range.</td></tr>';
                }
            }
        } catch (e) {
            console.error('Payment methods error:', e);
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
                        const safeRoom = safeEscapeHTML(r.accommodation_name);
                        return `
                        <tr>
                            <td style="font-weight:600;">${safeRoom}</td>
                            <td style="color:var(--text-muted); font-weight:600;">${r.cnt} stays</td>
                            <td style="text-align:right; font-weight:700; color:var(--primary);">${r.guests} guests</td>
                        </tr>
                        `;
                    }).join('');
                } else {
                    rTbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:24px; color:var(--text-muted);">No room booking records in selected range.</td></tr>';
                }
            }
        } catch (e) {
            console.error('Top accommodations error:', e);
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
                        const safeCountry = safeEscapeHTML(c.country);
                        const clampedPct = Math.max(0, Math.min(100, Number(c.share_pct) || 0));

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
                                        <div class="progress-bar-fill" style="width:${clampedPct}%;"></div>
                                    </div>
                                    <span style="font-size:11.5px; font-weight:700; color:var(--text-muted);">${clampedPct}%</span>
                                </div>
                            </td>
                            <td style="text-align:right; font-weight:700; color:var(--text-main);">₱${Number(c.revenue || 0).toLocaleString()}</td>
                        </tr>
                        `;
                    }).join('');
                } else {
                    cTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">No guest country data recorded in selected range.</td></tr>';
                }
            }
        } catch (e) {
            console.error('Country leaderboard error:', e);
            const cTbody = document.getElementById('country-leaderboard-tbody');
            if (cTbody) cTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">No guest country data recorded yet.</td></tr>';
        }

        // 8. Room-Type Performance (ADR, RevPAR & Occupancy)
        const rtTbody = document.getElementById('room-type-performance-tbody');
        if (rtTbody) {
            try {
                const rtData = await fetchAPI('/api/room-type-performance');
                const roomTypes = (rtData && Array.isArray(rtData.room_types)) ? rtData.room_types : [];
                if (roomTypes.length > 0) {
                    rtTbody.innerHTML = roomTypes.map(rt => {
                        const safeName = safeEscapeHTML(rt.display_name || rt.room_type);
                        const occPct = Math.max(0, Math.min(100, Number(rt.occupancy_pct) || 0));
                        return `
                        <tr>
                            <td style="font-weight:700;">
                                ${safeName}
                                <div style="font-size:11px; color:var(--text-muted); font-weight:500;">Base: ${formatPeso(rt.base_rate || 0, 0)}/night</div>
                            </td>
                            <td style="color:var(--text-muted); font-weight:600;">${rt.active_units} / ${rt.units} ready</td>
                            <td style="color:var(--text-muted); font-weight:600;">${rt.stays_count} stays (${rt.nights_sold} nights)</td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div class="progress-bar-bg">
                                        <div class="progress-bar-fill" style="width:${occPct}%;"></div>
                                    </div>
                                    <span style="font-size:11.5px; font-weight:700; color:var(--text-muted);">${occPct}%</span>
                                </div>
                            </td>
                            <td style="font-weight:700;">${formatPeso(rt.adr || 0, 0)}</td>
                            <td style="font-weight:700; color:#84563C;">${formatPeso(rt.revpar || 0, 0)}</td>
                            <td style="text-align:right; font-weight:800; color:var(--text-main);">${formatPeso(rt.verified_revenue || 0, 0)}</td>
                        </tr>
                        `;
                    }).join('');
                } else {
                    rtTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:24px; color:var(--text-muted);">No room categories found.</td></tr>';
                }
            } catch (e) {
                console.error('Room-type performance error:', e);
                rtTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:24px; color:var(--text-muted);">Could not load room-type metrics.</td></tr>';
            }
        }

        // 9. Promotions & Statutory Discounts Insights
        const discEl = document.getElementById('discounts-summary-container');
        if (discEl) {
            try {
                const disc = await fetchAPI('/api/discounts-and-promos');
                const promos = Array.isArray(disc.promo_breakdown) ? disc.promo_breakdown : [];
                const statutory = Array.isArray(disc.statutory_breakdown) ? disc.statutory_breakdown : [];

                const listItems = [];
                promos.slice(0, 3).forEach(p => {
                    listItems.push(`
                        <div class="insight-list-row">
                            <span><strong>${safeEscapeHTML(p.code)}</strong> <span style="color:var(--text-muted);">(${p.uses_count} uses)</span></span>
                            <strong style="color:#0D652D;">-${formatPeso(p.discount_total || 0, 0)}</strong>
                        </div>
                    `);
                });
                statutory.forEach(s => {
                    listItems.push(`
                        <div class="insight-list-row">
                            <span><strong>${safeEscapeHTML(s.label)}</strong> <span style="color:var(--text-muted);">(${s.approved_count}/${s.requests_count} approved)</span></span>
                            <strong style="color:#2563EB;">-${formatPeso(s.discount_total || 0, 0)}</strong>
                        </div>
                    `);
                });

                discEl.innerHTML = `
                    <div class="insight-stat-grid">
                        <div class="insight-stat-box">
                            <span class="lbl">Total Savings Granted</span>
                            <span class="val" style="color:#0D652D;">${formatPeso(disc.combined_discount_total || 0, 0)}</span>
                        </div>
                        <div class="insight-stat-box">
                            <span class="lbl">Promo Redemptions</span>
                            <span class="val">${disc.promo_redemptions_count || 0} (${disc.active_promotions_count || 0} active)</span>
                        </div>
                    </div>
                    <div class="insight-list">
                        ${listItems.length > 0 ? listItems.join('') : '<div style="font-size:12.5px; color:var(--text-muted); text-align:center; padding:12px 0;">No promo or Senior/PWD discounts recorded in range.</div>'}
                    </div>
                `;
            } catch (e) {
                console.error('Discounts summary error:', e);
                discEl.innerHTML = '<div class="error">Failed to load discount metrics.</div>';
            }
        }

        // 10. Cancellations & Booking Lead-Time Insights
        const canEl = document.getElementById('cancellations-summary-container');
        if (canEl) {
            try {
                const canData = await fetchAPI('/api/cancellation-insights');
                const reasons = Array.isArray(canData.reasons) ? canData.reasons : [];
                const reasonHtml = reasons.slice(0, 4).map(r => `
                    <div class="insight-list-row">
                        <span>${safeEscapeHTML(r.reason)}</span>
                        <strong>${r.share_pct ?? 0}% (${r.count})</strong>
                    </div>
                `).join('');

                canEl.innerHTML = `
                    <div class="insight-stat-grid">
                        <div class="insight-stat-box">
                            <span class="lbl">Cancellation Rate</span>
                            <span class="val" style="color:#DC2626;">${canData.cancellation_rate_pct ?? 0}% (${canData.cancelled_bookings ?? 0})</span>
                        </div>
                        <div class="insight-stat-box">
                            <span class="lbl">Avg Booking Lead Time</span>
                            <span class="val">${canData.avg_lead_time_days ?? 0} days</span>
                        </div>
                    </div>
                    <div style="font-size:11.5px; color:var(--text-muted); margin-bottom:8px;">
                        Unpaid balance auto-expiries: <strong>${canData.nonpayment_auto_cancelled ?? 0}</strong>
                    </div>
                    <div class="insight-list">
                        ${reasonHtml || '<div style="font-size:12.5px; color:var(--text-muted); text-align:center; padding:12px 0;">Zero cancellations in selected range.</div>'}
                    </div>
                `;
            } catch (e) {
                console.error('Cancellation insights error:', e);
                canEl.innerHTML = '<div class="error">Failed to load cancellation insights.</div>';
            }
        }

        // 11. Guest Reviews & Satisfaction Summary
        const revSumEl = document.getElementById('reviews-summary-container');
        if (revSumEl) {
            try {
                const revSum = await fetchAPI('/api/reviews-summary');
                const dist = Array.isArray(revSum.distribution) ? revSum.distribution : [];
                const distHtml = dist.map(d => `
                    <div class="insight-list-row">
                        <span><strong>${d.stars} ★</strong></span>
                        <div style="flex:1; margin:0 10px; background:var(--border, #e2e8f0); height:6px; border-radius:99px; overflow:hidden;">
                            <div style="width:${Math.max(0, Math.min(100, Number(d.share_pct) || 0))}%; height:100%; background:#D97706;"></div>
                        </div>
                        <span style="font-weight:700; color:var(--text-muted); min-width:68px; text-align:right;">${d.share_pct ?? 0}% (${d.count})</span>
                    </div>
                `).join('');

                revSumEl.innerHTML = `
                    <div class="insight-stat-grid">
                        <div class="insight-stat-box">
                            <span class="lbl">Average Guest Rating</span>
                            <span class="val" style="color:#D97706;">${Number(revSum.avg_rating || 0).toFixed(2)} / 5.0 ★</span>
                        </div>
                        <div class="insight-stat-box">
                            <span class="lbl">Approved Reviews</span>
                            <span class="val">${revSum.approved_count || 0} <span style="font-size:11px; font-weight:600; color:var(--text-muted);">(${revSum.pending_count || 0} pending)</span></span>
                        </div>
                    </div>
                    <div class="insight-list">
                        ${distHtml}
                    </div>
                `;
            } catch (e) {
                console.error('Reviews summary error:', e);
                revSumEl.innerHTML = '<div class="error">Failed to load guest review ratings.</div>';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', loadReportsData);

    document.addEventListener('click', function (e) {
        if (!e || !e.target || typeof e.target.closest !== 'function') return;

        const printBtn = e.target.closest('[data-action="print-report"]');
        if (printBtn) {
            window.print();
            return;
        }

        const presetBtn = e.target.closest('[data-range-preset]');
        if (presetBtn) {
            const preset = presetBtn.getAttribute('data-range-preset') || 'all';
            currentFilter.range = preset;
            currentFilter.startDate = '';
            currentFilter.endDate = '';
            if (document.querySelectorAll) {
                document.querySelectorAll('[data-range-preset]').forEach(btn => {
                    btn.classList.toggle('active', btn === presetBtn);
                });
            }
            loadReportsData();
            return;
        }

        const applyBtn = e.target.closest('#applyCustomDateBtn');
        if (applyBtn) {
            const startInput = document.getElementById('reportStartDate');
            const endInput = document.getElementById('reportEndDate');
            const sVal = startInput ? startInput.value.trim() : '';
            const eVal = endInput ? endInput.value.trim() : '';
            if (sVal && eVal && sVal <= eVal) {
                currentFilter.range = 'custom';
                currentFilter.startDate = sVal;
                currentFilter.endDate = eVal;
                if (document.querySelectorAll) {
                    document.querySelectorAll('[data-range-preset]').forEach(btn => btn.classList.remove('active'));
                }
                loadReportsData();
            }
            return;
        }

        const statusItem = e.target.closest('[data-drilldown-status]');
        if (statusItem) {
            const status = statusItem.getAttribute('data-drilldown-status');
            if (status) {
                navigateToReservationsByStatus(status);
            }
            return;
        }

        const paymentRow = e.target.closest('[data-drilldown-payment]');
        if (paymentRow) {
            const method = paymentRow.getAttribute('data-drilldown-payment');
            if (method) {
                navigateToPaymentsByMethod(method);
            }
            return;
        }

        const closeMonthlyBtn = e.target.closest('[data-close-monthly-modal]');
        if (closeMonthlyBtn || e.target.id === 'monthlyRevenueDrilldownModal') {
            closeMonthlyRevenueModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e && e.key === 'Escape') {
            closeMonthlyRevenueModal();
        }
    });
})();
