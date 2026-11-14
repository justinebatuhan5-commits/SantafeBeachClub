<?php
if (defined('SFBC_STAFF_ASSISTANT_LOADED')) {
    return;
}
define('SFBC_STAFF_ASSISTANT_LOADED', true);

require_once __DIR__ . '/../../backend/helpers/csrf_helper.php';
$sfbc_staff_csrf = get_csrf_token();
$sfbc_staff_is_admin = (($_SESSION['admin_role'] ?? '') === 'admin');
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['sfbc_staff_ai_session_key'])) {
    $_SESSION['sfbc_staff_ai_session_key'] = bin2hex(random_bytes(8));
}
$sfbc_staff_scope = substr(
    hash(
        'sha256',
        (string)($_SESSION['admin_role'] ?? 'staff') . '|' .
        (string)($_SESSION['admin_username'] ?? '') . '|' .
        (string)($_SESSION['sfbc_staff_ai_session_key'] ?? '')
    ),
    0,
    16
);
?>
<!-- Santa Fe Beach Club — Read-Only Staff AI Operations Assistant -->
<style>
#sfbcStaffAiWidget {
    position: fixed !important;
    bottom: 22px !important;
    right: 22px !important;
    z-index: 2147483647 !important;
    font-family: inherit !important;
    margin: 0 !important;
    padding: 0 !important;
    pointer-events: none !important;
}
#sfbcStaffAiToggle {
    height: 46px !important;
    padding: 0 16px !important;
    border-radius: 999px !important;
    background: linear-gradient(135deg, #0F172A, #1E293B) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.18) !important;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.35) !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    font-size: 12.5px !important;
    font-weight: 700 !important;
    pointer-events: auto !important;
    touch-action: manipulation !important;
    user-select: none !important;
    -webkit-user-select: none !important;
    transition: transform 0.15s ease, box-shadow 0.15s ease !important;
}
#sfbcStaffAiToggle:hover {
    transform: scale(1.04) !important;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.45) !important;
}
#sfbcStaffAiToggle:active {
    transform: scale(0.96) !important;
}
#sfbcStaffAiBox {
    display: none;
    position: fixed !important;
    bottom: 78px !important;
    right: 22px !important;
    width: 370px !important;
    max-width: calc(100vw - 32px) !important;
    height: 490px !important;
    max-height: calc(100vh - 100px) !important;
    background: var(--card-bg, #ffffff) !important;
    color: var(--text-main, #0F172A) !important;
    border: 1px solid var(--border, #CBD5E1) !important;
    border-radius: 16px !important;
    box-shadow: 0 18px 48px rgba(0, 0, 0, 0.28) !important;
    flex-direction: column !important;
    overflow: hidden !important;
    z-index: 2147483647 !important;
    pointer-events: auto !important;
    transition: width 0.22s cubic-bezier(0.4, 0, 0.2, 1), height 0.22s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
#sfbcStaffAiBox.sfbc-staff-open {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}
#sfbcStaffAiBox.sfbc-staff-expanded {
    width: 760px !important;
    max-width: calc(100vw - 32px) !important;
    height: 720px !important;
    max-height: calc(100vh - 90px) !important;
}
#sfbcStaffClearBtn:hover,
#sfbcStaffExpandBtn:hover {
    background: rgba(255, 255, 255, 0.24) !important;
}
</style>

<div id="sfbcStaffAiWidget">
    <button id="sfbcStaffAiToggle" type="button" onclick="if(window.sfbcStaffAiToggleBox){window.sfbcStaffAiToggleBox();}else{var b=document.getElementById('sfbcStaffAiBox');if(b){b.style.setProperty('display','flex','important');b.classList.add('sfbc-staff-open');}}" aria-label="Toggle Staff AI Assistant" title="Staff AI Assistant (Read-Only)">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="pointer-events:none;"><path d="M12 2a4 4 0 0 1 4 4v2a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M16 14H8a4 4 0 0 0-4 4v2h16v-2a4 4 0 0 0-4-4z"/></svg>
        <span style="pointer-events:none;">Staff AI Assistant</span>
    </button>

    <div id="sfbcStaffAiBox">
        <div style="background:linear-gradient(135deg,#0F172A,#1E293B); color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
            <div>
                <strong style="font-size:13px; display:block;">Staff Operations Assistant</strong>
                <span style="font-size:10.5px; opacity:0.8;">Read-Only · <?php echo $sfbc_staff_is_admin ? 'Administrator' : 'Reception Desk'; ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">
                <button id="sfbcStaffClearBtn" type="button" onclick="if(window.sfbcStaffClearChat){window.sfbcStaffClearChat();}" aria-label="Clear staff assistant conversation" title="Clear Chat" style="background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.25); color:#ffffff; font-size:11px; font-weight:600; line-height:1; cursor:pointer; padding:5px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px; transition:background 0.15s ease;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    <span>Clear</span>
                </button>
                <button id="sfbcStaffExpandBtn" type="button" onclick="window.sfbcStaffToggleExpand()" aria-label="Expand staff assistant window" title="Make Bigger / Expand" style="background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.25); color:#ffffff; font-size:12px; line-height:1; cursor:pointer; padding:5px 7px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; transition:background 0.15s ease;">
                    <svg id="sfbcStaffIconExpand" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    <svg id="sfbcStaffIconRestore" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:none; pointer-events:none;"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                </button>
                <button type="button" onclick="if(window.sfbcStaffAiToggleBox){window.sfbcStaffAiToggleBox(false);}else{var b=document.getElementById('sfbcStaffAiBox');if(b){b.style.setProperty('display','none','important');b.classList.remove('sfbc-staff-open');}}" style="background:none; border:none; color:#94A3B8; font-size:18px; cursor:pointer; padding:2px 6px;">×</button>
            </div>
        </div>

        <div style="padding:8px 10px; background:rgba(148,163,184,0.1); border-bottom:1px solid var(--border,#E2E8F0); display:flex; gap:6px; overflow-x:auto; white-space:nowrap; flex-shrink:0;">
            <button type="button" onclick="sfbcStaffQuickAsk('Show today arrivals and departures')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Today's Desk</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Who is currently checked in?')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">In-House</button>
            <button type="button" onclick="sfbcStaffQuickAsk('What is our occupancy rate today?')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Occupancy</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Show pending payments, refunds, and inquiries')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Pending Queue</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Show live chats, inquiries, and reviews')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Chats &amp; Inquiries</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Show room inventory, rates, and active promotions')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Rooms &amp; Promos</button>
            <?php if ($sfbc_staff_is_admin): ?>
            <button type="button" onclick="sfbcStaffQuickAsk('Show 30 days revenue summary')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">30d Revenue</button>
            <button type="button" onclick="sfbcStaffQuickAsk('Show active sessions and activity logs')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Audit &amp; Sessions</button>
            <?php endif; ?>
            <button type="button" onclick="sfbcStaffQuickAsk('help')" style="padding:4px 9px; border-radius:999px; border:1px solid var(--border,#CBD5E1); background:var(--card-bg,#fff); color:var(--text-main,#1E293B); font-size:11px; cursor:pointer;">Help</button>
        </div>

        <div id="sfbcStaffAiMessages" style="flex:1; padding:12px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; font-size:12.5px;">
            <div id="sfbcStaffGreetingMsg" style="align-self:flex-start; background:rgba(148,163,184,0.12); padding:10px 12px; border-radius:10px; max-width:90%; line-height:1.45;">
                Hello <strong><?php echo htmlspecialchars((string)($_SESSION['admin_username'] ?? 'Staff')); ?></strong>! Ask me about bookings or guests (<code>REF-...</code>), today's arrivals or in-house stays, room occupancy, promotions, live chats, inquiries, reviews, or pending queues. <em>(Read-only)</em>
            </div>
        </div>

        <div style="padding:10px; border-top:1px solid var(--border,#E2E8F0); display:flex; gap:6px; flex-shrink:0;">
            <input type="text" id="sfbcStaffAiInput" maxlength="400" placeholder="Ask about bookings, guests, rooms, promos, queues..." style="flex:1; padding:8px 10px; border:1px solid var(--border,#CBD5E1); border-radius:8px; font-size:12.5px; background:var(--card-bg,#fff); color:var(--text-main,#0F172A);" onkeypress="if(event.key==='Enter') sfbcStaffSend();">
            <button type="button" onclick="sfbcStaffSend()" style="padding:8px 14px; border-radius:8px; background:#0284C7; color:#fff; border:none; font-weight:700; font-size:12px; cursor:pointer;">Ask</button>
        </div>
    </div>
</div>

<script>
(function() {
    const STAFF_CSRF = <?php echo json_encode($sfbc_staff_csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const STAFF_SCOPE = <?php echo json_encode($sfbc_staff_scope, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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
</script>
