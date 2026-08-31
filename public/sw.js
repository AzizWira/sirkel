const SIRKEL_STATIC_CACHE = 'sirkel-static-v1.0.57';
const STATIC_PREFIXES = ['/build/', '/brand/'];
const STATIC_FILES = ['/site.webmanifest'];

self.addEventListener('install', event => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names
            .filter(name => name.startsWith('sirkel-static-') && name !== SIRKEL_STATIC_CACHE)
            .map(name => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET' || request.mode === 'navigate') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    const isStatic = STATIC_PREFIXES.some(prefix => url.pathname.startsWith(prefix))
        || STATIC_FILES.includes(url.pathname);
    if (!isStatic) return;

    event.respondWith((async () => {
        const cache = await caches.open(SIRKEL_STATIC_CACHE);
        try {
            const response = await fetch(request);
            if (response.ok) await cache.put(request, response.clone());
            return response;
        } catch (error) {
            const cached = await cache.match(request);
            if (cached) return cached;
            throw error;
        }
    })());
});
