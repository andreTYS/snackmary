/* Service worker de Snack Mary.
 * - Archivos estáticos (css, js, imágenes): se guardan en caché para cargar rápido.
 * - Páginas: siempre desde el servidor (los datos deben estar al día);
 *   si no hay internet se muestra offline.html.
 */
const VERSION = 'snackmary-v3';
const ESTATICOS = [
  '/offline.html',
  '/assets/css/styles.css',
  '/assets/css/tablet.css?v=3',
  '/assets/js/jquery-3.6.0.min.js',
  '/assets/js/bootstrap.bundle.min.js',
  '/assets/js/all.min.js',
  '/assets/img/icon-192.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(ESTATICOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((claves) => Promise.all(claves.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  if (url.pathname.startsWith('/assets/')) {
    e.respondWith(
      caches.match(req).then((r) => r || fetch(req).then((resp) => {
        if (resp.ok) {
          const copia = resp.clone();
          caches.open(VERSION).then((c) => c.put(req, copia));
        }
        return resp;
      }))
    );
    return;
  }

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
  }
});
