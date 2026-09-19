/* Lifevuww Service Worker: static-asset cache + offline fallback.
   Dynamic PHP pages are NEVER cached (network-only) to avoid stale
   session data. Only versioned on purpose via CACHE_NAME. */
const CACHE_NAME = 'lifevuww-v1';
const STATIC_ASSETS = [
  './assets/css/style.css',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
  './assets/images/logo.svg',
  './offline.html'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  const isStatic = /\.(css|js|png|jpg|jpeg|svg|ico|woff2?)$/i.test(url.pathname);

  if (isStatic && url.origin === self.location.origin) {
    // Cache-first for versioned static assets
    event.respondWith(
      caches.match(request).then((hit) =>
        hit || fetch(request).then((res) => {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return res;
        }).catch(() => caches.match('./offline.html'))
      )
    );
    return;
  }

  // Navigations and PHP: network-first, offline fallback page when unreachable
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('./offline.html'))
    );
  }
});
