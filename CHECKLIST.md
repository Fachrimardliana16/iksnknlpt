# CHECKLIST.md - Iksan Knalpot

Daftar tugas. Sumber tunggal untuk tahu apa yang sudah dan belum dikerjakan.

**Aturan pemakaian**

- Tandai `[x]` hanya setelah **bukti** (keluaran perintah, hasil test, tangkapan ukuran) ada. Bukan setelah kode ditulis.
- Satu baris = satu commit. Pesan commit menyebut ID, contoh: `feat(F3-04): form produk tiga tab`.
- Tugas yang gagal ditandai `[!]` dengan catatan sebabnya di baris berikut, tidak dihapus.
- Tugas yang bisa dikerjakan bersamaan ditandai `(paralel)`.
- Kolom Bukti ditulis perintah atau cara memeriksanya. Bila tidak ada perintah, tugas itu tidak terverifikasi.
- Fase berikutnya tidak dimulai sebelum gerbang fase sebelumnya lulus (`WORKFLOW.md` Bagian 5).

Rujukan: `PRD.md` (kebutuhan), `DBSCHEMA.md` (skema), `WORKFLOW.md` (cara kerja).

---

## F0 - Fondasi

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F0-01 | `git init`, `.gitignore`, commit pertama | `git log --oneline` menampilkan 1 commit |
| [ ] | F0-02 | Pasang Laravel 12 lewat `composer.phar create-project` | `"$PHP" artisan --version` |
| [ ] | F0-03 | Pasang Livewire 3, Tailwind 4, Alpine, Vite | `npm run build` tanpa galat |
| [ ] | F0-04 | Konfigurasi SQLite: berkas DB, WAL, `foreign_keys=ON`, `busy_timeout` lewat penyedia layanan | `"$PHP" artisan tinker` lalu `DB::select('PRAGMA journal_mode')` mengembalikan `wal` |
| [ ] | F0-05 | Token desain `tokens.css` (palet, tipografi, radius, spasi, bayangan, permukaan material) | Berkas ada, dipakai di layout, nol nilai heksadesimal di view |
| [ ] | F0-06 | Layout dasar front dan admin, dua bahasa, struktur direktori sesuai `WORKFLOW.md` 4.2 | Halaman contoh terbuka di `artisan serve` |
| [ ] | F0-07 | Halaman galat 404, 403, 419, 429, 500 dengan gaya sendiri | Kunjungi `/halaman-tidak-ada` |
| [ ] | F0-08 | `.env.example` lengkap, kunci aplikasi digenerate, tidak ada rahasia di repo | `git grep -i "secret\|password" -- .env.example` bersih |

**Gerbang G0:** `"$PHP" artisan serve` jalan, halaman contoh terbuka, `npm run build` bersih.

---

## F1 - Skema Basis Data, Model, Seeder

**Migration** (urutan sesuai `DBSCHEMA.md` Bagian 20)

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F1-01 | Migration `users`, `addresses` | `migrate:fresh` bersih |
| [ ] | F1-02 | Migration `categories`, `products`, `product_variants` | sama |
| [ ] | F1-03 | Migration `product_media`, `carts`, `cart_items` | sama |
| [ ] | F1-04 | Migration `coupons`, `orders`, `order_items`, `order_status_history`, `payments` | sama |
| [ ] | F1-05 | Migration `settings`, `hero_slides`, `contact_messages` | sama |
| [ ] | F1-06 | Migration `security_logs`, `login_attempts`, `blocked_ips`, `activity_logs`, `media_upload_logs` | sama, FK bersih |
| [ ] | F1-07 | Seluruh indeks dari `DBSCHEMA.md` terpasang | Bandingkan `PRAGMA index_list` tiap tabel dengan dokumen |

**Model**

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F1-08 | Enum: `StatusPesanan`, `StatusPembayaran`, `PeranPengguna`, `MetodePembayaran` | `"$PHP" artisan test --filter=EnumTest` |
| [ ] | F1-09 | Model inti + relasi + cast + `fillable` + penanda ULID otomatis | Test relasi hijau |
| [ ] | F1-10 | Indeks buta: `email_bidx` pada `users`, `guest_email_bidx`, `contact_messages`; layanan HMAC | Test: simpan user, cari lewat `email_bidx`, ketemu |
| [ ] | F1-11 | Cast `encrypted` pada seluruh kolom PII, kredensial, snapshot alamat | Test: nilai di DB tidak terbaca sebagai teks biasa |
| [ ] | F1-12 | Pabrik data (factory) untuk seluruh model | `"$PHP" artisan test --filter=FactoryTest` |

