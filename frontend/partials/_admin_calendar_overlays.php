<!-- Availability Results Modal -->
<div class="cal-avail-modal-overlay" id="availModalOverlay">
    <div class="cal-avail-modal" id="availModal" role="dialog" aria-modal="true" aria-labelledby="availModalTitle">
        <div class="cal-avail-modal-header">
            <div>
                <h3 id="availModalTitle">Available Accommodations</h3>
                <div class="cal-avail-modal-sub" id="availModalSubtitle">&mdash;</div>
            </div>
            <button type="button" class="cal-detail-close" id="availModalClose" title="Close" aria-label="Close availability modal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="cal-avail-modal-body" id="availModalBody"></div>
    </div>
</div>

<!-- Booking Detail Slide-Over Panel -->
<div class="cal-detail-overlay" id="calDetailOverlay"></div>
<aside class="cal-detail-panel" id="calDetailPanel" aria-label="Booking details">
    <div class="cal-detail-header">
        <h3>Reservation Details</h3>
        <button type="button" class="cal-detail-close" id="calDetailClose" title="Close" aria-label="Close booking details">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="cal-detail-body" id="calDetailBody"></div>
    <div class="cal-detail-footer">
        <a class="cal-detail-link" id="calDetailLink" href="#">
            <span>Open Full Reservation</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
    </div>
</aside>

<!-- Live Drag Preview & Toast -->
<div class="cal-drag-pill" id="calDragPill"></div>
<div class="cal-toast" id="calToast" role="status" aria-live="polite"></div>

