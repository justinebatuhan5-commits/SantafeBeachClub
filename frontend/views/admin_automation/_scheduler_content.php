<div class="auto-card">
    <div class="auto-card-header">
        <div>
            <h3 class="auto-card-title">Active Laravel Scheduled Tasks</h3>
            <p class="auto-card-sub">Autonomous operations managed by Laravel scheduler daemon</p>
        </div>
        <span class="auto-status-pill">
            <span class="auto-status-dot"></span> Scheduler Engine Active
        </span>
    </div>

    <div class="auto-grid" id="tasksContainer">
        <div class="auto-loading-msg">Loading automation tasks...</div>
    </div>
</div>

<div class="auto-card">
    <div class="auto-card-header auto-card-header--logs">
        <div>
            <h3 class="auto-card-title">Automated Activity Log</h3>
            <p class="auto-card-sub">Real-time logs of background jobs and scheduled notifications</p>
        </div>
        <button type="button" class="btn-primary auto-refresh-btn" data-refresh-automation>Refresh</button>
    </div>

    <div class="auto-table-scroll">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Category / Type</th>
                    <th>Event Title</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody id="logsContainer">
                <tr><td colspan="4" class="log-empty-cell">Loading log history...</td></tr>
            </tbody>
        </table>
    </div>
</div>