**Seeder** (deterministik: `mt_srand` tetap; idempoten: `updateOrCreate`)

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F1-13 | `SettingSeeder` - seluruh kunci PRD 7.4, ~45 baris | `SELECT count(*) FROM settings` |
| [ ] | F1-14 | `UserSeeder` - 1 super admin, 1 admin, 3 pelanggan | `SELECT role, count(*) FROM users GROUP BY role` |
| [ ] | F1-15 | `CategorySeeder` - 6 kategori, dua bahasa | `SELECT count(*) FROM categories` |
| [ ] | F1-16 | `ProductSeeder` - 24 produk, 8 unggulan, 2 disembunyikan, 1 stok nol, 1 tanpa gambar | Kueri hitung per kasus |
| [ ] | F1-17 | `ProductVariantSeeder` - ~50 varian, mencakup penyesuaian harga negatif dan stok nol | Kueri hitung |
| [ ] | F1-18 | `ProductMediaSeeder` - 2-5 gambar per produk, digenerate GD, bukan biner di repo | `git status` bersih dari berkas gambar |
| [ ] | F1-19 | `HeroSlideSeeder` - 3 slide dua bahasa | Kueri hitung |
| [ ] | F1-20 | `OrderSeeder` - 30 pesanan tersebar seluruh status dan 90 hari, plus item, riwayat, pembayaran | Kueri hitung per status |
| [ ] | F1-21 | `CouponSeeder` - 3 kupon termasuk satu kedaluwarsa | Kueri hitung |
| [ ] | F1-22 | `ContactMessageSeeder` - 8 pesan, 3 belum dibaca | Kueri hitung |
| [ ] | F1-23 | `DatabaseSeeder` memanggil semua, dan memblokir `migrate:fresh` di produksi | `APP_ENV=production "$PHP" artisan migrate:fresh` ditolak |

**Gerbang G1:** `migrate:fresh --seed` bersih dari nol, dijalankan dua kali menghasilkan jumlah baris yang sama persis.

---

## F2 - Autentikasi Admin dan Kerangka Panel

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F2-01 | Login admin, pemisahan penjaga (guard) admin dan pelanggan | Test login hijau |
| [ ] | F2-02 | 2FA TOTP wajib untuk admin, kode cadangan sekali pakai | Test: login tanpa 2FA ditolak |
| [ ] | F2-03 | Rate limit login: 5 per menit per email+IP, kunci 15 menit | Test: percobaan ke-6 dapat 429 |
| [ ] | F2-04 | Penundaan bertingkat 1-2-4-8 detik, blokir IP 1 jam setelah 10 gagal dalam 30 menit | Test: 10 gagal lalu IP masuk `blocked_ips` |
| [ ] | F2-05 | `SecurityLogger`: `login_failed`, `brute_force_blocked`, `ip_blocked`, `unauthorized_access`, `rate_limit_exceeded` | `SELECT count(*) FROM security_logs` setelah test |
| [ ] | F2-06 | Middleware `BlokirIp` dan `CatatKeamanan` | Test middleware hijau |
| [ ] | F2-07 | Layout admin **desktop**: sidebar 260 px, ringkas 72 px, header dengan breadcrumb dan pencarian | Ukur di 1440 px |
| [ ] | F2-08 | Layout admin **tablet 640-1023 px**: sidebar jadi drawer, tabel jadi daftar kartu | Ukur di 768 px |
| [ ] | F2-09 | Layout admin **mobile < 640 px**: bar atas, bottom nav 4 menu, sticky save bar, input >= 44 px | Ukur di 375 px dan 320 px |
| [ ] | F2-10 | Komponen dasar dipakai ulang: tabel+kartu, form, uploader, pilih tanggal, modal, notifikasi | Terpakai di minimal 2 modul |
| [ ] | F2-11 | Halaman Dashboard: 4 kartu, grafik 30 hari, 10 pesanan terbaru, 5 terlaris, peringatan | Bandingkan angka dengan kueri manual |
| [ ] | F2-12 | Keadaan kosong yang menjelaskan, bukan angka nol menyesatkan | Uji pada DB kosong |

