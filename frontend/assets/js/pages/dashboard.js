(function () {
    const config = window.SFBC_DASHBOARD_CONFIG || {};
    const csrfToken = config.csrfToken || '';
    const overdueGuestNames = Array.isArray(config.overdueGuestNames) ? config.overdueGuestNames : [];

    function openRoomDetailModal(room) {
        const modal = document.getElementById('roomDetailModal');
        if (!modal || !room) return;

        document.getElementById('rm-modal-title').innerText = `Room ${room.room_number}`;
        document.getElementById('rm-modal-type').innerText = (room.type || 'Standard Accommodation').replace(/_/g, ' ').toUpperCase();

        const badge = document.getElementById('rm-modal-badge');
        const body = document.getElementById('rm-modal-body');
        const btnSlot = document.getElementById('rm-modal-btn-slot');

        const status = room.resolved_status;
        badge.innerText = status;

        if (status === 'ready') {
            badge.style.background = '#ECFDF5';
            badge.style.color = '#059669';
            body.innerHTML = `
                <div style="color:#059669; font-weight:700; margin-bottom:6px;">Unit Cleaned & Available</div>
                <p style="margin:0; color:var(--text-muted); font-size:12.5px;">This room is ready for immediate walk-in guest check-in or allocation.</p>
            `;
            btnSlot.innerHTML = `
                <button type="button" class="btn-primary" data-action="walkin-from-room" style="height:38px; padding:0 16px; font-size:13px;">
                    Walk-in Check-in &rarr;
                </button>
            `;
        } else if (status === 'occupied') {
            badge.style.background = '#EFF6FF';
            badge.style.color = '#2563EB';
            body.innerHTML = `
                <div style="font-weight:700; color:var(--text-main); margin-bottom:4px;">Guest: ${Security.escapeHTML(room.guest_name || 'In-House Guest')}</div>
                <div style="font-size:12.5px; color:var(--text-muted); margin-bottom:3px;">Tier: <strong>${Security.escapeHTML(room.guest_type || 'Standard')}</strong></div>
                <div style="font-size:12.5px; color:var(--text-muted); margin-bottom:3px;">Check-in: ${Security.escapeHTML(room.check_in || 'Active')}</div>
                <div style="font-size:12.5px; color:var(--text-muted);">Scheduled Check-out: <strong>${Security.escapeHTML(room.check_out || 'Today')}</strong></div>
            `;
            btnSlot.innerHTML = `
                <a href="checkout" class="btn-primary" style="height:38px; padding:0 16px; font-size:13px; text-decoration:none; display:inline-flex; align-items:center;">
                    Go to Check-out &rarr;
                </a>
            `;
        } else if (status === 'reserved') {
            badge.style.background = '#FFFBEB';
            badge.style.color = '#D97706';
            body.innerHTML = `
                <div style="font-weight:700; color:#D97706; margin-bottom:4px;">Reserved: ${Security.escapeHTML(room.guest_name || 'Upcoming Arrival')}</div>
                <div style="font-size:12.5px; color:var(--text-muted); margin-bottom:3px;">Expected: ${Security.escapeHTML(room.check_in || 'Today')}</div>
                <div style="font-size:12.5px; color:var(--text-muted);">Stay until: ${Security.escapeHTML(room.check_out || '—')}</div>
            `;
            btnSlot.innerHTML = `
                <a href="admin_reservations" class="btn-primary" style="height:38px; padding:0 16px; font-size:13px; text-decoration:none; display:inline-flex; align-items:center;">
                    View Reservation &rarr;
                </a>
            `;
        } else {
            badge.style.background = '#FEF2F2';
            badge.style.color = '#EF4444';
            body.innerHTML = `
                <div style="color:#EF4444; font-weight:700; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Room in Turnover / Maintenance
                </div>
                <p style="margin:0; color:var(--text-muted); font-size:12.5px;">Under cleaning inspection or scheduled repairs.</p>
            `;
            btnSlot.innerHTML = '';
        }

        modal.classList.add('open');
    }

    function closeRoomDetailModal() {
        const modal = document.getElementById('roomDetailModal');
        if (modal) modal.classList.remove('open');
    }

    function setStayDuration(nights) {
        const checkinVal = document.getElementById('newCheckin')?.value || new Date().toISOString().split('T')[0];
        const start = new Date(checkinVal);
        start.setDate(start.getDate() + nights);
        const yyyy = start.getFullYear();
        const mm = String(start.getMonth() + 1).padStart(2, '0');
        const dd = String(start.getDate()).padStart(2, '0');
        const checkoutInput = document.getElementById('newCheckout');
        if (checkoutInput) checkoutInput.value = `${yyyy}-${mm}-${dd}`;
    }

    function openReservationModal() {
        const modal = document.getElementById('newReservationModal');
        if (modal) modal.classList.add('open');
    }

    function closeReservationModal() {
        const modal = document.getElementById('newReservationModal');
        if (modal) modal.classList.remove('open');
    }

    function submitReservation(status) {
        const name = (document.getElementById('newGuestName')?.value || '').trim();
        if (!name) {
            SiteDialog.alert('Please enter guest name.');
            return;
        }
        const email = document.getElementById('newGuestEmail').value;
        const phone = (document.getElementById('newGuestPhone')?.value || '').trim();
        const type = document.getElementById('newGuestType').value;
        const roomTypeId = document.getElementById('newRoomType').value;
        const checkin = document.getElementById('newCheckin').value;
        const checkout = document.getElementById('newCheckout').value;
        const guests = document.getElementById('newGuestsCount').value;
        const eta = document.getElementById('newETA').value;

        const formData = new FormData();
        formData.append('guest_name', name);
        formData.append('guest_email', email);
        formData.append('guest_phone', phone);
        formData.append('guest_type', type);
        formData.append('room_type_id', roomTypeId);
        formData.append('check_in', checkin);
        formData.append('check_out', checkout);
        formData.append('guests_count', guests);
        formData.append('eta', eta);
        formData.append('status', status);
        formData.append('csrf_token', csrfToken);

        fetch('../backend/api/staff_create_booking.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    SiteDialog.alert('Error: ' + (data.message || data.error || 'Failed to save reservation.'));
                }
            })
            .catch(err => {
                console.error('Error saving reservation:', err);
                SiteDialog.alert('An error occurred while creating the reservation.');
            });
    }

    function checkInGuest(rowId) {
        const bookingId = rowId.split('-')[1];
        const formData = new FormData();
        formData.append('action', 'checkin');
        formData.append('booking_id', bookingId);
        formData.append('format', 'json');
        formData.append('csrf_token', csrfToken);

        fetch('checkin', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById(rowId);
                    if (row) row.remove();
                    window.location.reload();
                } else {
                    SiteDialog.alert('Check-in failed. Please try again.');
                }
            })
            .catch(err => {
                console.error('Check-in error:', err);
                SiteDialog.alert('An error occurred during check-in.');
            });
    }

    function filterArrivalsTable() {
        const query = (document.getElementById('dashboardSearch')?.value || '').toLowerCase().trim();
        const selectedType = document.getElementById('arrivalTypeFilter')?.value || '';
        const rows = document.querySelectorAll('#arrivalsTable tbody tr');

        rows.forEach(row => {
            const searchText = row.getAttribute('data-search-text') || '';
            const guestType = row.getAttribute('data-guest-type') || '';
            const matchesType = !selectedType || guestType === selectedType;
            const matchesQuery = !query || searchText.includes(query);

            row.style.display = (matchesQuery && matchesType) ? '' : 'none';
        });
    }

    document.addEventListener('click', function (e) {
        const overlay = e.target.closest('.modal-overlay');
        if (overlay && e.target === overlay) {
            overlay.classList.remove('open');
            return;
        }

        const roomTrigger = e.target.closest('[data-action="open-room-detail"]');
        if (roomTrigger) {
            try {
                const room = JSON.parse(roomTrigger.getAttribute('data-room') || '{}');
                openRoomDetailModal(room);
            } catch (err) {
                console.error('Failed to parse room details:', err);
            }
            return;
        }

        const actionEl = e.target.closest('[data-action]');
        if (!actionEl) return;

        const action = actionEl.getAttribute('data-action');
        if (action === 'open-reservation-modal') {
            openReservationModal();
        } else if (action === 'close-reservation-modal') {
            closeReservationModal();
        } else if (action === 'close-room-detail') {
            closeRoomDetailModal();
        } else if (action === 'walkin-from-room') {
            closeRoomDetailModal();
            openReservationModal();
        } else if (action === 'set-stay-duration') {
            const nights = parseInt(actionEl.getAttribute('data-nights') || '1', 10);
            if (nights > 0) setStayDuration(nights);
        } else if (action === 'submit-reservation') {
            const status = actionEl.getAttribute('data-status') || 'Pending';
            submitReservation(status);
        } else if (action === 'checkin-guest') {
            const rowId = actionEl.getAttribute('data-row-id') || '';
            if (rowId) checkInGuest(rowId);
        } else if (action === 'dismiss-overdue-modal') {
            const ocm = document.getElementById('overdueCheckoutModal');
            if (ocm) ocm.style.display = 'none';
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('dashboardSearch');
        const typeFilter = document.getElementById('arrivalTypeFilter');
        if (searchInput) searchInput.addEventListener('input', filterArrivalsTable);
        if (typeFilter) typeFilter.addEventListener('change', filterArrivalsTable);

        const resForm = document.getElementById('newReservationForm');
        if (resForm) {
            resForm.addEventListener('submit', function (e) {
                e.preventDefault();
            });
        }

        filterArrivalsTable();

        if (overdueGuestNames.length > 0) {
            const list = document.getElementById('ocmGuestList');
            const modal = document.getElementById('overdueCheckoutModal');
            if (list && modal) {
                list.innerHTML = overdueGuestNames.map(function (name) {
                    return '<li><div class="ocm-guest-name"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> ' + Security.escapeHTML(name) + '</div><span class="ocm-guest-badge">Overdue</span></li>';
                }).join('');
                modal.style.display = 'flex';
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeReservationModal();
            closeRoomDetailModal();
        }
    });

    window.openRoomDetailModal = openRoomDetailModal;
    window.closeRoomDetailModal = closeRoomDetailModal;
    window.setStayDuration = setStayDuration;
    window.openReservationModal = openReservationModal;
    window.closeReservationModal = closeReservationModal;
    window.submitReservation = submitReservation;
    window.checkInGuest = checkInGuest;
    window.filterArrivalsTable = filterArrivalsTable;
})();
