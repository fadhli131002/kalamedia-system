// Portal Kala Progressive Web App (PWA) Service Worker
const CACHE_NAME = 'portal-kala-pwa-v3.0';
const urlsToCache = [
  'assets/css/style.css',
  'assets/js/app.js',
  'assets/js/charts.js',
  'assets/img/Logo-1.webp',
  'assets/img/icon-192.png',
  'assets/img/icon-512.png',
  'manifest.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache)).catch(err => {
      console.warn('PWA: Failed to cache some initial assets', err);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const req = event.request;

  // 1. Only intercept GET requests
  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);

  // 2. Never cache or intercept dynamic API calls, live PHP scripts, or dynamic query URLs
  if (url.pathname.includes('/api/') || url.pathname.endsWith('.php') || url.search) {
    return;
  }

  // 3. Never intercept cross-origin third-party resources (CDNs, Google Fonts, etc.)
  if (url.origin !== self.location.origin) {
    return;
  }

  // 4. Only cache static files (CSS, WebP, PNG, SVG, fonts, manifest)
  const isStaticAsset = url.pathname.match(/\.(css|js|webp|png|jpg|jpeg|svg|woff2?|ttf|ico|json)$/i);
  if (!isStaticAsset) {
    return;
  }

  // Cache-first with network fallback for static assets
  event.respondWith(
    caches.match(req).then(cached => {
      if (cached) {
        return cached;
      }
      return fetch(req).then(response => {
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response;
        }
        const toCache = response.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(req, toCache);
        });
        return response;
      });
    })
  );
});