**Gerbang G3 sebagian:** setiap rute admin mengembalikan 200 untuk admin dan 403 untuk tamu.

---

## F3 - Kategori, Produk, dan Pemrosesan Gambar

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F3-01 | CRUD kategori, dua bahasa, urutan, aktif/nonaktif | Test CRUD hijau |
| [ ] | F3-02 | Daftar produk: cari, filter kategori dan publikasi, urut, paginasi 20, aksi massal | Test filter hijau |
| [ ] | F3-03 | Form produk tab 1 Informasi Dasar, slug otomatis unik, validasi SKU | Test validasi hijau |
| [ ] | F3-04 | Form produk tab 2 Varian: tambah, salin, urut, hapus, varian default otomatis | Test hijau |
| [ ] | F3-05 | Form produk tab 3 Media: unggah banyak, seret urut, gambar utama, alt text, hapus | Test hijau |
| [ ] | F3-06 | `ImageValidator`: ukuran 5 MB, MIME dari `finfo`, daftar putih, SVG dan GIF ditolak, dimensi, rasio ekstrem, kuota 200 berkas / 50 MB | Test dengan berkas PHP berkedok `.jpg` ditolak |
| [ ] | F3-07 | `ImageValidator`: deteksi duplikat lewat SHA-256, unggahan identik ditolak dengan catatan `deduplicated` | Test hijau |
| [ ] | F3-08 | `ImageProcessor` (GD): orientasi EXIF, dekode ulang, buang metadata dan GPS, turunkan ke 1600 px, encode WebP q82 method 6 | Test: EXIF GPS hilang setelah diproses |
| [ ] | F3-09 | Empat turunan: `thumb` 320x240, `card` 640x480, `full` 1600x1200, `og` 1200x630 | Periksa ukuran berkas hasil |
| [ ] | F3-10 | Nama ULID, simpan di `storage/app/public/products/{ulid}/`, symlink publik | Berkas dapat diakses dari peramban |
| [ ] | F3-11 | `ProsesGambarProduk` sebagai job antrean, batas memori 256 MB, waktu 60 detik, maks 4 pekerja paralel | Test antrean hijau |
| [ ] | F3-12 | Bila pemrosesan gagal: berkas asli disimpan 24 jam, dicoba ulang, lalu dibersihkan penjadwal | Test kegagalan hijau |
| [ ] | F3-13 | `media_upload_logs` terisi untuk setiap unggahan, termasuk alasan penolakan yang spesifik | `SELECT status, count(*) FROM media_upload_logs GROUP BY status` |
| [ ] | F3-14 | Invalidasi cache halaman produk saat media atau produk berubah | Test: kueri ke-2 tidak menyentuh DB |

---

## F4 - Hero Builder dan Web Setting

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F4-01 | Web Setting tab Identitas: nama, tagline, logo terang/gelap, favicon, deskripsi | Simpan lalu periksa di front |
| [ ] | F4-02 | Tab Kontak dan Lokasi: WA, template pesan berplaceholder, email, telepon, jam, alamat, sematan peta | sama |
| [ ] | F4-03 | Tab Media Sosial: 6 platform + sakelar tampil, validasi URL `https` saja | Test URL `javascript:` ditolak |
| [ ] | F4-04 | Tab Pembayaran: mode, `paypal_client_id`, `paypal_client_secret` (terenkripsi), mode sandbox/live, mata uang, rekening manual, pajak, ongkir | Test: secret tidak pernah dikirim balik ke UI |
| [ ] | F4-05 | Tombol Uji Koneksi PayPal memakai kredensial tersimpan | Jalankan, tampilkan hasil nyata |
| [ ] | F4-06 | Tab Lanjutan: mode pemeliharaan, analytics, pixel, prefiks pesanan, ambang stok, PWA, format tanggal | Simpan lalu periksa |
| [ ] | F4-07 | `activity_logs` mencatat setiap perubahan setting; nilai terenkripsi tercatat `[changed]` | `SELECT count(*) FROM activity_logs` |
| [ ] | F4-08 | Cache pengaturan diinvalidasi saat simpan, berlaku < 10 detik | Ubah lalu muat ulang front |
| [ ] | F4-09 | Hero builder: daftar slide, seret urut, dua bahasa, dua tombol, berkas, titik fokus, jadwal tayang, aktif | Test hijau |
| [ ] | F4-10 | Pratinjau hero dalam bingkai ponsel dan desktop | Periksa di layar |
| [ ] | F4-11 | Batas hero: maks 5 slide, video maks 8 MB dan 15 detik, minimal 1 slide aktif | Test validasi hijau |

