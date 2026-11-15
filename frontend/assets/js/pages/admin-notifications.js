(function () {
    let _acceptForm = null;

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
    });

    document.addEventListener('click', function (e) {
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
