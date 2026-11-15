(function () {
    'use strict';

    const primaryColor = '#84563C';
    const primaryGradColor = 'rgba(132, 86, 60, 0.12)';
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(15, 23, 42, 0.06)';
    const tickColor = isDark ? '#94A3B8' : '#64748B';

    let pageData = { overdueGuestNames: [] };
    const dataEl = document.getElementById('adminDashboardPageData');
    if (dataEl && dataEl.textContent) {
        try {
            pageData = JSON.parse(dataEl.textContent);
        } catch (err) {
            pageData = { overdueGuestNames: [] };
        }
    }

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
            const occEl = document.getElementById('kpi-occupied-rooms');
            if (occEl) occEl.innerText = stats.occupied_rooms ?? 0;
            const totRoomsEl = document.getElementById('kpi-total-rooms');
            if (totRoomsEl) totRoomsEl.innerText = stats.total_rooms ?? 0;
            document.getElementById('kpi-total-bookings').innerText = Number(stats.total_bookings || 0).toLocaleString();
            const pbEl = document.getElementById('kpi-pending-bookings');
            if (pbEl) pbEl.innerText = stats.pending_bookings ?? 0;
            document.getElementById('link-total-bookings').innerText = stats.total_bookings ?? 0;
            document.getElementById('kpi-reserved-rooms').innerText = stats.reserved_rooms ?? '—';
            document.getElementById('kpi-pending-payments').innerText = stats.pending_payments ?? '—';
        } catch (e) {
            console.error('KPI Metrics error:', e);
        }

        // 1b. Live Room Occupancy & Front Desk Strip
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
                            pointHoverRadius: 6.5
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
                            y: { grid: { color: gridColor }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11.5 }, color: tickColor, callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) }, beginAtZero: true }
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Revenue trajectory error:', e);
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
            console.error('Daily summary error:', e);
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
            console.error('Recent bookings error:', e);
            const bTbody = document.getElementById('recent-bookings-tbody');
            if (bTbody) bTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);"><span class="error">Failed to load reservations.</span></td></tr>';
        }

        // 5. Recent Logs Feed
        try {
            await renderAuditLogs();
        } catch (e) {
            console.error('Audit logs error:', e);
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
            console.error('Security threats check error:', e);
        }
    }

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

                const detailsHtml = log.details ? `<span style="color:var(--text-muted); font-size:12.5px;"> · ${Security.escapeHTML(log.details)}</span>` : '';

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

    let searchTimeout = null;
    function adminSearch(q) {
        const dropdown = document.getElementById('adminSearchResults');
        if (!dropdown) return;
        q = q.trim();
        if (!q) {
            dropdown.classList.remove('open');
            dropdown.innerHTML = '';
            return;
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetch('../backend/api/admin_search_api.php?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(results => {
                    if (!results.length) {
                        dropdown.innerHTML = '<div class="search-no-results">No records found for "<strong>' + Security.escapeHTML(q) + '</strong>"</div>';
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

    function initOverdueModal() {
        const names = Array.isArray(pageData.overdueGuestNames) ? pageData.overdueGuestNames : [];
        if (names.length > 0) {
            const list = document.getElementById('ocmGuestList');
            if (list) {
                list.innerHTML = names.map(function (n) {
                    return '<li><div class="ocm-guest-name"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> ' + Security.escapeHTML(String(n)) + '</div><span class="ocm-guest-badge">Overdue</span></li>';
                }).join('');
            }
            const modal = document.getElementById('overdueCheckoutModal');
            if (modal) modal.style.display = 'flex';
        }
    }

    setInterval(async () => {
        try {
            const ds = await fetchAPI('/api/daily-summary');
            const dsBk = document.getElementById('ds-bookings');
            const dsPc = document.getElementById('ds-payments-count');
            const dsPa = document.getElementById('ds-payments-amount');
            const dsCi = document.getElementById('ds-checkins');
            const dsCo = document.getElementById('ds-checkouts');
            const dsCn = document.getElementById('ds-cancellations');
            const dsRef = document.getElementById('ds-refreshed');
            if (dsBk) dsBk.textContent = ds.bookings_today;
            if (dsPc) dsPc.textContent = ds.payments_today_count;
            if (dsPa) dsPa.textContent = '₱' + Number(ds.payments_today_amount).toLocaleString();
            if (dsCi) dsCi.textContent = ds.checkins_today;
            if (dsCo) dsCo.textContent = ds.checkouts_today;
            if (dsCn) dsCn.textContent = ds.cancellations_today;
            if (dsRef) dsRef.textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        } catch (_) {}
    }, 60000);

    document.addEventListener('DOMContentLoaded', function () {
        loadDashboardData();
        initOverdueModal();

        const searchInput = document.getElementById('adminGlobalSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                adminSearch(this.value);
            });
        }

        document.querySelectorAll('img[data-fallback-src]').forEach(function (img) {
            img.addEventListener('error', function () {
                if (!this.dataset.fallbackApplied) {
                    this.dataset.fallbackApplied = '1';
                    this.src = this.dataset.fallbackSrc;
                }
            });
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.admin-search-wrapper')) {
            const dd = document.getElementById('adminSearchResults');
            if (dd) dd.classList.remove('open');
        }

        const refreshBtn = e.target.closest('[data-action="refresh-audit"]');
        if (refreshBtn) {
            refreshAuditFeed(refreshBtn);
            return;
        }

        const closeOverdueBtn = e.target.closest('[data-close-overdue-modal]');
        if (closeOverdueBtn) {
            const modal = document.getElementById('overdueCheckoutModal');
            if (modal) modal.style.display = 'none';
        }
    });
})();
