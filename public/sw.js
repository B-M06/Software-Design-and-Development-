'use strict';
const CACHE_NAME = 'foodrescue-static-v1';
const STATIC_ASSETS = ['./assets/style.css', './assets/app.js', './offline.html'];
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  // Never cache PHP pages, API responses, user-specific data, or form submissions.
  if (url.pathname.endsWith('.php') || url.pathname.includes('/api/')) return;
  if (STATIC_ASSETS.some(asset => url.pathname.endsWith(asset.replace('./','/')))) {
    event.respondWith(caches.match(request).then(cached => cached || fetch(request)));
  }
});
