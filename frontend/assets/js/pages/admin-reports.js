(function () {
    'use strict';

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(15, 23, 42, 0.06)';
    const tickColor = isDark ? '#94A3B8' : '#64748B';

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

    async function loadReportsData() {
        // 1. KPI Stats
        try {
            const stats = await fetchAPI('/api/dashboard-stats');
            document.getElementById('kpi-revenue').innerText = '₱' + Number(stats.total_revenue || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            document.getElementById('kpi-bookings').innerText = Number(stats.total_bookings || 0).toLocaleString();
            document.getElementById('kpi-pending').innerText = stats.pending_bookings || 0;
            document.getElementById('kpi-guests').innerText = Number(stats.total_guests || 0).toLocaleString();
            document.getElementById('kpi-occupancy').innerText = (stats.occupancy_rate || 0) + '%';
            document.getElementById('kpi-avg-stay').innerText = (stats.avg_stay || '0') + ' nights';
        } catch (e) {
            console.error('KPI load error:', e);
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
                            y: { grid: { color: gridColor }, ticks: { color: tickColor, font: { family: 'Plus Jakarta Sans', size: 10.5 }, callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) }, beginAtZero: true }
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Revenue overview load error:', e);
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
                                    label: ctx => ` ${ctx.label}: ${ctx.parsed} (${totalStatus > 0 ? Math.round((ctx.parsed / totalStatus) * 100) : 0}%)`
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
            const totalOriginsBookings = countryData.total_bookings || 1;

            if (rankingContainer) {
                if (countryList.length > 0) {
                    const top5Markets = countryList.slice(0, 5);
                    rankingContainer.innerHTML = top5Markets.map(c => {
                        const pct = c.share_pct || Math.round((c.bookings / totalOriginsBookings) * 100);
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
            console.error('Country demographics error:', e);
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
                        const safeCountry = typeof Security !== 'undefined' ? Security.escapeHTML(c.country) : c.country;
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
                    cTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">No guest country data recorded yet.</td></tr>';
                }
            }
        } catch (e) {
            console.error('Country leaderboard error:', e);
            const cTbody = document.getElementById('country-leaderboard-tbody');
            if (cTbody) cTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">No guest country data recorded yet.</td></tr>';
        }
    }

    document.addEventListener('DOMContentLoaded', loadReportsData);

    document.addEventListener('click', function (e) {
        const printBtn = e.target.closest('[data-action="print-report"]');
        if (printBtn) {
            window.print();
        }
    });
})();
