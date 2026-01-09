// 🔥 Service Worker Kill Switch for Homebaze
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', async () => {
  // Delete ALL caches
  const keys = await caches.keys();
  await Promise.all(keys.map(key => caches.delete(key)));

  // Unregister THIS service worker
  await self.registration.unregister();

  // Release control immediately
  self.clients.claim();
});
