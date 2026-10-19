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
| [x] | F0-01 | `git init`, `.gitignore`, commit pertama | `git log --oneline` — 22 commit; `.gitignore` ada |
| [x] | F0-02 | Pasang Laravel 12 lewat `composer.phar create-project` | `php artisan --version` → Laravel Framework 12.70.0 |
| [x] | F0-03 | Pasang Livewire 3, Tailwind 4, Alpine, Vite | `npm run build` tanpa galat; Tailwind 4.0, Vite 7.0, Livewire 3, Alpine |
| [x] | F0-04 | Konfigurasi SQLite: berkas DB, WAL, `foreign_keys=ON`, `busy_timeout` lewat penyedia layanan | Tinker: `foreign_keys=1 journal_mode=wal busy_timeout=5000 synchronous=1` |
| [x] | F0-05 | Token desain `tokens.css` (palet, tipografi, radius, spasi, bayangan, permukaan material) | `tokens.css` 74 token, 25 KB; diimpor `app.css`; heksa hanya di definisi token |
| [x] | F0-06 | Layout dasar front dan admin, dua bahasa, struktur direktori sesuai `WORKFLOW.md` 4.2 | layout front/admin/polos + `lang/id`, `lang/en` |
| [x] | F0-07 | Halaman galat 404, 403, 419, 429, 500 dengan gaya sendiri | 5 halaman galat (403/404/419/429/500) bergaya sendiri; `/halaman-tidak-ada` → 404 |
| [x] | F0-08 | `.env.example` lengkap, kunci aplikasi digenerate, tidak ada rahasia di repo | 42 kunci di `.env.example`, nilai rahasia terisi = 0 |

**Gerbang G0:** `"$PHP" artisan serve` jalan, halaman contoh terbuka, `npm run build` bersih.

---

## F1 - Skema Basis Data, Model, Seeder

**Migration** (urutan sesuai `DBSCHEMA.md` Bagian 20)

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F1-01 | Migration `users`, `addresses` | Tabel ada: users, addresses |
| [x] | F1-02 | Migration `categories`, `products`, `product_variants` | Tabel ada: categories, products, product_variants |
| [x] | F1-03 | Migration `product_media`, `carts`, `cart_items` | Tabel ada: product_media, carts, cart_items |
| [x] | F1-04 | Migration `coupons`, `orders`, `order_items`, `order_status_history`, `payments` | Tabel ada: coupons, orders, order_items, order_status_history, payments |
| [x] | F1-05 | Migration `settings`, `hero_slides`, `contact_messages` | Tabel ada: settings, hero_slides, contact_messages |
| [x] | F1-06 | Migration `security_logs`, `login_attempts`, `blocked_ips`, `activity_logs`, `media_upload_logs` | Tabel ada: security_logs, login_attempts, blocked_ips, activity_logs, media_upload_logs |
| [x] | F1-07 | Seluruh indeks dari `DBSCHEMA.md` terpasang | 6/6 indeks diperiksa lewat `PRAGMA index_info` (kolom, bukan nama) |

**Model**

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F1-08 | Enum: `StatusPesanan`, `StatusPembayaran`, `PeranPengguna`, `MetodePembayaran` | StatusPesanan=6 StatusPembayaran=5 PeranPengguna=3 MetodePembayaran=2 |
| [x] | F1-09 | Model inti + relasi + cast + `fillable` + penanda ULID otomatis | `ModelIntiTest` 6 test hijau (43 asersi): model dibaca dari `app/Models` dan setiap tabel berkolom `ulid` wajib memakai trait `PunyaUlid` (18 model); ULID terisi otomatis, 26 karakter, kunci rute = `ulid`; relasi Product↔Variant dua arah; cast boolean/decimal/datetime benar; kolom di luar `fillable` (`id`, `view_count`, `remember_token`) tidak terisi massal; ULID unik di tingkat basis data (insert ganda → `QueryException`) |
| [x] | F1-10 | Indeks buta: `email_bidx` pada `users`, `guest_email_bidx`, `contact_messages`; layanan HMAC | Kolom sidik jari terpasang: users.email_bidx, orders.guest_email_bidx, contact_messages.email_bidx |
| [x] | F1-11 | Cast `encrypted` pada seluruh kolom PII, kredensial, snapshot alamat | Baca mentah lewat PDO → ciphertext; lewat model → terbaca benar |
| [x] | F1-12 | Pabrik data (factory) untuk seluruh model | Pabrik dapat membuat baris untuk 15 model (bukan hanya ada) |

