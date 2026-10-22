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
    // Ripulisce le cache delle versioni precedenti (audit 2026-10-22, B5):
    // senza, ogni bump di CACHE lasciava la vecchia orfana per sempre,
    // occupando spazio senza mai essere letta.
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))
        ).then(() => self.clients.claim())
    );
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
                        // Mai in cache una risposta di errore (audit
                        // 2026-10-22, B5): durante un deploy un asset con
                        // hash cambiato può dare un 404/500 transitorio —
                        // cacharlo lo renderebbe permanentemente rotto fino
                        // al prossimo deploy che rigenera lo stesso path.
                        if (response.ok) {
                            cache.put(event.request, response.clone());
                        }
                        return response;
                    })
            )
        )
    );
});
