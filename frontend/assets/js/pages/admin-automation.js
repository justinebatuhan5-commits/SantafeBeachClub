(function () {
    const config = window.SFBC_AUTOMATION_CONFIG || {};
    const API_BASE = config.apiBase || '../backend/api/automation_api.php';
    const CSRF_TOKEN = config.csrfToken || '';

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        }[ch]));
    }

    function formatLogTimestamp(rawValue) {
        if (!rawValue) {
            return '—';
        }
        const parsed = new Date(rawValue);
        if (Number.isNaN(parsed.getTime())) {
            return String(rawValue);
        }
        return parsed.toLocaleDateString() + ' ' + parsed.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    async function readJsonResponse(res) {
        const rawText = await res.text();
        let data = null;
        if (rawText.trim() !== '') {
            try {
                data = JSON.parse(rawText);
            } catch (_parseErr) {
                throw new Error('Unexpected response (HTTP ' + res.status + ').');
            }
        }
        if (!res.ok || !data || !data.success) {
            const detail = (data && (data.error || data.message)) || ('HTTP ' + res.status);
            throw new Error(detail);
        }
        return data;
    }

    async function loadAutomationData() {
        const tasksContainer = document.getElementById('tasksContainer');
        const logsContainer = document.getElementById('logsContainer');
        if (!tasksContainer || !logsContainer) {
            return;
        }

        try {
            const res = await fetch(API_BASE, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const data = await readJsonResponse(res);

            const taskHtml = (data.tasks || []).map((t) => {
                const badgeLabel = t.enabled === false ? 'Paused' : 'Automated';
                return `
                    <div class="task-box">
                        <div>
                            <div class="task-header">
                                <span class="task-title">${escapeHtml(t.name)}</span>
                                <span class="task-badge">${escapeHtml(badgeLabel)}</span>
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
                `;
            }).join('');
            tasksContainer.innerHTML = taskHtml || '<div class="auto-error-msg">No scheduled tasks are configured.</div>';

            const logHtml = (data.recent_logs || []).map((l) => {
                const typeClass = l.type === 'success' ? 'log-success' : (l.type === 'warning' ? 'log-warning' : 'log-info');
                const timeStr = formatLogTimestamp(l.created_at);
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
            const message = err && err.message ? err.message : 'Unable to reach server.';
            tasksContainer.innerHTML = '<div class="auto-error-msg">Failed to communicate with Automation API (' + escapeHtml(message) + ').</div>';
            logsContainer.innerHTML = '<tr><td colspan="4" class="log-empty-cell">Unable to load automated activity logs.</td></tr>';
        }
    }

    async function triggerTask(taskId, btn) {
        const originalText = btn.textContent;
        btn.textContent = 'Running...';
        btn.disabled = true;

        try {
            const headers = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };
            if (CSRF_TOKEN) {
                headers['X-CSRF-TOKEN'] = CSRF_TOKEN;
            }

            const res = await fetch(API_BASE, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify({ task_id: taskId }),
            });
            const data = await readJsonResponse(res);
            alert('Task completed successfully:\n\n' + (data.output || data.message));
            await loadAutomationData();
        } catch (err) {
            const detail = err && err.message ? err.message : 'Unknown error';
            alert('Task failed: ' + detail);
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
