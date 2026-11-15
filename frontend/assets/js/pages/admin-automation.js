(function () {
    const API_BASE = '../laravel_core/public/api/v1/automation';

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        }[ch]));
    }

    async function loadAutomationData() {
        const tasksContainer = document.getElementById('tasksContainer');
        const logsContainer = document.getElementById('logsContainer');
        if (!tasksContainer || !logsContainer) {
            return;
        }

        try {
            const res = await fetch(API_BASE);
            const data = await res.json();
            if (!data.success) {
                throw new Error('Failed to load');
            }

            const taskHtml = (data.tasks || []).map((t) => `
                <div class="task-box">
                    <div>
                        <div class="task-header">
                            <span class="task-title">${escapeHtml(t.name)}</span>
                            <span class="task-badge">Automated</span>
                        </div>
                        <div class="task-desc">${escapeHtml(t.description)}</div>
                    </div>
                    <div>
                        <div class="task-schedule">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            ${escapeHtml(t.schedule)}
                        </div>
                        <button type="button" class="btn-trigger" data-task-id="${escapeHtml(t.id)}">Run Now</button>
                    </div>
                </div>
            `).join('');
            tasksContainer.innerHTML = taskHtml;

            const logHtml = (data.recent_logs || []).map((l) => {
                const typeClass = l.type === 'success' ? 'log-success' : (l.type === 'warning' ? 'log-warning' : 'log-info');
                const d = new Date(l.created_at);
                const timeStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                return `
                    <tr>
                        <td class="log-time-cell">${escapeHtml(timeStr)}</td>
                        <td><span class="log-badge ${typeClass}">${escapeHtml(l.type)}</span></td>
                        <td><strong>${escapeHtml(l.title)}</strong></td>
                        <td class="log-message-cell">${escapeHtml(l.message)}</td>
                    </tr>
                `;
            }).join('');
            logsContainer.innerHTML = logHtml || '<tr><td colspan="4" class="log-empty-cell">No recent automated events logged.</td></tr>';
        } catch (err) {
            console.error(err);
            tasksContainer.innerHTML = '<div class="auto-error-msg">Failed to communicate with Laravel Automation API.</div>';
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
                body: JSON.stringify({ task_id: taskId }),
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

    window.loadAutomationData = loadAutomationData;
    window.triggerTask = triggerTask;

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-refresh-automation]')) {
            loadAutomationData();
            return;
        }
        const triggerBtn = event.target.closest('.btn-trigger[data-task-id]');
        if (triggerBtn) {
            triggerTask(triggerBtn.dataset.taskId || '', triggerBtn);
        }
    });

    document.addEventListener('DOMContentLoaded', loadAutomationData);
})();