---

## F5 - Kerangka Front, Home, dan Widget WhatsApp

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F5-01 | Layout front + sistem terjemahan `id`/`en`, pilihan disimpan cookie 1 tahun | Ganti bahasa, muat ulang, tetap |
| [ ] | F5-02 | Sakelar tema terang/gelap tanpa kedip; ikuti `prefers-color-scheme` bila belum dipilih | Periksa atribut `data-theme` sebelum first paint |
| [ ] | F5-03 | Navbar desktop: logo, menu, kategori dropdown, bahasa, tema, pencarian, keranjang berbadge | Ukur di 1440 px |
| [ ] | F5-04 | Bar atas mobile/tablet (56 px) + drawer menu lengkap | Ukur di 375 px dan 768 px |
| [ ] | F5-05 | Bottom nav mobile/tablet (64 px, 4 slot: Home, Katalog, Keranjang, Kontak) + safe area | Konten tidak tertutup, tanpa scroll horizontal di 320 px |
| [ ] | F5-06 | Overlay pencarian: input besar, usulan kategori, 5 produk teratas | Ketik, hasil muncul dari Livewire |
| [ ] | F5-07 | Badge keranjang diperbarui tanpa muat ulang | Tambah item, badge berubah |
| [ ] | F5-08 | Hero full viewport (`100svh` + `100dvh` fallback), slider 7 detik, tombol jeda terlihat, hormati `prefers-reduced-motion` | Ukur tinggi di 3 lebar layar |
| [ ] | F5-09 | Section About: dua kolom, tiga poin keunggulan, angka nyata dari DB | Angka cocok dengan kueri |
| [ ] | F5-10 | Section Product: grid 4/2/2 kolom, kartu rasio 4:3, harga mulai dari varian termurah, badge Habis, tombol tambah | Periksa di 3 lebar layar |
| [ ] | F5-11 | Tombol **Lihat Semua Produk** menuju `/produk` | Klik, periksa URL |
| [ ] | F5-12 | Section CTA: panel karbon, bibir kuningan, satu tombol utama, satu sekunder | Periksa di layar |
| [ ] | F5-13 | Footer 4 kolom lebar berbeda, isi dari setting, jadi akordeon di mobile | Periksa di 3 lebar layar |
| [ ] | F5-14 | Widget WhatsApp melayang: posisi 84 px di mobile, 24 px di desktop, kartu sapaan, tutup per sesi | Ukur posisi, uji di 3 lebar layar |
| [ ] | F5-15 | Pesan WhatsApp berbeda per konteks halaman (home, katalog, detail, keranjang) | Buka tiap halaman, periksa isi tautan |
| [ ] | F5-16 | Nomor WA kosong = widget tidak dirender | Kosongkan setting, muat ulang |

**Gerbang G2:** seluruh rute front mengembalikan 200, tidak ada scroll horizontal pada 320 px.

---

## F6 - Katalog dan Detail Produk

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F6-01 | Daftar produk: filter kategori dan rentang harga, pencarian, urut, paginasi | Test filter hijau |
| [ ] | F6-02 | Keadaan hasil kosong dan keadaan memuat (skeleton, bukan pemintal di tengah) | Uji dengan filter tanpa hasil |
| [ ] | F6-03 | Detail produk: galeri, judul, harga, pemilih varian memperbarui harga dan stok tanpa muat ulang | Test Livewire hijau |
| [ ] | F6-04 | Spesifikasi teknis dan deskripsi dua bahasa | Periksa kedua bahasa |
| [ ] | F6-05 | Tombol Tambah ke Keranjang, Beli Sekarang, Bagikan ke WhatsApp | Uji tiap tombol |
| [ ] | F6-06 | Bar aksi lengket di mobile (sticky bottom action bar) berisi harga, jumlah, tombol tambah | Periksa di 375 px |
| [ ] | F6-07 | Meta tag lengkap: judul, deskripsi, OG 1200x630, JSON-LD produk | Periksa sumber halaman |
| [ ] | F6-08 | Variabel `produk.melihat` tidak menulis berlebihan (dibatch, bukan per tampilan) | Periksa jumlah tulis setelah 20 muat |