<script src="assets/js/occupancy_kpi.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/occupancy_kpi.js'); ?>"></script>
<script>
(function () {
    'use strict';

    // ── Config & State ────────────────────────────────────────────────────────
    let DAYS_TO_SHOW = 30;
    const DAY_NAMES   = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const MONTH_UP    = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    let viewStart = addDays(getEffectiveToday(), -3);
    let roomsData = [];
    let bookingsData = [];
    let searchQuery = '';
    let statusFilterVal = 'all';

    // DOM References
    const gridEl        = document.getElementById('calGrid');
    const scrollWrapper = document.getElementById('calScrollWrapper');
    const rangeLabel    = document.getElementById('calRangeLabel');
    const filterSelect  = document.getElementById('calFilter');
    const statusSelect  = document.getElementById('calStatusFilter');
    const searchInput   = document.getElementById('calRoomSearch');
    const searchClear   = document.getElementById('calSearchClear');
    const horizonBtns   = document.querySelectorAll('#calHorizonSwitch .cal-horizon-btn');
    const summaryEl     = document.getElementById('calVisibleSummary');
    const overlay       = document.getElementById('calDetailOverlay');
    const panel         = document.getElementById('calDetailPanel');
    const detailBody    = document.getElementById('calDetailBody');
    const detailLink    = document.getElementById('calDetailLink');
    const dragPillEl    = document.getElementById('calDragPill');
    const toastEl       = document.getElementById('calToast');

    // ── Helpers ───────────────────────────────────────────────────────────────
    function esc(str) {
        if (window.Security && typeof window.Security.escapeHTML === 'function') {
            return window.Security.escapeHTML(str);
        }
        return String(str === null || str === undefined ? '' : str).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function getEffectiveToday() {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate());
    }

    function parseDate(str) {
        const parts = String(str).split('-');
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }

    function formatISO(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function addDays(d, n) {
        const copy = new Date(d.getTime());
        copy.setDate(copy.getDate() + n);
        return copy;
    }

    function diffDays(a, b) {
        const utcA = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
        const utcB = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());
        return Math.round((utcB - utcA) / 86400000);
    }

    function formatRangeText(start, end) {
        const sM = MONTH_SHORT[start.getMonth()];
        const eM = MONTH_SHORT[end.getMonth()];
        if (start.getFullYear() === end.getFullYear()) {
            if (start.getMonth() === end.getMonth()) {
                return sM + ' ' + start.getDate() + ' - ' + end.getDate() + ', ' + start.getFullYear();
            }
            return sM + ' ' + start.getDate() + ' - ' + eM + ' ' + end.getDate() + ', ' + start.getFullYear();
        }
        return sM + ' ' + start.getDate() + ', ' + start.getFullYear() + ' - ' + eM + ' ' + end.getDate() + ', ' + end.getFullYear();
    }

    function formatNiceDate(isoStr) {
        const d = parseDate(isoStr);
        return DAY_NAMES[d.getDay()] + ', ' + MONTH_SHORT[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    function formatTypeTitle(raw) {
        const clean = String(raw || 'Room').replace(/[_-]+/g, ' ').trim();
        if (!clean) return 'Room';
        return clean.replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function formatPeso(val) {
        return '\u20B1' + Number(val || 0).toLocaleString('en-US');
    }

    function getLabelWidth() {
        return window.innerWidth <= 640 ? 158 : 248;
    }

    function getCellWidth() {
        const labelW = getLabelWidth();
        const containerW = scrollWrapper.clientWidth || 1000;
        const availW = Math.max(260, containerW - labelW);
        const minW = window.innerWidth <= 640
            ? (DAYS_TO_SHOW <= 14 ? 54 : 44)
            : (DAYS_TO_SHOW <= 14 ? 66 : (DAYS_TO_SHOW <= 30 ? 48 : 38));
        return Math.max(minW, Math.floor(availW / DAYS_TO_SHOW));
    }

    function getHeaderHeight() {
        return window.innerWidth <= 640 ? 58 : 64;
    }

    function getRowHeight() {
        return window.innerWidth <= 640 ? 58 : 64;
    }

    function statusClass(status) {
        const s = String(status || '').toLowerCase();
        if (s === 'checked in') return 'status-checked-in';
        if (s === 'checked out') return 'status-checked-out';
        if (s === 'cancelled') return 'status-cancelled';
        if (s === 'confirmed') return 'status-confirmed';
        return 'status-pending';
    }

    function getStatusBadgeInfo(status) {
        const s = String(status || '');
        if (s === 'Checked In')  return { bg: 'rgba(5, 150, 105, 0.14)', color: '#059669' };
        if (s === 'Confirmed')   return { bg: 'rgba(37, 99, 235, 0.14)', color: '#2563EB' };
        if (s === 'Checked Out') return { bg: 'rgba(100, 116, 139, 0.16)', color: '#475569' };
        if (s === 'Cancelled')   return { bg: 'rgba(220, 38, 38, 0.14)', color: '#DC2626' };
        return { bg: 'rgba(217, 119, 6, 0.14)', color: '#B45309' };
    }

    function bookingMatchesStatusFilter(b) {
        if (statusFilterVal === 'all') return true;
        if (statusFilterVal === 'active') {
            return b.status !== 'Cancelled' && b.status !== 'Checked Out';
        }
        if (statusFilterVal === 'Pending') {
            return b.status === 'Pending' || b.status === 'Pending Payment';
        }
        return b.status === statusFilterVal;
    }

    function bookingMatchesSearch(b, query) {
        if (!query) return true;
        const hay = ((b.guest_name || '') + ' ' + (b.guest_email || '') + ' #' + b.id).toLowerCase();
        return hay.indexOf(query) !== -1;
    }

    function showToast(msg, type) {
        toastEl.textContent = msg;
        toastEl.className = 'cal-toast show ' + (type || 'info');
        clearTimeout(toastEl._timer);
        toastEl._timer = setTimeout(function () {
            toastEl.classList.remove('show');
        }, 3600);
    }

    // ── Room Occupancy Helper ─────────────────────────────────────────────────
    function computeOccupiedTodaySet(bookings, todayStr) {
        if (window.SantaFeOccupancyKPI) {
            return window.SantaFeOccupancyKPI.computeOccupiedTodaySet(bookings, todayStr);
        }
        return new Set();
    }

    // ── Data Fetching ─────────────────────────────────────────────────────────
    function fetchData() {
        const startStr = formatISO(viewStart);
        const endDate  = addDays(viewStart, DAYS_TO_SHOW - 1);
        const endStr   = formatISO(endDate);
        rangeLabel.textContent = formatRangeText(viewStart, endDate);

        const apiPath = window.location.pathname.includes('/frontend/')
            ? '../backend/api/calendar_api.php'
            : 'backend/api/calendar_api.php';

        fetch(apiPath + '?start=' + startStr + '&end=' + endStr)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    showToast(data.error, 'error');
                    return;
                }
                roomsData    = data.rooms || [];
                bookingsData = data.bookings || [];
                render();
            })
            .catch(function (err) {
                console.error('Calendar fetch error:', err);
                showToast('Failed to load calendar data.', 'error');
            });
    }

    // ── Row Hover Synchronization ─────────────────────────────────────────────
    function setRowHover(rowIdx, active) {
        const targets = gridEl.querySelectorAll('[data-row-idx="' + rowIdx + '"]');
        targets.forEach(function (el) {
            if (active) el.classList.add('is-row-hover');
            else el.classList.remove('is-row-hover');
        });
    }

    // ── Render Grid ───────────────────────────────────────────────────────────
    function render() {
        const typeFilterVal = filterSelect.value;
        const q             = searchQuery.trim().toLowerCase();
        const todayStr      = formatISO(getEffectiveToday());
        const occupiedToday = computeOccupiedTodaySet(bookingsData, todayStr);

        const labelWidth   = getLabelWidth();
        const cellWidth    = getCellWidth();
        const headerHeight = getHeaderHeight();
        const rowHeight    = getRowHeight();

        gridEl.style.setProperty('--cal-label-w', labelWidth + 'px');
        gridEl.style.setProperty('--cal-cell-w', cellWidth + 'px');
        gridEl.style.setProperty('--cal-header-h', headerHeight + 'px');
        gridEl.style.setProperty('--cal-row-h', rowHeight + 'px');

        const roomBookingsMap = new Map();
        bookingsData.forEach(function (b) {
            if (!bookingMatchesStatusFilter(b)) return;
            const rid = String(b.room_id);
            if (!roomBookingsMap.has(rid)) roomBookingsMap.set(rid, []);
            roomBookingsMap.get(rid).push(b);
        });

        const filteredRooms = roomsData.filter(function (room) {
            if (typeFilterVal !== 'all' && room.type !== typeFilterVal) return false;
            if (!q) return true;
            const roomText = ((room.number || '') + ' ' + (room.name || '') + ' ' + (room.type_label || room.type || '')).toLowerCase();
            if (roomText.indexOf(q) !== -1) return true;
            const rBookings = roomBookingsMap.get(String(room.id)) || [];
            return rBookings.some(function (b) { return bookingMatchesSearch(b, q); });
        });

        const days = [];
        for (let i = 0; i < DAYS_TO_SHOW; i++) {
            days.push(addDays(viewStart, i));
        }

        const startStr    = formatISO(viewStart);
        const endBoundStr = formatISO(addDays(viewStart, DAYS_TO_SHOW));

        gridEl.innerHTML = '';
        gridEl.style.gridTemplateColumns = labelWidth + 'px repeat(' + DAYS_TO_SHOW + ', ' + cellWidth + 'px)';

        // 1. Header Corner
        const corner = document.createElement('div');
        corner.className = 'cal-header-corner';
        corner.style.gridColumn = '1';
        corner.style.gridRow = '1';
        corner.innerHTML = '<span class="cal-corner-title">Accommodations</span><span class="cal-corner-sub">' + filteredRooms.length + ' units</span>';
        gridEl.appendChild(corner);

        // 2. Date Headers
        let todayOffset = -1;
        days.forEach(function (d, idx) {
            const dStr         = formatISO(d);
            const dow          = d.getDay();
            const isWeekend    = (dow === 0 || dow === 6);
            const isToday      = (dStr === todayStr);
            const isMonthStart = (idx === 0 || d.getDate() === 1);
            if (isToday) todayOffset = idx;

            const hdr = document.createElement('div');
            hdr.className = 'cal-day-header' +
                (isWeekend ? ' is-weekend' : '') +
                (isToday ? ' is-today' : '') +
                (isMonthStart ? ' is-month-start' : '');
            hdr.style.gridColumn = String(idx + 2);
            hdr.style.gridRow = '1';
            hdr.innerHTML =
                (isMonthStart ? '<span class="cal-day-month-pill">' + MONTH_UP[d.getMonth()] + '</span>' : '') +
                '<span class="cal-day-name">' + DAY_NAMES[dow] + '</span>' +
                '<span class="cal-day-num">' + d.getDate() + '</span>';
            gridEl.appendChild(hdr);
        });

        if (filteredRooms.length === 0) {
            summaryEl.textContent = '0 units shown';
            const empty = document.createElement('div');
            empty.className = 'cal-empty-state';
            empty.innerHTML =
                '<div class="cal-empty-icon" aria-hidden="true">' +
                    '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
                '</div>' +
                '<p style="font-size:15px;font-weight:700;color:var(--text-main);margin:0 0 4px;">No matching accommodations found</p>' +
                '<p style="font-size:13px;margin:0;">Try clearing your search query or switching room and status filters.</p>';
            gridEl.appendChild(empty);
            return;
        }

        // 3. Group Rooms by Type Preserving Natural API Order
        const groups = [];
        const groupMap = new Map();
        filteredRooms.forEach(function (room) {
            const gKey = room.type || 'other';
            if (!groupMap.has(gKey)) {
                const gObj = {
                    key: gKey,
                    label: formatTypeTitle(room.type_label || room.type),
                    rooms: []
                };
                groupMap.set(gKey, gObj);
                groups.push(gObj);
            }
            groupMap.get(gKey).rooms.push(room);
        });

        let currentGridRow = 2;
        let visibleBarsCount = 0;

        groups.forEach(function (group) {
            const gRow = currentGridRow++;
            const gLabel = document.createElement('div');
            gLabel.className = 'cal-group-label';
            gLabel.style.gridColumn = '1';
            gLabel.style.gridRow = String(gRow);
            const samplePrice = group.rooms[0] ? formatPeso(group.rooms[0].price) : '';
            gLabel.innerHTML =
                '<span class="cal-group-name">' + esc(group.label) + '</span>' +
                '<span class="cal-group-meta">' + group.rooms.length + (group.rooms.length === 1 ? ' unit' : ' units') + (samplePrice ? ' \u00B7 ' + samplePrice : '') + '</span>';
            gridEl.appendChild(gLabel);

            const gTrack = document.createElement('div');
            gTrack.className = 'cal-group-track';
            gTrack.style.gridColumn = '2 / ' + (DAYS_TO_SHOW + 2);
            gTrack.style.gridRow = String(gRow);
            gridEl.appendChild(gTrack);

            group.rooms.forEach(function (room) {
                const rRowIdx = currentGridRow++;
                const roomText = ((room.number || '') + ' ' + (room.name || '') + ' ' + (room.type_label || room.type || '')).toLowerCase();
                const roomMatchesText = !q || roomText.indexOf(q) !== -1;

                const isMaint = String(room.status || '').toLowerCase() === 'maintenance' ||
                                String(room.status || '').toLowerCase() === 'out of order';
                const isOccupied = occupiedToday.has(String(room.id));
                const numPillCls = 'cal-room-num-pill' + (isMaint ? ' is-maintenance' : (isOccupied ? ' is-occupied' : ''));
                const statusTagCls = isMaint ? 'maint' : (isOccupied ? 'occupied' : 'open');
                const statusTagTxt = isMaint ? 'Maint' : (isOccupied ? 'In-House' : 'Open');
                const shortNum = room.number ? String(room.number) : String(room.id);

                const label = document.createElement('div');
                label.className = 'cal-room-label';
                label.style.gridColumn = '1';
                label.style.gridRow = String(rRowIdx);
                label.dataset.rowIdx = String(rRowIdx);
                label.innerHTML =
                    '<span class="' + numPillCls + '">' + esc(shortNum) + '</span>' +
                    '<div class="cal-room-info">' +
                        '<div class="cal-room-name">' + esc(room.name) + '</div>' +
                        '<div class="cal-room-meta">' +
                            '<span>' + esc(room.capacity || 2) + ' pax \u00B7 ' + formatPeso(room.price) + '/nt</span>' +
                            '<span class="cal-room-status-tag ' + statusTagCls + '">' + statusTagTxt + '</span>' +
                        '</div>' +
                    '</div>';
                label.addEventListener('mouseenter', function () { setRowHover(rRowIdx, true); });
                label.addEventListener('mouseleave', function () { setRowHover(rRowIdx, false); });
                gridEl.appendChild(label);

                days.forEach(function (d, cIdx) {
                    const dStr         = formatISO(d);
                    const dow          = d.getDay();
                    const isWeekend    = (dow === 0 || dow === 6);
                    const isToday      = (dStr === todayStr);
                    const isMonthStart = (cIdx === 0 || d.getDate() === 1);

                    const cell = document.createElement('div');
                    cell.className = 'cal-cell' +
                        (isWeekend ? ' is-weekend' : '') +
                        (isToday ? ' is-today' : '') +
                        (isMonthStart ? ' is-month-start' : '');
                    cell.style.gridColumn = String(cIdx + 2);
                    cell.style.gridRow = String(rRowIdx);
                    cell.dataset.rowIdx = String(rRowIdx);
                    cell.dataset.date = dStr;
                    cell.dataset.roomId = String(room.id);
                    cell.title = 'Click to check availability starting ' + formatNiceDate(dStr);

                    cell.addEventListener('mouseenter', function () { setRowHover(rRowIdx, true); });
                    cell.addEventListener('mouseleave', function () { setRowHover(rRowIdx, false); });
                    cell.addEventListener('click', function () {
                        const ciInput = document.getElementById('availabilityCheckIn');
                        const coInput = document.getElementById('availabilityCheckOut');
                        const nextStr = formatISO(addDays(parseDate(dStr), 1));
                        if (ciInput) ciInput.value = dStr;
                        if (coInput) coInput.value = nextStr;
                        if (typeof window.__runAvailabilityCheck === 'function') {
                            window.__runAvailabilityCheck(dStr, nextStr);
                        }
                    });
                    gridEl.appendChild(cell);
                });

                const roomBookings = roomBookingsMap.get(String(room.id)) || [];
                roomBookings.forEach(function (booking) {
                    const placed = renderBar(booking, rRowIdx, days, startStr, endBoundStr, cellWidth, labelWidth, roomMatchesText, q);
                    if (placed) visibleBarsCount++;
                });
            });
        });

        summaryEl.textContent = filteredRooms.length + (filteredRooms.length === 1 ? ' unit' : ' units') + ' \u00B7 ' +
            visibleBarsCount + (visibleBarsCount === 1 ? ' reservation visible' : ' reservations visible');

        // 4. Today Marker Line
        if (todayOffset >= 0) {
            const line = document.createElement('div');
            line.className = 'cal-today-line';
            const leftPx = labelWidth + todayOffset * cellWidth + Math.floor(cellWidth / 2);
            line.style.left = leftPx + 'px';
            line.style.top = headerHeight + 'px';
            gridEl.appendChild(line);
        }
    }

    // ── Booking Bar Renderer ──────────────────────────────────────────────────
    function renderBar(b, gridRowIdx, days, viewStartStr, viewEndBoundStr, cellWidth, labelWidth, roomMatchesText, q) {
        if (b.check_out <= viewStartStr || b.check_in >= viewEndBoundStr) return false;

        const barStart = b.check_in < viewStartStr ? viewStartStr : b.check_in;
        const barEnd   = b.check_out > viewEndBoundStr ? viewEndBoundStr : b.check_out;

        const startCol = Math.max(0, diffDays(viewStart, parseDate(barStart)));
        const spanDays = Math.max(1, diffDays(parseDate(barStart), parseDate(barEnd)));
        const totalNights = Math.max(1, diffDays(parseDate(b.check_in), parseDate(b.check_out)));

        const isDimmed = Boolean(q && !roomMatchesText && !bookingMatchesSearch(b, q));
        const bar = document.createElement('div');
        bar.className = 'cal-bar ' + statusClass(b.status) +
            (b.check_in < viewStartStr ? ' continues-left' : '') +
            (b.check_out > viewEndBoundStr ? ' continues-right' : '') +
            (isDimmed ? ' is-dimmed' : '');

        bar.style.gridColumn = (startCol + 2) + ' / span ' + spanDays;
        bar.style.gridRow = String(gridRowIdx);
        bar.dataset.bookingId = String(b.id);
        bar.dataset.rowIdx = String(gridRowIdx);

        bar.title = b.guest_name + ' (' + b.status + ') \u2022 ' +
            b.check_in + ' \u2192 ' + b.check_out + ' (' + totalNights + (totalNights === 1 ? ' night)' : ' nights)');

        const pxWidth = spanDays * cellWidth;
        let metaHtml = '';
        if (pxWidth >= 145) {
            metaHtml = '<span class="cal-bar-meta">' + totalNights + 'n \u00B7 ' + esc(b.guests_count || 1) + 'p</span>';
        } else if (pxWidth >= 96) {
            metaHtml = '<span class="cal-bar-meta">' + totalNights + 'n</span>';
        }

        bar.innerHTML =
            '<span class="cal-bar-main">' +
                '<span class="cal-bar-dot"></span>' +
                '<span class="cal-bar-name">' + esc(b.guest_name) + '</span>' +
            '</span>' +
            metaHtml;

        bar.addEventListener('mouseenter', function () { setRowHover(gridRowIdx, true); });
        bar.addEventListener('mouseleave', function () { setRowHover(gridRowIdx, false); });

        initDrag(bar, b, startCol, spanDays, cellWidth, labelWidth);
        gridEl.appendChild(bar);
        return true;
    }

    // ── Drag-and-Drop Rescheduling ────────────────────────────────────────────
    function initDrag(bar, booking, origStartCol, spanDays, cellWidth, labelWidth) {
        let startX = 0;
        let isDragging = false;
        let wasDragged = false;
        const canDrag = (booking.status !== 'Cancelled' && booking.status !== 'Checked Out');

        bar.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            e.preventDefault();
            startX = e.clientX;
            isDragging = true;
            wasDragged = false;

            const totalNights = Math.max(1, diffDays(parseDate(booking.check_in), parseDate(booking.check_out)));
            const onMove = function (ev) {
                if (!isDragging || !canDrag) return;
                const dx = ev.clientX - startX;
                if (Math.abs(dx) > 4) {
                    wasDragged = true;
                    bar.classList.add('dragging');
                    const colDelta = Math.round(dx / cellWidth);
                    bar.style.transform = 'translateX(' + (colDelta * cellWidth) + 'px)';

                    const previewCi = formatISO(addDays(parseDate(booking.check_in), colDelta));
                    const previewCo = formatISO(addDays(parseDate(booking.check_out), colDelta));
                    dragPillEl.textContent = booking.guest_name + ': ' +
                        MONTH_SHORT[parseDate(previewCi).getMonth()] + ' ' + parseDate(previewCi).getDate() +
                        ' \u2192 ' +
                        MONTH_SHORT[parseDate(previewCo).getMonth()] + ' ' + parseDate(previewCo).getDate() +
                        ' (' + totalNights + 'n)';
                    dragPillEl.style.left = Math.min(window.innerWidth - 260, Math.max(12, ev.clientX + 14)) + 'px';
                    dragPillEl.style.top  = Math.max(12, ev.clientY - 38) + 'px';
                    dragPillEl.classList.add('visible');
                }
            };

            const onUp = function (ev) {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                isDragging = false;
                bar.classList.remove('dragging');
                bar.style.transform = '';
                dragPillEl.classList.remove('visible');

                if (!wasDragged) {
                    showDetail(booking);
                    return;
                }

                const dx = ev.clientX - startX;
                const colDelta = Math.round(dx / cellWidth);
                if (colDelta === 0) return;

                const newCheckIn  = formatISO(addDays(parseDate(booking.check_in), colDelta));
                const newCheckOut = formatISO(addDays(parseDate(booking.check_out), colDelta));
                saveMove(booking, newCheckIn, newCheckOut);
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }

    function saveMove(booking, newCheckIn, newCheckOut) {
        const updateApiPath = window.location.pathname.includes('/frontend/')
            ? '../backend/api/calendar_update.php'
            : 'backend/api/calendar_update.php';

        fetch(updateApiPath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: booking.id,
                new_check_in: newCheckIn,
                new_check_out: newCheckOut
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                showToast('Updated: ' + booking.guest_name + ' \u2192 ' + newCheckIn + ' to ' + newCheckOut, 'success');
                fetchData();
            } else {
                showToast(res.error || 'Could not update dates.', 'error');
            }
        })
        .catch(function (err) {
            console.error('Save move error:', err);
            showToast('Network error saving schedule.', 'error');
        });
    }

    // ── Booking Detail Slide-Over ─────────────────────────────────────────────
    function showDetail(b) {
        const badge   = getStatusBadgeInfo(b.status);
        const nights  = Math.max(1, diffDays(parseDate(b.check_in), parseDate(b.check_out)));
        const roomObj = roomsData.find(function (r) { return String(r.id) === String(b.room_id); });
        const estRate = roomObj ? (nights * Number(roomObj.price || 0)) : 0;
        const canDrag = (b.status !== 'Cancelled' && b.status !== 'Checked Out');

        detailBody.innerHTML =
            '<div class="cal-detail-hero">' +
                '<div class="cal-detail-hero-top">' +
                    '<span class="cal-detail-id">Booking #' + esc(b.id) + '</span>' +
                    '<span class="badge" style="background:' + badge.bg + ';color:' + badge.color + ';border:1px solid transparent;">' + esc(b.status) + '</span>' +
                '</div>' +
                '<h4 class="cal-detail-guest-title">' + esc(b.guest_name) + '</h4>' +
                '<p class="cal-detail-guest-sub">' + esc(b.guest_email || 'No email on record') + '</p>' +
                '<div class="cal-detail-stay-grid">' +
                    '<div class="cal-stay-date-box"><span>Check-In</span><strong>' + esc(formatNiceDate(b.check_in)) + '</strong></div>' +
                    '<span class="cal-stay-nights-pill">' + nights + (nights === 1 ? ' Night' : ' Nights') + '</span>' +
                    '<div class="cal-stay-date-box" style="text-align:right;"><span>Check-Out</span><strong>' + esc(formatNiceDate(b.check_out)) + '</strong></div>' +
                '</div>' +
            '</div>' +
            '<div class="cal-detail-row"><span class="cal-detail-key">Room Assignment</span><span class="cal-detail-val">' + esc(b.room_name) + (b.room_number ? ' (Unit ' + esc(b.room_number) + ')' : '') + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-key">Category</span><span class="cal-detail-val">' + esc(formatTypeTitle(b.room_type)) + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-key">Party Size</span><span class="cal-detail-val">' + esc(b.guests_count || 1) + (Number(b.guests_count || 1) === 1 ? ' Guest' : ' Guests') + '</span></div>' +
            '<div class="cal-detail-row"><span class="cal-detail-key">Estimated Arrival (ETA)</span><span class="cal-detail-val">' + esc(b.eta || 'Not specified') + '</span></div>' +
            (estRate > 0 ? '<div class="cal-detail-row"><span class="cal-detail-key">Base Room Estimate</span><span class="cal-detail-val">' + esc(formatPeso(estRate)) + ' (' + esc(formatPeso(roomObj.price)) + '/night)</span></div>' : '') +
            (canDrag ? '<div class="cal-detail-tip">Tip: Drag this guest\'s timeline bar left or right on the schedule grid to shift their check-in and check-out dates.</div>' : '');

        detailLink.href = 'admin_reservations?highlight=' + encodeURIComponent(b.id);
        overlay.classList.add('open');
        panel.classList.add('open');
    }

    function closeDetail() {
        overlay.classList.remove('open');
        panel.classList.remove('open');
    }

    overlay.addEventListener('click', closeDetail);
    document.getElementById('calDetailClose').addEventListener('click', closeDetail);

    // ── Toolbar Event Listeners ───────────────────────────────────────────────
    document.getElementById('calPrev').addEventListener('click', function () {
        viewStart = addDays(viewStart, -7);
        fetchData();
    });

    document.getElementById('calNext').addEventListener('click', function () {
        viewStart = addDays(viewStart, 7);
        fetchData();
    });

    document.getElementById('calToday').addEventListener('click', function () {
        viewStart = addDays(getEffectiveToday(), -3);
        fetchData();
    });

    horizonBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const newDays = parseInt(btn.dataset.days, 10) || 30;
            if (newDays === DAYS_TO_SHOW) return;
            DAYS_TO_SHOW = newDays;
            horizonBtns.forEach(function (b) { b.classList.toggle('active', b === btn); });
            fetchData();
        });
    });

    filterSelect.addEventListener('change', render);
    statusSelect.addEventListener('change', function () {
        statusFilterVal = statusSelect.value || 'all';
        render();
    });

    searchInput.addEventListener('input', function () {
        searchQuery = searchInput.value;
        searchClear.classList.toggle('visible', searchQuery.trim().length > 0);
        render();
    });

    searchClear.addEventListener('click', function () {
        searchInput.value = '';
        searchQuery = '';
        searchClear.classList.remove('visible');
        render();
        searchInput.focus();
    });

    let resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (roomsData.length > 0) render();
        }, 140);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDetail();
            const availOv = document.getElementById('availModalOverlay');
            if (availOv) availOv.classList.remove('open');
        }
    });

    // ── Availability Search Modal ─────────────────────────────────────────────
    function setupAvailabilitySearch() {
        const ciInput   = document.getElementById('availabilityCheckIn');
        const coInput   = document.getElementById('availabilityCheckOut');
        const searchBtn = document.getElementById('availabilitySearchBtn');
        const modalOv   = document.getElementById('availModalOverlay');
        const modalBody = document.getElementById('availModalBody');
        const subtitle  = document.getElementById('availModalSubtitle');
        const closeBtn  = document.getElementById('availModalClose');

        if (!ciInput || !coInput || !searchBtn || !modalOv) return;

        const today    = getEffectiveToday();
        const tomorrow = addDays(today, 1);
        ciInput.value = formatISO(today);
        coInput.value = formatISO(tomorrow);
        ciInput.min   = formatISO(today);
        coInput.min   = formatISO(tomorrow);

        ciInput.addEventListener('change', function () {
            if (!ciInput.value) return;
            const d = parseDate(ciInput.value);
            const nextDayStr = formatISO(addDays(d, 1));
            coInput.min = nextDayStr;
            if (!coInput.value || coInput.value <= ciInput.value) {
                coInput.value = nextDayStr;
            }
        });

        function closeModal() {
            modalOv.classList.remove('open');
        }

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        modalOv.addEventListener('click', function (e) {
            if (e.target === modalOv) closeModal();
        });

        function runAvailabilityCheck(ci, co) {
            if (!ci || !co) {
                showToast('Please select both check-in and check-out dates.', 'error');
                return;
            }
            if (co <= ci) {
                showToast('Check-out date must be after check-in date.', 'error');
                return;
            }

            const nights = Math.max(1, diffDays(parseDate(ci), parseDate(co)));
            subtitle.textContent = formatNiceDate(ci) + ' \u2192 ' + formatNiceDate(co) + ' (' + nights + (nights === 1 ? ' night)' : ' nights)');
            modalBody.innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);font-weight:600;">Checking live room inventory...</div>';
            modalOv.classList.add('open');

            const searchApiPath = window.location.pathname.includes('/frontend/')
                ? '../backend/api/calendar_api.php'
                : 'backend/api/calendar_api.php';

            fetch(searchApiPath + '?start=' + encodeURIComponent(ci) + '&end=' + encodeURIComponent(co))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) {
                        modalBody.innerHTML = '<div style="text-align:center;padding:24px;color:#DC2626;font-weight:600;">' + esc(data.error) + '</div>';
                        return;
                    }

                    const rooms    = data.rooms || [];
                    const bookings = data.bookings || [];
                    const availRooms = rooms.filter(function (room) {
                        const rStatus = String(room.status || '').toLowerCase();
                        if (rStatus === 'maintenance' || rStatus === 'out of order') return false;
                        const hasOverlap = bookings.some(function (b) {
                            if (String(b.room_id) !== String(room.id)) return false;
                            if (b.status === 'Cancelled' || b.status === 'Checked Out') return false;
                            return b.check_in < co && b.check_out > ci;
                        });
                        return !hasOverlap;
                    });

                    if (availRooms.length === 0) {
                        modalBody.innerHTML =
                            '<div style="text-align:center;padding:34px 20px;color:var(--text-muted);">' +
                                '<div style="font-size:15px;font-weight:700;color:var(--text-main);margin-bottom:6px;">No open units for these dates</div>' +
                                '<div style="font-size:13px;">All accommodations are occupied or in maintenance between ' + esc(ci) + ' and ' + esc(co) + '.</div>' +
                            '</div>';
                        return;
                    }

                    const typeFilter = filterSelect ? filterSelect.value : 'all';
                    const displayRooms = (typeFilter !== 'all')
                        ? availRooms.filter(function (r) { return r.type === typeFilter; })
                        : availRooms;

                    const summaryHtml =
                        '<div class="cal-avail-summary-bar">' +
                            '<span>' + displayRooms.length + (displayRooms.length === 1 ? ' accommodation' : ' accommodations') + ' ready to book</span>' +
                            '<span>' + nights + (nights === 1 ? ' night stay' : ' nights stay') + '</span>' +
                        '</div>';

                    if (displayRooms.length === 0) {
                        modalBody.innerHTML = summaryHtml +
                            '<div style="text-align:center;padding:24px;color:var(--text-muted);font-size:13px;">No units match the selected Room Type filter (' + esc(formatTypeTitle(typeFilter)) + '). Switch filter to All Room Types to view ' + availRooms.length + ' other open units.</div>';
                        return;
                    }

                    modalBody.innerHTML = summaryHtml + displayRooms.map(function (room) {
                        const totalStay = nights * Number(room.price || 0);
                        const numLabel = room.number ? String(room.number) : String(room.id);
                        return '<div class="cal-avail-card">' +
                            '<div class="cal-avail-card-left">' +
                                '<span class="cal-avail-num">' + esc(numLabel) + '</span>' +
                                '<div>' +
                                    '<div class="cal-avail-name">' + esc(room.name) + '</div>' +
                                    '<div class="cal-avail-meta">' + esc(formatTypeTitle(room.type_label || room.type)) + ' \u00B7 Up to ' + esc(room.capacity || 2) + ' guests</div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="cal-avail-card-right">' +
                                '<div class="cal-avail-price">' + esc(formatPeso(room.price)) + '/night <span style="font-weight:500;color:var(--text-muted);font-size:12px;">(' + esc(formatPeso(totalStay)) + ' total)</span></div>' +
                                '<span class="cal-avail-badge-open">Available</span>' +
                            '</div>' +
                        '</div>';
                    }).join('');
                })
                .catch(function (err) {
                    console.error('Availability search error:', err);
                    modalBody.innerHTML = '<div style="text-align:center;padding:24px;color:#DC2626;font-weight:600;">Network error checking availability.</div>';
                });
        }

        window.__runAvailabilityCheck = runAvailabilityCheck;
        searchBtn.addEventListener('click', function () {
            runAvailabilityCheck(ciInput.value, coInput.value);
        });
    }

    // ── Auto Date Sync Monitor ────────────────────────────────────────────────
    function syncCalendarWithSystemDate() {
        let lastKnownDate = formatISO(getEffectiveToday());
        setInterval(function () {
            const currentSysDate = formatISO(getEffectiveToday());
            if (currentSysDate !== lastKnownDate) {
                lastKnownDate = currentSysDate;
                viewStart = addDays(getEffectiveToday(), -3);
                fetchData();
                const ciInput = document.getElementById('availabilityCheckIn');
                const coInput = document.getElementById('availabilityCheckOut');
                if (ciInput) ciInput.min = currentSysDate;
                if (coInput) coInput.min = formatISO(addDays(getEffectiveToday(), 1));
            }
        }, 60000);
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    setupAvailabilitySearch();
    syncCalendarWithSystemDate();
    fetchData();
})();
</script>