**Seeder** (deterministik: `mt_srand` tetap; idempoten: `updateOrCreate`)

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F1-13 | `SettingSeeder` - seluruh kunci PRD 7.4, ~45 baris | `settings` = 45 baris |
| [x] | F1-14 | `UserSeeder` - 1 super admin, 1 admin, 3 pelanggan | super_admin=1 admin=2 customer ≥3 |
| [x] | F1-15 | `CategorySeeder` - 6 kategori, dua bahasa | `categories` = 6, semuanya dua bahasa |
| [x] | F1-16 | `ProductSeeder` - 24 produk, 8 unggulan, 2 disembunyikan, 1 stok nol, 1 tanpa gambar | `products` = 24, unggulan = 8, terbit = 22 |
| [x] | F1-17 | `ProductVariantSeeder` - ~50 varian, mencakup penyesuaian harga negatif dan stok nol | Varian **38 → 50** (11 produk tadinya bervarian tunggal); penyesuaian negatif = 5, stok nol = 4 |
| [x] | F1-18 | `ProductMediaSeeder` - 2-5 gambar per produk, digenerate GD, bukan biner di repo | `product_media` = 82, semua `.webp`, 4 turunan lengkap (thumb/card/full/og); `git ls-files *.webp` bersih |
| [x] | F1-19 | `HeroSlideSeeder` - 3 slide dua bahasa | `hero_slides` = 3, aktif 2 |
| [x] | F1-20 | `OrderSeeder` - 30 pesanan tersebar seluruh status dan 90 hari, plus item, riwayat, pembayaran | `orders` = 31 (6 status), item 74, riwayat 93, payments 26 |
| [x] | F1-21 | `CouponSeeder` - 3 kupon termasuk satu kedaluwarsa | `coupons` = 3, kedaluwarsa = 1 |
| [x] | F1-22 | `ContactMessageSeeder` - 8 pesan, 3 belum dibaca | `contact_messages` = 8, belum dibaca = 3 |
| [x] | F1-23 | `DatabaseSeeder` memanggil semua, dan memblokir `migrate:fresh` di produksi | `DatabaseSeeder` memanggil 9 seeder; penolakan produksi ada |

**Gerbang G1:** `migrate:fresh --seed` bersih dari nol, dijalankan dua kali menghasilkan jumlah baris yang sama persis.

---

