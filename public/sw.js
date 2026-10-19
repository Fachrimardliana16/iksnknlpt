/*
 * Service worker Iksan Knalpot (Fase 2: P2-03).
 *
 * Tiga aturan yang menentukan bentuk berkas ini, dan ketiganya dipilih supaya
 * tidak pernah menyajikan data yang salah:
 *
 * 1. HANYA aset statis yang disimpan (/build/, gambar produk, ikon). Halaman
 *    HTML, /keranjang, /checkout, dan /admin TIDAK pernah disimpan: keranjang
 *    berbeda untuk setiap pengunjung, dan menyajikannya dari cache berarti
 *    pembeli melihat isi keranjang orang lain atau keranjang yang sudah basi.
 * 2. Permintaan selain GET dilewatkan apa adanya. Menyimpan POST berarti
 *    mengulang pesanan.
 * 3. Saat jaringan gagal, halaman yang tidak tersimpan dijawab dengan halaman
 *    luring sederhana, bukan galat peramban.
 */

const VERSI = 'iksan-v1';
const SHELL_LURING = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSI).then((cache) => cache.addAll([SHELL_LURING])).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    // Versi lama dibuang supaya aset yang sudah tidak dipakai tidak menumpuk.
    event.waitUntil(
        caches.keys()
            .then((kunci) => Promise.all(kunci.filter((k) => k !== VERSI).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

/** Aset yang boleh disimpan: build, gambar produk, ikon, dan manifes. */
function bolehDisimpan(url) {
    return /\/(build|storage\/products|storage\/hero)\//.test(url.pathname)
        || /\.(webp|jpg|jpeg|png|svg|woff2?|css|js)$/i.test(url.pathname)
        || url.pathname === '/manifest.webmanifest';
}

self.addEventListener('fetch', (event) => {
    const permintaan = event.request;

    if (permintaan.method !== 'GET') {
        return;
    }

    const url = new URL(permintaan.url);

    // Asal lain (PayPal, CDN) tidak disentuh.
    if (url.origin !== self.location.origin) {
        return;
    }

    if (bolehDisimpan(url)) {
        // Cache-first untuk aset: namanya memuat hash isi, jadi isinya tidak
        // pernah berubah selama namanya sama.
        event.respondWith(
            caches.match(permintaan).then((tersimpan) => {
                if (tersimpan) {
                    return tersimpan;
                }

                return fetch(permintaan).then((respons) => {
                    if (respons.ok) {
                        const salinan = respons.clone();
                        caches.open(VERSI).then((cache) => cache.put(permintaan, salinan));
                    }

                    return respons;
                });
            })
        );

        return;
    }

    // Halaman: selalu ke jaringan. Kalau jaringan mati, halaman luring.
    event.respondWith(
        fetch(permintaan).catch(() => caches.match(SHELL_LURING).then((r) => r || Response.error()))
    );
});
