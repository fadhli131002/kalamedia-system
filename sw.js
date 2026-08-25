// Kalamedia Progressive Web App (PWA) Service Worker
const CACHE_NAME = 'kalamedia-pwa-v1';
const urlsToCache = [
  './',
  'assets/css/style.css',
  'assets/js/app.js',
  'assets/Jpg/Asset 3.png'
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
  // Network first fallback to cache strategy
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