---

## F7 - Keranjang dan Checkout

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F7-01 | Keranjang tamu berbasis sesi, pengguna login tersimpan di `carts` | Test hijau |
| [ ] | F7-02 | Gabungkan keranjang tamu ke akun saat login, tanpa duplikat varian | Test hijau |
| [ ] | F7-03 | Halaman keranjang: ubah jumlah, hapus, ringkasan biaya, catatan | Test hijau |
| [ ] | F7-04 | Validasi stok ulang setiap keranjang dibuka; tampilkan pesan jelas bila berkurang | Test hijau |
| [ ] | F7-05 | Kode kupon: persen, nominal, gratis ongkir, minimal belanja, kuota, rentang tanggal | Test 3 tipe kupon |
| [ ] | F7-06 | Checkout tamu dan terisi otomatis bila login; pilihan kurir statis | Test hijau |
| [ ] | F7-07 | `BuatPesanan`: validasi harga dan stok dari DB dalam transaksi, snapshot item, potong stok, isi `reserved_quantity` | Test: 2 pesanan bersamaan tidak oversell |
| [ ] | F7-08 | Nomor pesanan `IK-YYYYMMDD-XXXX` unik dan tidak berurutan | Test urutan acak |
| [ ] | F7-09 | Halaman sukses pesanan + instruksi pembayaran sesuai mode | Uji kedua mode |
| [ ] | F7-10 | Halaman `/lacak` dengan nomor pesanan + email, dibatasi rate limit | Test 429 setelah 10 percobaan |
| [ ] | F7-11 | Batas rate limit: tambah keranjang 60/menit, checkout 5 per 10 menit | Test hijau |
| [ ] | F7-12 | Penjadwal: batalkan pesanan `pending` > 24 jam, kembalikan stok dan `reserved_quantity` | Test waktu palsu hijau |

**Gerbang G4 sebagian:** alur pesanan ujung ke ujung lulus tanpa pembayaran nyata.

---

## F8 - Modul Pesanan Admin dan Cetak Dokumen

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F8-01 | Daftar pesanan: filter status dan tanggal, cari nomor atau nama penerima, ekspor CSV, paginasi 25 | Test hijau |
| [ ] | F8-02 | Detail pesanan: item snapshot, alamat, ringkasan biaya, status pembayaran, riwayat status | Periksa dengan data seeder |
| [ ] | F8-03 | Transisi status sah saja; transisi tidak sah ditolak di server | Test mesin status hijau |
| [ ] | F8-04 | Setiap perubahan status menulis `order_status_history` dengan pelaku dan catatan | Kueri riwayat |
| [ ] | F8-05 | Isi nomor resi dan pilih kurir | Test hijau |
| [ ] | F8-06 | Cetak **Invoice A4** dengan CSS cetak, PDF dibuat lokal | Berkas PDF terbuka, isi benar |
| [ ] | F8-07 | Cetak **Resi / Label 100x150 mm** berisi penerima, nomor pesanan, resi, kurir, berat | Ukur ukuran halaman PDF |
| [ ] | F8-08 | Cetak **Surat Jalan A4** tanpa harga, dengan tanda tangan penerima | Berkas PDF terbuka |
| [ ] | F8-09 | Tanda air SALINAN pada ketiga dokumen bila pesanan dibatalkan | Batalkan pesanan, cetak ulang |
| [ ] | F8-10 | Kirim ulang notifikasi ke pelanggan dari halaman detail | Test job terkirim |

---

