/*
 * Executive Desk service worker.
 *
 * Caching policy (deliberately conservative):
 * - Only public, non-sensitive static files are cached: hashed build assets, icons,
 *   the manifest, and the offline page.
 * - HTML pages, Inertia/JSON responses, downloads, and anything requiring sign-in are
 *   never stored in the Cache API. When a page cannot be reached offline, the static
 *   offline page is shown instead; it reads the signed-in user's limited snapshot from
 *   IndexedDB, which is cleared on sign-out.
 */

const VERSION = 'v1';
const STATIC_CACHE = `executive-desk-static-${VERSION}`;
const PRECACHE = [
    '/offline.html',
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/favicon.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith('executive-desk-') &&
                                key !== STATIC_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

function isStaticAsset(url) {
    return (
        url.pathname.startsWith('/build/assets/') ||
        url.pathname.startsWith('/icons/') ||
        PRECACHE.includes(url.pathname)
    );
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok && response.type === 'basic') {
                            const copy = response.clone();
                            caches
                                .open(STATIC_CACHE)
                                .then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );

        return;
    }

    // Full page navigations: always go to the network; fall back to the offline page.
    // Inertia XHR visits are not navigations, so the app shows its own offline state.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html')),
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = {
            title: 'Executive Desk',
            body: event.data ? event.data.text() : '',
        };
    }

    const title = data.title || 'Executive Desk';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: data.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: data.tag || undefined,
            renotify: Boolean(data.tag),
            data: { url: data.url || '/dashboard' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    let target = new URL('/dashboard', self.location.origin);

    try {
        const requested = new URL(
            event.notification.data && event.notification.data.url,
            self.location.origin,
        );

        // Only open pages of this application.
        if (requested.origin === self.location.origin) {
            target = requested;
        }
    } catch {
        // Keep the dashboard default.
    }

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                for (const client of clients) {
                    if (
                        new URL(client.url).origin === self.location.origin &&
                        'focus' in client
                    ) {
                        return client
                            .navigate(target.href)
                            .then((navigated) => (navigated || client).focus());
                    }
                }

                return self.clients.openWindow(target.href);
            }),
    );
});