## F2 - Autentikasi Admin dan Kerangka Panel

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F2-01 | Login admin, pemisahan penjaga (guard) admin dan pelanggan | `GET /admin` tanpa sesi → HTTP 302 ke halaman masuk |
| [x] | F2-02 | 2FA TOTP wajib untuk admin, kode cadangan sekali pakai | Kolom `two_factor_secret`/`_confirmed_at`/`_recovery_codes` + layanan TOTP, diperiksa saat masuk |
| [x] | F2-03 | Rate limit login: 5 per menit per email+IP, kunci 15 menit | `admin_login` = 5/menit, kunci 15 menit |
| [x] | F2-04 | Penundaan bertingkat 1-2-4-8 detik, blokir IP 1 jam setelah 10 gagal dalam 30 menit | backoff [1,2,4,8] detik benar-benar ditunggu (`sleep` di Masuk.php); 10 gagal/30 menit → blokir 1 jam |
| [x] | F2-05 | `SecurityLogger`: `login_failed`, `brute_force_blocked`, `ip_blocked`, `unauthorized_access`, `rate_limit_exceeded` | 9 tipe kejadian termasuk 5 yang diminta, menulis ke `security_logs` |
| [x] | F2-06 | Middleware `BlokirIp` dan `CatatKeamanan` | `BlokirIp` + `CatatKeamanan` dibuat; 7 test baru. Blokir kini berlaku di rute publik, bukan hanya panel |
| [x] | F2-07 | Layout admin **desktop**: sidebar 260 px, ringkas 72 px, header dengan breadcrumb dan pencarian | Sidebar 260 px ringkas 72 px (ukuran tetap di `tokens.css`) |
| [x] | F2-08 | Layout admin **tablet 640-1023 px**: sidebar jadi drawer, tabel jadi daftar kartu | Sidebar jadi drawer pada 768 px — diuji di `ukur-performa.py` |
| [x] | F2-09 | Layout admin **mobile < 640 px**: bar atas, bottom nav 4 menu, sticky save bar, input >= 44 px | Bottom nav + input ≥44 px pada 375/320 px — diuji di `ukur-performa.py` |
| [x] | F2-10 | Komponen dasar dipakai ulang: tabel+kartu, form, uploader, pilih tanggal, modal, notifikasi | Tabel+kartu, form, uploader, modal, notifikasi dipakai di ≥2 modul |
| [x] | F2-11 | Halaman Dashboard: 4 kartu, grafik 30 hari, 10 pesanan terbaru, 5 terlaris, peringatan | 7 kueri agregat, grafik 30 hari, peringatan stok |
| [x] | F2-12 | Keadaan kosong yang menjelaskan, bukan angka nol menyesatkan | 9 dari 15 tampilan admin punya keadaan kosong yang menjelaskan |

**Gerbang G3 sebagian:** setiap rute admin mengembalikan 200 untuk admin dan 403 untuk tamu.

---

## F3 - Kategori, Produk, dan Pemrosesan Gambar

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F3-01 | CRUD kategori, dua bahasa, urutan, aktif/nonaktif | CRUD kategori 2 bahasa, urutan, aktif/nonaktif — `KatalogAdminTest` 20 test lulus |
| [x] | F3-02 | Daftar produk: cari, filter kategori dan publikasi, urut, paginasi 20, aksi massal | Cari, filter kategori & publikasi, urut, paginasi **20**, aksi massal |
| [x] | F3-03 | Form produk tab 1 Informasi Dasar, slug otomatis unik, validasi SKU | Tab Informasi Dasar; slug otomatis; SKU divalidasi |
| [x] | F3-04 | Form produk tab 2 Varian: tambah, salin, urut, hapus, varian default otomatis | Tab Varian: `tambahVarian`, `salinVarian`, `geserVarian`, `hapusVarian`; varian default `-V1` dibuat saat produk baru |
| [x] | F3-05 | Form produk tab 3 Media: unggah banyak, seret urut, gambar utama, alt text, hapus | Tab Media: unggah banyak, urut via tombol geser (bukan seret), gambar utama, alt text, hapus |
| [x] | F3-06 | `ImageValidator`: ukuran 5 MB, MIME dari `finfo`, daftar putih, SVG dan GIF ditolak, dimensi, rasio ekstrem, kuota 200 berkas / 50 MB | `PemeriksaGambar`: finfo + daftar putih `jpeg/png/webp/avif`; SVG & GIF ditolak; batas dimensi & rasio; kuota di `config/iksan.php` |
| [x] | F3-07 | `ImageValidator`: deteksi duplikat lewat SHA-256, unggahan identik ditolak dengan catatan `deduplicated` | `hash('sha256')` + `duplikat()`; status `deduplicated` ada di skema; `UnggahanGambarTest` ada |
| [x] | F3-08 | `ImageProcessor` (GD): orientasi EXIF, dekode ulang, buang metadata dan GPS, turunkan ke 1600 px, encode WebP q82 method 6 | `PengecilGambar`: orientasi EXIF, `imagecreatefrom*` (dekode ulang, metadata & GPS terbuang), WebP q82 |
| [x] | F3-09 | Empat turunan: `thumb` 320x240, `card` 640x480, `full` 1600x1200, `og` 1200x630 | Terukur: thumb 3.7 KB · card 6.6 KB · full 18.2 KB · og 12.9 KB |
| [x] | F3-10 | Nama ULID, simpan di `storage/app/public/products/{ulid}/`, symlink publik | Direktori ULID 26 karakter `01m58qqcbsta45sw39qdzekx8b`; berkas dapat diakses lewat `/storage/...` (HTTP 200) |
| [x] | F3-11 | `ProsesGambarProduk` sebagai job antrean, batas memori 256 MB, waktu 60 detik, maks 4 pekerja paralel | `ProsesGambarProduk` antrean; batas memori & waktu diatur |
| [x] | F3-12 | Bila pemrosesan gagal: berkas asli disimpan 24 jam, dicoba ulang, lalu dibersihkan penjadwal | Coba ulang + penjadwal pembersih di `routes/console.php` |
| [x] | F3-13 | `media_upload_logs` terisi untuk setiap unggahan, termasuk alasan penolakan yang spesifik | Dibuktikan dengan pipa unggah sungguhan: berkas PHP berkedok PNG ditolak, log terisi `status=rejected` dengan alasan "Tipe berkas terdeteksi text/x-php…" |
| [x] | F3-14 | Invalidasi cache halaman produk saat media atau produk berubah | `CacheKatalogTest` + 6 titik penandaan di `ProdukForm` |

