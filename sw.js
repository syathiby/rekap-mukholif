/**
 * AsuhTrack Service Worker
 * Strategi: Cache-first untuk aset statis, Network-first untuk halaman PHP
 * Versi cache: naikkan angka ini setiap kali ada perubahan besar
 */

const CACHE_VERSION = 'asuhtrack-v2';
const STATIC_CACHE  = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;

// Aset penting yang wajib di-cache saat instalasi SW
const STATIC_ASSETS = [
  './offline.php',
  './assets/img/logo_aplikasi.png',
  './assets/img/logo_favicon.png',
];

// ─────────────────────────────────────────────
// INSTALL: Pre-cache aset penting
// ─────────────────────────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then(cache => {
      // Gunakan { cache: 'reload' } agar tidak ambil dari browser cache yang lama
      const fetchPromises = STATIC_ASSETS.map(url =>
        fetch(url, { cache: 'reload' })
          .then(response => {
            if (response.ok) return cache.put(url, response);
          })
          .catch(err => console.warn('[SW] Gagal cache:', url, err))
      );
      return Promise.all(fetchPromises);
    }).then(() => self.skipWaiting())
  );
});

// ─────────────────────────────────────────────
// ACTIVATE: Hapus cache versi lama
// ─────────────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(key => key.startsWith('asuhtrack-') && key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
          .map(key => {
            console.log('[SW] Menghapus cache lama:', key);
            return caches.delete(key);
          })
      )
    ).then(() => self.clients.claim())
  );
});

// ─────────────────────────────────────────────
// MESSAGE: Tangani pesan dari halaman (mis: SKIP_WAITING)
// ─────────────────────────────────────────────
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

// ─────────────────────────────────────────────
// FETCH: Strategi request
// ─────────────────────────────────────────────
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // 1. Abaikan request non-GET
  if (request.method !== 'GET') return;

  // 2. Abaikan protocol selain http/https (chrome-extension://, dll)
  if (!url.protocol.startsWith('http')) return;

  // 3. Abaikan WebSocket
  if (request.headers.get('Upgrade') === 'websocket') return;

  // 4. Cek apakah request ke origin yang sama dengan SW
  const isSameOrigin = url.origin === self.location.origin;

  if (!isSameOrigin) {
    // Request ke CDN/font eksternal — network saja, tanpa caching
    // (tidak pakai respondWith agar browser handle normal)
    return;
  }

  // Tentukan strategi berdasarkan tipe file
  const pathname = url.pathname;
  const isStaticAsset = /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)(\?.*)?$/.test(pathname);
  const isPhpPage     = pathname.endsWith('.php') || pathname.endsWith('/') || !pathname.includes('.');

  if (isStaticAsset) {
    // CACHE FIRST: Aset statis — tampilkan dari cache, fetch di background jika belum ada
    event.respondWith(cacheFirst(request));
  } else if (isPhpPage) {
    // NETWORK FIRST: Halaman PHP — selalu coba ambil dari server dulu
    event.respondWith(networkFirst(request));
  }
  // Untuk tipe lain, biarkan browser handle normal (tidak pakai respondWith)
});

// ─────────────────────────────────────────────
// STRATEGI: Cache First (untuk aset statis)
// ─────────────────────────────────────────────
async function cacheFirst(request) {
  // Coba dari cache dulu
  const cached = await caches.match(request);
  if (cached) {
    // Update cache di background (stale-while-revalidate)
    fetchAndUpdate(request, STATIC_CACHE);
    return cached;
  }

  // Belum ada di cache — fetch dari network dan simpan
  return fetchAndUpdate(request, STATIC_CACHE);
}

async function fetchAndUpdate(request, cacheName) {
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    throw err;
  }
}

// ─────────────────────────────────────────────
// STRATEGI: Network First (untuk halaman PHP)
// ─────────────────────────────────────────────
async function networkFirst(request) {
  try {
    // Selalu coba network dulu dengan timeout 10 detik
    const networkPromise = fetch(request, { credentials: 'same-origin' });
    const timeoutPromise = new Promise((_, reject) =>
      setTimeout(() => reject(new Error('Network timeout')), 10000)
    );

    const response = await Promise.race([networkPromise, timeoutPromise]);

    if (response && response.ok) {
      // Simpan ke dynamic cache untuk fallback offline
      const cache = await caches.open(DYNAMIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;

  } catch (err) {
    // Network gagal atau timeout — coba dari cache
    const cached = await caches.match(request);
    if (cached) {
      console.log('[SW] Network gagal, menggunakan cache untuk:', request.url);
      return cached;
    }

    const offlinePage = await caches.match('./offline.php');
    if (offlinePage) return offlinePage;

    // Last resort
    return new Response(
      `<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Offline</title></head>
       <body style="font-family:sans-serif;text-align:center;padding:3rem">
       <h2>📶 Tidak Ada Koneksi</h2>
       <p>Periksa koneksi internet Anda dan coba lagi.</p>
       <button onclick="window.location.reload()">Coba Lagi</button>
       </body></html>`,
      { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
    );
  }
}
