// Marquee service worker: cache the static app shell for fast, offline-tolerant
// asset loads. Everything else passes through to the network.

const CACHE = 'marquee-assets-v1';
const ASSETS = [
    '/assets/app.css',
    '/assets/app.js',
    '/assets/alpine.min.js',
    '/assets/wall.css',
    '/assets/wall.js',
    '/assets/favicon.svg',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(ASSETS)).then(() => self.skipWaiting())
    );
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

    event.respondWith(
        caches.match(event.request).then((hit) => {
            if (hit) {
                return hit;
            }
            return fetch(event.request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE).then((cache) => cache.put(event.request, copy));
                return response;
            });
        })
    );
});
