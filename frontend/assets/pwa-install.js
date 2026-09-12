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
            // Try to register Service Worker from /frontend/sw.js
            navigator.serviceWorker.register('/frontend/sw.js', { scope: '/frontend/' })
                .then((registration) => {
                    console.log('✓ Service Worker registered successfully', registration);
                    
                    // Check for updates every hour
                    setInterval(() => {
                        registration.update().catch(() => {});
                    }, 3600000);
                })
                .catch((error) => {
                    console.warn('✗ Service Worker registration failed:', error.message);
                    
                    // Fallback: try alternative registration path
                    navigator.serviceWorker.register('sw.js', { scope: './' })
                        .then(() => console.log('✓ Fallback Service Worker registered'))
                        .catch((err) => console.warn('✗ Fallback also failed:', err.message));
                });
        });
    } else {
        console.warn('Service Worker not supported in this browser');
    }
})();
