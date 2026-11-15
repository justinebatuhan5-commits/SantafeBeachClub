(function () {
    function switchAiTab(tabId, btnEl) {
        document.querySelectorAll('.ai-panel').forEach(function (p) { p.classList.remove('active'); });
        document.querySelectorAll('.ai-tab-btn').forEach(function (b) { b.classList.remove('active'); });
        var target = document.getElementById('ai-tab-' + tabId);
        if (target) target.classList.add('active');
        if (btnEl) {
            btnEl.classList.add('active');
        } else {
            var autoBtn = document.getElementById('ai-tab-btn-' + tabId);
            if (autoBtn) autoBtn.classList.add('active');
        }
    }

    function populateKbEdit(entry) {
        if (!entry) return;
        switchAiTab('knowledge');
        var idEl = document.getElementById('kbFormId');
        if (idEl) idEl.value = entry.id || 0;
        var catSelect = document.getElementById('kbFormCategory');
        if (catSelect && entry.category) {
            catSelect.value = entry.category;
        }
        var langSelect = document.getElementById('kbFormLanguage');
        if (langSelect && entry.language) {
            langSelect.value = entry.language;
        }
        var qEl = document.getElementById('kbFormQuestion');
        if (qEl) qEl.value = entry.question || '';
        var aEl = document.getElementById('kbFormAnswer');
        if (aEl) aEl.value = entry.answer || '';
        var hEl = document.getElementById('kbFormHeading');
        if (hEl) hEl.textContent = 'Edit Multilingual FAQ Entry #' + entry.id;
        var btnEl = document.getElementById('kbSubmitBtn');
        if (btnEl) btnEl.textContent = 'Update FAQ Entry #' + entry.id;
        var cancelBtn = document.getElementById('kbCancelEditBtn');
        if (cancelBtn) cancelBtn.style.display = 'inline-block';
        var card = document.getElementById('kbEditorCard');
        if (card && card.scrollIntoView) {
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function resetKbForm() {
        var idEl = document.getElementById('kbFormId');
        if (idEl) idEl.value = '0';
        var qEl = document.getElementById('kbFormQuestion');
        if (qEl) qEl.value = '';
        var aEl = document.getElementById('kbFormAnswer');
        if (aEl) aEl.value = '';
        var hEl = document.getElementById('kbFormHeading');
        if (hEl) hEl.textContent = 'Add New Multilingual FAQ Entry';
        var btnEl = document.getElementById('kbSubmitBtn');
        if (btnEl) btnEl.textContent = 'Save FAQ Entry';
        var cancelBtn = document.getElementById('kbCancelEditBtn');
        if (cancelBtn) cancelBtn.style.display = 'none';
    }

    function prefillKbFromFlagged(questionText, lang) {
        switchAiTab('knowledge');
        resetKbForm();
        if (lang) {
            var langSelect = document.getElementById('kbFormLanguage');
            if (langSelect) {
                for (var i = 0; i < langSelect.options.length; i++) {
                    if (langSelect.options[i].value.toLowerCase() === String(lang).toLowerCase()) {
                        langSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }
        var qInput = document.getElementById('kbFormQuestion');
        if (qInput) {
            qInput.value = questionText || '';
            qInput.focus();
        }
        var card = document.getElementById('kbEditorCard');
        if (card && card.scrollIntoView) {
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function jumpToConversationCard(convId) {
        switchAiTab('conversations');
        var target = document.getElementById('conv-card-' + convId);
        if (target) {
            target.style.display = '';
            if (target.scrollIntoView) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            var oldOutline = target.style.outline;
            target.style.outline = '3px solid #2A9D8F';
            setTimeout(function () {
                target.style.outline = oldOutline;
            }, 2400);
        } else {
            window.location.href = 'admin_chatbot.php?tab=conversations&conv_status=all#conv-card-' + convId;
        }
    }

    function filterKbTable() {
        var q = (document.getElementById('kbSearchFilter')?.value || '').toLowerCase().trim();
        var cat = document.getElementById('kbCategoryFilter')?.value || 'all';
        var lang = document.getElementById('kbLanguageFilter')?.value || 'all';
        document.querySelectorAll('#kbEntriesTable tbody .kb-row').forEach(function (row) {
            var rCat = row.getAttribute('data-kb-category') || '';
            var rLang = row.getAttribute('data-kb-language') || '';
            var rSearch = row.getAttribute('data-kb-search') || '';
            var matchCat = (cat === 'all' || rCat === cat);
            var matchLang = (lang === 'all' || rLang === lang);
            var matchQ = (q === '' || rSearch.indexOf(q) !== -1);
            row.style.display = (matchCat && matchLang && matchQ) ? '' : 'none';
        });
    }

    function filterConversationsClient(query) {
        var q = (query || '').toLowerCase().trim();
        document.querySelectorAll('#ai-tab-conversations .conv-item').forEach(function (item) {
            var hay = item.getAttribute('data-conv-search') || '';
            item.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
        });
    }

    // Event delegation and explicit bindings
    document.addEventListener('click', function (e) {
        var tabBtn = e.target.closest('[data-ai-tab]');
        if (tabBtn) {
            switchAiTab(tabBtn.dataset.aiTab, tabBtn);
            return;
        }

        var prefillBtn = e.target.closest('.js-prefill-kb');
        if (prefillBtn) {
            prefillKbFromFlagged(prefillBtn.dataset.question || '', prefillBtn.dataset.lang || '');
            return;
        }

        var jumpLink = e.target.closest('.js-jump-conv');
        if (jumpLink) {
            jumpToConversationCard(parseInt(jumpLink.dataset.convId || '0', 10));
            return;
        }

        var editBtn = e.target.closest('.js-edit-kb');
        if (editBtn && editBtn.dataset.kbEntry) {
            try {
                populateKbEdit(JSON.parse(editBtn.dataset.kbEntry));
            } catch (err) {
                console.error('Failed to parse KB entry payload', err);
            }
        }
    });

    document.addEventListener('submit', function (e) {
        var delForm = e.target.closest('.js-delete-kb-form');
        if (delForm) {
            var kbId = delForm.dataset.kbId || '';
            if (!window.confirm('Delete FAQ entry #' + kbId + '?')) {
                e.preventDefault();
            }
        }
    });

    document.getElementById('kbCancelEditBtn')?.addEventListener('click', resetKbForm);

    document.getElementById('convSearchInput')?.addEventListener('input', function () {
        filterConversationsClient(this.value);
    });

    document.getElementById('kbSearchFilter')?.addEventListener('input', filterKbTable);
    document.getElementById('kbCategoryFilter')?.addEventListener('change', filterKbTable);
    document.getElementById('kbLanguageFilter')?.addEventListener('change', filterKbTable);

    window.switchAiTab = switchAiTab;
    window.populateKbEdit = populateKbEdit;
    window.resetKbForm = resetKbForm;
    window.prefillKbFromFlagged = prefillKbFromFlagged;
    window.jumpToConversationCard = jumpToConversationCard;
    window.filterKbTable = filterKbTable;
    window.filterConversationsClient = filterConversationsClient;
})();
