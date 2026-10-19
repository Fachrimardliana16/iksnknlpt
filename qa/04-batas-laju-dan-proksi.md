# 04 — Batas Laju, Load Balancer, dan Keamanan Operasional

Tanggal: 20 Oktober 2026
Pelengkap `qa/03-laporan-audit-menyeluruh.md`.
Harness: `tools/audit-batas-laju.php` (5/5 lulus) · `BatasLajuProksiTest` (5 test, 14 asersi).

## 1. Batas laju — apa yang benar-benar berjalan

| Aksi | Kunci | Batas | Status |
| :--- | :--- | :--- | :--- |
| Login admin | email + IP | 5/menit, kunci 15 menit | 429 + halaman tunggu + `security_logs` |
| Login pelanggan | email + IP | 5/menit, 20/jam | konfigurasi siap; rute login pelanggan belum ada |
| Daftar akun | IP | 5/jam | konfigurasi siap; rute belum ada |
| Reset sandi | email | 3/jam | konfigurasi siap; rute belum ada |
| Form kontak | IP | 3/jam | di dalam komponen → pesan di halaman |
| Tambah keranjang | sesi | 60/menit | di dalam komponen |
| Checkout | IP + sesi | 5/10 menit | di dalam komponen |
| Lacak pesanan | IP | 10/menit | **middleware → 429** + `Retry-After` |
| Pencarian | IP | 30/menit | **middleware → 429** |
| Unggah bukti bayar | pesanan | 3/jam | `ValidationException` di halaman |
| Bukti bayar (unduh) | IP | 30/menit | throttle rute |

Bukti dari eksekusi:

- `/lacak` dijawab **429** sejak permintaan ke-11, dengan header `Retry-After`;
- `/produk?q=…` dijawab **429** setelah 30 pencarian (5 dari 35 dijawab 429);
- kunci limiter berbentuk `iksanlaju:track_order:<sha256>` — **sidik jari**, bukan
  `127.0.0.1`. Diperiksa langsung di tabel `cache`: 0 kunci memuat alamat mentah.

**Penyimpanan.** `CACHE_STORE=database` (tanpa Redis). `BatasLajuProksiTest` membuktikan
`RateLimiter::hit/attempts/tooManyAttempts/availableIn` benar-benar menyimpan hitungan — limiter
yang tidak menyimpan apa pun berarti tidak ada batas sama sekali.

**Catatan jujur tentang uji penyamaran alamat.** Di mesin ini seluruh permintaan datang dari
`127.0.0.1`, jadi "apakah `X-Forwarded-For` palsu mengelak dari batas" **tidak dapat dijawab
lewat HTTP di sini**. Yang diuji adalah intinya, di tingkat permintaan:
XFF dari alamat **non-proksi diabaikan** (`8.8.8.8` tetap `8.8.8.8`), sedangkan dari proksi
tepercaya diteruskan (`127.0.0.1` + XFF `9.9.9.9` → `9.9.9.9`).

## 2. Load balancer / proksi terbalik

Konfigurasi: `bootstrap/app.php` membaca `IKK_TRUSTED_PROXIES`. Bila kosong **dan**
`APP_ENV=local`, longgarnya `127.0.0.1`. Di produksi tidak ada nilai longgar otomatis.

| Skenario | Akibat bila salah | Kondisi sekarang |
| :--- | :--- | :--- |
| `trustProxies('*')` | Siapa pun memalsukan `X-Forwarded-For`, mengelak dari batas laju + blokir | **tidak dipakai**; hanya `local` yang longgar |
| Tanpa trustProxies di balik LB | Semua pengunjung terlihat satu IP; satu penyerang memblokir seluruh toko | **wajib isi** `IKK_TRUSTED_PROXIES` saat dipasang di balik LB |

Hal lain yang terpengaruh dan sudah benar:

- **HSTS & cookie `Secure`** mengikuti `$request->isSecure()`. Di balik LB yang memutus TLS,
  header `X-Forwarded-Proto` harus tepercaya — dan itu ikut diatur `IKK_TRUSTED_PROXIES`.
  Tanpa itu, cookie sesi tidak `Secure` dan HSTS tidak dikirim.
- **Sesi**: `SESSION_DRIVER=database`, umur 120 menit, `SameSite=Lax`, `HttpOnly`.
  Karena sesi disimpan di basis data (bukan berkas), beberapa proses PHP di belakang LB
  **tidak** memerlukan sticky session.
- **Antrean**: `QUEUE_CONNECTION=database` — job notifikasi dan pembayaran tidak terikat ke
  proses yang menerima permintaan, jadi dapat dijalankan worker terpisah.
- **Idempotensi pembayaran**: webhook PayPal dicatat per `provider + provider_transaction_id`
  dengan indeks unik. Pengiriman ganda dari LB/PayPal tidak menghasilkan dua baris.

## 3. Keamanan lain yang diperiksa

| Hal | Hasil |
| :--- | :--- |
| CSP tanpa `unsafe-inline` di `script-src` | ya; `style-src` masih memuatnya (Tailwind menulis atribut `style`) |
| Galat konsol / CSP di 8 halaman | **0** |
| Kebocoran pesan pengecualian | **temuan SK-01, diperbaiki** — 3 tempat sekarang mencatat ke log, bukan ke layar |
| `X-Forwarded-Host` palsu | tidak dipakai untuk tautan kanalik/pengalihan |
| Berkas sensitif | disk privat + URL bertanda tangan 15 menit; tanpa tanda tangan → 403 |
| Unggahan | tipe dideteksi dari isi; `.php` berkedok `.jpg` ditolak; 0 berkas dapat dieksekusi di direktori unggahan |
| Rahasia di repositori | 0; `.env` tidak terlacak git |
| SQL mentah / `eval` | 0 |
| `composer audit` / `npm audit` | 0 / 0 |

## 4. Yang harus dikerjakan saat pemasangan di server

Tidak dapat dikerjakan dari sisi aplikasi, dan bila dilewati akan menggugurkan jaminan di atas:

1. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain-anda`.
2. `IKK_TRUSTED_PROXIES` diisi alamat proksi/LB (atau rentang privat LB), **bukan** `*`.
3. `SESSION_SECURE_COOKIE=true`; TLS diputus di LB dengan `X-Forwarded-Proto` diteruskan.
4. `expose_php = Off` di php.ini (sudah disetel di mesin ini; build PHP baru harus diulang).
5. Penjadwal: satu entri cron `* * * * * php artisan schedule:run` — atau di Windows Task
   Scheduler menjalankan `php artisan schedule:work`.
6. Worker antrean: `php artisan queue:work --tries=3` dijalankan sebagai layanan.
7. `php artisan iksan:backup` sudah terjadwal 02.00; pastikan folder `storage/app/backup`
   berada di disk yang dicadangkan.
8. Bila memakai lebih dari satu proses PHP: sesi & cache sudah di basis data, tetapi
   **rate limiter** ikut di cache itu — pastikan semua proses menunjuk basis data yang sama.
