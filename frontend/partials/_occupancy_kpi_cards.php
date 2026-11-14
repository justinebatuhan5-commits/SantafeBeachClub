<!-- Shared Live Room Occupancy & Front Desk KPI Strip -->
<section class="cal-kpi-grid<?php echo !empty($kpi_extra_class) ? ' ' . htmlspecialchars($kpi_extra_class, ENT_QUOTES, 'UTF-8') : ''; ?>" id="calKpiGrid" aria-label="Occupancy overview">
    <div class="cal-kpi-card">
        <div class="cal-kpi-body">
            <div class="cal-kpi-label">Occupied Today</div>
            <div class="cal-kpi-value" id="kpiOccupiedPct">&mdash;</div>
            <div class="cal-kpi-sub" id="kpiOccupiedSub">Loading room count...</div>
        </div>
        <div class="cal-kpi-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20v-8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8"/><path d="M5 10V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4"/><path d="M3 18h18"/></svg>
        </div>
    </div>

    <div class="cal-kpi-card kpi-arrivals">
        <div class="cal-kpi-body">
            <div class="cal-kpi-label">Arrivals Today</div>
            <div class="cal-kpi-value" id="kpiArrivalsVal">&mdash;</div>
            <div class="cal-kpi-sub" id="kpiArrivalsSub">Scheduled check-ins</div>
        </div>
        <div class="cal-kpi-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        </div>
    </div>

    <div class="cal-kpi-card kpi-departures">
        <div class="cal-kpi-body">
            <div class="cal-kpi-label">Departures Today</div>
            <div class="cal-kpi-value" id="kpiDeparturesVal">&mdash;</div>
            <div class="cal-kpi-sub" id="kpiDeparturesSub">Scheduled check-outs</div>
        </div>
        <div class="cal-kpi-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </div>
    </div>

    <div class="cal-kpi-card kpi-ready">
        <div class="cal-kpi-body">
            <div class="cal-kpi-label">Units Available</div>
            <div class="cal-kpi-value" id="kpiReadyVal">&mdash;</div>
            <div class="cal-kpi-sub" id="kpiReadySub">Open for tonight</div>
        </div>
        <div class="cal-kpi-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
    </div>
</section>
