// Kalamedia Progressive Web App (PWA) Service Worker
const CACHE_NAME = 'kalamedia-pwa-v12-mobile-responsive';
const urlsToCache = [
  'assets/css/style.css',
  'assets/js/app.js',
  'assets/js/charts.js',
  'assets/img/Logo-1.webp'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
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

  // 1. Never intercept non-GET requests (POST, PUT, DELETE)
  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);

  // 2. Never intercept API calls or dynamic PHP script execution
  if (url.pathname.includes('/api/') || url.pathname.endsWith('.php') || url.search) {
    return;
  }

  // 3. Never intercept cross-origin third-party resources (CDNs, Google Fonts, etc.)
  if (url.origin !== self.location.origin) {
    return;
  }

  // 4. Only cache static files (CSS, WebP, PNG, SVG, fonts)
  const isStaticAsset = url.pathname.match(/\.(css|js|webp|png|jpg|jpeg|svg|woff2?|ttf|ico)$/i);
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
