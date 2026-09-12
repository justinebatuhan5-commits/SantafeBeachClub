// Santa Fe Beach Club - PWA Service Worker with Offline Support
const CACHE_VERSION = 'v1';
const CACHE_NAME = 'sbc-cache-' + CACHE_VERSION;
const RUNTIME_CACHE = 'sbc-runtime-' + CACHE_VERSION;
const ADMIN_CACHE = 'sbc-admin-' + CACHE_VERSION;

// Static assets to cache on install
const ASSETS_TO_CACHE = [
    './assets/logo.jpg',
    './assets/css/style.css',
    './assets/js/dark-mode-toggle.js',
    './assets/pwa-install.js',
    './manifest.json'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE).catch(() => {});
        })
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key.startsWith('sbc-') && key !== CACHE_NAME && key !== RUNTIME_CACHE && key !== ADMIN_CACHE)
                  .map((key) => caches.delete(key))
            );
        })
    );
});

self.addEventListener('fetch', (event) => {
    // Only handle GET requests; never intercept POST/API/admin mutations
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    const isAdminPage = url.pathname.includes('admin_login') || 
                        url.pathname.includes('admin_dashboard') || 
                        url.pathname.includes('staff_login');
    const isBackendAPI = url.pathname.includes('backend/');
    const isAsset = url.pathname.match(/\.(css|js|jpg|jpeg|png|gif|svg|woff|woff2|ttf)$/i);

    // Cache assets
    if (isAsset) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) => {
                return cache.match(event.request).then((response) => {
                    if (response) return response;
                    
                    return fetch(event.request).then((fetchedResponse) => {
                        if (fetchedResponse && fetchedResponse.status === 200) {
                            cache.put(event.request, fetchedResponse.clone());
                        }
                        return fetchedResponse;
                    }).catch(() => {
                        return caches.match(event.request);
                    });
                });
            })
        );
        return;
    }

    // For admin pages: cache on use for offline support
    if (isAdminPage && !isBackendAPI) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    if (response && response.status === 200) {
                        caches.open(ADMIN_CACHE).then((cache) => {
                            cache.put(event.request, response.clone());
                        });
                    }
                    return response;
                })
                .catch(async () => {
                    // Serve from cache if offline
                    const cachedResponse = await caches.match(event.request);
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Fallback offline page
                    return new Response(
                        '<html><body style="font-family:sans-serif; padding:20px;"><h1>Offline</h1><p>You are currently offline. This page was cached on your last visit.</p></body></html>',
                        {
                            status: 503,
                            statusText: 'Service Unavailable',
                            headers: { 'Content-Type': 'text/html; charset=utf-8' }
                        }
                    );
                })
        );
        return;
    }

    // Backend APIs: don't cache, always fetch
    if (isBackendAPI) {
        event.respondWith(fetch(event.request));
        return;
    }

    // Public pages: network first, cache as fallback
    event.respondWith(
        fetch(event.request)
            .catch(async () => {
                const cachedResponse = await caches.match(event.request);
                if (cachedResponse) {
                    return cachedResponse;
                }
                return new Response('Network connection failed. Please check your internet connection.', {
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' }
                });
            })
    );
});
