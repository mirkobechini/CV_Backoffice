// Service worker minimale: nessuna cache di pagine/dati (i dati di flotta
// devono sempre essere live, mai serviti stale da cache). Il solo scopo è
// soddisfare il criterio di installabilità PWA (serve un fetch handler
// registrato) senza introdurre rischio di mostrare stato vecchio.
const CACHE = 'cv-backoffice-static-v1';
const STATIC_PATTERN = /\/build\//;

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || !STATIC_PATTERN.test(url.pathname)) {
        return;
    }

    event.respondWith(
        caches.open(CACHE).then((cache) =>
            cache.match(event.request).then(
                (cached) =>
                    cached ||
                    fetch(event.request).then((response) => {
                        cache.put(event.request, response.clone());
                        return response;
                    })
            )
        )
    );
});
