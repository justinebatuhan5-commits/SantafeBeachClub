/* Shared Staff-to-Guest Two-Way Messaging UI Script */
(function() {
    var cfg = window.SF_REPLY_UI_CONFIG || {};
    var CSRF_TOKEN = cfg.csrfToken || '';
    var SUPPORT_TAB_SELECTOR = cfg.supportTabSelector || '';
    var BADGE_MODE = cfg.badgeStyleMode || 'inquiries';
    var LAST_UI_ACTIVITY = Date.now();
    var IDLE_LIMIT_MS = 14 * 60 * 1000;
    var currentFilterMode = 'all';
    var currentSearchQuery = '';

    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function(evt) {
        document.addEventListener(evt, function() {
            LAST_UI_ACTIVITY = Date.now();
        }, { passive: true });
    });

    function isSessionIdle() {
        if (Date.now() - LAST_UI_ACTIVITY >= IDLE_LIMIT_MS) {
            return true;
        }
        try {
            var lsTs = localStorage.getItem('sbc_last_active_timestamp');
            if (lsTs) {
                var delta = Date.now() - parseInt(lsTs, 10);
                if (!isNaN(delta) && delta >= IDLE_LIMIT_MS) {
                    return true;
                }
            }
        } catch (e) {}
        return false;
    }

    function markUserActive() {
        LAST_UI_ACTIVITY = Date.now();
        try {
            localStorage.setItem('sbc_last_active_timestamp', String(Date.now()));
        } catch (e) {}
    }

    function formatThreadTime(rawTs) {
        if (!rawTs) return 'Just now';
        var iso = String(rawTs).replace(' ', 'T');
        var d = new Date(iso);
        if (isNaN(d.getTime())) return String(rawTs);
        var now = new Date();
        var sameDay = d.getFullYear() === now.getFullYear()
            && d.getMonth() === now.getMonth()
            && d.getDate() === now.getDate();
        if (sameDay) {
            return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }
        return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function formatSenderName(rawName, isStaff) {
        var raw = String(rawName || '').trim();
        if (!isStaff) {
            var cleanGuest = raw.replace(/\s*\(Live Follow-Up\)\s*$/i, '').trim();
            return { label: cleanGuest || 'Guest', tooltip: raw || 'Guest' };
        }
        if (!raw) {
            return { label: 'Reception Staff', tooltip: 'Reception Staff' };
        }
        var role = '';
        var identity = raw;
        var m = raw.match(/^(.+?)\s*\(([^)]+)\)\s*$/);
        if (m) {
            identity = m[1].trim();
            role = m[2].trim();
        }
        var prettyName = identity;
        if (identity.indexOf('@') !== -1) {
            var local = identity.split('@')[0].replace(/[._-]+/g, ' ').trim();
            if (local) {
                prettyName = local.replace(/\b\w/g, function(ch) { return ch.toUpperCase(); });
            }
        }
        var label = prettyName;
        if (role && prettyName.toLowerCase() !== role.toLowerCase()) {
            label = prettyName + ' (' + role + ')';
        }
        return { label: label, tooltip: raw };
    }

    function truncateSnippet(text, limit) {
        var clean = String(text || '').replace(/\s+/g, ' ').trim();
        if (!clean) return '';
        return clean.length > limit ? clean.slice(0, limit - 3) + '...' : clean;
    }

    function updateQueueRowSummary(inquiryId, rep) {
        var row = document.querySelector('[data-select-chat="' + inquiryId + '"]');
        if (!row || !rep) return;
        var snippetEl = row.querySelector('[data-row-snippet]');
        var timeEl = row.querySelector('[data-row-time]');
        var isStaff = (rep.sender_type || 'staff') === 'staff';
        if (snippetEl && rep.message) {
            snippetEl.textContent = truncateSnippet((isStaff ? 'Staff: ' : '') + rep.message, 56);
        }
        if (timeEl && rep.created_at) {
            timeEl.textContent = formatThreadTime(rep.created_at);
        }
    }

    function appendReplyItem(box, rep) {
        var list = box.querySelector('[data-thread-list]');
        if (!list || !rep) return;

        var repId = parseInt(rep.id || 0, 10);
        if (repId > 0 && list.querySelector('[data-reply-id="' + repId + '"]')) {
            return;
        }

        var emptyEl = list.querySelector('[data-thread-empty]');
        if (emptyEl) {
            emptyEl.remove();
        }

        var isStaff = (rep.sender_type || 'staff') === 'staff';
        var item = document.createElement('div');
        item.className = 'sf-thread-item ' + (isStaff ? 'is-staff' : 'is-guest');
        if (repId > 0) {
            item.setAttribute('data-reply-id', String(repId));
        }

        var bubble = document.createElement('div');
        bubble.className = 'sf-bubble';
        bubble.textContent = rep.message || '';

        var meta = document.createElement('div');
        meta.className = 'sf-bubble-meta';

        var senderInfo = formatSenderName(rep.sender_name, isStaff);
        var senderSpan = document.createElement('span');
        senderSpan.className = 'sf-bubble-sender';
        senderSpan.title = senderInfo.tooltip;
        senderSpan.textContent = senderInfo.label;
        meta.appendChild(senderSpan);

        var deliveryParts = [];
        if (isStaff && rep.delivered_live) deliveryParts.push('Live Chat');
        if (isStaff && rep.delivered_email) deliveryParts.push('Email Sent');
        if (deliveryParts.length > 0) {
            var sep1 = document.createElement('span');
            sep1.textContent = '\u00b7';
            meta.appendChild(sep1);

            var delSpan = document.createElement('span');
            delSpan.className = 'sf-delivery-note';
            delSpan.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> ';
            delSpan.appendChild(document.createTextNode(deliveryParts.join(' & ')));
            meta.appendChild(delSpan);
        }

        if (isStaff && rep.email_error) {
            var warnSpan = document.createElement('span');
            warnSpan.className = 'sf-delivery-note is-warn';
            warnSpan.title = String(rep.email_error);
            warnSpan.textContent = '\u00b7 Email notice';
            meta.appendChild(warnSpan);
        }

        var timeSpan = document.createElement('span');
        timeSpan.textContent = '\u00b7 ' + formatThreadTime(rep.created_at);
        meta.appendChild(timeSpan);

        item.appendChild(bubble);
        item.appendChild(meta);
        list.appendChild(item);
        list.scrollTop = list.scrollHeight;

        var inqId = parseInt(box.getAttribute('data-inquiry-id') || '0', 10);
        if (inqId > 0) {
            updateQueueRowSummary(inqId, rep);
        }
    }

    function updateCardStatus(box, newStatus, liveActive) {
        var card = box.closest('[data-inquiry-card]');
        if (!card) return;
        var inqId = parseInt(card.getAttribute('data-inquiry-id') || '0', 10);

        if (newStatus) {
            var lower = String(newStatus).toLowerCase();
            card.classList.remove('unread', 'read', 'resolved');
            if (lower === 'unread' || lower === 'read' || lower === 'resolved') {
                card.classList.add(lower);
            }

            var badge = card.querySelector('[data-status-badge]');
            if (badge) {
                badge.textContent = newStatus;
                if (BADGE_MODE === 'chatbot') {
                    badge.classList.remove('badge-pending', 'badge-confirmed', 'badge-checkedin');
                    badge.classList.add(
                        newStatus === 'Resolved' ? 'badge-checkedin' : (newStatus === 'Read' ? 'badge-confirmed' : 'badge-pending')
                    );
                } else {
                    badge.classList.remove('status-unread', 'status-read', 'status-resolved');
                    badge.classList.add('status-' + lower);
                }
            }

            var queueRow = document.querySelector('[data-select-chat="' + inqId + '"]');
            if (queueRow) {
                queueRow.classList.remove('is-unread', 'is-read', 'is-resolved');
                queueRow.classList.add('is-' + lower);
                var rowTag = queueRow.querySelector('[data-row-status]');
                if (rowTag) {
                    rowTag.className = 'sf-row-state-tag ' + lower;
                    rowTag.textContent = newStatus === 'Read' ? 'Open' : newStatus;
                }
                var dot = queueRow.querySelector('[data-row-dot]');
                if (dot) {
                    if (liveActive && newStatus !== 'Resolved') {
                        dot.className = 'sf-chat-avatar-dot is-live';
                        dot.hidden = false;
                    } else if (newStatus === 'Unread') {
                        dot.className = 'sf-chat-avatar-dot is-unread';
                        dot.hidden = false;
                    } else {
                        dot.hidden = true;
                    }
                }
            }

            var readBtn = card.querySelector('button[value="mark_read"]');
            if (readBtn) readBtn.hidden = (lower !== 'unread');
            var resolveBtn = card.querySelector('button[value="mark_resolved"]');
            if (resolveBtn) resolveBtn.hidden = (lower === 'resolved');
            syncFilterButtonCounts();
        }

        var pill = box.querySelector('[data-live-status-pill]');
        var convId = parseInt(box.getAttribute('data-conversation-id') || '0', 10);
        if (pill && convId > 0 && typeof liveActive === 'boolean') {
            box.setAttribute('data-live-active', liveActive ? '1' : '0');
            pill.className = 'sf-live-pill ' + (liveActive ? 'is-active' : 'is-idle');
            if (liveActive) {
                pill.innerHTML = '<span class="sf-live-dot"></span> Active Two-Way Chat \u00b7 Session #' + convId;
            } else {
                pill.textContent = 'Chat Session #' + convId + ' \u00b7 ' + (newStatus === 'Resolved' ? 'Resolved' : 'AI Mode');
            }
        }
    }

    function syncFilterButtonCounts() {
        var cards = document.querySelectorAll('[data-inquiry-card]');
        if (!cards.length) return;
        var total = cards.length;
        var resolved = 0;
        cards.forEach(function(c) {
            if (c.classList.contains('resolved')) resolved++;
        });
        var open = total - resolved;
        var isDesk = !!document.querySelector('[data-chat-desk]');
        var allBtn = document.querySelector('[data-ticket-filter="all"]');
        var openBtn = document.querySelector('[data-ticket-filter="open"]');
        var resBtn = document.querySelector('[data-ticket-filter="resolved"]');
        if (allBtn) allBtn.textContent = 'All (' + total + ')';
        if (openBtn) openBtn.textContent = (isDesk ? 'Active (' : 'Open (') + open + ')';
        if (resBtn) resBtn.textContent = (isDesk ? 'Done (' : 'Resolved (') + resolved + ')';
    }

    function selectDeskConversation(inquiryId, openMobileDetail) {
        var desk = document.querySelector('[data-chat-desk]');
        if (!desk) return;
        var targetId = String(inquiryId || '');
        desk.querySelectorAll('[data-select-chat]').forEach(function(btn) {
            btn.classList.toggle('is-selected', btn.getAttribute('data-select-chat') === targetId);
        });
        var selectedPane = null;
        desk.querySelectorAll('[data-inquiry-card]').forEach(function(pane) {
            var match = pane.getAttribute('data-inquiry-id') === targetId;
            pane.classList.toggle('is-selected', match);
            if (match) selectedPane = pane;
        });
        if (openMobileDetail) {
            desk.classList.add('is-mobile-detail');
        }
        if (selectedPane) {
            var threadWrap = selectedPane.querySelector('[data-thread-list]');
            if (threadWrap) {
                threadWrap.scrollTop = threadWrap.scrollHeight;
            }
        }
    }

    function applyDeskOrCardFilters() {
        var desk = document.querySelector('[data-chat-desk]');
        if (desk) {
            var visibleCount = 0;
            var firstVisibleId = null;
            var selectedStillVisible = false;
            var activeBtn = desk.querySelector('[data-select-chat].is-selected');
            var activeId = activeBtn ? activeBtn.getAttribute('data-select-chat') : null;

            desk.querySelectorAll('[data-chat-row]').forEach(function(row) {
                var isRes = row.classList.contains('is-resolved');
                var haystack = (row.getAttribute('data-search-haystack') || '').toLowerCase();
                var matchFilter = (currentFilterMode === 'all'
                    || (currentFilterMode === 'open' && !isRes)
                    || (currentFilterMode === 'resolved' && isRes));
                var matchSearch = !currentSearchQuery || haystack.indexOf(currentSearchQuery) !== -1;
                var show = matchFilter && matchSearch;
                row.style.display = show ? '' : 'none';
                if (show) {
                    visibleCount++;
                    var rid = row.getAttribute('data-select-chat');
                    if (!firstVisibleId) firstVisibleId = rid;
                    if (rid === activeId) selectedStillVisible = true;
                }
            });

            var emptyQueue = desk.querySelector('[data-queue-empty]');
            if (emptyQueue) {
                emptyQueue.hidden = visibleCount > 0;
            }
            if (!selectedStillVisible && firstVisibleId) {
                selectDeskConversation(firstVisibleId, false);
            }
            return;
        }

        // Fallback for Contact Us Inquiry Cards
        document.querySelectorAll('[data-inquiry-card]').forEach(function(card) {
            var isRes = card.classList.contains('resolved');
            card.style.display = (currentFilterMode === 'all'
                || (currentFilterMode === 'open' && !isRes)
                || (currentFilterMode === 'resolved' && isRes)) ? '' : 'none';
        });
    }

    function selectFromHashIfPresent() {
        var hash = window.location.hash || '';
        var m = hash.match(/^#inquiry-(\d+)$/);
        if (m) {
            selectDeskConversation(parseInt(m[1], 10), true);
        }
    }

    window.addEventListener('hashchange', selectFromHashIfPresent);
    document.addEventListener('DOMContentLoaded', function() {
        selectFromHashIfPresent();
        document.querySelectorAll('[data-thread-list]').forEach(function(el) {
            el.scrollTop = el.scrollHeight;
        });
    });

    function showFeedback(box, text, tone) {
        var fb = box.querySelector('[data-reply-feedback]');
        if (!fb) return;
        fb.hidden = false;
        fb.className = 'sf-reply-feedback ' + (tone || 'is-ok');
        fb.textContent = text;
    }

    document.addEventListener('input', function(e) {
        var search = e.target.closest('[data-chat-search]');
        if (!search) return;
        currentSearchQuery = (search.value || '').trim().toLowerCase();
        applyDeskOrCardFilters();
    });

    document.addEventListener('keydown', function(e) {
        var ta = e.target.closest('[data-reply-input]');
        if (!ta) return;
        var box = ta.closest('[data-reply-box]');
        if (!box) return;
        var isChat = box.getAttribute('data-has-chat') === '1';
        if (isChat && e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            var sendBtn = box.querySelector('[data-send-reply][data-resolve="0"]');
            if (sendBtn && !sendBtn.disabled) {
                sendBtn.click();
            }
        }
    });

    document.addEventListener('click', function(e) {
        var rowBtn = e.target.closest('[data-select-chat]');
        if (rowBtn) {
            selectDeskConversation(rowBtn.getAttribute('data-select-chat'), true);
            return;
        }

        var backBtn = e.target.closest('[data-mobile-back]');
        if (backBtn) {
            var desk = backBtn.closest('[data-chat-desk]');
            if (desk) desk.classList.remove('is-mobile-detail');
            return;
        }

        var filterBtn = e.target.closest('[data-ticket-filter]');
        if (filterBtn) {
            currentFilterMode = filterBtn.getAttribute('data-ticket-filter') || 'all';
            var bar = filterBtn.closest('.sf-filter-bar');
            if (bar) {
                bar.querySelectorAll('[data-ticket-filter]').forEach(function(b) {
                    b.classList.toggle('is-active', b === filterBtn);
                });
            }
            applyDeskOrCardFilters();
            return;
        }

        var btn = e.target.closest('[data-send-reply]');
        if (!btn) return;

        var box = btn.closest('[data-reply-box]');
        if (!box) return;

        var inquiryId = parseInt(box.getAttribute('data-inquiry-id') || '0', 10);
        var input = box.querySelector('[data-reply-input]');
        var liveCb = box.querySelector('[data-send-live]');
        var emailCb = box.querySelector('[data-send-email]');
        var message = input ? input.value.trim() : '';

        if (!inquiryId || !message) {
            showFeedback(box, 'Please enter a reply message before sending.', 'is-err');
            if (input) input.focus();
            return;
        }

        var sendLive = liveCb ? !!liveCb.checked : false;
        var sendEmail = emailCb ? !!emailCb.checked : false;
        var markResolved = btn.getAttribute('data-resolve') === '1';

        if (!sendLive && !sendEmail) {
            showFeedback(box, 'Select at least one delivery channel (Live Chat or Email Copy).', 'is-err');
            return;
        }

        var allBtns = box.querySelectorAll('[data-send-reply]');
        allBtns.forEach(function(b) { b.disabled = true; });
        markUserActive();

        fetch('../backend/api/inquiry_reply_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({
                action: 'send_reply',
                inquiry_id: inquiryId,
                message: message,
                send_live: sendLive,
                send_email: sendEmail,
                mark_resolved: markResolved,
                csrf_token: CSRF_TOKEN
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            allBtns.forEach(function(b) { b.disabled = false; });
            if (!data || !data.success) {
                showFeedback(box, (data && data.error) ? data.error : 'Could not send reply right now.', 'is-err');
                return;
            }

            if (input) input.value = '';
            if (data.reply) {
                appendReplyItem(box, data.reply);
            }
            updateCardStatus(box, data.new_status, data.live_chat_active);

            var notes = [];
            if (data.delivered_live) notes.push('Sent to Live Chat');
            if (data.delivered_email) notes.push('Email sent');
            var tone = 'is-ok';
            if (data.email_error) {
                notes.push('Email notice: ' + data.email_error);
                tone = 'is-warn';
            }
            if (markResolved) {
                notes.push('Marked Resolved');
            }
            showFeedback(box, notes.join(' · ') || 'Reply saved.', tone);
        })
        .catch(function() {
            allBtns.forEach(function(b) { b.disabled = false; });
            showFeedback(box, 'Network error while sending reply. Please try again.', 'is-err');
        });
    });

    function pollActiveThreads() {
        if (document.hidden || isSessionIdle()) {
            return;
        }
        if (SUPPORT_TAB_SELECTOR) {
            var tabEl = document.querySelector(SUPPORT_TAB_SELECTOR);
            if (!tabEl || !tabEl.classList.contains('active')) {
                return;
            }
        }

        var channelHost = document.querySelector('[data-comm-channel]');
        var channel = channelHost ? (channelHost.getAttribute('data-comm-channel') || '') : '';
        var boxes = document.querySelectorAll('[data-reply-box]');
        if (!boxes.length && !channel) return;

        var ids = [];
        var knownMaxId = 0;
        boxes.forEach(function(box) {
            var id = parseInt(box.getAttribute('data-inquiry-id') || '0', 10);
            if (id > 0) {
                ids.push(id);
                if (id > knownMaxId) knownMaxId = id;
            }
        });
        if (!ids.length && !channel) return;

        var pollUrl = '../backend/api/inquiry_reply_api.php?action=poll_threads&inquiry_ids='
            + encodeURIComponent(ids.slice(0, 250).join(','));
        if (channel) {
            pollUrl += '&channel=' + encodeURIComponent(channel)
                + '&known_max_id=' + encodeURIComponent(knownMaxId)
                + '&known_count=' + encodeURIComponent(boxes.length);
        }

        fetch(pollUrl)
            .then(function(r) {
                if (!r.ok) return null;
                return r.json();
            })
            .then(function(data) {
                if (!data || !data.success) return;

                if (data.has_new_tickets) {
                    var isComposing = false;
                    var inputs = document.querySelectorAll('[data-reply-input]');
                    inputs.forEach(function(el) {
                        if (el === document.activeElement || (el.value && el.value.trim() !== '')) {
                            isComposing = true;
                        }
                    });
                    if (!isComposing) {
                        window.location.reload();
                        return;
                    }
                }

                if (!data.threads) return;
                boxes.forEach(function(box) {
                    var id = box.getAttribute('data-inquiry-id');
                    var thread = data.threads[id];
                    if (!thread) return;
                    if (Array.isArray(thread.replies)) {
                        thread.replies.forEach(function(rep) {
                            appendReplyItem(box, rep);
                        });
                    }
                    updateCardStatus(box, thread.status || '', !!thread.live_chat_active);
                });
            })
            .catch(function() {});
    }

    setInterval(pollActiveThreads, 6000);
})();
