(() => {
    'use strict';
    if (window.SiteDialog) return;
    let queue = Promise.resolve();
    function open(message, options = {}) {
        const task = queue.then(() => new Promise(resolve => {
            if (!document.getElementById('site-dialog-style')) {
                const style = document.createElement('style');
                style.id = 'site-dialog-style';
                style.textContent = `
                    .site-dialog{position:fixed;inset:0;box-sizing:border-box;width:calc(100% - 32px);max-width:440px;max-height:90vh;overflow:auto;margin:auto;padding:30px;border:1px solid #e8ded5;border-radius:22px;background:#fffdf9;color:#30261f;box-shadow:0 24px 80px #24180d40;font-family:Arial,sans-serif;text-align:left;z-index:999999}
                    .site-dialog::backdrop{background:#18120e99;backdrop-filter:blur(5px)}
                    .site-dialog-brand{font-size:11px;font-weight:700;letter-spacing:2px;color:#916445;text-transform:uppercase;margin-bottom:22px}
                    .site-dialog-icon{display:grid;place-items:center;width:48px;height:48px;border-radius:14px;background:#f4e8dc;color:#916445;font-size:24px;margin-bottom:18px}
                    .site-dialog h2{font-size:23px;line-height:1.25;margin:0 0 12px;color:#30261f}
                    .site-dialog p{font-size:14px;line-height:1.7;color:#6f6257;white-space:pre-wrap;overflow-wrap:anywhere;margin:0 0 26px}
                    .site-dialog-actions{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:10px}
                    .site-dialog button{border-radius:10px;padding:12px 20px;border:1px solid #ded2c6;background:#fffdf9;color:#594737;font:600 14px Arial,sans-serif;cursor:pointer}
                    .site-dialog button.site-dialog-ok{background:#79543b;color:#fff;border-color:transparent}
                    .site-dialog button.site-dialog-danger{background:#b73535}
                    .site-dialog button:hover{filter:brightness(.95)}
                    .site-dialog button:focus-visible{outline:3px solid #bc946c;outline-offset:3px}
                `;
                document.head.append(style);
            }
            const previousFocus = document.activeElement;
            const dialog = document.createElement('dialog');
            dialog.className = 'site-dialog';
            dialog.setAttribute('aria-labelledby', 'site-dialog-title');
            dialog.setAttribute('aria-describedby', 'site-dialog-message');
            dialog.innerHTML = '<div class="site-dialog-brand">Santa Fe Beach Club</div><div class="site-dialog-icon" aria-hidden="true"></div><h2 id="site-dialog-title"></h2><p id="site-dialog-message"></p><div class="site-dialog-actions"><button type="button" class="site-dialog-cancel">Cancel</button><button type="button" class="site-dialog-ok"></button></div>';
            dialog.querySelector('h2').textContent = options.title || (options.confirm ? 'Please confirm' : 'Notice');
            dialog.querySelector('p').textContent = String(message);
            dialog.querySelector('.site-dialog-icon').textContent = options.icon || (options.confirm ? '!' : 'i');
            const cancel = dialog.querySelector('.site-dialog-cancel');
            const ok = dialog.querySelector('.site-dialog-ok');
            cancel.hidden = !options.confirm;
            ok.textContent = options.confirmText || (options.confirm ? 'Confirm' : 'OK');
            if (options.danger) ok.classList.add('site-dialog-danger');
            let settled = false;
            function finish(accepted) {
                if (settled) return;
                settled = true;
                dialog.close();
                dialog.remove();
                if (previousFocus && previousFocus.isConnected) previousFocus.focus();
                resolve(accepted);
            }
            ok.addEventListener('click', () => finish(true));
            cancel.addEventListener('click', () => finish(false));
            dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
            document.body.append(dialog);
            dialog.showModal();
            (options.confirm ? cancel : ok).focus();
        }));
        queue = task.catch(() => {});
        return task;
    }
    window.SiteDialog = {
        alert: (message, options) => open(message, options),
        confirm: (message, options) => open(message, { ...options, confirm: true })
    };
    const approved = new WeakSet();
    const pending = new WeakSet();
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('form[data-site-confirm]')) return;
        if (approved.has(form)) { approved.delete(form); return; }
        event.preventDefault();
        if (pending.has(form)) return;
        pending.add(form);
        const submitter = event.submitter;
        try {
            const accepted = await window.SiteDialog.confirm(form.dataset.siteConfirm, {
                title: form.dataset.siteTitle,
                confirmText: form.dataset.siteButton,
                danger: true
            });
            if (accepted && form.isConnected) {
                approved.add(form);
                try { form.requestSubmit(submitter); } finally { approved.delete(form); }
            }
        } finally { pending.delete(form); }
    });
})();