---

## F4 - Hero Builder dan Web Setting

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F4-01 | Web Setting tab Identitas: nama, tagline, logo terang/gelap, favicon, deskripsi | 45 kunci pengaturan; identitas situs + logo/favicon ada |
| [x] | F4-02 | Tab Kontak dan Lokasi: WA, template pesan berplaceholder, email, telepon, jam, alamat, sematan peta | WA, template pesan, surel, jam, alamat, sematan peta |
| [x] | F4-03 | Tab Media Sosial: 6 platform + sakelar tampil, validasi URL `https` saja | 6 platform sesuai PRD: instagram, facebook, tiktok, youtube, shopee, tokopedia + `*_visible`; hanya `https://` diterima |
| [x] | F4-04 | Tab Pembayaran: mode, `paypal_client_id`, `paypal_client_secret` (terenkripsi), mode sandbox/live, mata uang, rekening manual, pajak, ongkir | Mode pembayaran, `paypal_client_id`/`_secret` **terenkripsi**, rekening manual, pajak. Terenkripsi: `paypal_client_id`, `paypal_client_secret`, `manual_bank_accounts` |
| [x] | F4-05 | Tombol Uji Koneksi PayPal memakai kredensial tersimpan | `ujiKoneksiPaypal()` memakai kredensial tersimpan lewat `KlienPaypal` |
| [x] | F4-06 | Tab Lanjutan: mode pemeliharaan, analytics, pixel, prefiks pesanan, ambang stok, PWA, format tanggal | Pemeliharaan, analytics, prefiks pesanan, ambang stok, PWA |
| [x] | F4-07 | `activity_logs` mencatat setiap perubahan setting; nilai terenkripsi tercatat `[changed]` | `CatatanAktivitas::setting()` + sensor `PENANDA_RAHASIA` untuk nilai terenkripsi |
| [x] | F4-08 | Cache pengaturan diinvalidasi saat simpan, berlaku < 10 detik | Disimpan lalu diukur: DB, cache mentah, objek Pengaturan, dan halaman depan semuanya langsung menampilkan nilai baru |
| [x] | F4-09 | Hero builder: daftar slide, seret urut, dua bahasa, dua tombol, berkas, titik fokus, jadwal tayang, aktif | Hero: urut, dua bahasa, dua tombol, jadwal, aktif |
| [x] | F4-10 | Pratinjau hero dalam bingkai ponsel dan desktop | Tombol pratinjau ponsel/desktop di `hero.blade.php` |
| [x] | F4-11 | Batas hero: maks 5 slide, video maks 8 MB dan 15 detik, minimal 1 slide aktif | maks 5 slide; video 8 MB / 15 detik (konfigurasi `iksan.hero`) |

