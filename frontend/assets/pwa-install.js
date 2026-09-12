// PWA Install Prompt Handler
(function() {
    'use strict';

    let deferredPrompt;

    // Capture the beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        showInstallPrompt();
    });

    // Show the install prompt UI
    function showInstallPrompt() {
        const installPrompt = document.getElementById('pwa-install-prompt');
        if (installPrompt) {
            installPrompt.style.display = 'flex';
        }
    }

    // Handle install button click
    window.addEventListener('DOMContentLoaded', function() {
        const installBtn = document.getElementById('pwa-install-btn');
        const dismissBtn = document.getElementById('pwa-dismiss-btn');

        if (installBtn) {
            installBtn.addEventListener('click', () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                        } else {
                            console.log('User dismissed the install prompt');
                        }
                        deferredPrompt = null;
                        hideInstallPrompt();
                    });
                }
            });
        }

        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => {
                hideInstallPrompt();
            });
        }

        // Hide prompt if app is already installed
        window.addEventListener('appinstalled', () => {
            deferredPrompt = null;
            hideInstallPrompt();
            console.log('PWA was installed');
        });
    });

    function hideInstallPrompt() {
        const installPrompt = document.getElementById('pwa-install-prompt');
        if (installPrompt) {
            installPrompt.style.display = 'none';
        }
    }

    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/frontend/sw.js', { scope: '/frontend/' })
                .then((registration) => {
                    console.log('Service Worker registered successfully:', registration);
                })
                .catch((error) => {
                    console.warn('Service Worker registration failed:', error);
                });
        });
    }
})();
