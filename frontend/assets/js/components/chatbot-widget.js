(function() {
    const cfg = window.SFBC_CHATBOT_CONFIG || {};
    const CSRF_TOKEN = cfg.csrfToken || '';
    const SFBC_LOGO_URL = cfg.logoUrl || 'assets/logo.jpg';
    const SUPPORT_HOURS_START = Number(cfg.supportHoursStart ?? 8);
    const SUPPORT_HOURS_END = Number(cfg.supportHoursEnd ?? 20);
    const SUPPORT_HOURS_LABEL = cfg.supportHoursLabel || '8:00 AM – 8:00 PM PHT';

    function resolveChatbotApiUrl() {
        const path = window.location.pathname || '';
        if (path.indexOf('/frontend/') !== -1) {
            return '../backend/api/chatbot_api.php';
        }
        return 'backend/api/chatbot_api.php';
    }

    /**
     * Strictly escape HTML entities and render only a safe Markdown subset
     * (bold, inline code, bullet lines, and whitelisted relative resort links).
     */
    function sfbcEscapeHtml(raw) {
        return String(raw ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/`/g, '&#96;');
    }

    function sfbcSanitizeRelativeUrl(url) {
        const clean = String(url ?? '').trim();
        const allowed = /^(book|rooms|my_booking|contact|location|gallery|discount_booking\.php|cancel_booking|checkin)(\?[a-zA-Z0-9_=&%\-\.]*)?(#[\w\-]+)?$/;
        return allowed.test(clean) ? clean : null;
    }

    function sfbcFormatSafeReply(rawText) {
        let escaped = sfbcEscapeHtml(rawText);

        // Safe relative links: [Label](book?room_type=...)
        escaped = escaped.replace(/\[([^\]]{1,80})\]\(([^)]{1,200})\)/g, function(_, label, rawHref) {
            const decodedHref = rawHref.replace(/&amp;/g, '&');
            const safeHref = sfbcSanitizeRelativeUrl(decodedHref);
            if (!safeHref) {
                return label;
            }
            const safeAttr = sfbcEscapeHtml(safeHref);
            const isBookingLink = safeHref.indexOf('book') === 0;
            const clickAttr = isBookingLink ? ' onclick="window.sfbcTrackBookingClick && window.sfbcTrackBookingClick()"' : '';
            return '<a href="' + safeAttr + '"' + clickAttr + ' style="display:inline-flex; align-items:center; gap:4px; font-weight:700; color:#84563C; text-decoration:underline;">' + label + ' &rarr;</a>';
        });

        // Bold: **text**
        escaped = escaped.replace(/\*\*([^*]{1,160})\*\*/g, '<strong>$1</strong>');

        // Inline code: &#96;code&#96;
        escaped = escaped.replace(/&#96;([^&]{1,80})&#96;/g, '<code style="padding:2px 4px; background:#f1f5f9; border-radius:4px; font-size:11px; font-family:monospace;">$1</code>');

        // Line breaks
        return escaped.replace(/\r?\n/g, '<br>');
    }

    // Expose formatter for automated XSS regression verification
    window.sfbcFormatSafeReply = sfbcFormatSafeReply;

    function getChatEls() {
        return {
            toggleBtn: document.getElementById('sfbcChatToggle'),
            chatBox: document.getElementById('sfbcChatBox'),
            iconOpen: document.getElementById('sfbcIconOpen'),
            iconClose: document.getElementById('sfbcIconClose'),
            inputEl: document.getElementById('sfbcChatInput'),
            messagesEl: document.getElementById('sfbcChatMessages'),
            langSelect: document.getElementById('sfbcLangSelect'),
            otpPanel: document.getElementById('sfbcOtpPanel'),
            handoffPanel: document.getElementById('sfbcHandoffPanel'),
            faqPanel: document.getElementById('sfbcFaqPanel'),
            availPanel: document.getElementById('sfbcAvailabilityPanel'),
            recPanel: document.getElementById('sfbcRecommendPanel')
        };
    }

    let sfbcLastSyncedId = 0;
    const sfbcRenderedServerIds = new Set();
    let sfbcLiveStaffActive = false;
    let sfbcPollTimer = null;
    let sfbcIsSending = false;
    let sfbcUnreadStaffCount = 0;
    let sfbcStaffWaitTimer = null;
    let sfbcHandoffCooldownTimer = null;
    let sfbcLastHandoffEmail = '';

    function isReceptionOnlinePHT() {
        try {
            const hourStr = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                hour: 'numeric',
                hour12: false
            }).format(new Date());
            const h = parseInt(hourStr, 10) % 24;
            return h >= SUPPORT_HOURS_START && h < SUPPORT_HOURS_END;
        } catch (e) {
            const h = new Date().getHours();
            return h >= SUPPORT_HOURS_START && h < SUPPORT_HOURS_END;
        }
    }

    function updateHandoffHoursBannerUI() {
        const banner = document.getElementById('sfbcHandoffHoursBanner');
        const textEl = document.getElementById('sfbcHandoffHoursText');
        if (!banner || !textEl) return;
        const online = isReceptionOnlinePHT();
        banner.classList.toggle('is-online', online);
        banner.classList.toggle('is-offline', !online);
        textEl.textContent = online
            ? 'Reception Online (' + SUPPORT_HOURS_LABEL + ') · Typical reply in 2–5 mins'
            : 'Reception Offline (Hours: ' + SUPPORT_HOURS_LABEL + ') · Morning shift replies at 8:00 AM via email & chat';
    }

    function formatChatBubbleTime(rawTs) {
        let d = new Date();
        if (rawTs) {
            const parsed = new Date(String(rawTs).replace(' ', 'T'));
            if (!isNaN(parsed.getTime())) d = parsed;
        }
        const now = new Date();
        const sameDay = d.getFullYear() === now.getFullYear()
            && d.getMonth() === now.getMonth()
            && d.getDate() === now.getDate();
        if (sameDay) {
            return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }
        return d.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function clearStaffWaitTimer() {
        if (sfbcStaffWaitTimer) {
            clearTimeout(sfbcStaffWaitTimer);
            sfbcStaffWaitTimer = null;
        }
    }

    function startStaffWaitTimer(guestEmail) {
        clearStaffWaitTimer();
        const waitStatus = document.getElementById('sfbcLiveWaitStatus');
        if (!isReceptionOnlinePHT()) {
            if (waitStatus) waitStatus.textContent = 'Offline hours · Morning reply via email & chat';
            return;
        }
        if (waitStatus) waitStatus.textContent = 'Waiting for staff (~2–5 mins)...';
        sfbcStaffWaitTimer = setTimeout(function() {
            sfbcStaffWaitTimer = null;
            if (!sfbcLiveStaffActive) return;
            if (waitStatus) waitStatus.textContent = 'Replies appear here & by email';
            const emailNote = guestEmail ? ' (**' + guestEmail + '**)' : '';
            appendBubble(
                'Our front desk staff is currently assisting guests at the counter. You do not need to wait on this page — as soon as staff replies, we will send it to your email' + emailNote + ' and keep it here in your chat. You can also click **Switch to AI** above anytime.',
                'model'
            );
        }, 180000);
    }

    function startHandoffCooldown(seconds) {
        const btn = document.getElementById('sfbcHandoffSubmitBtn');
        if (!btn) return;
        if (sfbcHandoffCooldownTimer) {
            clearInterval(sfbcHandoffCooldownTimer);
            sfbcHandoffCooldownTimer = null;
        }
        let remaining = Number(seconds || 60);
        btn.disabled = true;
        btn.style.opacity = '0.65';
        btn.style.cursor = 'not-allowed';
        btn.textContent = 'Request Sent (Wait ' + remaining + 's)';
        sfbcHandoffCooldownTimer = setInterval(function() {
            remaining -= 1;
            if (remaining <= 0) {
                clearInterval(sfbcHandoffCooldownTimer);
                sfbcHandoffCooldownTimer = null;
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
                btn.textContent = 'Send Transcript to Staff';
            } else {
                btn.textContent = 'Request Sent (Wait ' + remaining + 's)';
            }
        }, 1000);
    }

    function updateUnreadBadgeUI() {
        const badge = document.getElementById('sfbcToggleUnreadBadge');
        if (!badge) return;
        if (sfbcUnreadStaffCount > 0) {
            badge.textContent = sfbcUnreadStaffCount > 9 ? '9+' : String(sfbcUnreadStaffCount);
            badge.classList.add('is-visible');
        } else {
            badge.textContent = '';
            badge.classList.remove('is-visible');
        }
    }

    function isChatWindowOpen() {
        const els = getChatEls();
        return Boolean(els.chatBox && (els.chatBox.style.display === 'flex' || els.chatBox.classList.contains('sfbc-open')));
    }

    window.sfbcSetLiveStaffMode = function(active) {
        const nextActive = Boolean(active);
        const wasActive = sfbcLiveStaffActive;
        sfbcLiveStaffActive = nextActive;

        const banner = document.getElementById('sfbcLiveStaffBanner');
        const subtitle = document.getElementById('sfbcHeaderSubtitle');
        const inputEl = document.getElementById('sfbcChatInput');

        if (banner) {
            banner.style.setProperty('display', nextActive ? 'flex' : 'none', 'important');
        }
        if (subtitle) {
            subtitle.textContent = nextActive ? 'Reception Desk · Live Support' : '24/7 Resort Concierge';
        }
        if (inputEl) {
            if (nextActive) {
                inputEl.placeholder = 'Message our reception staff live...';
            } else {
                const langVal = (document.getElementById('sfbcLangSelect') || {}).value || 'English';
                const dict = (typeof SFBC_UI_I18N !== 'undefined' && SFBC_UI_I18N[langVal]) ? SFBC_UI_I18N[langVal] : null;
                inputEl.placeholder = dict ? dict.placeholder : 'Ask about rooms, dates, rates, policies...';
            }
        }

        if (nextActive && !wasActive) {
            startLiveSyncPolling();
        } else if (!nextActive) {
            clearStaffWaitTimer();
            if (sfbcPollTimer) {
                clearInterval(sfbcPollTimer);
                sfbcPollTimer = null;
            }
        }
    };

    window.sfbcResumeAiMode = async function() {
        try {
            await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'resume_ai', csrf_token: CSRF_TOKEN })
            });
        } catch (e) {}
        window.sfbcSetLiveStaffMode(false);
        appendBubble('Switched back to **Santa Fe AI Concierge**. How else may I assist you today?', 'model');
    };

    async function syncGuestMessages(isBackgroundPoll) {
        if (isBackgroundPoll && (sfbcIsSending || document.hidden)) {
            return;
        }
        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'sync_messages', after_id: sfbcLastSyncedId, csrf_token: CSRF_TOKEN })
            });
            const data = await res.json();
            if (!data || !data.success) return;

            const wasLive = sfbcLiveStaffActive;
            if (Array.isArray(data.messages) && data.messages.length > 0) {
                data.messages.forEach(function(m) {
                    const mid = Number(m.id || 0);
                    if (mid > sfbcLastSyncedId) {
                        sfbcLastSyncedId = mid;
                    }
                    if (mid > 0 && sfbcRenderedServerIds.has(mid)) {
                        return;
                    }
                    if (mid > 0) {
                        sfbcRenderedServerIds.add(mid);
                    }
                    const role = m.role === 'staff' ? 'staff' : (m.role === 'user' ? 'user' : 'model');
                    appendBubble(
                        String(m.content || ''),
                        role,
                        role === 'model' ? mid : 0,
                        [],
                        [],
                        false,
                        [],
                        { sender_name: m.sender_name || 'Reception Staff', created_at: m.created_at || '' }
                    );
                    if (role === 'staff') {
                        clearStaffWaitTimer();
                        const waitStatusEl = document.getElementById('sfbcLiveWaitStatus');
                        if (waitStatusEl) {
                            waitStatusEl.textContent = 'Connected with Reception · Replies appear here & by email';
                        }
                    }
                    if (isBackgroundPoll && role === 'staff' && !isChatWindowOpen()) {
                        sfbcUnreadStaffCount += 1;
                        updateUnreadBadgeUI();
                    }
                });
            }

            if (Number(data.last_id || 0) > sfbcLastSyncedId) {
                sfbcLastSyncedId = Number(data.last_id || 0);
            }

            window.sfbcSetLiveStaffMode(Boolean(data.live_chat_active));

            if (wasLive && data.ticket_resolved) {
                appendBubble('Our reception team has marked this support ticket as resolved. Let me know if there is anything else I can assist you with!', 'model');
            }
        } catch (e) {}
    }

    function startLiveSyncPolling() {
        if (sfbcPollTimer) {
            clearInterval(sfbcPollTimer);
        }
        sfbcPollTimer = setInterval(function() {
            if (sfbcLiveStaffActive && !document.hidden) {
                syncGuestMessages(true);
            }
        }, 4000);
    }

    // Rock-solid toggle function with debounce and !important property enforcement
    let sfbcLastToggleTime = 0;
    window.sfbcToggleChat = function(forceState) {
        const now = Date.now();
        if (typeof forceState !== 'boolean' && (now - sfbcLastToggleTime < 300)) {
            return;
        }
        sfbcLastToggleTime = now;

        const els = getChatEls();
        if (!els.chatBox) return;
        const isCurrentlyOpen = (els.chatBox.style.display === 'flex' || els.chatBox.classList.contains('sfbc-open'));
        const shouldOpen = (typeof forceState === 'boolean') ? forceState : !isCurrentlyOpen;

        if (shouldOpen) {
            els.chatBox.style.setProperty('display', 'flex', 'important');
            els.chatBox.classList.add('sfbc-open');
            if (els.widget) els.widget.classList.add('is-chat-open');
            if (els.iconOpen) els.iconOpen.style.setProperty('display', 'none', 'important');
            if (els.iconClose) els.iconClose.style.setProperty('display', 'block', 'important');
            if (els.toggleBtn) els.toggleBtn.setAttribute('aria-expanded', 'true');
            sfbcUnreadStaffCount = 0;
            updateUnreadBadgeUI();
            if (els.messagesEl) {
                els.messagesEl.scrollTop = els.messagesEl.scrollHeight;
            }
            if (sfbcLiveStaffActive) {
                syncGuestMessages(true);
            }
            setTimeout(function() {
                if (els.inputEl) els.inputEl.focus();
            }, 60);
        } else {
            els.chatBox.style.setProperty('display', 'none', 'important');
            els.chatBox.classList.remove('sfbc-open');
            if (els.widget) els.widget.classList.remove('is-chat-open');
            if (els.iconOpen) els.iconOpen.style.setProperty('display', 'block', 'important');
            if (els.iconClose) els.iconClose.style.setProperty('display', 'none', 'important');
            if (els.toggleBtn) els.toggleBtn.setAttribute('aria-expanded', 'false');
        }
    };

    // Toggle between normal and big/expanded window size
    window.sfbcToggleExpand = function(forceState) {
        const box = document.getElementById('sfbcChatBox');
        const iconExp = document.getElementById('sfbcIconExpand');
        const iconRes = document.getElementById('sfbcIconRestore');
        const btn = document.getElementById('sfbcExpandBtn');
        if (!box) return;
        const isExpanded = box.classList.contains('sfbc-expanded');
        const shouldExpand = (typeof forceState === 'boolean') ? forceState : !isExpanded;

        if (shouldExpand) {
            box.classList.add('sfbc-expanded');
            if (iconExp) iconExp.style.display = 'none';
            if (iconRes) iconRes.style.display = 'block';
            if (btn) {
                btn.title = 'Shrink / Normal Size';
                btn.setAttribute('aria-label', 'Shrink chat window');
            }
            try { localStorage.setItem('sfbc_chat_expanded', '1'); } catch (e) {}
        } else {
            box.classList.remove('sfbc-expanded');
            if (iconExp) iconExp.style.display = 'block';
            if (iconRes) iconRes.style.display = 'none';
            if (btn) {
                btn.title = 'Make Bigger / Expand';
                btn.setAttribute('aria-label', 'Expand chat window');
            }
            try { localStorage.removeItem('sfbc_chat_expanded'); } catch (e) {}
        }
    };

    // Auto-restore expanded preference
    try {
        if (localStorage.getItem('sfbc_chat_expanded') === '1') {
            window.sfbcToggleExpand(true);
        }
    } catch (e) {}

    window.sfbcTogglePanel = function(which) {
        const els = getChatEls();
        if (els.otpPanel) els.otpPanel.style.setProperty('display', 'none', 'important');
        if (els.handoffPanel) els.handoffPanel.style.setProperty('display', 'none', 'important');
        if (els.faqPanel) els.faqPanel.style.setProperty('display', 'none', 'important');
        if (els.availPanel) els.availPanel.style.setProperty('display', 'none', 'important');
        if (els.recPanel) els.recPanel.style.setProperty('display', 'none', 'important');

        if (which === 'otp' && els.otpPanel) {
            els.otpPanel.style.setProperty('display', 'block', 'important');
        } else if (which === 'handoff' && els.handoffPanel) {
            updateHandoffHoursBannerUI();
            els.handoffPanel.style.setProperty('display', 'block', 'important');
            const handoffNameEl = document.getElementById('sfbcHandoffName');
            if (handoffNameEl) handoffNameEl.focus();
        } else if (which === 'faq' && els.faqPanel) {
            els.faqPanel.style.setProperty('display', 'block', 'important');
        } else if (which === 'avail' && els.availPanel) {
            els.availPanel.style.setProperty('display', 'block', 'important');
        } else if (which === 'recommend' && els.recPanel) {
            els.recPanel.style.setProperty('display', 'block', 'important');
        }
    };

    window.sfbcSwitchFaqTab = function(tabName, btnEl) {
        const tabs = document.querySelectorAll('#sfbcFaqTabs .sfbc-faq-tab');
        tabs.forEach(function(t) { t.classList.remove('active'); });
        if (btnEl) btnEl.classList.add('active');

        const sections = document.querySelectorAll('.sfbc-faq-section');
        sections.forEach(function(s) { s.style.display = 'none'; });
        const target = document.getElementById('sfbcFaqSection_' + tabName);
        if (target) target.style.display = 'block';
    };

    window.sfbcQuickAsk = function(questionText) {
        const els = getChatEls();
        if (!els.inputEl) return;
        els.inputEl.value = questionText;
        window.sfbcSendMessage();
    };

    window.sfbcOpenDatePrompt = function() {
        const els = getChatEls();
        const isAvailOpen = (els.availPanel && els.availPanel.style.display === 'block');
        if (isAvailOpen) {
            window.sfbcTogglePanel(null);
            return;
        }
        window.sfbcTogglePanel('avail');

        const cinEl = document.getElementById('sfbcAvailCin');
        const coutEl = document.getElementById('sfbcAvailCout');
        const today = new Date().toISOString().slice(0, 10);
        const tom = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
        const nextDay = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);

        if (cinEl) {
            cinEl.min = today;
            if (!cinEl.value) cinEl.value = tom;
            cinEl.addEventListener('change', function() {
                if (coutEl && coutEl.value <= cinEl.value) {
                    const next = new Date(new Date(cinEl.value).getTime() + 86400000);
                    coutEl.value = next.toISOString().slice(0, 10);
                    coutEl.min = next.toISOString().slice(0, 10);
                }
            });
        }
        if (coutEl) {
            coutEl.min = tom;
            if (!coutEl.value) coutEl.value = nextDay;
        }
    };

    window.sfbcSubmitAvailabilityQuery = function() {
        const cinEl = document.getElementById('sfbcAvailCin');
        const coutEl = document.getElementById('sfbcAvailCout');
        const guestsEl = document.getElementById('sfbcAvailGuests');
        const roomEl = document.getElementById('sfbcAvailRoomType');

        const cin = (cinEl && cinEl.value) ? cinEl.value : '';
        const cout = (coutEl && coutEl.value) ? coutEl.value : '';
        const guests = (guestsEl && guestsEl.value) ? guestsEl.value : '2';
        const roomSlug = (roomEl && roomEl.value) ? roomEl.value : '';

        if (!cin || !cout) {
            alert('Please select check-in and check-out dates.');
            return;
        }
        if (cout <= cin) {
            alert('Check-out date must be after check-in date.');
            return;
        }

        window.sfbcTogglePanel(null);

        let query = 'Check availability from ' + cin + ' to ' + cout + ' for ' + guests + ' guest(s)';
        if (roomSlug) {
            query += ' in ' + roomSlug;
        }
        window.sfbcQuickAsk(query);
    };

    window.sfbcDirectToConfirmBooking = function() {
        const cinEl = document.getElementById('sfbcAvailCin');
        const coutEl = document.getElementById('sfbcAvailCout');
        const guestsEl = document.getElementById('sfbcAvailGuests');
        const roomEl = document.getElementById('sfbcAvailRoomType');

        const cin = (cinEl && cinEl.value) ? cinEl.value : '';
        const cout = (coutEl && coutEl.value) ? coutEl.value : '';
        const guests = (guestsEl && guestsEl.value) ? guestsEl.value : '2';
        const roomSlug = (roomEl && roomEl.value) ? roomEl.value : 'beachview_duplex';

        if (!cin || !cout || cout <= cin) {
            alert('Please select valid check-in and check-out dates first.');
            return;
        }
        window.sfbcTrackBookingClick();
        const target = 'book?room_type=' + encodeURIComponent(roomSlug)
            + '&checkin=' + encodeURIComponent(cin)
            + '&checkout=' + encodeURIComponent(cout)
            + '&guests=' + encodeURIComponent(guests);
        window.location.href = target;
    };

    window.sfbcSubmitRecommendQuery = function() {
        const guestsEl = document.getElementById('sfbcRecGuests');
        const budgetEl = document.getElementById('sfbcRecBudget');
        const styleEl = document.getElementById('sfbcRecStyle');

        const guests = (guestsEl && guestsEl.value) ? guestsEl.value : '2';
        const budget = (budgetEl && budgetEl.value) ? budgetEl.value : '';
        const style = (styleEl && styleEl.value) ? styleEl.value : 'beachfront';

        const styleMap = {
            'beachfront': 'beachfront ocean view',
            'honeymoon': 'romantic honeymoon couple',
            'family': 'family barkada group',
            'budget': 'budget affordable',
            'senior': 'senior accessible ground floor',
            'workation': 'quiet workation remote work'
        };
        const styleDesc = styleMap[style] || style;

        window.sfbcTogglePanel(null);

        let query = 'Please recommend the best room for ' + guests + ' guests looking for a ' + styleDesc + ' stay';
        if (budget) {
            query += ' under ' + budget + ' per night';
        }
        window.sfbcQuickAsk(query);
    };

    window.sfbcTrackBookingClick = function() {
        fetch(resolveChatbotApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ action: 'track_booking_click', csrf_token: CSRF_TOKEN })
        }).catch(function() {});
    };

    window.sfbcSendFeedback = function(messageId, rating, btnEl) {
        if (!messageId) return;
        fetch(resolveChatbotApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ action: 'feedback', message_id: messageId, feedback: rating, csrf_token: CSRF_TOKEN })
        }).then(function() {
            if (btnEl && btnEl.parentElement) {
                if (rating === -1) {
                    btnEl.parentElement.innerHTML = '<span style="font-size:10.5px; color:#b45309;">Sorry about that! <button type="button" onclick="sfbcTogglePanel(\'handoff\')" style="background:none; border:none; color:#84563C; font-weight:700; text-decoration:underline; cursor:pointer; font-size:10.5px; padding:0;">Forward chat to live staff &rarr;</button></span>';
                    window.sfbcTogglePanel('handoff');
                } else {
                    btnEl.parentElement.innerHTML = '<span style="font-size:10px; color:#16a34a;">Thank you for your feedback!</span>';
                }
            }
        }).catch(function() {});
    };

    window.sfbcRequestBookingOtp = async function() {
        const ref = (document.getElementById('sfbcOtpRef').value || '').trim();
        const email = (document.getElementById('sfbcOtpEmail').value || '').trim();
        if (!ref || !email) {
            appendBubble('Please enter both your Booking Reference (e.g. **REF-001**) and email address.', 'model');
            return;
        }
        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'request_booking_otp', booking_ref: ref, email: email, csrf_token: CSRF_TOKEN })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('sfbcOtpStep1').style.setProperty('display', 'none', 'important');
                document.getElementById('sfbcOtpStep2').style.setProperty('display', 'flex', 'important');
                document.getElementById('sfbcOtpHintText').textContent = data.message || 'Enter the 6-digit code sent to your email.';
            } else {
                appendBubble(data.error || 'Could not find that booking.', 'model');
            }
        } catch (e) {
            appendBubble('Could not send OTP right now. Please try again.', 'model');
        }
    };

    window.sfbcVerifyBookingOtp = async function() {
        const otp = (document.getElementById('sfbcOtpCode').value || '').trim();
        if (!otp) return;
        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'verify_booking_otp', otp: otp, csrf_token: CSRF_TOKEN })
            });
            const data = await res.json();
            if (data.success && data.reply) {
                window.sfbcTogglePanel(null);
                appendBubble(data.reply, 'model', 0, data.suggested_actions || []);
            } else {
                appendBubble(data.error || 'Invalid OTP code.', 'model');
            }
        } catch (e) {
            appendBubble('Verification failed. Please try again.', 'model');
        }
    };

    window.sfbcSubmitHandoff = async function() {
        const name = (document.getElementById('sfbcHandoffName').value || '').trim();
        const email = (document.getElementById('sfbcHandoffEmail').value || '').trim();
        const noteEl = document.getElementById('sfbcHandoffNote');
        const note = (noteEl ? noteEl.value : '').trim();
        if (!name || !email) {
            appendBubble('Please enter your name and email address so our reception desk can contact you.', 'model');
            return;
        }
        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'handoff', guest_name: name, guest_email: email, note: note, csrf_token: CSRF_TOKEN })
            });
            const data = await res.json();
            if (data.success) {
                sfbcLastHandoffEmail = email;
                window.sfbcTogglePanel(null);
                if (noteEl) noteEl.value = '';
                startHandoffCooldown(60);
                const hmId = Number(data.handoff_message_id || 0);
                if (hmId > 0) {
                    sfbcRenderedServerIds.add(hmId);
                    if (hmId > sfbcLastSyncedId) sfbcLastSyncedId = hmId;
                }
                appendBubble(data.message || 'Your message has been sent to our front desk staff.', 'model');
                if (data.live_chat_active) {
                    window.sfbcSetLiveStaffMode(true);
                    startStaffWaitTimer(sfbcLastHandoffEmail);
                }
            } else {
                appendBubble(data.error || 'Could not forward conversation.', 'model');
            }
        } catch (e) {
            appendBubble('Network error while forwarding your request.', 'model');
        }
    };

    const SFBC_UI_I18N = {
        'English': {
            placeholder: 'Ask about rooms, dates, rates, recommendations...',
            faq: 'Browse FAQs & Rooms',
            recommend: 'Recommend a Room',
            recommendQ: 'Can you recommend the best room for us?',
            rates: 'Room Rates',
            ratesQ: 'What are your room rates and capacities?',
            avail: 'Check Availability',
            pay: 'How to Pay',
            payQ: 'How does the 50% deposit and balance payment work?',
            cancel: 'Cancellation',
            cancelQ: 'What is your cancellation and refund policy?',
            dir: 'Directions',
            dirQ: 'How do I get to Santa Fe Beach Club?',
            otp: 'My Booking (OTP)',
            staff: 'Talk to Staff'
        },
        'Tagalog': {
            placeholder: 'Magtanong tungkol sa kwarto, petsa, presyo, o rekomendasyon...',
            faq: 'Mga FAQ at Kwarto',
            recommend: 'Mag-recommend ng Kwarto',
            recommendQ: 'Anong magandang kwarto ang ma-rerecommend ninyo para sa amin?',
            rates: 'Presyo ng Kwarto',
            ratesQ: 'Magkano ang mga kwarto at ilang tao ang kasya?',
            avail: 'Tingnan ang Petsa',
            pay: '50% Deposit',
            payQ: 'Paano magbayad ng 50% deposit at kailan ang balanse?',
            cancel: 'Cancellation Policy',
            cancelQ: 'Ano ang cancellation at refund policy?',
            dir: 'Direksyon',
            dirQ: 'Paano pumunta sa Santa Fe Beach Club mula sa pantalan?',
            otp: 'Booking Ko (OTP)',
            staff: 'Kausapin ang Staff'
        },
        'Cebuano / Bisaya': {
            placeholder: 'Pangutana bahin sa kwarto, petsa, presyo, o rekomendasyon...',
            faq: 'Mga FAQ ug Kwarto',
            recommend: 'I-recommend og Kwarto',
            recommendQ: 'Unsang maayong kwarto ang imong ma-recommend para namo?',
            rates: 'Presyo sa Kwarto',
            ratesQ: 'Pila ang presyo sa inyong mga kwarto ug kapasidad?',
            avail: 'Tan-awa ang Petsa',
            pay: 'Unsaon Pagbayad',
            payQ: 'Unsaon pagbayad sa 50% deposit ug kanus-a ang balanse?',
            cancel: 'Polisiya sa Refund',
            cancelQ: 'Unsa ang inyong polisiya sa pag-cancel ug refund?',
            dir: 'Direksyon',
            dirQ: 'Unsaon pag-abot sa Santa Fe Beach Club gikan sa pantalan?',
            otp: 'Akong Booking (OTP)',
            staff: 'Istorya sa Staff'
        },
        'Korean': {
            placeholder: '객실, 날짜, 요금, 추천에 대해 질문해 보세요...',
            faq: 'FAQ 및 객실 안내',
            recommend: '맞춤 객실 추천',
            recommendQ: '저희에게 가장 좋은 객실을 추천해 주시겠어요?',
            rates: '객실 요금',
            ratesQ: '객실 요금과 정원은 어떻게 되나요?',
            avail: '빈방 조회',
            pay: '결제 방법',
            payQ: '50% 예약금 및 잔금 결제는 어떻게 하나요?',
            cancel: '취소 규정',
            cancelQ: '예약 취소 및 환불 규정이 어떻게 되나요?',
            dir: '오시는 길',
            dirQ: '산타페 비치 클럽까지 어떻게 가나요?',
            otp: '예약 확인 (OTP)',
            staff: '상담원 연결'
        },
        'Japanese': {
            placeholder: '客室、日程、料金、おすすめについて質問してください...',
            faq: 'FAQ・客室案内',
            recommend: 'おすすめ客室診断',
            recommendQ: '私たちに最適なお部屋をおすすめしてくれますか？',
            rates: '客室料金',
            ratesQ: '客室料金と定員を教えてください。',
            avail: '空室確認',
            pay: 'お支払い方法',
            payQ: '50%のデポジットと残金の支払い方法は？',
            cancel: 'キャンセル規定',
            cancelQ: 'キャンセルと返金ポリシーを教えてください。',
            dir: 'アクセス',
            dirQ: 'サンタフェ・ビーチクラブへの行き方を教えてください。',
            otp: '予約確認 (OTP)',
            staff: 'スタッフに相談'
        },
        'Chinese': {
            placeholder: '询问房型、日期、价格或住宿推荐...',
            faq: '常见问题与房型',
            recommend: '智能房型推荐',
            recommendQ: '您能为我们推荐最合适的房间吗？',
            rates: '房价与人数',
            ratesQ: '你们的房价和可住人数是多少？',
            avail: '查询空房',
            pay: '付款方式',
            payQ: '50%定金和余款如何支付？',
            cancel: '取消政策',
            cancelQ: '你们的取消和退款政策是什么？',
            dir: '交通指南',
            dirQ: '从码头怎么去圣达菲海滩俱乐部？',
            otp: '我的预订 (OTP)',
            staff: '联系人工客服'
        },
        'Spanish': {
            placeholder: 'Pregunte sobre habitaciones, fechas, tarifas o recomendaciones...',
            faq: 'Preguntas y Habitaciones',
            recommend: 'Recomendar Habitación',
            recommendQ: '¿Puede recomendarnos la mejor habitación?',
            rates: 'Tarifas',
            ratesQ: '¿Cuáles son las tarifas y capacidades de las habitaciones?',
            avail: 'Ver Disponibilidad',
            pay: 'Cómo Pagar',
            payQ: '¿Cómo funciona el depósito del 50% y el saldo?',
            cancel: 'Cancelación',
            cancelQ: '¿Cuál es su política de cancelación y reembolso?',
            dir: 'Cómo Llegar',
            dirQ: '¿Cómo llego a Santa Fe Beach Club desde el puerto?',
            otp: 'Mi Reserva (OTP)',
            staff: 'Hablar con Recepción'
        },
        'French': {
            placeholder: 'Posez vos questions sur les chambres, dates, tarifs...',
            faq: 'FAQ et Chambres',
            recommend: 'Recommander une Chambre',
            recommendQ: 'Pouvez-vous nous recommander la meilleure chambre ?',
            rates: 'Tarifs',
            ratesQ: 'Quels sont les tarifs et capacités des chambres ?',
            avail: 'Disponibilités',
            pay: 'Paiement',
            payQ: 'Comment fonctionne l’acompte de 50 % ?',
            cancel: 'Annulation',
            cancelQ: 'Quelle est votre politique d’annulation et de remboursement ?',
            dir: 'Itinéraire',
            dirQ: 'Comment se rendre au Santa Fe Beach Club ?',
            otp: 'Ma Réservation (OTP)',
            staff: 'Parler au Personnel'
        },
        'German': {
            placeholder: 'Fragen Sie nach Zimmern, Daten, Preisen oder Empfehlungen...',
            faq: 'FAQs & Zimmer',
            recommend: 'Zimmer Empfehlen',
            recommendQ: 'Können Sie uns das beste Zimmer empfehlen?',
            rates: 'Zimmerpreise',
            ratesQ: 'Wie hoch sind die Zimmerpreise und Kapazitäten?',
            avail: 'Verfügbarkeit',
            pay: 'Zahlung',
            payQ: 'Wie funktioniert die 50% Anzahlung?',
            cancel: 'Stornierung',
            cancelQ: 'Wie lautet Ihre Stornierungs- und Rückerstattungsrichtlinie?',
            dir: 'Anfahrt',
            dirQ: 'Wie komme ich zum Santa Fe Beach Club?',
            otp: 'Meine Buchung (OTP)',
            staff: 'Personal Kontaktieren'
        }
    };

    window.sfbcOnLangChange = function(lang) {
        const key = SFBC_UI_I18N[lang] ? lang : 'English';
        const dict = SFBC_UI_I18N[key];
        const inputEl = document.getElementById('sfbcChatInput');
        if (inputEl) inputEl.placeholder = dict.placeholder;

        const setChip = function(id, label, q) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = label;
            if (q) {
                el.onclick = function() { window.sfbcQuickAsk(q); };
            }
        };
        setChip('sfbcChipFaq', dict.faq, null);
        setChip('sfbcChipRecommend', dict.recommend, null);
        setChip('sfbcChipRates', dict.rates, dict.ratesQ);
        setChip('sfbcChipAvail', dict.avail, null);
        setChip('sfbcChipPay', dict.pay, dict.payQ);
        setChip('sfbcChipCancel', dict.cancel, dict.cancelQ);
        setChip('sfbcChipDir', dict.dir, dict.dirQ);
        setChip('sfbcChipOtp', dict.otp, null);
        setChip('sfbcChipStaff', dict.staff, null);
    };

    window.sfbcUpdateContextBar = function(ctx) {
        const bar = document.getElementById('sfbcContextBar');
        const badgesEl = document.getElementById('sfbcContextBadges');
        if (!bar || !badgesEl || !ctx) return;

        const items = [];
        if (Array.isArray(ctx.dates) && ctx.dates.length === 2) {
            items.push('Dates: ' + ctx.dates[0] + ' → ' + ctx.dates[1]);
        }
        if (ctx.guests) {
            items.push(ctx.guests + ' Guest(s)');
        }
        if (ctx.room_name) {
            items.push('Room: ' + ctx.room_name);
        }
        if (ctx.max_budget) {
            items.push('Budget ≤ ₱' + Number(ctx.max_budget).toLocaleString() + '/night');
        }
        if (ctx.preference) {
            items.push('Style: ' + ctx.preference);
        }

        if (items.length === 0) {
            bar.style.setProperty('display', 'none', 'important');
            badgesEl.innerHTML = '';
            return;
        }

        badgesEl.innerHTML = '';
        items.forEach(function(txt) {
            const span = document.createElement('span');
            span.style.background = '#DBEAFE';
            span.style.color = '#1E3A8A';
            span.style.padding = '1px 7px';
            span.style.borderRadius = '999px';
            span.style.fontWeight = '600';
            span.style.fontSize = '10.5px';
            span.textContent = txt;
            badgesEl.appendChild(span);
        });
        bar.style.setProperty('display', 'flex', 'important');
    };

    window.sfbcResetContext = async function() {
        try {
            await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ action: 'reset_context', csrf_token: CSRF_TOKEN })
            });
        } catch (e) {}
        sfbcLastSyncedId = 0;
        sfbcRenderedServerIds.clear();
        window.sfbcSetLiveStaffMode(false);
        const bar = document.getElementById('sfbcContextBar');
        const badgesEl = document.getElementById('sfbcContextBadges');
        if (bar) bar.style.setProperty('display', 'none', 'important');
        if (badgesEl) badgesEl.innerHTML = '';
        appendBubble('Conversation context memory cleared. Feel free to share new travel dates, guest counts, or room preferences!', 'model');
    };

    window.sfbcSendMessage = async function() {
        const els = getChatEls();
        if (!els.inputEl) return;
        const text = els.inputEl.value.trim();
        if (!text) return;
        els.inputEl.value = '';

        const userWrapper = appendBubble(text, 'user');
        const typingId = sfbcLiveStaffActive ? null : appendTypingIndicator();
        sfbcIsSending = true;

        try {
            const res = await fetch(resolveChatbotApiUrl(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    action: 'message',
                    message: text,
                    language: (els.langSelect && els.langSelect.value) ? els.langSelect.value : null,
                    csrf_token: CSRF_TOKEN
                })
            });
            const data = await res.json();
            if (typingId) removeElement(typingId);

            const uMid = Number(data.user_message_id || 0);
            const mMid = Number(data.message_id || 0);
            if (uMid > 0) {
                sfbcRenderedServerIds.add(uMid);
                if (uMid > sfbcLastSyncedId) sfbcLastSyncedId = uMid;
            }
            if (mMid > 0) {
                sfbcRenderedServerIds.add(mMid);
                if (mMid > sfbcLastSyncedId) sfbcLastSyncedId = mMid;
            }

            if (data.success && data.live_staff_mode) {
                window.sfbcSetLiveStaffMode(true);
                if (userWrapper) {
                    const noteEl = document.createElement('div');
                    noteEl.className = 'sfbc-live-delivered-note';
                    noteEl.textContent = 'Sent to Reception Desk';
                    userWrapper.appendChild(noteEl);
                }
            } else if (data.success && data.reply) {
                if (data.language && (!els.langSelect || !els.langSelect.value)) {
                    window.sfbcOnLangChange(data.language);
                }
                if (data.context_memory) {
                    window.sfbcUpdateContextBar(data.context_memory);
                }
                appendBubble(
                    data.reply,
                    'model',
                    mMid,
                    data.suggested_actions || [],
                    data.suggested_questions || [],
                    Boolean(data.unanswered_detected),
                    data.room_cards || []
                );
                if (data.suggest_handoff) {
                    window.sfbcTogglePanel('handoff');
                }
            } else {
                appendBubble(data.error || 'Sorry, I encountered an issue. Please ask our front desk staff for assistance.', 'model');
            }
        } catch (err) {
            if (typingId) removeElement(typingId);
            appendBubble('Could not connect to the resort concierge service. Please check your network connection.', 'model');
        } finally {
            sfbcIsSending = false;
        }
    };

    function resolveRoomCardImageUrl(rawUrl) {
        const clean = String(rawUrl || 'assets/hero-slide-2.jpg').trim();
        if (/^(https?:)?\/\//i.test(clean)) return clean;
        const path = window.location.pathname || '';
        if (path.indexOf('/frontend/') !== -1) {
            return clean.indexOf('frontend/') === 0 ? clean.slice(9) : clean;
        }
        return clean.indexOf('frontend/') === 0 ? clean : clean;
    }

    function appendBubble(text, sender, messageId, suggestedActions, suggestedQuestions, unansweredDetected, roomCards, meta) {
        const els = getChatEls();
        if (!els.messagesEl) return null;
        const wrapper = document.createElement('div');
        wrapper.style.display = 'flex';
        wrapper.style.flexDirection = 'column';
        wrapper.style.alignItems = sender === 'user' ? 'flex-end' : 'flex-start';
        wrapper.style.width = '100%';

        if (sender === 'staff') {
            const staffLabel = document.createElement('div');
            staffLabel.className = 'sfbc-staff-label';
            staffLabel.innerHTML = '<span class="sfbc-staff-avatar" aria-hidden="true"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg></span>';
            const sName = document.createElement('span');
            sName.textContent = (meta && meta.sender_name) ? String(meta.sender_name) : 'Reception Staff';
            const livePill = document.createElement('span');
            livePill.className = 'sfbc-staff-live-pill';
            livePill.textContent = 'LIVE STAFF';
            staffLabel.appendChild(sName);
            staffLabel.appendChild(livePill);
            wrapper.appendChild(staffLabel);
        } else if (sender !== 'user') {
            const botLabel = document.createElement('div');
            botLabel.className = 'sfbc-bot-label';
            const botImg = document.createElement('img');
            botImg.src = SFBC_LOGO_URL || 'assets/logo.jpg';
            botImg.alt = 'Santa Fe Concierge';
            botImg.onerror = function() {
                if (!this.dataset.fb) {
                    this.dataset.fb = '1';
                    this.src = 'frontend/assets/logo.jpg';
                }
            };
            const botName = document.createElement('span');
            botName.textContent = 'Santa Fe Concierge';
            botLabel.appendChild(botImg);
            botLabel.appendChild(botName);
            wrapper.appendChild(botLabel);
        }

        const div = document.createElement('div');
        div.className = sender === 'user'
            ? 'sfbc-msg-bubble-user'
            : (sender === 'staff' ? 'sfbc-msg-bubble-staff' : 'sfbc-msg-bubble-model');

        if (sender === 'user') {
            div.textContent = text;
        } else {
            div.innerHTML = sfbcFormatSafeReply(text);
        }
        wrapper.appendChild(div);

        const timeEl = document.createElement('div');
        timeEl.className = 'sfbc-msg-time';
        timeEl.textContent = formatChatBubbleTime(meta && meta.created_at ? meta.created_at : null);
        wrapper.appendChild(timeEl);

        // Visual Room Cards Carousel (Reservation Assistance — matching Akia / Hotel Segundo style)
        if (sender === 'model' && Array.isArray(roomCards) && roomCards.length > 0) {
            const carousel = document.createElement('div');
            carousel.className = 'sfbc-room-carousel';

            roomCards.forEach(function(rc) {
                const card = document.createElement('div');
                card.className = 'sfbc-visual-room-card';

                const topWrap = document.createElement('div');

                const imgWrap = document.createElement('div');
                imgWrap.className = 'sfbc-visual-room-img-wrap';

                const img = document.createElement('img');
                img.className = 'sfbc-visual-room-img';
                const rawImg = String(rc.image_url || 'assets/hero-slide-2.jpg');
                img.src = resolveRoomCardImageUrl(rawImg);
                img.alt = String(rc.display_name || 'Resort Room');
                img.loading = 'lazy';
                img.onerror = function() {
                    if (!this.dataset.retried) {
                        this.dataset.retried = '1';
                        this.src = (rawImg.indexOf('frontend/') === 0) ? rawImg.slice(9) : ('frontend/' + rawImg);
                    } else {
                        this.onerror = null;
                        this.src = 'assets/hero-slide-2.jpg';
                    }
                };
                imgWrap.appendChild(img);

                if (rc.badge) {
                    const badge = document.createElement('span');
                    badge.className = 'sfbc-visual-room-badge';
                    badge.textContent = String(rc.badge);
                    imgWrap.appendChild(badge);
                }
                topWrap.appendChild(imgWrap);

                const titleEl = document.createElement('div');
                titleEl.className = 'sfbc-visual-room-title';
                titleEl.textContent = String(rc.display_name || 'Resort Room');
                topWrap.appendChild(titleEl);

                const priceEl = document.createElement('div');
                priceEl.className = 'sfbc-visual-room-price';
                priceEl.textContent = String(rc.price_label || ('₱' + Number(rc.price_per_night || 0).toLocaleString() + '/night'));
                topWrap.appendChild(priceEl);

                const metaEl = document.createElement('div');
                metaEl.className = 'sfbc-visual-room-meta';
                let metaText = String(rc.capacity_label || '');
                if (rc.stay_summary) {
                    metaText += (metaText ? ' · ' : '') + String(rc.stay_summary);
                }
                metaEl.textContent = metaText;
                topWrap.appendChild(metaEl);

                card.appendChild(topWrap);

                const safeHref = sfbcSanitizeRelativeUrl(rc.booking_url || 'book');
                const bookBtn = document.createElement('a');
                bookBtn.className = 'sfbc-visual-book-btn';
                bookBtn.href = safeHref || 'book';
                bookBtn.textContent = 'BOOK NOW';
                bookBtn.addEventListener('click', function() {
                    window.sfbcTrackBookingClick();
                });
                card.appendChild(bookBtn);

                carousel.appendChild(card);
            });

            wrapper.appendChild(carousel);
        }

        if (sender === 'model' && unansweredDetected) {
            const uaBadge = document.createElement('div');
            uaBadge.style.marginTop = '5px';
            uaBadge.style.padding = '4px 9px';
            uaBadge.style.background = '#FEF3C7';
            uaBadge.style.border = '1px solid #FCD34D';
            uaBadge.style.borderRadius = '6px';
            uaBadge.style.fontSize = '10.5px';
            uaBadge.style.color = '#92400E';
            uaBadge.innerHTML = '<strong>Unanswered Question Detected:</strong> Logged for admin FAQ review. <button type="button" onclick="sfbcTogglePanel(\'handoff\')" style="background:none;border:none;color:#84563C;font-weight:700;text-decoration:underline;cursor:pointer;font-size:10.5px;padding:0;">Forward to Reception Staff &rarr;</button>';
            wrapper.appendChild(uaBadge);
        }

        // Suggested action buttons (e.g. Pre-filled Book Now link)
        if (sender === 'model' && Array.isArray(suggestedActions) && suggestedActions.length > 0) {
            const actionsRow = document.createElement('div');
            actionsRow.style.display = 'flex';
            actionsRow.style.flexWrap = 'wrap';
            actionsRow.style.gap = '6px';
            actionsRow.style.marginTop = '6px';
            suggestedActions.forEach(function(act) {
                const safeHref = sfbcSanitizeRelativeUrl(act.url || '');
                if (!safeHref) return;
                const link = document.createElement('a');
                link.href = safeHref;
                link.style.display = 'inline-flex';
                link.style.alignItems = 'center';
                link.style.gap = '4px';
                link.style.padding = '4px 10px';
                link.style.borderRadius = '6px';
                link.style.background = '#84563C';
                link.style.color = '#ffffff';
                link.style.fontSize = '11px';
                link.style.fontWeight = '600';
                link.style.textDecoration = 'none';
                link.textContent = (act.label || 'Open') + ' →';
                if (safeHref.indexOf('book') === 0) {
                    link.addEventListener('click', function() {
                        window.sfbcTrackBookingClick();
                    });
                }
                actionsRow.appendChild(link);
            });
            wrapper.appendChild(actionsRow);
        }

        // Contextual Suggested Questions pills
        if (sender === 'model' && Array.isArray(suggestedQuestions) && suggestedQuestions.length > 0) {
            const qRow = document.createElement('div');
            qRow.style.display = 'flex';
            qRow.style.flexWrap = 'wrap';
            qRow.style.gap = '5px';
            qRow.style.marginTop = '8px';
            qRow.style.maxWidth = '100%';

            const hint = document.createElement('span');
            hint.style.fontSize = '10px';
            hint.style.color = '#7C533C';
            hint.style.fontWeight = '600';
            hint.style.width = '100%';
            hint.textContent = 'Suggested follow-ups:';
            qRow.appendChild(hint);

            suggestedQuestions.forEach(function(q) {
                const qBtn = document.createElement('button');
                qBtn.type = 'button';
                qBtn.className = 'sfbc-suggested-q-pill';
                qBtn.textContent = q;
                qBtn.addEventListener('click', function() {
                    window.sfbcQuickAsk(q);
                });
                qRow.appendChild(qBtn);
            });
            wrapper.appendChild(qRow);
        }

        // Feedback buttons (Helpful / Not helpful)
        if (sender === 'model' && messageId > 0) {
            const fbRow = document.createElement('div');
            fbRow.style.display = 'flex';
            fbRow.style.alignItems = 'center';
            fbRow.style.gap = '8px';
            fbRow.style.marginTop = '4px';
            fbRow.style.paddingLeft = '4px';
            fbRow.style.color = '#94a3b8';
            fbRow.style.fontSize = '10.5px';
            fbRow.innerHTML = '<span>Helpful?</span>'
                + '<button type="button" onclick="sfbcSendFeedback(' + Number(messageId) + ', 1, this)" style="background:none; border:none; cursor:pointer; color:#64748b; padding:2px; display:inline-flex; align-items:center;" title="Helpful" aria-label="Helpful"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg></button>'
                + '<button type="button" onclick="sfbcSendFeedback(' + Number(messageId) + ', -1, this)" style="background:none; border:none; cursor:pointer; color:#64748b; padding:2px; display:inline-flex; align-items:center;" title="Not helpful" aria-label="Not helpful"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/></svg></button>';
            wrapper.appendChild(fbRow);
        }

        els.messagesEl.appendChild(wrapper);
        els.messagesEl.scrollTop = els.messagesEl.scrollHeight;
        return wrapper;
    }

    function appendTypingIndicator() {
        const els = getChatEls();
        if (!els.messagesEl) return null;
        const id = 'typing_' + Date.now();
        const div = document.createElement('div');
        div.id = id;
        div.style.alignSelf = 'flex-start';
        div.style.background = '#EFE7DC';
        div.style.color = '#6B4F42';
        div.style.border = '1px solid rgba(132, 86, 60, 0.16)';
        div.style.borderRadius = '999px';
        div.style.padding = '7px 14px';
        div.style.fontSize = '11.5px';
        div.style.fontWeight = '600';
        div.textContent = 'Santa Fe Concierge is typing...';
        els.messagesEl.appendChild(div);
        els.messagesEl.scrollTop = els.messagesEl.scrollHeight;
        return id;
    }

    function removeElement(id) {
        if (!id) return;
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    // Attach immediate click handler
    const sfbcBtn = document.getElementById('sfbcChatToggle');
    if (sfbcBtn) {
        sfbcBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            window.sfbcToggleChat();
        });
    }

    // Hydrate prior session messages & active live staff support across page navigation
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && sfbcLiveStaffActive) {
            syncGuestMessages(true);
        }
    });
    setTimeout(function() {
        syncGuestMessages(false);
    }, 350);
})();
