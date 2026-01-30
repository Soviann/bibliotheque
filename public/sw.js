const CACHE_NAME = 'bibliotheque-v1';
const STATIC_CACHE = 'bibliotheque-static-v1';
const API_CACHE = 'bibliotheque-api-v1';

const STATIC_ASSETS = [
    '/',
    '/login',
    '/manifest.json',
    '/styles/app.css',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME && name !== STATIC_CACHE && name !== API_CACHE)
                    .map((name) => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') {
        return;
    }

    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirstStrategy(request, API_CACHE));
        return;
    }

    if (
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.ico') ||
        url.pathname.endsWith('.json')
    ) {
        event.respondWith(cacheFirstStrategy(request, STATIC_CACHE));
        return;
    }

    event.respondWith(staleWhileRevalidate(request, CACHE_NAME));
});

async function cacheFirstStrategy(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        return new Response('Offline', { status: 503 });
    }
}

async function networkFirstStrategy(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        const cachedResponse = await cache.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        return new Response(JSON.stringify({ error: 'Offline' }), {
            headers: { 'Content-Type': 'application/json' },
            status: 503,
        });
    }
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    const fetchPromise = fetch(request)
        .then((networkResponse) => {
            if (networkResponse.ok) {
                cache.put(request, networkResponse.clone());
            }
            return networkResponse;
        })
        .catch(() => cachedResponse);

    return cachedResponse || fetchPromise;
}
