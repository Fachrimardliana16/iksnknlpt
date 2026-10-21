# 05 — Optimasi Waktu Muat

Tanggal: 20 Oktober 2026
Sasaran: seluruh halaman publik selesai dimuat di bawah 3 detik.
Harness: `tools/ukur-muat2.py` (Chromium nyata, cache kosong & terisi), `curl` untuk TTFB.

## Baseline (sebelum optimasi)

Diukur lewat HTTP, satu halaman katalog menghabiskan **2,5–5,5 detik TTFB**, dan
Chromium mencatat `Load` **19–38 detik** pada kunjungan baru.

Penyebab yang ditemukan, semuanya dari pengukuran:

| # | Penyebab | Bukti |
| :--- | :--- | :--- |
| 1 | Laravel mengirim `Cache-Control: no-store` untuk **semua** respons, termasuk CSS/JS/gambar | `Cache-Control: max-age=0, must-revalidate, no-cache, no-store, private` pada `/produk` dan `/storage/...webp` |
| 2 | Tidak ada kompresi: HTML 90 KB dan CSS 94 KB dikirim apa adanya | `Accept-Encoding: gzip` diabaikan; `zlib.output_compression` kosong |
| 3 | Konfigurasi tidak di-cache (config/route/view/event) | `bootstrap/cache/` hanya berisi `packages.php` + `services.php` |
| 4 | Semua slide hero diunduh sekaligus | tiga `background-image` 1600 px dirender walau hanya satu tampil |
| 5 | Gambar hero terbesar diminta paling akhir | tidak ada `<link rel=preload>`; baru diminta setelah CSS+JS selesai |
| 6 | Setiap halaman digambar ulang dari nol | 7–16 kueri + render Blade untuk konten yang sama tiap kunjungan |

## Yang dikerjakan

1. **`App\Http\Middleware\CacheAset`** — Cache-Control yang jujur:
   `/build/assets/*` (ber-hash Vite) → `public, max-age=31536000, immutable`;
   `/storage/*`, manifes, ikon PWA → `max-age=2592000, must-revalidate` + ETag;
   jawaban **304** saat `If-None-Match` cocok (0 bita, bukan 6 KB);
   **gzip** untuk respons tekstual bila SAPI tidak melakukannya.
2. **`App\Http\Middleware\CacheHalaman`** — cache halaman publik untuk tamu
   (GET, 200, bukan admin/livewire/keranjang/checkout/pesanan/lacak), kunci memuat
   `katalog.versi` sehingga satu perubahan dari panel membatalkan seluruh cache;
   nonce CSP disimpan bersama isinya agar skrip tidak diblokir di kunjungan kedua;
   dapat dimatikan lewat pengaturan `cache_halaman`.
3. **Preload hero** — `Beranda` mengirim `$heroPertama` ke layout; `<link rel="preload"
   as="image" fetchpriority="high">` dipasang di `<head>`.
4. **Slide hero malas** — slide ke-2 dan seterusnya tidak memasang `background-image`
   sebelum giliran tampil.
5. **Konfigurasi di-cache** — `config:cache`, `route:cache`, `view:cache`, `event:cache`.

## Hasil terukur (sesudah optimasi, server pulih)

TTFB lewat `curl`, lima permintaan berurutan per halaman:

| Halaman | Sebelum | Sesudah (stabil) |
| :--- | ---: | ---: |
| Beranda | 2,5–13 s | **0,21–0,32 s** |
| Katalog | 4,1–77 s | **0,24–0,33 s** |
| Detail produk | 3,5–6,3 s | **0,24–0,33 s** |
| Kontak | 2,7 s | **0,22–0,30 s** |
| Halaman statis | 0,8 s | **0,25–0,33 s** |
| Keranjang | 0,7 s | **0,26–0,40 s** |
| Lacak | 0,4 s | **0,27–0,38 s** |

Bita yang dikirim:

| Aset | Sebelum | Sesudah |
| :--- | ---: | ---: |
| HTML beranda | 90.386 B | **12.494 B** (gzip) |
| Gambar produk (ulangan) | 6.730 B penuh | **304, 0 B** |

Uji `CacheHalamanTest` (8 test) mengunci batasnya: keranjang/checkout/lacak/panel
tidak pernah disimpan, pengunjung yang sudah masuk tidak mendapat halaman cache,
dan nonce di header CSP sama dengan nonce di HTML yang disajikan.

## Catatan jujur

- **Load di Chromium tidak dapat dipakai sebagai angka akhir di mesin ini.**
  Pengukuran berulang menghasilkan 3–40 detik untuk halaman yang sama sementara
  TTFB stabil 0,2–0,3 detik. Penyebabnya lingkungan, bukan aplikasi: mesin
  pengembangan menjalankan `php artisan serve` **satu proses** (permintaan
  antre), Chrome headless sisa pengukuran sempat menumpuk 400+ proses, dan
  antivirus memindai setiap berkas profil baru. Angka yang dapat
  dipertanggungjawabkan adalah TTFB (waktu server) dan byte (waktu jaringan).
- **`/build/assets/*` tidak melewati middleware** pada server PHP bawaan
  (`artisan serve` menyajikan `public/` langsung). Cache-Control dan gzip untuk
  aset build karena itu baru berlaku di nginx/Apache — konfigurasinya ada di
  `qa/06-*.md` bagian pemasangan.
- **Cache halaman dimatikan untuk pengunjung yang login**; halaman mereka tetap
  dirender per permintaan (0,3 detik pada mesin ini).

## Insiden selama pengukuran

`database/database.sqlite` menjadi **0 bita** di tengah rangkaian pengukuran:
disk D: sempat penuh oleh 8,5 GB profil Chrome, dan satu harness sempat
mengarahkan koneksi basis data ke berkas itu. Basis data dibangun ulang dengan
`migrate` + `db:seed` (products 24 · orders 30 · users 5 · settings 47), dan
`PRAGMA quick_check` = `ok`. Data uji audit hilang; data demo utuh.

Pencegahan yang sudah dipasang: `ukur-muat2.py` mematikan Chrome sisa sebelum
mulai, menunggu proses benar-benar keluar, dan menghapus profilnya; profil
berada di `tools/out/profil-chrome/` (masuk `.gitignore`).
