(function () {
    'use strict';

    function switchTab(tab) {
        var panel = document.getElementById('tab-' + tab);
        var btn = document.querySelector('.tab-btn[data-tab="' + tab + '"]');
        if (!panel || !btn) {
            return;
        }
        document.querySelectorAll('.tab-panel').forEach(function (p) {
            p.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(function (b) {
            b.classList.remove('active');
        });
        panel.classList.add('active');
        btn.classList.add('active');
        history.replaceState(null, '', '?tab=' + tab);
    }

    function confirmDeleteAdmin(username, formEl) {
        if (typeof showConfirm === 'function') {
            showConfirm({
                title: 'Remove Admin Account',
                message: 'Remove admin account "' + username + '"? This cannot be undone.',
                icon: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
                iconBg: '#FEE2E2',
                confirmText: 'Remove',
                onConfirm: function () {
                    formEl.submit();
                }
            });
            return;
        }
        formEl.submit();
    }

    function initNotificationControls() {
        var soundToggle = document.getElementById('toggle-sound');
        var desktopToggle = document.getElementById('toggle-desktop');
        var soundStatus = document.getElementById('sound-status');
        var desktopStatus = document.getElementById('desktop-status');
        var permNote = document.getElementById('permission-note');
        var testSoundBtn = document.getElementById('test-sound-btn');
        var testDesktopBtn = document.getElementById('test-desktop-btn');

        if (!soundToggle) {
            return;
        }

        function setSoundUI(muted) {
            soundToggle.checked = !muted;
            soundStatus.textContent = muted ? 'Muted' : 'Sound On';
            soundStatus.className = 'notif-pref-status ' + (muted ? 'off' : 'on');
        }

        function setDesktopUI(enabled) {
            desktopToggle.checked = enabled;
            desktopStatus.textContent = enabled ? 'Enabled' : 'Disabled';
            desktopStatus.className = 'notif-pref-status ' + (enabled ? 'on' : 'off');
        }

        function playTestSound() {
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) {
                    SiteDialog.alert('Web Audio not supported in this browser.');
                    return;
                }
                var ctx = new AC();
                var now = ctx.currentTime;
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, now);
                osc.frequency.exponentialRampToValueAtTime(660, now + 0.18);
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.08, now + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.24);
            } catch (e) {
                console.warn('Audio test error:', e);
            }
        }

        function checkPermission() {
            if (!('Notification' in window)) {
                desktopToggle.disabled = true;
                desktopStatus.textContent = 'Not supported';
                desktopStatus.className = 'notif-pref-status off';
                return;
            }
            if (Notification.permission === 'denied') {
                permNote.classList.add('visible');
                setDesktopUI(false);
                desktopToggle.disabled = true;
            } else {
                permNote.classList.remove('visible');
                desktopToggle.disabled = false;
            }
        }

        var isMuted = localStorage.getItem('sbc_notif_sound_muted') === '1';
        var isDesktop = localStorage.getItem('sbc_notif_desktop_enabled') === '1';
        setSoundUI(isMuted);
        setDesktopUI(isDesktop);
        checkPermission();

        soundToggle.addEventListener('change', function () {
            var muted = !soundToggle.checked;
            localStorage.setItem('sbc_notif_sound_muted', muted ? '1' : '0');
            setSoundUI(muted);
        });

        desktopToggle.addEventListener('change', function () {
            if (!desktopToggle.checked) {
                localStorage.setItem('sbc_notif_desktop_enabled', '0');
                setDesktopUI(false);
                return;
            }
            if (Notification.permission === 'granted') {
                localStorage.setItem('sbc_notif_desktop_enabled', '1');
                setDesktopUI(true);
            } else {
                Notification.requestPermission().then(function (p) {
                    if (p === 'granted') {
                        localStorage.setItem('sbc_notif_desktop_enabled', '1');
                        setDesktopUI(true);
                        permNote.classList.remove('visible');
                    } else {
                        localStorage.setItem('sbc_notif_desktop_enabled', '0');
                        setDesktopUI(false);
                        if (p === 'denied') {
                            permNote.classList.add('visible');
                            desktopToggle.disabled = true;
                        }
                    }
                });
            }
        });

        testSoundBtn.addEventListener('click', function () {
            playTestSound();
            testSoundBtn.textContent = 'Played!';
            setTimeout(function () {
                testSoundBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg> Test Sound';
            }, 1600);
        });

        testDesktopBtn.addEventListener('click', function () {
            if (Notification.permission !== 'granted') {
                SiteDialog.alert('Desktop notifications are not permitted. Enable them first using the toggle above.');
                return;
            }
            var n = new Notification('Santa Fe Beach Club', {
                body: 'This is a test desktop notification. You are all set!',
                tag: 'sbc-test-notif'
            });
            setTimeout(function () { n.close(); }, 5000);
            testDesktopBtn.textContent = 'Sent!';
            setTimeout(function () {
                testDesktopBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Send Test Popup';
            }, 1600);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.tab-btn[data-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchTab(this.dataset.tab);
            });
        });

        document.querySelectorAll('.js-delete-admin-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                confirmDeleteAdmin(this.dataset.username || '', this);
            });
        });

        initNotificationControls();
    });
})();