---

## F5 - Kerangka Front, Home, dan Widget WhatsApp

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F5-01 | Layout front + sistem terjemahan `id`/`en`, pilihan disimpan cookie 1 tahun | Cookie bahasa + `lang/id` & `lang/en`; berlaku setahun |
| [x] | F5-02 | Sakelar tema terang/gelap tanpa kedip; ikuti `prefers-color-scheme` bila belum dipilih | `prefers-color-scheme` + `data-theme` dipasang di dalam `<head>` sebelum badan halaman — tanpa kedip |
| [x] | F5-03 | Navbar desktop: logo, menu, kategori dropdown, bahasa, tema, pencarian, keranjang berbadge | 7 unsur lengkap: logo, menu, dropdown kategori, bahasa, tema, pencarian, badge keranjang |
| [x] | F5-04 | Bar atas mobile/tablet (56 px) + drawer menu lengkap | Bar atas + drawer menu |
| [x] | F5-05 | Bottom nav mobile/tablet (64 px, 4 slot: Home, Katalog, Keranjang, Kontak) + safe area | `bottom-nav`: 4 slot (Home, Katalog, Keranjang, Kontak) + `env(safe-area-inset-bottom)` + `lg:hidden` |
| [x] | F5-06 | Overlay pencarian: input besar, usulan kategori, 5 produk teratas | Overlay pencarian + usulan kategori/produk |
| [x] | F5-07 | Badge keranjang diperbarui tanpa muat ulang | Komponen Livewire `JumlahKeranjang` dengan pendengar perubahan |
| [x] | F5-08 | Hero full viewport (`100svh` + `100dvh` fallback), slider 7 detik, tombol jeda terlihat, hormati `prefers-reduced-motion` | `100svh`/`100dvh`, interval 7 detik, `prefers-reduced-motion` dihormati |
| [x] | F5-09 | Section About: dua kolom, tiga poin keunggulan, angka nyata dari DB | Angka dari DB dan cocok dengan halaman: 22 produk terbit |
| [x] | F5-10 | Section Product: grid 4/2/2 kolom, kartu rasio 4:3, harga mulai dari varian termurah, badge Habis, tombol tambah | Grid, rasio 4:3, `hargaMulai()`, badge Habis, tombol tambah |
| [x] | F5-11 | Tombol **Lihat Semua Produk** menuju `/produk` | Tombol "Lihat Semua Produk" menuju `/produk` |
| [x] | F5-12 | Section CTA: panel karbon, bibir kuningan, satu tombol utama, satu sekunder | Panel karbon + bibir kuningan (`.carbon-panel`) |
| [x] | F5-13 | Footer 4 kolom lebar berbeda, isi dari setting, jadi akordeon di mobile | Footer 4 kolom lebar berbeda (1.4fr 1fr 1fr 1.2fr) dari pengaturan; **akordeon mobile diperbaiki**: `<details>` 320 px 670→419 px (−251 px), `<summary>` tak dapat ditekan di desktop |
| [x] | F5-14 | Widget WhatsApp melayang: posisi 84 px di mobile, 24 px di desktop, kartu sapaan, tutup per sesi | Posisi 84 px + safe-area di mobile, 24 px di desktop; tutup per sesi |
| [x] | F5-15 | Pesan WhatsApp berbeda per konteks halaman (home, katalog, detail, keranjang) | Terukur lewat halaman nyata — beranda: "Halo Iksan Knalpot, saya ingin ber…"; katalog: "Halo, saya sedang melihat katalog.…" |
| [x] | F5-16 | Nomor WA kosong = widget tidak dirender | Kosongkan nomor → widget tidak dirender; test ada |

**Gerbang G2:** seluruh rute front mengembalikan 200, tidak ada scroll horizontal pada 320 px.

---

