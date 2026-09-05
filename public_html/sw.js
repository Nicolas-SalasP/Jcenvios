// __BUILD_ID__ lo reemplaza el pipeline de deploy (ver deploy-production.yml,
// paso "Forzar actualización del Service Worker") con el SHA del commit antes
// de subir public_html/. Sin esto CACHE_NAME quedaba fijo en 'jcenvios-v1'
// para siempre: como el propio archivo sw.js nunca cambiaba de bytes entre
// despliegues, el navegador jamás detectaba una versión nueva del worker, y
// STATIC_ASSETS (bootstrap, style.css, el logo) quedaban cacheados de por
// vida para cualquiera que hubiera instalado la PWA — ni Ctrl+F5 lo arregla,
// porque el service worker vive fuera del ciclo normal de caché del navegador.
// Al cambiar CACHE_NAME en cada deploy, el archivo cambia de bytes, el
// navegador SÍ detecta la actualización, y el 'activate' de abajo borra el
// caché con el nombre viejo. En local (placeholder sin reemplazar) sigue
// funcionando igual, solo que sin ese forzado entre corridas.
const CACHE_NAME = 'jcenvios-__BUILD_ID__';
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
