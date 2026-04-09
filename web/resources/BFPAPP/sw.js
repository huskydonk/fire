// Define a name for the cache
const CACHE_NAME = 'bfp-early-alert-v1';

// List the files to be cached.
const assetsToCache = [
    '/',
    'bfpduty.html'
];

// 1. On install, open the cache and add the core files to it.
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Caching App Shell');
        return cache.addAll(assetsToCache);
      })
  );
});

// 2. On activate, clean up any old caches.
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            console.log('Service Worker: Clearing Old Cache');
            return caches.delete(cache);
          }
        })
      );
    })
  );
});

// 3. On fetch, serve from cache first (Cache-First strategy).
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // If the file is in the cache, serve it. Otherwise, fetch it from the network.
        return response || fetch(event.request);
      })
  );
});

// 4. Handle push events - show notification using payload (if provided)
self.addEventListener('push', event => {
  let payload = {};
  try {
    if (event.data) payload = event.data.json();
  } catch (e) {
    payload = { title: 'BFP Alert', body: event.data ? event.data.text() : 'New notification' };
  }

  const title = payload.title || 'BFP Early Alert';
  const options = {
    body: payload.body || 'You have a new alert.',
    icon: payload.icon || '/components/image/bfp-icon.png',
    data: payload.data || {},
    tag: payload.tag || 'bfp-alert'
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// 5. Handle notification click
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/bfplogin.php';
  event.waitUntil(clients.matchAll({ type: 'window' }).then(windowClients => {
    for (let client of windowClients) {
      if (client.url === url && 'focus' in client) return client.focus();
    }
    if (clients.openWindow) return clients.openWindow(url);
  }));
});