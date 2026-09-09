// Santa Fe Beach Club - Lightweight Service Worker (PWA Offline / Cache Support)
const CACHE_NAME = 'sbc-cache-v1';
const ASSETS_TO_CACHE = [
    './assets/logo.jpg',
    './assets/css/style.css',
    './assets/js/dark-mode-toggle.js'
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
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        })
    );
});

self.addEventListener('fetch', (event) => {
    // Only handle GET requests; never intercept POST/API/admin mutations
    if (event.request.method !== 'GET') {
        return;
    }

    // Do not cache or intercept admin, staff login, or backend API dynamic routes
    const url = new URL(event.request.url);
    if (url.pathname.includes('admin') || url.pathname.includes('staff') || url.pathname.includes('backend/')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .catch(async () => {
                const cachedResponse = await caches.match(event.request);
                if (cachedResponse) {
                    return cachedResponse;
                }
                // Return a safe offline fallback Response object instead of undefined
                return new Response('Network connection failed. Please check your internet connection.', {
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' }
                });
            })
    );
});