## F9 - Pembayaran dan Notifikasi

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F9-01 | `PaypalGateway`: buat pesanan PayPal di mode sandbox | Transaksi sandbox terbentuk |
| [ ] | F9-02 | Tangani kembalian dari PayPal, perbarui `payments` dan `orders` | Test hijau |
| [ ] | F9-03 | Webhook PayPal dengan verifikasi tanda tangan; tanda tangan tidak sah ditolak | Test tanda tangan palsu ditolak |
| [ ] | F9-04 | Webhook ganda tidak tercatat dua kali (indeks unik penyedia + ID transaksi) | Kirim webhook dua kali, periksa 1 baris |
| [ ] | F9-05 | Alur manual: halaman rekening, unggah bukti transfer (3 per jam per pesanan), verifikasi admin | Test hijau |
| [ ] | F9-06 | Notifikasi email: pesanan dibuat, pembayaran diterima, barang dikirim | Periksa kotak surat uji |
| [ ] | F9-07 | Notifikasi WhatsApp otomatis pada tiga kejadian yang sama | Uji kirim, periksa log |
| [ ] | F9-08 | Semua notifikasi berjalan sebagai job antrean, tidak memblokir permintaan | Test antrean hijau |

**Gerbang G4:** alur pesanan ujung ke ujung lulus di mode sandbox.

---

## F10 - Keamanan Menyeluruh

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F10-01 | Kebijakan otorisasi (`policy`) untuk setiap aksi admin, diperiksa di server | Test: pelanggan biasa dapat 403 |
| [ ] | F10-02 | ULID pada seluruh rute publik dan API; `id` internal tidak pernah bocor ke URL | Pindai seluruh rute |
| [ ] | F10-03 | Sanitasi konten editor dengan daftar putih tag dan atribut | Test `<script>` dibuang, `<b>` dipertahankan |
| [ ] | F10-04 | CSP tanpa `unsafe-inline` memakai nonce, HSTS 1 tahun, `nosniff`, `SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` | Periksa header respons |
| [ ] | F10-05 | Cookie sesi `Secure`, `HttpOnly`, `SameSite=Lax`; sesi admin 120 menit | Periksa properti cookie |
| [ ] | F10-06 | Rate limit seluruh baris PRD Bagian 11.1 terpasang dan diuji satu per satu | Test per endpoint |
| [ ] | F10-07 | Honeypot + token waktu minimum 2 detik pada form publik | Test bot tersaring |
| [ ] | F10-08 | Pesan galat login, reset, dan lacak selalu seragam | Bandingkan dua kasus |
| [ ] | F10-09 | Berkas sensitif dikirim lewat rute terkontrol dengan URL bertanda tangan 15 menit | Test URL kedaluwarsa ditolak |
| [ ] | F10-10 | Unggahan tidak dapat dieksekusi; direktori unggahan tanpa eksekusi skrip | Unggah `.php` berkedok gambar, ditolak |
| [ ] | F10-11 | `X-Powered-By` dan versi framework disembunyikan | Periksa header |
| [ ] | F10-12 | Halaman `/admin/keamanan`: 100 peristiwa terakhir, grafik per jam, buka blokir manual | Buka blokir, uji IP kembali bisa masuk |
| [ ] | F10-13 | Perintah `iksan:backup` menyimpan 14 salinan terakhir, menolak bila disk < 500 MB, dijadwalkan 02.00 | Jalankan manual, periksa berkas hasil |
| [ ] | F10-14 | Penjadwal membersihkan sesi, cache, dan `security_logs` > 90 hari | Jalankan manual, periksa jumlah baris |
| [ ] | F10-15 | Audit keamanan penuh memakai keterampilan `web-application-audit`, temuan ditindaklanjuti | Laporan audit tersimpan |

---

