(function () {
    let _acceptForm = null;
    let activeFilter = 'all';

    function openAcceptModal(form) {
        _acceptForm = form;
        const modal = document.getElementById('acceptModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeAcceptModal() {
        _acceptForm = null;
        const modal = document.getElementById('acceptModal');
        if (modal) modal.style.display = 'none';
    }

    function matchesFilter(item, filterKey) {
        const type = (item.dataset.type || 'info').toLowerCase();
        const isUnread = item.dataset.unread === '1';
        const isToday = item.dataset.today === '1';
        if (filterKey === 'today') return isToday;
        if (filterKey === 'unread') return isUnread;
        if (filterKey === 'urgent') return type === 'alert' || type === 'warning';
        if (filterKey === 'updates') return type === 'info' || type === 'success';
        return true;
    }

    function applyFeedFilter() {
        const list = document.getElementById('notifCardList');
        if (!list) return;

        const items = Array.from(list.querySelectorAll('.notif-item'));
        const searchInput = document.getElementById('notifSearchInput');
        const clearBtn = document.getElementById('notifSearchClear');
        const emptyState = document.getElementById('notifFilterEmpty');
        const visibleBadge = document.getElementById('notifVisibleCount');

        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        if (clearBtn) {
            clearBtn.hidden = query === '';
        }

        let visibleCount = 0;
        items.forEach(item => {
            const haystack = (item.dataset.search || '').toLowerCase();
            const show = matchesFilter(item, activeFilter) && (query === '' || haystack.includes(query));
            item.hidden = !show;
            if (show) visibleCount++;
        });

        if (visibleBadge) {
            visibleBadge.textContent = visibleCount + (query || activeFilter !== 'all' ? ' shown' : ' total');
        }
        if (emptyState && items.length > 0) {
            emptyState.hidden = visibleCount > 0;
        }
    }

    function setActiveFilter(filterKey) {
        activeFilter = filterKey;
        document.querySelectorAll('.notif-filter-pill').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.notifFilter === filterKey);
        });
        applyFeedFilter();
    }

    function markItemReadVisually(itemEl) {
        if (!itemEl || itemEl.dataset.unread !== '1') return;

        itemEl.dataset.unread = '0';
        itemEl.classList.remove('unread');
        itemEl.classList.add('is-read');

        const unreadPill = itemEl.querySelector('.notif-unread-pill');
        if (unreadPill) unreadPill.remove();

        const markBtn = itemEl.querySelector('.btn-mark-read');
        const markForm = markBtn ? markBtn.closest('form') : null;
        if (markForm) markForm.remove();

        const remainingUnread = document.querySelectorAll('#notifCardList .notif-item[data-unread="1"]').length;
        const unreadBadge = document.getElementById('notifUnreadPill');
        const tabUnread = document.getElementById('notifTabUnreadCount');
        const summaryUnread = document.getElementById('summaryUnreadValue');

        if (unreadBadge) unreadBadge.textContent = remainingUnread + ' unread';
        if (tabUnread) tabUnread.textContent = String(remainingUnread);
        if (summaryUnread) summaryUnread.textContent = String(remainingUnread);

        const headerBtn = document.querySelector('.notif-mark-all-header');
        if (headerBtn && remainingUnread === 0) {
            headerBtn.disabled = true;
            const span = headerBtn.querySelector('span');
            if (span) span.textContent = 'All Caught Up';
        }

        if (activeFilter === 'unread') {
            applyFeedFilter();
        }
    }

    function initBroadcastPreview() {
        const titleInput = document.getElementById('broadcastTitle');
        const msgInput = document.getElementById('broadcastMessage');
        const titleCounter = document.getElementById('broadcastTitleCount');
        const msgCounter = document.getElementById('broadcastMsgCount');
        const preview = document.getElementById('broadcastPreview');
        const previewBadge = document.getElementById('broadcastPreviewBadge');
        const previewTitle = document.getElementById('broadcastPreviewTitle');
        const previewMsg = document.getElementById('broadcastPreviewMsg');
        const radios = document.querySelectorAll('.broadcast-type-grid input[name="type"]');

        if (!preview) return;

        function syncPreview() {
            const titleVal = titleInput ? titleInput.value.trim() : '';
            const msgVal = msgInput ? msgInput.value.trim() : '';
            let selectedType = 'info';
            radios.forEach(r => {
                if (r.checked) selectedType = r.value;
            });

            if (titleCounter && titleInput) {
                titleCounter.textContent = titleInput.value.length + ' / ' + (titleInput.maxLength || 120);
            }
            if (msgCounter && msgInput) {
                msgCounter.textContent = msgInput.value.length + ' / ' + (msgInput.maxLength || 1000);
            }

            preview.className = 'broadcast-preview type-' + selectedType;
            if (previewBadge) {
                previewBadge.className = 'notif-badge badge-' + selectedType;
                previewBadge.textContent = selectedType.toUpperCase();
            }
            if (previewTitle) {
                previewTitle.textContent = titleVal || 'Your alert title preview';
            }
            if (previewMsg) {
                previewMsg.textContent = msgVal || 'Broadcast message details will appear in the team feed.';
            }
        }

        if (titleInput) titleInput.addEventListener('input', syncPreview);
        if (msgInput) msgInput.addEventListener('input', syncPreview);
        radios.forEach(r => r.addEventListener('change', syncPreview));
        syncPreview();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const confirmBtn = document.getElementById('acceptConfirmBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (_acceptForm) {
                    _acceptForm.onsubmit = null;
                    _acceptForm.submit();
                }
            });
        }

        const acceptModal = document.getElementById('acceptModal');
        if (acceptModal) {
            acceptModal.addEventListener('click', function (e) {
                if (e.target === acceptModal) closeAcceptModal();
            });
        }

        const searchInput = document.getElementById('notifSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', applyFeedFilter);
        }

        const clearBtn = document.getElementById('notifSearchClear');
        if (clearBtn && searchInput) {
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                applyFeedFilter();
                searchInput.focus();
            });
        }

        const resetBtn = document.getElementById('notifResetFilter');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (searchInput) searchInput.value = '';
                setActiveFilter('all');
            });
        }

        const summaryUnreadCard = document.getElementById('summaryUnreadCard');
        if (summaryUnreadCard) {
            summaryUnreadCard.addEventListener('click', function () {
                setActiveFilter('unread');
                document.querySelector('.notif-feed-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        initBroadcastPreview();
    });

    document.addEventListener('click', function (e) {
        const filterBtn = e.target.closest('.notif-filter-pill');
        if (filterBtn) {
            setActiveFilter(filterBtn.dataset.notifFilter || 'all');
            return;
        }

        if (e.target.closest('.notif-actions')) {
            return;
        }

        const detailTarget = e.target.closest('[data-action="show-notif-detail"]');
        if (detailTarget) {
            try {
                const payload = JSON.parse(detailTarget.getAttribute('data-notif') || '{}');
                if (typeof window.showNotificationDetailModal === 'function') {
                    window.showNotificationDetailModal(payload);
                }
                markItemReadVisually(detailTarget.closest('.notif-item'));
            } catch (err) {
                console.error('Failed to parse notification detail payload:', err);
            }
            return;
        }

        const actionEl = e.target.closest('[data-action="close-accept-modal"]');
        if (actionEl) {
            closeAcceptModal();
        }
    });

    window.openAcceptModal = openAcceptModal;
    window.closeAcceptModal = closeAcceptModal;
})();