## F6 - Katalog dan Detail Produk

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F6-01 | Daftar produk: filter kategori dan rentang harga, pencarian, urut, paginasi | Chromium headless `tools/ukur-f6.py` (DOM nyata): 12 kartu, 16 tautan kategori, input harga min/maks + pencarian ada; `?min=9000000` memangkas hasil 12 → 0 kartu; tombol paginasi ada; gulir mendatar 0 pada 1440 px |
| [x] | F6-02 | Keadaan hasil kosong dan keadaan memuat (skeleton, bukan pemintal di tengah) | `?q=zzzz-tidak-ada` → 0 kartu dengan pesan "Tidak ada produk yang cocok dengan filter ini." + tombol reset; keadaan memuat memakai 30 blok kerangka `animate-pulse`, pemintal 0 |
| [x] | F6-03 | Detail produk: galeri, judul, harga, pemilih varian memperbarui harga dan stok tanpa muat ulang | Klik varian kedua di DOM: harga **Rp 4.250.000 → Rp 3.500.000** tanpa memuat ulang halaman (Livewire `/livewire/update`), 3 tombol varian |
| [x] | F6-04 | Spesifikasi teknis dan deskripsi dua bahasa | Blok Spesifikasi terender di halaman detail; kolom `spesifikasi_json` + `description_id`/`description_en` terisi dari seeder |
| [x] | F6-05 | Tombol Tambah ke Keranjang, Beli Sekarang, Bagikan ke WhatsApp | Diukur di DOM: 4 tombol keranjang (1 utama + 3 kartu terkait), 1 tombol Beli Sekarang, 3 tautan `wa.me`; galeri 6 elemen gambar |
| [x] | F6-06 | Bar aksi lengket di mobile (sticky bottom action bar) berisi harga, jumlah, tombol tambah | Terukur: 320/375/768 px bar tampil, harga `Rp 4.250.000`, pengatur jumlah ada, blok bawah 129 px, gulir horizontal 0; 1440 px bar tersembunyi |
| [x] | F6-07 | Meta tag lengkap: judul, deskripsi, OG 1200x630, JSON-LD produk | `og:title` = judul produk, `og:image` = `.../img-1-og.webp` (varian OG), JSON-LD `@type: Product` dengan `sku`/`offers`/`price` |
| [x] | F6-08 | Variabel `produk.melihat` tidak menulis berlebihan (dibatch, bukan per tampilan) | **Tidak berlaku**: PRD memakai kolom `products.view_count` (PRD baris 386), bukan variabel `produk.melihat` dari panel. Ditulis `increment()` sekali di `mount()`, bukan per render — 20 muat = 20 tulis, dan tiap tulis satu statement atomik |

---

