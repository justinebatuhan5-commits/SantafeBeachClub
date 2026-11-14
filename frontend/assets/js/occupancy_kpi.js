/**
 * occupancy_kpi.js — Shared live room occupancy & daily operations KPI helper
 * Used by both frontend/admin_calendar.php and frontend/admin_dashboard.php.
 */
(function (global) {
    'use strict';

    function getEffectiveToday() {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate());
    }

    function formatISO(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function computeOccupiedTodaySet(bookings, todayStr) {
        const occupied = new Set();
        (bookings || []).forEach(function (b) {
            if (b.status !== 'Cancelled' && b.status !== 'Checked Out' &&
                b.check_in <= todayStr && b.check_out > todayStr) {
                occupied.add(String(b.room_id));
            }
        });
        return occupied;
    }

    function calculateTodayStats(rooms, bookings, todayStr) {
        const safeRooms = rooms || [];
        const safeBookings = bookings || [];
        const totalRooms = safeRooms.length;
        const occupiedSet = computeOccupiedTodaySet(safeBookings, todayStr);
        const occupiedCount = occupiedSet.size;
        const pct = totalRooms > 0 ? Math.round((occupiedCount / totalRooms) * 100) : 0;

        let arrivals = 0;
        let departures = 0;
        safeBookings.forEach(function (b) {
            if (b.status !== 'Cancelled' && b.status !== 'Checked Out' && b.check_in === todayStr) {
                arrivals++;
            }
            if (b.status !== 'Cancelled' && b.check_out === todayStr) {
                departures++;
            }
        });

        let maintCount = 0;
        let readyCount = 0;
        safeRooms.forEach(function (r) {
            const rs = String(r.status || '').toLowerCase();
            if (rs === 'maintenance' || rs === 'out of order') {
                maintCount++;
            } else if (!occupiedSet.has(String(r.id))) {
                readyCount++;
            }
        });

        return {
            totalRooms: totalRooms,
            occupiedCount: occupiedCount,
            pct: pct,
            arrivals: arrivals,
            departures: departures,
            readyCount: readyCount,
            maintCount: maintCount
        };
    }

    function renderStatsToDOM(stats) {
        if (!stats) return;
        const occPct = document.getElementById('kpiOccupiedPct');
        const occSub = document.getElementById('kpiOccupiedSub');
        const arrVal = document.getElementById('kpiArrivalsVal');
        const arrSub = document.getElementById('kpiArrivalsSub');
        const depVal = document.getElementById('kpiDeparturesVal');
        const depSub = document.getElementById('kpiDeparturesSub');
        const rdyVal = document.getElementById('kpiReadyVal');
        const rdySub = document.getElementById('kpiReadySub');

        if (occPct) occPct.textContent = stats.pct + '%';
        if (occSub) occSub.textContent = stats.occupiedCount + ' of ' + stats.totalRooms + ' units occupied';
        if (arrVal) arrVal.textContent = String(stats.arrivals);
        if (arrSub) arrSub.textContent = stats.arrivals === 1 ? '1 check-in due today' : stats.arrivals + ' check-ins due today';
        if (depVal) depVal.textContent = String(stats.departures);
        if (depSub) depSub.textContent = stats.departures === 1 ? '1 check-out due today' : stats.departures + ' check-outs due today';
        if (rdyVal) rdyVal.textContent = String(stats.readyCount);
        if (rdySub) {
            rdySub.textContent = stats.maintCount > 0
                ? (stats.maintCount + ' in maintenance')
                : 'Open for tonight';
        }
    }

    function fetchAndRenderToday() {
        const todayStr = formatISO(getEffectiveToday());
        const apiPath = window.location.pathname.includes('/frontend/')
            ? '../backend/api/calendar_api.php'
            : 'backend/api/calendar_api.php';

        return fetch(apiPath + '?start=' + encodeURIComponent(todayStr) + '&end=' + encodeURIComponent(todayStr))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.error) return null;
                const stats = calculateTodayStats(data.rooms || [], data.bookings || [], todayStr);
                renderStatsToDOM(stats);
                return stats;
            })
            .catch(function (err) {
                console.error('Occupancy KPI fetch error:', err);
                return null;
            });
    }

    global.SantaFeOccupancyKPI = {
        getEffectiveToday: getEffectiveToday,
        formatISO: formatISO,
        computeOccupiedTodaySet: computeOccupiedTodaySet,
        calculateTodayStats: calculateTodayStats,
        renderStatsToDOM: renderStatsToDOM,
        fetchAndRenderToday: fetchAndRenderToday
    };
})(window);
