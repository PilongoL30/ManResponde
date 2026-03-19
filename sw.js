// ManResponde Service Worker
// Progressive Web App - Offline Support

const CACHE_NAME = 'manresponde-v11';
const CACHE_URLS = [
    '/ManResponde/dashboard.php',
    '/ManResponde/responde.png',
    '/ManResponde/assets/css/custom.css',
    'https://cdn.tailwindcss.com',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap'
];

// Install event - cache resources
self.addEventListener('install', (event) => {
    console.log('Service Worker: Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Service Worker: Caching files');
                return cache.addAll(CACHE_URLS);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('Service Worker: Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        console.log('Service Worker: Clearing old cache');
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
    return self.clients.claim();
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Never cache non-GET requests (e.g. POST polling like recent_feed).
    if (req.method !== 'GET') {
        event.respondWith(fetch(req));
        return;
    }

    // Avoid caching dynamic dashboard/API responses.
    // (dashboard.php uses POST api_action for JSON; but keep GET navigations cacheable.)
    const isSameOrigin = url.origin === self.location.origin;
    const isDashboard = isSameOrigin && url.pathname.endsWith('/dashboard.php');
    const hasQuery = url.search && url.search.length > 0;

    // If it's dashboard.php with query params (views, etc), or any same-origin request with
    // obvious API-like query markers, just do network-first without writing to cache.
    const looksDynamic = (isDashboard && hasQuery) || url.searchParams.has('api_action') || url.searchParams.has('action');

    if (looksDynamic) {
        event.respondWith(
            fetch(req).catch(() => caches.match(req))
        );
        return;
    }

    // Default: network-first for GET, cache on success, fallback to cache when offline.
    event.respondWith(
        fetch(req)
            .then((response) => {
                const responseClone = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(req, responseClone);
                });
                return response;
            })
            .catch(() => caches.match(req))
    );
});

// Background sync for offline actions
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-reports') {
        event.waitUntil(syncReports());
    }
});

async function syncReports() {
    // Implement your background sync logic here
    console.log('Service Worker: Syncing reports...');
}

// Push notifications
self.addEventListener('push', (event) => {
    const options = {
        body: event.data ? event.data.text() : 'New emergency report',
        icon: '/ManResponde/responde.png',
        badge: '/ManResponde/responde.png',
        vibrate: [200, 100, 200],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'view',
                title: 'View Report'
            },
            {
                action: 'close',
                title: 'Close'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('ManResponde', options)
    );
});

// Notification click
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow('/ManResponde/dashboard.php')
        );
    }
});
