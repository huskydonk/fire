const CACHE_NAME = 'bfp-early-alert-v2'; // Changed version to force update
const ASSETS_TO_CACHE = [
    './',                // Current folder
    'index.php',         // Resident App
    'bfplogin.php',      // Officer App
    'install.php',       // Installer Page
    'manifest.json',     // Resident Manifest
    'manifest-officer.json', // Officer Manifest
    'logoBFP.png',       // Logos
    'logo.png',
    // Add your icon files if you have them locally. 
    // If they are missing from the folder, remove these lines or it will break!
    // 'icon-192.png',
    // 'icon-512.png' 
];

// Install Event
self.addEventListener('install', (event) => {
    // Force this new service worker to become the active one immediately
    self.skipWaiting(); 
    
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[Service Worker] Caching static assets');
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

// Activate Event (Cleanup old caches)
self.addEventListener('activate', (event) => {
    // Take control of all open clients immediately
    event.waitUntil(clients.claim());
    
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(keys.map((key) => {
                if (key !== CACHE_NAME) {
                    console.log('[Service Worker] Removing old cache:', key);
                    return caches.delete(key);
                }
            }));
        })
    );
});

// Fetch Event (Network First, then Cache)
self.addEventListener('fetch', (event) => {
    // Skip cross-origin requests (like Google Maps/Leaflet tiles)
    if (!event.request.url.startsWith(self.location.origin)) return;

    // Handle POST requests (like logins) by letting them go to network only
    // Service Workers cannot cache POST requests
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Check if we received a valid response
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }

                // Clone the response to put in cache
                const responseClone = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(event.request, responseClone);
                });
                return response;
            })
            .catch(() => {
                // If offline, try to return cached version
                return caches.match(event.request).then((response) => {
                    if (response) {
                        return response;
                    }
                    // Optional: You could return a custom offline.html here
                });
            })
    );
});