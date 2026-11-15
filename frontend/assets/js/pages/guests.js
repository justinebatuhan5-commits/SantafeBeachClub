(function () {
    const ALL_ROWS = Array.from(document.querySelectorAll('#guestTableBody .guest-row[data-name]'));
    let activeFilter = 'all';

    function applyFilters(searchTerm) {
        let visible = 0;
        ALL_ROWS.forEach(row => {
            const typeMatch = activeFilter === 'all' || row.dataset.type === activeFilter;
            const searchMatch = searchTerm === '' ||
                (row.dataset.name || '').includes(searchTerm) ||
                (row.dataset.email || '').includes(searchTerm);
            const show = typeMatch && searchMatch;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        const visibleCountEl = document.getElementById('visibleCount');
        const noResultsEl = document.getElementById('noResultsMsg');
        if (visibleCountEl) visibleCountEl.textContent = visible;
        if (noResultsEl) noResultsEl.style.display = (visible === 0 && ALL_ROWS.length > 0) ? 'block' : 'none';
    }

    function openGuestProfile(data) {
        if (!data) return;

        const av = document.getElementById('modalAvatar');
        av.textContent = data.initials;
        av.style.background = data.avatarBg;
        av.style.color = data.avatarText;

        document.getElementById('modalName').textContent = data.name;
        document.getElementById('modalEmail').textContent = data.email || '—';
        document.getElementById('modalBookAgainBtn').href = data.book_again_url || 'book?step=2';
        document.getElementById('modalTotalBookings').textContent = data.total;
        document.getElementById('modalFirstVisit').textContent = data.first_visit || '—';
        document.getElementById('modalTotalSpend').textContent = data.spend > 0
            ? '₱' + Number(data.spend).toLocaleString('en-PH', { minimumFractionDigits: 0 })
            : '—';

        window._currentModalRawPhone = data.phone || '';
        window._currentModalMaskedPhone = data.masked_phone || '—';
        window._isPhoneRevealed = false;

        const phoneEl = document.getElementById('modalPhone');
        const toggleBtn = document.getElementById('btnTogglePhone');
        phoneEl.textContent = window._currentModalMaskedPhone;

        if (data.phone && data.phone.trim() !== '') {
            toggleBtn.style.display = 'inline-flex';
            toggleBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><span>Show</span>';
        } else {
            toggleBtn.style.display = 'none';
        }

        document.getElementById('modalCountry').textContent = data.country || '—';

        const specialRequestsSection = document.getElementById('modalSpecialRequestsSection');
        const specialRequestsDiv = document.getElementById('modalSpecialRequests');
        if (data.special_requests && data.special_requests.trim()) {
            specialRequestsDiv.textContent = data.special_requests;
            specialRequestsSection.style.display = 'block';
        } else {
            specialRequestsSection.style.display = 'none';
        }

        const badgeStyles = {
            'VIP Member':      'background:rgba(132,86,60,0.1);color:#7C533C;border:1px solid rgba(132,86,60,0.25);',
            'Corporate':       'background:rgba(59,130,246,0.1);color:#1565C0;border:1px solid rgba(59,130,246,0.25);',
            'Returning Guest': 'background:rgba(124,58,237,0.1);color:#7C3AED;border:1px solid rgba(124,58,237,0.25);',
        };
        const bs = badgeStyles[data.type] || 'background:rgba(16,185,129,0.1);color:#059669;border:1px solid rgba(16,185,129,0.25);';
        document.getElementById('modalTypeBadge').innerHTML =
            `<span style="${bs}padding:4px 14px;border-radius:99px;font-size:12px;font-weight:700;display:inline-block;">${Security.escapeHTML(data.type)}</span>`;

        const hist = document.getElementById('modalBookingHistory');
        if (!data.bookings || data.bookings.length === 0) {
            hist.innerHTML = '<div class="empty-history">No booking records found.</div>';
        } else {
            const statusClass = s => {
                if (!s) return '';
                const map = { 'Pending': 'pending', 'Checked In': 'checked-in', 'Checked Out': 'checked-out', 'Cancelled': 'cancelled' };
                return map[s] || 'pending';
            };
            let rows = '';
            data.bookings.forEach(b => {
                const amount = b.rate > 0 ? '₱' + (b.rate * b.nights).toLocaleString('en-PH') : '—';
                rows += `<tr>
                    <td><strong>#${Security.escapeHTML(b.id)}</strong></td>
                    <td>${Security.escapeHTML(b.room)}</td>
                    <td>${Security.escapeHTML(b.check_in)}</td>
                    <td>${Security.escapeHTML(b.check_out)}</td>
                    <td>${Security.escapeHTML(b.nights)} night${b.nights !== 1 ? 's' : ''}</td>
                    <td><span class="status-pill ${statusClass(b.status)}">${Security.escapeHTML(b.status)}</span></td>
                    <td><strong>${amount}</strong></td>
                </tr>`;
            });
            hist.innerHTML = `<table class="booking-history-table">
                <thead><tr>
                    <th>#ID</th><th>Room</th><th>Check-in</th><th>Check-out</th>
                    <th>Nights</th><th>Status</th><th>Amount</th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>`;
        }

        document.getElementById('guestModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('guestModal');
        if (modal) modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    function togglePhoneVisibility() {
        const phoneEl = document.getElementById('modalPhone');
        const toggleBtn = document.getElementById('btnTogglePhone');
        window._isPhoneRevealed = !window._isPhoneRevealed;

        if (window._isPhoneRevealed) {
            phoneEl.textContent = window._currentModalRawPhone;
            toggleBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg><span>Hide</span>';
        } else {
            phoneEl.textContent = window._currentModalMaskedPhone;
            toggleBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><span>Show</span>';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const searchEl = document.getElementById('guestSearch');
        if (searchEl) {
            searchEl.addEventListener('input', function () {
                applyFilters(this.value.toLowerCase().trim());
            });
        }

        document.querySelectorAll('.filter-tab').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                activeFilter = this.dataset.filter;
                applyFilters((document.getElementById('guestSearch')?.value || '').toLowerCase().trim());
            });
        });

        const exportBtn = document.getElementById('exportCsvBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                const visibleRows = ALL_ROWS.filter(r => r.style.display !== 'none');
                if (!visibleRows.length) {
                    SiteDialog.alert('No guests to export.');
                    return;
                }

                const headers = ['Guest Name', 'Email', 'Tier', 'Total Bookings', 'Last Visit'];
                const lines = [headers.join(',')];

                visibleRows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    const name = cells[0].querySelector('.guest-name-main, .g-name')?.textContent.trim() ?? '';
                    const email = cells[1].textContent.trim();
                    const type = cells[2].textContent.trim();
                    const bookings = cells[3].textContent.trim();
                    const lastVisit = cells[4].textContent.trim();
                    lines.push([name, email, type, bookings, lastVisit].map(v => `"${v.replace(/"/g, '""')}"`).join(','));
                });

                const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'guests_' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }
    });

    document.addEventListener('click', function (e) {
        const guestModal = document.getElementById('guestModal');
        if (guestModal && e.target === guestModal) {
            closeModal();
            return;
        }

        const profileBtn = e.target.closest('[data-action="open-guest-profile"]');
        if (profileBtn) {
            try {
                const profile = JSON.parse(profileBtn.getAttribute('data-profile') || '{}');
                openGuestProfile(profile);
            } catch (err) {
                console.error('Failed to parse guest profile:', err);
            }
            return;
        }

        const actionEl = e.target.closest('[data-action]');
        if (!actionEl) return;

        const action = actionEl.getAttribute('data-action');
        if (action === 'close-guest-modal') {
            closeModal();
        } else if (action === 'toggle-phone') {
            togglePhoneVisibility();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    window.openGuestProfile = openGuestProfile;
    window.closeModal = closeModal;
    window.togglePhoneVisibility = togglePhoneVisibility;
})();
