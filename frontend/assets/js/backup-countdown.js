(() => {
    'use strict';
    const data = document.getElementById('backup-countdown-data');
    if (!data) return;
    let state = JSON.parse(data.textContent);
    let receivedAt = performance.now();
    let stale = false;
    const title = document.getElementById('backup-countdown-status');
    const note = document.getElementById('backup-countdown-note');
    const digits = ['hours', 'minutes', 'seconds'].map(unit => document.getElementById(`backup-${unit}`));
    const savedTime = document.getElementById('backup-pc-saved-time');
    const savedName = document.getElementById('backup-pc-saved-name');
    const checkButton = document.getElementById('backup-check-status');
    function render() {
        let remaining = null;
        if (stale || !state.available) {
            title.textContent = 'Schedule update unavailable';
            note.textContent = 'Reconnecting to check the latest PC request.';
        } else if (!state.enabled) {
            title.textContent = 'Connect your PC to get started';
            note.textContent = 'Enable PC access and complete the Windows setup below.';
        } else if (!state.lastCreated) {
            title.textContent = 'Waiting for the first PC backup';
            note.textContent = 'The countdown starts after the PC requests its first backup.';
        } else {
            const now = state.serverNow + (performance.now() - receivedAt) / 1000;
            remaining = Math.max(0, Math.ceil(state.lastCreated + state.intervalSeconds - now));
            title.textContent = remaining ? 'Your next backup is approaching' : 'Waiting for the next PC request';
            note.textContent = remaining
                ? 'Estimate based on the last PC request + 6 hours. Keep your PC awake, online, and signed in. A custom Windows schedule may differ.'
                : 'The estimated time has passed. Windows will run the task when eligible; this page cannot confirm a saved download.';
        }
        const values = remaining === null ? ['--', '--', '--'] : [Math.floor(remaining / 3600), Math.floor(remaining / 60) % 60, remaining % 60].map(value => String(value).padStart(2, '0'));
        digits.forEach((element, index) => { element.textContent = values[index]; });
        if (savedTime && savedName) {
            savedTime.textContent = state.pcSavedAt
                ? new Date(state.pcSavedAt * 1000).toLocaleString()
                : 'Not confirmed yet';
            savedName.textContent = state.pcSavedName || 'Waiting for the PC to save and verify a bundle';
        }
    }
    let refreshing = false;
    async function refresh() {
        if (refreshing || document.hidden) return;
        refreshing = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('backup_status', '1');
            const response = await fetch(url, { cache: 'no-store', signal: controller.signal });
            if (!response.ok) throw new Error('Status unavailable');
            const next = await response.json();
            if (!Number.isFinite(next.serverNow) || !Number.isFinite(next.lastCreated) || !(next.intervalSeconds > 0)) throw new Error('Invalid status');
            state = next;
            receivedAt = performance.now();
            stale = false;
        } catch (_) { stale = true; }
        finally { clearTimeout(timeout); refreshing = false; if (checkButton) checkButton.disabled = false; render(); }
    }
    render();
    setInterval(render, 1000);
    setInterval(refresh, 30000);
    checkButton?.addEventListener('click', () => { checkButton.disabled = true; refresh(); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