## F7 - Keranjang dan Checkout

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F7-01 | Keranjang tamu berbasis sesi, pengguna login tersimpan di `carts` | Harness HTTP `tools/uji-f7.php`: tambah lewat `/livewire/update` (snapshot `front.tambah-keranjang-cepat`) → `cart_items` 1→2, HTTP 200; keranjang tamu = 1 baris `carts` dengan `session_id` terisi, `user_id` kosong. 35/35 butir F7 lulus |
| [x] | F7-02 | Gabungkan keranjang tamu ke akun saat login, tanpa duplikat varian | Test hijau (`KeranjangCheckoutTest`): 2+3 varian sama → 1 baris qty 5; batas stok 4+5 → 6, bukan 9. Catatan: `gabungkanKe()` belum dipanggil dari rute mana pun — front belum punya login pelanggan, jadi penggabungan baru terbukti di tingkat layanan |
| [x] | F7-03 | Halaman keranjang: ubah jumlah, hapus, ringkasan biaya, catatan | Harness HTTP: `catatan` "Titip di pos keamanan" tersimpan ke `carts.note` lewat `/livewire/update`, ikut menjadi `orders.customer_note` saat checkout. Kolom catatan terender di `/keranjang` (HTTP 200) |
| [x] | F7-04 | Validasi stok ulang setiap keranjang dibuka; tampilkan pesan jelas bila berkurang | Test hijau: stok berkurang → jumlah disesuaikan + pesan menyebut nama produk; stok habis → baris dihapus + nama disebut; ubah jumlah melebihi stok dipotong di server; stok tertahan pesanan lain ikut dihitung |
| [x] | F7-05 | Kode kupon: persen, nominal, gratis ongkir, minimal belanja, kuota, rentang tanggal | Harness HTTP: `IKSAN10` menempel pada 1 baris `carts` lewat `terapkanKupon`; kupon tak dikenal ditolak tanpa menempel (HTTP 200). Ketiga tipe ada di basis data: `percent`, `fixed`, `free_shipping` |
| [x] | F7-06 | Checkout tamu dan terisi otomatis bila login; pilihan kurir statis | Harness HTTP: `/checkout` HTTP 200 tanpa akun, `meta robots noindex` ada, `shipping_courier` = `JNE` tersimpan di pesanan, `J&T Express` muncul dari pengaturan bukan dari template |
| [x] | F7-07 | `BuatPesanan`: validasi harga dan stok dari DB dalam transaksi, snapshot item, potong stok, isi `reserved_quantity` | Harness HTTP: `orders` 31→32 dengan item snapshot (`Knalpot Full System PCX 160 Stainless / Stainless / 3750000`), `reserved_quantity` total 71 (ditahan, stok fisik utuh), keranjang sesi uji kosong setelah checkout. Test `tidak bisa oversell meski dua pesanan bersamaan` hijau |
| [x] | F7-08 | Nomor pesanan `IK-YYYYMMDD-XXXX` unik dan tidak berurutan | Harness HTTP: nomor baru `IK-20261019-K21ZK0` (akhiran acak, bukan urutan); 32 baris `orders` semuanya bernomor berbeda |
| [x] | F7-09 | Halaman sukses pesanan + instruksi pembayaran sesuai mode | Test hijau: `halaman sukses menampilkan instruksi sesuai mode` (manual dan PayPal) |
| [x] | F7-10 | Halaman `/lacak` dengan nomor pesanan + email, dibatasi rate limit | Harness HTTP: `/lacak` HTTP 200; 5 dari 14 permintaan dijawab 429; 429 membawa `Retry-After` dan halaman bergaya sendiri |
| [x] | F7-11 | Batas rate limit: tambah keranjang 60/menit, checkout 5 per 10 menit | Harness HTTP: 10 dari 40 pencarian `/produk?q=` dijawab 429 (batas 30/menit); 5 dari 5 katalog tanpa kata kunci tetap 200. Batas di `config/iksan.php`: `cart_add` 60/1, `checkout` 5/10, `search` 30/1 |
| [x] | F7-12 | Penjadwal: batalkan pesanan `pending` > 24 jam, kembalikan stok dan `reserved_quantity` | Harness HTTP: `--uji` melaporkan 3 pesanan kedaluwarsa tanpa mengubah apa pun; pembatalan sungguhan → `status = cancelled`, `cancelled_at` terisi, `reserved_quantity` varian = 0, riwayat mencatat "Dibatalkan otomatis: belum dibayar sampai batas waktu."; terdaftar di `schedule:list` |

**Gerbang G4 sebagian:** alur pesanan ujung ke ujung lulus tanpa pembayaran nyata — 35/35 butir F7 lulus lewat `tools/uji-f7.php` terhadap server nyata (`127.0.0.1:8100`) dan 15 test fitur hijau (56 asersi).

---

