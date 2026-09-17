// Cache same-origin static assets only; account and booking pages use the network.
const CACHE_NAME = 'sbc-cache-v2';
const ASSETS = ['./assets/logo.jpg', './assets/css/styles.css', './assets/js/dark-mode-toggle.js', './assets/pwa-install.js', './manifest.json'];
self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(caches.open(CACHE_NAME).then(cache => Promise.all(ASSETS.map(asset => cache.add(asset).catch(() => {})))).catch(() => {}));
});
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        try {
            const keys = await caches.keys();
            await Promise.all(keys.filter(key => key.startsWith('sbc-') && key !== CACHE_NAME).map(key => caches.delete(key)));
        } catch (_) { /* Networking still works without cache storage. */ }
        await self.clients.claim();
    })());
});
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || !['http:', 'https:'].includes(url.protocol) || url.origin !== self.location.origin) return;
    const assetRoot = new URL('./assets/', self.location.href).pathname;
    if (!url.pathname.startsWith(assetRoot) || !/\.(css|js|jpg|jpeg|png|gif|svg|webp|ico|woff2?|ttf)$/i.test(url.pathname)) return;
    event.respondWith((async () => {
        let cache;
        try {
            cache = await caches.open(CACHE_NAME);
            const cached = await cache.match(request);
            if (cached) return cached;
        } catch (_) { /* Cache failures must not prevent fetching. */ }
        try {
            const response = await fetch(request);
            if (cache && response.ok) {
                // Clone before returning/consuming the response; await asynchronous errors.
                const copy = response.clone();
                try { await cache.put(request, copy); } catch (_) { /* Quota or storage failure. */ }
            }
            return response;
        } catch (_) {
            return new Response('This file is unavailable offline. Please reconnect and reload.', {
                status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' }
            });
        }
    })());
});
