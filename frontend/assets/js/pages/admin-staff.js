(function () {
    function openReset(id, name, type) {
        document.getElementById('resetStaffId').value = id;
        document.getElementById('resetStaffType').value = type || 'receptionist';
        document.getElementById('resetModalSub').textContent = 'Set a new password for "' + name + '".';
        document.getElementById('resetModal').classList.add('open');
    }

    function openEditEmail(id, username, email, type) {
        document.getElementById('editEmailStaffId').value = id;
        document.getElementById('editEmailStaffType').value = type || 'receptionist';
        document.getElementById('editEmailInput').value = email;
        document.getElementById('editEmailModalSub').textContent = 'Update OTP delivery email for "' + username + '".';
        document.getElementById('editEmailModal').classList.add('open');
    }

    function openEditPhoto(id, username, currentPhoto, type) {
        document.getElementById('editPhotoStaffId').value = id;
        document.getElementById('editPhotoStaffType').value = type || 'receptionist';
        document.getElementById('removePhotoStaffId').value = id;
        document.getElementById('removePhotoStaffType').value = type || 'receptionist';
        document.getElementById('editPhotoModalSub').textContent = 'Upload or change profile photo for "' + username + '".';
        document.getElementById('removePhotoBtn').style.display = currentPhoto ? 'flex' : 'none';
        document.getElementById('editPhotoModal').classList.add('open');
    }

    function checkPasswordStrength() {
        const input = document.getElementById('staffPassword');
        if (!input) return;
        const password = input.value;
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthMeter = document.getElementById('passwordStrengthMeter');
        const strengthText = document.getElementById('passwordStrengthText');

        if (!password) {
            strengthBar.style.display = 'none';
            return;
        }

        strengthBar.style.display = 'block';

        let strength = 0;
        if (password.length >= 8) strength += 20;
        if (password.length >= 12) strength += 10;
        if (password.length >= 16) strength += 10;
        if (/[a-z]/.test(password)) strength += 15;
        if (/[A-Z]/.test(password)) strength += 15;
        if (/\d/.test(password)) strength += 15;
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength += 15;

        let level = 'Weak';
        let color = '#ef4444';

        if (strength >= 80) {
            level = 'Strong';
            color = '#22c55e';
        } else if (strength >= 60) {
            level = 'Good';
            color = '#eab308';
        } else if (strength >= 40) {
            level = 'Fair';
            color = '#f97316';
        }

        strengthMeter.style.width = strength + '%';
        strengthMeter.style.backgroundColor = color;
        strengthText.textContent = level;
        strengthText.style.color = color;
    }

    document.addEventListener('click', function (e) {
        const overlay = e.target.closest('.modal-overlay');
        if (overlay && e.target === overlay) {
            overlay.classList.remove('open');
            return;
        }

        const closeId = e.target.closest('[data-close-modal]')?.getAttribute('data-close-modal');
        if (closeId) {
            const modal = document.getElementById(closeId);
            if (modal) modal.classList.remove('open');
            return;
        }

        const actionEl = e.target.closest('[data-action]');
        if (!actionEl) return;

        const action = actionEl.getAttribute('data-action');
        if (action === 'open-add-staff') {
            document.getElementById('addModal')?.classList.add('open');
        } else if (action === 'open-edit-photo') {
            openEditPhoto(
                actionEl.getAttribute('data-id'),
                actionEl.getAttribute('data-username') || '',
                actionEl.getAttribute('data-photo') || '',
                actionEl.getAttribute('data-type') || 'receptionist'
            );
        } else if (action === 'open-edit-email') {
            openEditEmail(
                actionEl.getAttribute('data-id'),
                actionEl.getAttribute('data-username') || '',
                actionEl.getAttribute('data-email') || '',
                actionEl.getAttribute('data-type') || 'receptionist'
            );
        } else if (action === 'open-reset-password') {
            openReset(
                actionEl.getAttribute('data-id'),
                actionEl.getAttribute('data-username') || '',
                actionEl.getAttribute('data-type') || 'receptionist'
            );
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.matches('.js-role-select') && e.target.form) {
            e.target.form.submit();
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target.id === 'staffPassword') {
            checkPasswordStrength();
        }
    });

    document.addEventListener('submit', function (e) {
        if (e.target.matches('form.js-confirm-only')) {
            e.preventDefault();
        }
    });

    window.openReset = openReset;
    window.openEditEmail = openEditEmail;
    window.openEditPhoto = openEditPhoto;
    window.checkPasswordStrength = checkPasswordStrength;
})();
