/* Santa Fe Beach Club — Read-Only Staff AI Operations Assistant */
(function() {
    const cfg = window.SFBC_STAFF_AI_CONFIG || {};
    const STAFF_CSRF = cfg.csrfToken || '';
    const STAFF_SCOPE = cfg.scope || '';
    const STAFF_STATE_PREFIX = 'sfbc_staff_ai_state_';
    const STAFF_STATE_KEY = STAFF_STATE_PREFIX + STAFF_SCOPE;
    const MAX_HISTORY = 40;

    function getStaffEls() {
        return {
            toggleBtn: document.getElementById('sfbcStaffAiToggle'),
            box: document.getElementById('sfbcStaffAiBox'),
            input: document.getElementById('sfbcStaffAiInput'),
            messages: document.getElementById('sfbcStaffAiMessages'),
            greeting: document.getElementById('sfbcStaffGreetingMsg')
        };
    }

    function staffEscape(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function staffSafeFormat(text) {
        let out = staffEscape(text);
        out = out.replace(/\[([^\]]{1,80})\]\(([a-zA-Z0-9_.\-?#=&]+)\)/g, function(_, label, href) {
            if (href.indexOf('..') !== -1) return label;
            return '<a href="' + staffEscape(href) + '" style="color:#0284C7;font-weight:700;text-decoration:underline;">' + label + ' &rarr;</a>';
        });
        out = out.replace(/\*\*([^*]{1,120})\*\*/g, '<strong>$1</strong>');
        out = out.replace(/`([^`]{1,60})`/g, '<code>$1</code>');
        return out.replace(/\r?\n/g, '<br>');
    }

    function pruneStaffStates(keepCurrentKey) {
        try {
            const toRemove = [];
            for (let i = 0; i < sessionStorage.length; i++) {
                const k = sessionStorage.key(i);
                if (k && k.indexOf(STAFF_STATE_PREFIX) === 0 && (!keepCurrentKey || k !== STAFF_STATE_KEY)) {
                    toRemove.push(k);
                }
            }
            for (let i = 0; i < toRemove.length; i++) {
                sessionStorage.removeItem(toRemove[i]);
            }
        } catch (e) {}
    }

    function readStaffState() {
        const empty = { open: false, draft: '', scrollTop: null, messages: [] };
        try {
            const raw = sessionStorage.getItem(STAFF_STATE_KEY);
            if (!raw) return empty;
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return empty;
            const msgs = Array.isArray(parsed.messages)
                ? parsed.messages.filter(function(item) {
                    return item &&
                        (item.role === 'user' || item.role === 'assistant' || item.role === 'error') &&
                        typeof item.text === 'string' &&
                        item.text.length > 0;
                }).slice(-MAX_HISTORY)
                : [];
            return {
                open: parsed.open === true,
                draft: typeof parsed.draft === 'string' ? parsed.draft.slice(0, 400) : '',
                scrollTop: (typeof parsed.scrollTop === 'number' && isFinite(parsed.scrollTop)) ? parsed.scrollTop : null,
                messages: msgs
            };
        } catch (e) {
            return empty;
        }
    }

    function writeStaffState(state) {
        try {
            const trimmed = {
                open: state.open === true,
                draft: typeof state.draft === 'string' ? state.draft.slice(0, 400) : '',
                scrollTop: (typeof state.scrollTop === 'number' && isFinite(state.scrollTop)) ? Math.max(0, Math.round(state.scrollTop)) : null,
                messages: Array.isArray(state.messages) ? state.messages.slice(-MAX_HISTORY) : []
            };
            sessionStorage.setItem(STAFF_STATE_KEY, JSON.stringify(trimmed));
        } catch (e) {}
    }

    let isLoggingOut = false;
    let isSending = false;
    pruneStaffStates(true);
    let staffState = readStaffState();

    const BUBBLE_STYLES = {
        user: 'align-self:flex-end; background:#0284C7; color:#fff; padding:8px 12px; border-radius:10px; max-width:85%; line-height:1.4; word-break:break-word;',
        assistant: 'align-self:flex-start; background:rgba(148,163,184,0.12); padding:10px 12px; border-radius:10px; max-width:90%; line-height:1.45; word-break:break-word;',
        error: 'align-self:flex-start; background:#FEE2E2; color:#991B1B; padding:8px 12px; border-radius:10px; max-width:90%; line-height:1.4; word-break:break-word;'
    };

    function renderBubble(role, text, messagesEl) {
        const div = document.createElement('div');
        div.style.cssText = BUBBLE_STYLES[role] || BUBBLE_STYLES.assistant;
        if (role === 'assistant') {
            div.innerHTML = staffSafeFormat(text);
        } else {
            div.textContent = text;
        }
        messagesEl.appendChild(div);
        return div;
    }

    function restoreScrollPosition(messagesEl) {
        if (!messagesEl) return;
        const targetTop = (typeof staffState.scrollTop === 'number')
            ? staffState.scrollTop
            : messagesEl.scrollHeight;
        messagesEl.scrollTop = targetTop;
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(function() {
                messagesEl.scrollTop = (typeof staffState.scrollTop === 'number')
                    ? staffState.scrollTop
                    : messagesEl.scrollHeight;
            });
        }
    }

    function applyBoxOpen(shouldOpen, options) {
        const els = getStaffEls();
        if (!els.box) return;
        const opts = options || {};
        if (shouldOpen) {
            els.box.style.setProperty('display', 'flex', 'important');
            els.box.classList.add('sfbc-staff-open');
            restoreScrollPosition(els.messages);
            if (opts.focus !== false && els.input) {
                setTimeout(function() { els.input.focus(); }, 60);
            }
        } else {
            els.box.style.setProperty('display', 'none', 'important');
            els.box.classList.remove('sfbc-staff-open');
        }
        if (opts.persist !== false && !isLoggingOut) {
            staffState.open = shouldOpen;
            writeStaffState(staffState);
        }
    }

    let sfbcStaffLastToggleTime = 0;
    window.sfbcStaffAiToggleBox = function(forceState) {
        const now = Date.now();
        if (typeof forceState !== 'boolean' && (now - sfbcStaffLastToggleTime < 300)) {
            return;
        }
        sfbcStaffLastToggleTime = now;

        const els = getStaffEls();
        if (!els.box) return;
        const open = (els.box.style.display === 'flex' || els.box.classList.contains('sfbc-staff-open'));
        const shouldOpen = (typeof forceState === 'boolean') ? forceState : !open;
        applyBoxOpen(shouldOpen, { focus: shouldOpen, persist: true });
    };

    window.sfbcStaffClearChat = function() {
        const els = getStaffEls();
        staffState.messages = [];
        staffState.draft = '';
        staffState.scrollTop = null;
        if (!isLoggingOut) {
            writeStaffState(staffState);
        }
        if (els.input) {
            els.input.value = '';
        }
        if (els.messages) {
            while (els.messages.lastElementChild) {
                if (els.greeting && els.messages.lastElementChild === els.greeting) {
                    break;
                }
                els.messages.removeChild(els.messages.lastElementChild);
            }
            els.messages.scrollTop = 0;
        }
    };

    // Toggle staff assistant between normal and big/expanded window size
    window.sfbcStaffToggleExpand = function(forceState) {
        const box = document.getElementById('sfbcStaffAiBox');
        const iconExp = document.getElementById('sfbcStaffIconExpand');
        const iconRes = document.getElementById('sfbcStaffIconRestore');
        const btn = document.getElementById('sfbcStaffExpandBtn');
        if (!box) return;
        const isExpanded = box.classList.contains('sfbc-staff-expanded');
        const shouldExpand = (typeof forceState === 'boolean') ? forceState : !isExpanded;

        if (shouldExpand) {
            box.classList.add('sfbc-staff-expanded');
            if (iconExp) iconExp.style.display = 'none';
            if (iconRes) iconRes.style.display = 'block';
            if (btn) {
                btn.title = 'Shrink / Normal Size';
                btn.setAttribute('aria-label', 'Shrink staff assistant window');
            }
            try { localStorage.setItem('sfbc_staff_expanded', '1'); } catch (e) {}
        } else {
            box.classList.remove('sfbc-staff-expanded');
            if (iconExp) iconExp.style.display = 'block';
            if (iconRes) iconRes.style.display = 'none';
            if (btn) {
                btn.title = 'Make Bigger / Expand';
                btn.setAttribute('aria-label', 'Expand staff assistant window');
            }
            try { localStorage.removeItem('sfbc_staff_expanded'); } catch (e) {}
        }
    };

    // Auto-restore expanded preference, saved messages, draft, and open state
    try {
        if (localStorage.getItem('sfbc_staff_expanded') === '1') {
            window.sfbcStaffToggleExpand(true);
        }
    } catch (e) {}

    (function hydrateStaffAssistant() {
        const els = getStaffEls();
        if (els.messages && staffState.messages.length > 0) {
            for (let i = 0; i < staffState.messages.length; i++) {
                renderBubble(staffState.messages[i].role, staffState.messages[i].text, els.messages);
            }
        }
        if (els.input && staffState.draft) {
            els.input.value = staffState.draft;
        }
        if (staffState.open) {
            applyBoxOpen(true, { focus: false, persist: false });
        }

        if (els.input) {
            els.input.addEventListener('input', function() {
                if (isLoggingOut) return;
                staffState.draft = els.input.value || '';
                writeStaffState(staffState);
            });
        }
        if (els.messages) {
            let scrollSaveTimer = null;
            els.messages.addEventListener('scroll', function() {
                if (isLoggingOut || !staffState.open) return;
                if (scrollSaveTimer) clearTimeout(scrollSaveTimer);
                scrollSaveTimer = setTimeout(function() {
                    staffState.scrollTop = els.messages.scrollTop;
                    writeStaffState(staffState);
                }, 120);
            }, { passive: true });
        }

        window.addEventListener('pagehide', function() {
            if (isLoggingOut) return;
            const currentEls = getStaffEls();
            if (currentEls.input) {
                staffState.draft = currentEls.input.value || '';
            }
            if (staffState.open && currentEls.messages) {
                staffState.scrollTop = currentEls.messages.scrollTop;
            }
            writeStaffState(staffState);
        });

        document.addEventListener('click', function(e) {
            const logoutTrigger = e.target && e.target.closest
                ? e.target.closest('a[href="logout"], a[href^="logout?"], a[href*="logout.php"], #sbcLogoutNowBtn')
                : null;
            if (logoutTrigger) {
                isLoggingOut = true;
                pruneStaffStates(false);
            }
        }, true);

        window.addEventListener('storage', function(e) {
            if (e && e.key === 'sbc_force_logout_event') {
                isLoggingOut = true;
                pruneStaffStates(false);
            }
        });
    })();

    window.sfbcStaffQuickAsk = function(q) {
        const els = getStaffEls();
        if (!els.input) return;
        applyBoxOpen(true, { focus: false, persist: true });
        els.input.value = q;
        window.sfbcStaffSend();
    };

    window.sfbcStaffSend = async function() {
        if (isSending) return;
        const els = getStaffEls();
        if (!els.input || !els.messages) return;
        const text = (els.input.value || '').trim();
        if (!text) return;

        isSending = true;
        els.input.value = '';
        staffState.draft = '';

        renderBubble('user', text, els.messages);
        staffState.messages.push({ role: 'user', text: text });
        if (staffState.messages.length > MAX_HISTORY) {
            staffState.messages = staffState.messages.slice(-MAX_HISTORY);
        }
        staffState.scrollTop = null;
        if (!isLoggingOut) {
            writeStaffState(staffState);
        }
        els.messages.scrollTop = els.messages.scrollHeight;

        const loadingDiv = document.createElement('div');
        loadingDiv.style.cssText = 'align-self:flex-start; background:rgba(148,163,184,0.12); color:#64748B; font-style:italic; padding:8px 12px; border-radius:10px; max-width:90%; line-height:1.4;';
        loadingDiv.textContent = 'Checking records...';
        els.messages.appendChild(loadingDiv);
        els.messages.scrollTop = els.messages.scrollHeight;

        const apiUrl = (window.location.pathname.indexOf('/frontend/') !== -1)
            ? '../backend/api/staff_assistant_api.php'
            : 'backend/api/staff_assistant_api.php';

        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': STAFF_CSRF },
                body: JSON.stringify({ message: text, csrf_token: STAFF_CSRF })
            });
            const data = await res.json();
            if (loadingDiv.parentNode) {
                loadingDiv.parentNode.removeChild(loadingDiv);
            }
            const replyText = (data && (data.reply || data.error)) || 'No response.';
            const role = (data && data.reply) ? 'assistant' : 'error';
            renderBubble(role, replyText, els.messages);
            staffState.messages.push({ role: role, text: replyText });
            if (staffState.messages.length > MAX_HISTORY) {
                staffState.messages = staffState.messages.slice(-MAX_HISTORY);
            }
            els.messages.scrollTop = els.messages.scrollHeight;
            staffState.scrollTop = els.messages.scrollTop;
            if (!isLoggingOut) {
                writeStaffState(staffState);
            }
        } catch (e) {
            if (loadingDiv.parentNode) {
                loadingDiv.parentNode.removeChild(loadingDiv);
            }
            const errText = 'Connection error while querying Staff Assistant.';
            renderBubble('error', errText, els.messages);
            staffState.messages.push({ role: 'error', text: errText });
            if (staffState.messages.length > MAX_HISTORY) {
                staffState.messages = staffState.messages.slice(-MAX_HISTORY);
            }
            els.messages.scrollTop = els.messages.scrollHeight;
            staffState.scrollTop = els.messages.scrollTop;
            if (!isLoggingOut) {
                writeStaffState(staffState);
            }
        } finally {
            isSending = false;
        }
    };
})();