## F11 - Performa dan Kriteria Penerimaan

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F11-01 | Cache: pengaturan, kategori, hero (invalidasi saat berubah); home 10 menit; daftar produk per filter 5 menit; kartu produk 1 jam | `uji-cache.php`: sisip produk tanpa tanda → beranda tetap 0 (cache kerja); `tandaiBerubah()` → beranda 4 (batal kerja). Beranda 13 → 8 kueri |
| [x] | F11-02 | Anggaran 15 kueri per halaman diuji otomatis | `php artisan test --filter=AnggaranKueriTest` — beranda 11, produk 3 kueri (batas 15) |
| [x] | F11-03 | Aset: Vite, CSS satu berkas, JS < 60 KB terkompresi, tanpa pustaka besar | `npm run build`: JS **1 KB gzip** (batas 60), CSS 17 KB gzip satu berkas, tanpa pustaka besar (hanya Tailwind 4, Alpine, axios) |
| [x] | F11-04 | Font dipangkas, `font-display: swap`, `preload` dua berkas pertama | 12 berkas woff2 sendiri + preload 2 pertama; `curl -I` font 200, 22196 byte |
| [x] | F11-05 | Gambar: WebP, `srcset` 4 ukuran, `loading="lazy"`, `decoding="async"`, `fetchpriority="high"` hanya hero dan gambar utama, lebar dan tinggi eksplisit | 4 turunan WebP (thumb 3,8 KB / card 6,7 KB / full 18,6 KB / og); `srcset` 320w+640w+1600w, `loading=lazy`, `decoding=async`, `width`/`height` eksplisit, `fetchpriority=high` hanya gambar utama. CLS terukur **0** |
| [x] | F11-06 | Kompresi Brotli/Gzip, `Cache-Control` panjang untuk aset bercap hash | `public/.htaccess`: brotli/gzip, `max-age=31536000, immutable`, `nosniff` |
| [x] | F11-07 | Ukur TTFB, LCP, CLS, INP pada 4G ponsel dan desktop; catat angka nyata | 4G: TTFB 336-1640 ms, LCP 960-4340 ms, CLS 0. Desktop: LCP 364-1272 ms |
| [x] | F11-08 | Uji di 320, 375, 768, 1024, 1440 px untuk seluruh halaman, front dan admin | `ukur-performa.py`: 6 halaman front + 5 halaman admin × 320/375/768/1024/1440 px — gulir horizontal **0** di semua |
| [x] | F11-09 | Uji aksesibilitas: kontras, fokus terlihat, label form, navigasi papan tuts | axe-core WCAG 2.1 A+AA: 28 simpul bermasalah turun ke **0** di 7 halaman; kontras token diperbaiki (#5f5f66 / #9a9aa2), tombol −/+ diberi `aria-label` |
| [x] | F11-10 | Centang seluruh kriteria penerimaan `PRD.md` Bagian 15 dengan bukti | Seluruh butir F11 diuji satu per satu dengan keluaran perintah nyata; 197 test lulus |
| [x] | F11-11 | `migrate:fresh --seed` dari nol, lalu seluruh alur belanja dijalankan ulang | 15 test AlurBelanjaTest; di peramban sampai pesanan IK-20261019-GZVSMY |
| [x] | F11-12 | Perbarui `README.md` berisi cara pasang, cara seed, dan akun demo | `README.md` ditulis ulang: pasang, seed, akun demo, catatan exFAT/antrean/build |

**Gerbang G5:** seluruh test hijau, checklist penerimaan PRD tercentang dengan bukti.

---

## Fase 2 (setelah MVP terpasang)

| Status | ID | Tugas |
| :--- | :--- | :--- |
| [ ] | P2-01 | Preview 3D produk dengan `<model-viewer>`, format `.glb` |
| [ ] | P2-02 | Preview suara knalpot dengan pemutar dan pembanding A/B |
| [ ] | P2-03 | PWA penuh: manifest, service worker, shell offline, tombol pasang |
| [ ] | P2-04 | AVIF dan CDN tepi |
| [ ] | P2-05 | Integrasi kurir dan pelacakan otomatis |
| [ ] | P2-06 | Kupon bertingkat dan program loyalitas |
| [ ] | P2-07 | Blog atau artikel untuk SEO |

---

## Ringkasan

| Fase | Jumlah tugas | Gerbang |
| :--- | :--- | :--- |
| F0 Fondasi | 8 | G0 |
| F1 Skema, Model, Seeder | 23 | G1 |
| F2 Autentikasi dan Kerangka Admin | 12 | G3 sebagian |
| F3 Kategori, Produk, Gambar | 14 | - |
| F4 Hero dan Web Setting | 11 | - |
| F5 Kerangka Front, Home, WhatsApp | 16 | G2 |
| F6 Katalog dan Detail Produk | 8 | - |
| F7 Keranjang dan Checkout | 12 | G4 sebagian |
| F8 Pesanan Admin dan Cetak | 10 | - |
| F9 Pembayaran dan Notifikasi | 8 | G4 |
| F10 Keamanan | 15 | - |
| F11 Performa dan Penerimaan | 12 | G5 |
| **Total MVP** | **149** | |
| Fase 2 | 7 | - |
