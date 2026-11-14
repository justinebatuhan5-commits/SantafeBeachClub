<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/config/db.php';

$page_title = 'Automation & Scheduler';
$page_subtitle = 'Monitor background jobs, scheduled tasks, and automated operations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Santa Fe Beach Club</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-circle-32.png">
    <style>
        .auto-card { background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .auto-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; margin-top: 16px; }
        .task-box { border: 1px solid #E2E8F0; border-radius: 10px; padding: 16px; background: #F8FAFC; display: flex; flex-direction: column; justify-content: space-between; }
        .task-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .task-title { font-weight: 700; font-size: 14px; color: #1E293B; }
        .task-badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: #DCFCE7; color: #15803D; }
        .task-desc { font-size: 12px; color: #64748B; line-height: 1.5; margin-bottom: 12px; }
        .task-schedule { font-size: 11px; font-weight: 600; color: #0284C7; display: flex; align-items: center; gap: 4px; margin-bottom: 12px; }
        .btn-trigger { background: #84563C; color: #fff; border: none; padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-trigger:hover { background: #6B442E; }
        .log-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .log-table th { text-align: left; padding: 10px 12px; background: #F1F5F9; color: #475569; font-weight: 600; border-bottom: 1px solid #E2E8F0; }
        .log-table td { padding: 10px 12px; border-bottom: 1px solid #F1F5F9; color: #334155; }
        .log-badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .log-info { background: #E0F2FE; color: #0369A1; }
        .log-success { background: #DCFCE7; color: #15803D; }
        .log-warning { background: #FEF3C7; color: #B45309; }
    </style>
</head>
<body>
    <?php $active_page = 'automation'; include __DIR__ . '/partials/_sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/partials/_page_header.php'; ?>

        <div class="admin-body">
            <!-- Scheduled Tasks Overview -->
            <div class="auto-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3 style="font-size:16px; font-weight:700; color:#1E293B; margin:0;">Active Laravel Scheduled Tasks</h3>
                        <p style="font-size:12px; color:#64748B; margin:4px 0 0;">Autonomous operations managed by Laravel scheduler daemon</p>
                    </div>
                    <span style="font-size:12px; color:#16A34A; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#16A34A;"></span> Scheduler Engine Active
                    </span>
                </div>

                <div class="auto-grid" id="tasksContainer">
                    <!-- Loaded dynamically via JS -->
                    <div style="color:#94A3B8; font-size:13px;">Loading automation tasks...</div>
                </div>
            </div>

            <!-- Execution Logs -->
            <div class="auto-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <div>
                        <h3 style="font-size:16px; font-weight:700; color:#1E293B; margin:0;">Automated Activity Log</h3>
                        <p style="font-size:12px; color:#64748B; margin:4px 0 0;">Real-time logs of background jobs and scheduled notifications</p>
                    </div>
                    <button type="button" onclick="loadAutomationData()" class="btn-primary" style="padding:6px 12px; font-size:12px; height:auto;">Refresh</button>
                </div>

                <div style="overflow-x:auto;">
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
                            <tr><td colspan="4" style="text-align:center; color:#94A3B8;">Loading log history...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const API_BASE = '../laravel_core/public/api/v1/automation';

        async function loadAutomationData() {
            try {
                const res = await fetch(API_BASE);
                const data = await res.json();
                if (!data.success) throw new Error('Failed to load');

                // Render tasks
                const taskHtml = data.tasks.map(t => `
                    <div class="task-box">
                        <div>
                            <div class="task-header">
                                <span class="task-title">${t.name}</span>
                                <span class="task-badge">Automated</span>
                            </div>
                            <div class="task-desc">${t.description}</div>
                        </div>
                        <div>
                            <div class="task-schedule">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                ${t.schedule}
                            </div>
                            <button type="button" class="btn-trigger" onclick="triggerTask('${t.id}', this)">Run Now</button>
                        </div>
                    </div>
                `).join('');
                document.getElementById('tasksContainer').innerHTML = taskHtml;

                // Render logs
                const logHtml = data.recent_logs.map(l => {
                    const typeClass = l.type === 'success' ? 'log-success' : (l.type === 'warning' ? 'log-warning' : 'log-info');
                    const d = new Date(l.created_at);
                    const timeStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    return `
                        <tr>
                            <td style="white-space:nowrap; font-size:11px; color:#64748B;">${timeStr}</td>
                            <td><span class="log-badge ${typeClass}">${l.type}</span></td>
                            <td><strong>${l.title}</strong></td>
                            <td style="font-size:12px; color:#475569;">${l.message}</td>
                        </tr>
                    `;
                }).join('');
                document.getElementById('logsContainer').innerHTML = logHtml || '<tr><td colspan="4" style="text-align:center;">No recent automated events logged.</td></tr>';
            } catch (err) {
                console.error(err);
                document.getElementById('tasksContainer').innerHTML = '<div style="color:#DC2626;">Failed to communicate with Laravel Automation API.</div>';
            }
        }

        async function triggerTask(taskId, btn) {
            const originalText = btn.textContent;
            btn.textContent = 'Running...';
            btn.disabled = true;

            try {
                const res = await fetch(API_BASE + '/trigger', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ task_id: taskId })
                });
                const data = await res.json();
                if (data.success) {
                    alert('Task completed successfully:\n\n' + (data.output || data.message));
                    loadAutomationData();
                } else {
                    alert('Task failed: ' + data.error);
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            } finally {
                btn.textContent = originalText;
                btn.disabled = false;
            }
        }

        document.addEventListener('DOMContentLoaded', loadAutomationData);
    </script>
</body>
</html>