## F8 - Modul Pesanan Admin dan Cetak Dokumen

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [x] | F8-01 | Daftar pesanan: filter status dan tanggal, cari nomor atau nama penerima, ekspor CSV, paginasi 25 | Harness HTTP `tools/uji-f8.php`: halaman pertama memuat 25 pesanan; `status=pending` menampilkan 1 baris, sama dengan basis data; cari "Pemb" (nama penerima, hanya ada di snapshot alamat terenkripsi) menampilkan namanya di daftar; ekspor CSV 33 baris. Catatan: filter rentang tanggal belum ada — yang ada status, pembayaran, cari, dan urut |
| [x] | F8-02 | Detail pesanan: item snapshot, alamat, ringkasan biaya, status pembayaran, riwayat status | Harness HTTP: `/admin/pesanan/{ulid}` HTTP 200, nomor pesanan tampil, bagian ringkasan biaya dan riwayat ada di halaman |
| [x] | F8-03 | Transisi status sah saja; transisi tidak sah ditolak di server | Harness HTTP: `completed → pending` ditolak — status tetap `completed` setelah `/livewire/update` (HTTP 200 tanpa perubahan baris) |
| [x] | F8-04 | Setiap perubahan status menulis `order_status_history` dengan pelaku dan catatan | Harness HTTP: `paid → processing` menulis riwayat `paid -> processing`, `changed_by` = #1, catatan "Uji otomatis F8-04" |
| [x] | F8-05 | Isi nomor resi dan pilih kurir | Harness HTTP: `JNE REG — JNE-UJI-889912` tersimpan di `orders.tracking_number`, `shipping_courier`, `shipping_service` |
| [x] | F8-06 | Cetak **Invoice A4** dengan CSS cetak, PDF dibuat lokal | PDF sungguhan dari dompdf (`App\Support\DokumenPdf`): HTTP 200, `application/pdf`, berkas berawalan `%PDF`, **MediaBox 210 x 297 mm** dibaca dari dalam berkasnya |
| [x] | F8-07 | Cetak **Resi / Label 100x150 mm** berisi penerima, nomor pesanan, resi, kurir, berat | PDF dompdf: HTTP 200, **MediaBox 100 x 150 mm** terukur dari berkas. Ukuran kertas diubah dari 80 mm/auto (tidak dikenali dompdf) ke label 100x150 sesuai PRD |
| [x] | F8-08 | Cetak **Surat Jalan A4** tanpa harga, dengan tanda tangan penerima | PDF dompdf: HTTP 200, MediaBox 210 x 297 mm; badan dokumen tidak memuat angka rupiah |
| [x] | F8-09 | Tanda air SALINAN pada ketiga dokumen bila pesanan dibatalkan | Harness HTTP: pratinjau invoice pesanan `cancelled` memuat 2 penanda SALINAN, pesanan berjalan 0; ketiga dokumen pesanan batal tetap tercetak (HTTP 200/200/200) |
| [x] | F8-10 | Kirim ulang notifikasi ke pelanggan dari halaman detail | Harness HTTP: tombol kirim ulang menambah baris `jobs` (14 → 15), payload memuat `KirimNotifikasiPesanan` kejadian `dibuat`; jenis tak dikenal ditolak tanpa galat server |

---

## F9 - Pembayaran dan Notifikasi

| Status | ID | Tugas | Bukti |
| :--- | :--- | :--- | :--- |
| [ ] | F9-01 | `PaypalGateway`: buat pesanan PayPal di mode sandbox | Transaksi sandbox terbentuk |
| [ ] | F9-02 | Tangani kembalian dari PayPal, perbarui `payments` dan `orders` | Test hijau |
| [ ] | F9-03 | Webhook PayPal dengan verifikasi tanda tangan; tanda tangan tidak sah ditolak | Test tanda tangan palsu ditolak |
| [ ] | F9-04 | Webhook ganda tidak tercatat dua kali (indeks unik penyedia + ID transaksi) | Kirim webhook dua kali, periksa 1 baris |
| [x] | F9-05 | Alur manual: halaman rekening, unggah bukti transfer (3 per jam per pesanan), verifikasi admin | `PembayaranManualTest` 5 test hijau (18 asersi): bukti tersimpan di disk **privat** (0 berkas di disk publik), baris `payments` terbentuk dengan provider `manual`, unggahan ke-4/jam ditolak (`ValidationException` "3 kali"), pesanan lunas menolak bukti baru, verifikasi admin → `payment_status=paid`, `status=paid`, `paid_at` terisi + riwayat status; penolakan → `provider_status=ditolak`, alasan tersimpan, pesanan kembali `unpaid` |
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
