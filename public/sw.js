// Marquee service worker: runtime cache for static assets using a
// stale-while-revalidate strategy so an updated stylesheet or script always
// reaches the browser on the next load. Asset URLs are additionally versioned
// by file mtime (see the `asset()` Twig helper), so a changed file is a brand
// new URL that can never be answered from a stale cache entry.

const CACHE = 'marquee-assets-v2';

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }
    if (!url.pathname.startsWith('/assets/')) {
        return;
    }

    // Stale-while-revalidate: answer from cache immediately when present, but
    // always fetch a fresh copy in the background and update the cache.
    event.respondWith(
        caches.open(CACHE).then((cache) =>
            cache.match(event.request).then((hit) => {
                const network = fetch(event.request)
                    .then((response) => {
                        if (response.ok) {
                            cache.put(event.request, response.clone());
                        }
                        return response;
                    })
                    .catch(() => hit);
                return hit || network;
            })
        )
    );
});
