const CACHE_NAME = 'jcenvios-v1';
const STATIC_ASSETS = [
  '/assets/vendor/bootstrap.min.css',
  '/assets/vendor/bootstrap-icons.min.css',
  '/assets/css/style.css',
  '/assets/img/SoloLogoNegroSinFondo.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Solo cachear GET; nunca cachear peticiones autenticadas ni la API.
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/admin/')) return;

  // Activos estáticos: cache-first.
  if (
    url.pathname.startsWith('/assets/') ||
    url.pathname.startsWith('/uploads/')
  ) {
    event.respondWith(
      caches.match(event.request).then((cached) => cached || fetch(event.request))
    );
    return;
  }

  // Páginas HTML: network-first (para que las sesiones siempre sean frescas).
  // Si falla la red Y no hay nada en caché, caches.match resuelve undefined,
  // y respondWith(undefined) rompe con "Failed to convert value to 'Response'"
  // (se ve en el navegador como "Este contenido está bloqueado"). Por eso
  // siempre debe resolver a un Response real, con fallback final incluido.
  event.respondWith(
    fetch(event.request).catch(async () => {
      const cached = await caches.match(event.request);
      return cached || new Response('Sin conexión.', {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'text/plain; charset=utf-8' }
      });
    })
  );
});
