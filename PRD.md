# PRODUCT REQUIREMENT DOCUMENT
## Iksan Knalpot - E-Commerce Toko Knalpot

| Field | Value |
| :--- | :--- |
| **Versi** | 1.0 (Draft untuk review) |
| **Status** | Ready for Architecture Design |
| **Tanggal** | 17 Oktober 2026 |
| **Baseline Stack** | Laravel 12 + Livewire 3 + Tailwind CSS + SQLite (WAL) |
| **Target Rilis** | MVP 4 minggu, Fase 2 (PWA + integrasi kurir) 2 minggu |
| **Pemilik Produk** | Iksan Knalpot |

---

## 1. Ringkasan Produk

Toko online knalpot yang menjual produk siap pakai sekaligus melayani pesanan custom. Situs berfungsi sebagai katalog dan mesin checkout, dengan panel admin yang membuat seluruh isi situs dapat diubah tanpa menyentuh kode.

**Tujuan:**

1. Pelanggan menemukan knalpot yang cocok dan menyelesaikan checkout tanpa menghubungi admin.
2. Admin mengelola produk, pesanan, dan seluruh identitas situs dari satu panel.
3. Situs terasa seperti aplikasi native di tablet dan ponsel, bukan situs desktop yang dikecilkan.

**Bukan tujuan (MVP):**

- Marketplace multi-penjual.
- Integrasi 3D viewer dan audio preview (ditunda ke Fase 2, lihat Bagian 16).
- Aplikasi mobile native.

**Metrik keberhasilan:**

- Checkout selesai tanpa bantuan admin pada 70% transaksi.
- Waktu muat home dan daftar produk di bawah 2,5 detik (LCP) pada 4G ponsel.
- Nol temuan kritis pada audit keamanan internal (Bagian 12).
- Admin dapat menambah produk lengkap dengan 5 foto dalam waktu di bawah 5 menit.

---

## 2. Peran Pengguna

| Peran | Hak Akses | Catatan |
| :--- | :--- | :--- |
| **Guest** | Home, katalog, detail produk, kontak, keranjang (berbasis sesi), checkout dengan data tamu | Tidak perlu daftar untuk membeli |
| **Customer** | Ditambah: riwayat pesanan, buku alamat, profil | Opsional, daftar setelah checkout atau saat menautkan riwayat |
| **Admin** | Seluruh panel admin | Wajib autentikasi + wajib 2FA |
| **Super Admin** | Ditambah: manajemen admin lain, lihat log keamanan, ubah kredensial pembayaran | Hanya satu akun pada MVP |

---

## 3. Peta Halaman dan Rute

### 3.1 Halaman Front

| Rute | Nama | Akses | Keterangan |
| :--- | :--- | :--- | :--- |
| `GET /` | Home | Publik | Enam section, lihat Bagian 4 |
| `GET /produk` | Daftar Produk | Publik | Filter, urut, pencarian, paginasi |
| `GET /produk/{slug}` | Detail Produk | Publik | Wajib ada, karena keranjang butuh titik masuk ini |
| `GET /kontak` | Kontak Kami | Publik | Form + peta + kanal resmi |
| `GET /keranjang` | Keranjang | Publik | Berbasis sesi, bukan wajib login |
| `GET /checkout` | Checkout | Publik | Data tamu atau prefilled bila login |
| `POST /checkout` | Proses Pesanan | Publik | Rate limit ketat |
| `GET /pesanan/sukses/{order_number}` | Pesanan Dibuat | Publik | Halaman terima kasih + instruksi bayar |
| `GET /lacak` | Lacak Pesanan | Publik | Cari dengan nomor pesanan + email |
| `GET /lang/{locale}` | Ganti Bahasa | Publik | `id` atau `en`, disimpan di cookie 1 tahun |
| `GET /sitemap.xml`, `GET /robots.txt` | SEO | Publik | Digenerate otomatis |

### 3.2 Halaman Admin

| Rute | Nama | Keterangan |
| :--- | :--- | :--- |
| `GET /admin/login` | Login | Rate limit 5 percobaan per menit |
| `GET /admin` | Dashboard | Ringkasan penjualan, pesanan baru, stok menipis |
| `/admin/produk` | Produk | CRUD, varian, media, stok, publikasi |
| `/admin/kategori` | Kategori | CRUD, urut, aktif/nonaktif |
| `/admin/pesanan` | Pesanan | Daftar, filter status, ubah status, cetak |
| `/admin/pesanan/{ulid}` | Detail Pesanan | Item, pembayaran, alamat, riwayat status |
| `/admin/pesanan/{ulid}/invoice` | Cetak Invoice | PDF A4 |
| `/admin/pesanan/{ulid}/resi` | Cetak Resi Pengiriman | PDF label 100x150 mm |
| `/admin/pengaturan` | Web Setting | Lima tab, lihat Bagian 7 |
| `/admin/hero` | Hero Section | Susun slide, urutan, jadwal tayang |
| `/admin/pelanggan` | Pelanggan | Daftar, riwayat, tautan WA |
| `/admin/laporan` | Laporan | Penjualan harian/bulanan, produk terlaris, ekspor CSV |
| `/admin/keamanan` | Log Keamanan | Percobaan login gagal, blokir IP, rate limit terlampaui |
| `/admin/profil` | Profil Admin | Ganti kata sandi, kelola 2FA |

---

## 4. Halaman Home - Spesifikasi Section

Enam section, urut dari atas. Setiap section punya nomor bagian bergaya lembar data teknik (`01 / KATALOG`) sebagai penanda urutan baca.

### 4.1 Navbar

Empat zona, dirender berbeda per ukuran layar (lihat Bagian 5).

**Desktop (satu baris, tinggi 72px, sticky dengan efek naik saat scroll):**

| Zona | Isi |
| :--- | :--- |
| Kiri | Logo (gambar dari Web Setting, fallback teks nama toko) |
| Tengah | Menu: Home, Produk, Kategori (dropdown), About, Kontak |
| Kanan | Sakelar bahasa `ID / EN`, sakelar tema terang/gelap, tombol pencarian, ikon keranjang dengan badge jumlah |

**Tablet dan Mobile:** logo dan ikon keranjang tetap di bar atas, menu dipindah ke navigasi bawah (Bagian 5.2).

**Perilaku:**

- Sakelar bahasa menukar seluruh teks antarmuka dan kolom konten `_id` / `_en`, tanpa reload halaman.
- Sakelar tema: pilihan manual disimpan di `localStorage`; bila belum pernah memilih, ikuti `prefers-color-scheme`. Atribut `data-theme` dipasang sebelum first paint untuk mencegah kedip.
- Pencarian: overlay dengan input besar, suggestion kategori dan 5 produk teratas, hasil diambil dari endpoint Livewire.
- Badge keranjang diperbarui tanpa reload saat item ditambah.

### 4.2 Hero Section (Full Viewport)

- Tinggi: `100svh` dikurangi tinggi navbar, dengan `100dvh` fallback untuk browser lama.
- Latar: satu gambar atau video pendek. Gambar wajib dikompresi ke WebP, maksimum 400 KB.
- Konten: judul, subjudul, dua tombol aksi (contoh: "Lihat Katalog" dan "Konsultasi via WhatsApp").
- Bila hero berisi lebih dari satu slide: geser otomatis setiap 7 detik, dapat dihentikan dengan tombol jeda yang terlihat, tombol navigasi titik, dan tombol panah. Menghormati `prefers-reduced-motion`.
- Pada mobile, konten ditata rata bawah agar tidak tertutup navigasi bawah.
- Teks hero diambil dari tabel `hero_slides`, jadi dapat diubah admin tanpa deploy.

### 4.3 About

- Dua kolom di desktop: teks di satu sisi, satu gambar atau video bengkel di sisi lain.
- Isi: paragraf profil singkat (maksimum 180 kata), tiga poin keunggulan bernomor, dan satu baris data nyata (jumlah tahun berdiri, jumlah produk, jumlah pelanggan). Angka wajib diambil dari database atau diisi admin, bukan dikarang di template.
- Di mobile menjadi satu kolom, gambar di atas teks.

### 4.4 Product Section

- Judul section, deskripsi singkat, dan grid produk unggulan (`is_featured = 1`) yang dipublikasikan.
- Desktop: 4 kolom. Tablet: 2 kolom. Mobile: 2 kolom dengan kartu ringkas.
- Kartu produk berisi: gambar rasio tetap 4:3, nama, kategori, harga mulai dari varian termurah, badge "Habis" bila seluruh varian stok nol, tombol tambah ke keranjang.
- Di bawah grid: tombol **"Lihat Semua Produk"** menuju `/produk`.
- Jumlah item unggulan diatur admin (default 8).

### 4.5 CTA

- Panel gelap selebar penuh dengan tenun karbon dan bibir kuningan 1px di tepi atas.
- Isi: satu kalimat ajakan, satu tombol utama (WhatsApp atau lihat katalog), satu tombol sekunder (kontak).
- Tidak ada angka karangan, tidak ada hitung mundur palsu.

### 4.6 Footer

- Empat kolom dengan lebar berbeda (bukan empat kolom sama rata):
  1. Identitas: logo, alamat, satu baris deskripsi.
  2. Navigasi: tautan halaman utama.
  3. Kategori: kategori aktif teratas, maksimum 6.
  4. Kontak: WhatsApp, email, jam operasional, dan ikon media sosial.
- Baris bawah: hak cipta dengan tahun otomatis, tautan Kebijakan Privasi dan Syarat Ketentuan.
- Semua isi ditarik dari tabel `settings`, jadi admin dapat mengubah tanpa deploy.
- Di mobile, empat kolom menumpuk menjadi akordeon agar tidak mengubur kontak di bawah.

---

## 5. Model Responsif dan Navigasi

### 5.1 Titik Putus

| Perangkat | Lebar | Model navigasi |
| :--- | :--- | :--- |
| Mobile | < 640px | Bar atas + navigasi bawah aplikasi |
| Tablet | 640px - 1023px | Bar atas + navigasi bawah aplikasi (ikon + label lebih besar) |
| Desktop | >= 1024px | Navbar penuh satu baris, tanpa navigasi bawah |

### 5.2 Halaman Front - Perilaku Aplikasi Mobile

Pada tablet dan mobile, situs harus terasa seperti aplikasi, bukan situs yang diciutkan.

**Bar atas (sticky, tinggi 56px):**

- Kiri: tombol menu (buka drawer berisi menu lengkap, kategori, sakelar bahasa, sakelar tema).
- Tengah: nama halaman saat ini, atau logo saat di home.
- Kanan: ikon pencarian dan ikon keranjang dengan badge.

**Bar navigasi bawah (fixed, tinggi 64px + safe area):**

| Slot | Ikon | Tujuan |
| :--- | :--- | :--- |
| 1 | Home | `/` |
| 2 | Katalog | `/produk` |
| 3 | Keranjang | `/keranjang`, dengan badge jumlah |
| 4 | Kontak | `/kontak` |

**Ketentuan tambahan:**

- `padding-bottom` pada isi halaman minimal 64px + `env(safe-area-inset-bottom)` agar konten tidak tertutup bar.
- Bar disembunyikan saat mengetik di input pencarian.
- Transisi halaman memakai `view-transition` bila didukung, dengan fallback fade 120ms.
- Tombol tambah ke keranjang pada detail produk: bar aksi lengket di bawah (sticky bottom action bar) berisi harga, pilihan jumlah, dan tombol tambah.
- Semua target sentuh minimal 44x44 px.
- Tidak ada scroll horizontal pada lebar 320px.
- Manifest PWA dan service worker untuk shell offline disertakan pada Fase 2 (Bagian 16).

### 5.3 Panel Admin - Perilaku Per Ukuran Layar

**Desktop (>= 1024px):**

- Sidebar tetap lebar 260px di kiri, dapat diringkas menjadi 72px (ikon saja), status disimpan di `localStorage`.
- Sidebar berisi: Dashboard, Produk, Kategori, Pesanan, Hero, Pelanggan, Laporan, Pengaturan, Keamanan.
- Header atas: breadcrumb, pencarian global, notifikasi pesanan baru, menu akun.

**Tablet (640px - 1023px):**

- Sidebar disembunyikan, dibuka sebagai drawer dari kiri dengan overlay.
- Tabel diubah menjadi daftar kartu (satu kartu per baris data).
- Tombol aksi baris dipindah ke menu "..." yang membuka bottom sheet.

**Mobile (< 640px):**

- Bar atas dengan tombol hamburger, judul halaman, dan ikon aksi utama.
- Bar navigasi bawah dengan 4 menu terpakai: Dashboard, Produk, Pesanan, Pengaturan. Sisanya lewat drawer.
- Form diubah menjadi satu kolom penuh, label di atas input, tinggi input minimal 44px.
- Form panjang memakai bar simpan lengket di bawah (sticky save bar): tombol Simpan dan Batal selalu terlihat.
- Unggah foto: tombol kamera langsung (`capture="environment"`) sebagai opsi selain galeri.
- Tabel apa pun dilarang pada lebar di bawah 640px. Bila data harus berbentuk tabel, gunakan mode gulir horizontal dengan kolom pertama dibekukan, dan hanya untuk halaman laporan.

---

## 6. Floating Button WhatsApp

- Posisi: `right: 20px; bottom: 84px` pada mobile dan tablet (di atas navigasi bawah), `bottom: 24px` pada desktop.
- Bentuk: tombol bulat 56px, ikon WhatsApp, warna resmi WhatsApp. Satu-satunya elemen situs yang boleh berbentuk pil atau bulat penuh.
- Perilaku: terbuka menjadi kartu kecil berisi sapaan, nama admin, dan tombol "Mulai Chat", bukan langsung membuka tab baru.
- Pesan terisi otomatis dan berbeda per konteks:
  - Home: `Halo [Nama Toko], saya ingin bertanya tentang knalpot.`
  - Daftar produk: `Halo, saya sedang melihat katalog. Boleh minta rekomendasi?`
  - Detail produk: `Halo, saya tertarik dengan [Nama Produk] ([SKU]). Stoknya masih ada?`
  - Keranjang: `Halo, saya ingin menanyakan pesanan saya dengan [N jumlah] item.`
- Nomor tujuan diambil dari Web Setting. Bila kosong, tombol tidak dirender sama sekali.
- Menghormati `prefers-reduced-motion`: tanpa animasi denyut.
- Dapat ditutup permanen per sesi; pilihan disimpan di `sessionStorage`.

---

## 7. Panel Admin - Spesifikasi Modul

### 7.1 Dashboard

- Empat kartu angka: pesanan hari ini, pendapatan hari ini, pesanan menunggu proses, produk stok menipis (stok <= 3).
- Grafik penjualan 30 hari terakhir (batang, tanpa pustaka berat, SVG atau Canvas).
- Daftar 10 pesanan terbaru dengan tautan cepat ke detail.
- Daftar 5 produk terlaris bulan berjalan.
- Peringatan: pesanan `pending` lebih dari 24 jam, produk tanpa foto, pengaturan wajib yang masih kosong.
- Semua angka berasal dari query nyata. Bila data belum ada, tampilkan keadaan kosong yang menjelaskan, bukan angka nol yang menyesatkan.

### 7.2 Produk

**Daftar:** pencarian, filter kategori dan status publikasi, urut berdasarkan nama, harga, stok, atau waktu dibuat, paginasi 20 per halaman, aksi massal (publikasikan, sembunyikan, hapus).

**Form produk (tiga tab):**

1. **Informasi Dasar:** SKU, nama `id`/`en`, slug otomatis dari nama `id` (dapat diedit, wajib unik), kategori, deskripsi `id`/`en` (editor sederhana: tebal, miring, daftar, tautan), harga dasar, status unggulan, status publikasi.
2. **Varian:** tabel varian dengan kolom SKU varian, nama `id`/`en`, penyesuaian harga, stok, status aktif. Varian dapat ditambah, disalin, diurut, dihapus. Bila produk tidak punya varian, sistem membuat satu varian default otomatis.
3. **Media:** unggah banyak berkas sekaligus dengan seret dan lepas, ubah urutan dengan seret, tandai satu sebagai gambar utama, hapus, serta isi teks alternatif untuk tiap gambar.

**Validasi:** SKU unik, slug unik, harga tidak negatif, minimal satu media sebelum dipublikasikan, minimal satu varian aktif.

### 7.3 Pesanan

**Daftar:** filter status, rentang tanggal, pencarian nomor pesanan atau nama penerima, ekspor CSV, paginasi 25.

**Status dan transisi yang sah:**

```
pending -> paid -> processing -> shipped -> completed
   |         |           |
   v         v           v
cancelled cancelled  cancelled
```

**Detail pesanan:**

- Item dengan harga saat transaksi (snapshot, bukan harga terkini).
- Alamat pengiriman lengkap.
- Ringkasan pembayaran: subtotal, diskon, ongkir, pajak, total.
- Status pembayaran PayPal: ID transaksi, status, waktu.
- Ubah status dengan catatan opsional, dan riwayat perubahan status yang tercatat (siapa, kapan, dari ke).
- Tambahkan nomor resi dan pilih kurir.
- Tombol cetak dan kirim ulang email/WhatsApp notifikasi ke pelanggan.

**Cetak:**

| Dokumen | Format | Isi |
| :--- | :--- | :--- |
| Invoice | A4 PDF | Logo, identitas toko, nomor pesanan, tanggal, data pembeli, item, ringkasan biaya, catatan |
| Resi / Label Pengiriman | 100x150 mm PDF | Nama dan alamat penerima, nomor pesanan, nomor resi, kurir, berat, nama pengirim |
| Surat Jalan | A4 PDF | Daftar item tanpa harga, tanda tangan penerima |

Ketiga dokumen dirender dari Blade dengan CSS cetak, dikonversi ke PDF memakai pustaka PDF lokal. Tidak ada ketergantungan pada layanan luar. Semua dokumen diberi tanda air "SALINAN" bila pesanan berstatus `cancelled`.

### 7.4 Web Setting

Halaman tunggal dengan lima tab. Semua nilai disimpan pada tabel `settings` sebagai pasangan kunci dan nilai, dengan kolom `group`, `type`, dan `is_encrypted`.

**Tab 1 - Identitas Situs**

| Kunci | Tipe | Keterangan |
| :--- | :--- | :--- |
| `site_name` | teks | Nama toko, dipakai di judul, footer, dan PDF |
| `site_tagline` | teks | Satu baris, tampil di bawah logo |
| `site_logo_light` | gambar | Logo untuk tema terang, PNG transparan, maksimum 400x120 px |
| `site_logo_dark` | gambar | Logo untuk tema gelap |
| `site_favicon` | gambar | ICO atau PNG 512x512, dipakai juga sebagai ikon PWA |
| `site_description` | teks panjang | Meta description, maksimum 160 karakter |

**Tab 2 - Kontak dan Lokasi**

| Kunci | Tipe | Keterangan |
| :--- | :--- | :--- |
| `whatsapp_number` | teks | Format internasional tanpa tanda plus, contoh `628123456789` |
| `whatsapp_message_template` | teks panjang | Template sapaan, mendukung placeholder `{product}`, `{sku}`, `{cart_count}` |
| `contact_email` | email | Email publik dan penerima notifikasi |
| `contact_email_cc` | email | Opsional |
| `phone_number` | teks | Nomor telepon kantor |
| `operational_hours` | teks | Contoh `Senin - Sabtu, 08.00 - 17.00 WIB` |
| `address_full` | teks panjang | Alamat lengkap |
| `maps_embed` | teks | Kode sematan Google Maps, disanitasi saat disimpan |
| `maps_lat`, `maps_lng` | angka | Untuk penanda peta bila sematan tidak dipakai |

**Tab 3 - Media Sosial**

Kunci bertipe teks untuk tiap platform: `social_instagram`, `social_facebook`, `social_tiktok`, `social_youtube`, `social_shopee`, `social_tokopedia`. Tiap kunci punya pasangan `*_visible` bertipe boolean. Ikon diambil dari set ikon internal, bukan gambar yang diunggah, agar konsisten. Ikon uji: setiap URL divalidasi sebagai URL absolut dan skema dibatasi ke `https`.

**Tab 4 - Pembayaran**

| Kunci | Tipe | Terenkripsi | Keterangan |
| :--- | :--- | :--- | :--- |
| `payment_mode` | pilihan | tidak | `paypal`, `manual`, atau `both` |
| `paypal_client_id` | teks | ya | Kredensial REST PayPal |
| `paypal_client_secret` | teks | ya | Disimpan dengan cast `encrypted`, tidak pernah dikirim balik ke browser |
| `paypal_mode` | pilihan | tidak | `sandbox` atau `live` |
| `paypal_currency` | pilihan | tidak | Default `IDR`, opsi `USD` |
| `manual_bank_accounts` | JSON | ya | Daftar rekening untuk mode manual |
| `tax_percent` | angka | tidak | Default 0 |
| `shipping_flat_rate` | angka | tidak | Ongkir datar, default 0 |

Tombol "Uji Koneksi" memanggil PayPal dengan kredensial tersimpan dan menampilkan hasil nyata tanpa pernah menampilkan ulang secret.

**Tab 5 - Lanjutan**

`maintenance_mode` (boolean + pesan), `analytics_id`, `meta_pixel_id`, `order_prefix` (default `IK`), `low_stock_threshold`, `enable_pwa` (boolean), `currency_symbol`, `date_format`.

**Aturan umum:**

- Setiap perubahan dicatat di `activity_logs` (siapa, kunci apa, nilai lama, nilai baru). Nilai terenkripsi dicatat sebagai `[diubah]` tanpa isi.
- Setiap tab punya aksi Simpan sendiri, dengan bar simpan lengket di mobile.
- Perubahan berlaku segera melalui cache yang diinvalidasi pada saat penyimpanan.

### 7.5 Hero Section Builder

- Daftar slide dengan urutan yang dapat diseret.
- Per slide: judul `id`/`en`, subjudul `id`/`en`, dua tombol (label dan URL tiap tombol), berkas latar (gambar atau video), titik fokus gambar, rentang tanggal tayang opsional, status aktif.
- Pratinjau langsung dalam bentuk bingkai ponsel dan bingkai desktop.
- Validasi: minimal satu slide aktif; bila tidak ada, home memakai hero bawaan dari gambar pertama produk unggulan.
- Batas: maksimum 5 slide, video maksimum 8 MB dan 15 detik.

---

## 8. Alur Belanja

1. **Tambah ke keranjang.** Keranjang berbasis sesi untuk tamu, dipindah ke akun saat login. Respons tanpa reload, dengan umpan balik visual pada ikon keranjang dan toast ringkas.
2. **Keranjang.** Ubah jumlah, hapus item, masukkan kode diskon, lihat ringkasan biaya. Stok divalidasi ulang setiap kali keranjang dibuka.
3. **Checkout.** Data tamu: nama, email, nomor WhatsApp, alamat lengkap, kota, kode pos, catatan. Bila login, data alamat terisi otomatis. Metode pengiriman: kurir dipilih dari daftar statis (Bagian 16).
4. **Buat pesanan.** Server memvalidasi stok dan harga ulang dari database, membuat `orders` dan `order_items` dengan snapshot harga, mengurangi stok dalam satu transaksi database, lalu membuat nomor pesanan berformat `IK-YYYYMMDD-XXXX`.
5. **Pembayaran.** Mode PayPal: dialihkan ke PayPal, status diperbarui lewat webhook yang tanda tangannya diverifikasi. Mode manual: halaman instruksi berisi rekening dan unggah bukti transfer; admin memverifikasi manual.
6. **Notifikasi.** Email dan pesan WhatsApp otomatis ke pelanggan pada saat pesanan dibuat, saat pembayaran diterima, dan saat barang dikirim (berisi nomor resi).
7. **Pelacakan.** Halaman `/lacak` menampilkan status dengan nomor pesanan dan email, dibatasi rate limit untuk mencegah enumerasi.

**Aturan stok:** stok dikurangi pada saat pesanan dibuat, bukan pada saat pembayaran, untuk mencegah overselling. Pesanan `pending` yang tidak dibayar dalam 24 jam otomatis dibatalkan oleh penjadwal, dan stok dikembalikan.

---

## 9. Model Data

Basis data SQLite dengan WAL aktif. Kunci primer internal berupa integer untuk kecepatan relasi, sementara seluruh rute publik memakai ULID untuk mencegah enumerasi ID.

### 9.1 Tabel

| Tabel | Isi Pokok |
| :--- | :--- |
| `users` | Akun admin dan pelanggan. `name`, `email`, `phone_number` terenkripsi, ditambah `email_bidx` sebagai indeks buta HMAC-SHA256. |
| `addresses` | Buku alamat pelanggan. Kolom alamat terenkripsi. |
| `categories` | Kategori produk, `name_id`/`name_en`, slug, status aktif, urutan. |
| `products` | Produk induk, `title_id`/`title_en`, `description_id`/`description_en`, harga dasar, unggulan, publikasi, dilihat. |
| `product_variants` | Varian, penyesuaian harga, stok, status aktif. |
| `product_media` | Gambar, video, audio, model 3D. Menyimpan jalur asli, jalur WebP, empat ukuran turunan, dimensi, teks alternatif, urutan, dan penanda gambar utama. |
| `orders` | Pesanan dengan nomor publik, status, ringkasan biaya, snapshot alamat terenkripsi, data kurir dan resi. |
| `order_items` | Item dengan snapshot nama, harga, dan jumlah saat transaksi. |
| `order_status_history` | Riwayat perubahan status beserta pelaku dan catatan. |
| `payments` | Transaksi pembayaran, penyedia, ID eksternal, status, nilai, dan muatan webhook. |
| `carts` / `cart_items` | Keranjang persisten untuk pengguna yang login. Keranjang tamu memakai sesi. |
| `coupons` | Kode diskon, tipe, nilai, minimal belanja, kuota, rentang tanggal. |
| `settings` | Pasangan kunci dan nilai dengan grup, tipe, dan penanda enkripsi. |
| `hero_slides` | Slide hero, teks dua bahasa, berkas, tautan tombol, urutan, jadwal tayang. |
| `contact_messages` | Pesan dari halaman kontak, beserta status sudah dibaca dan balasan. |
| `security_logs` | Catatan keamanan: login gagal, blokir brute force, akses tidak sah, rate limit terlampaui. IP disimpan sebagai hash SHA-256. |
| `activity_logs` | Jejak perubahan data oleh admin. |
| `media_upload_logs` | Jejak unggahan berkas: berkas asli, ukuran, hash, MIME terdeteksi, hasil pemrosesan, waktu. |
| `password_reset_tokens`, `sessions`, `cache`, `jobs` | Tabel bawaan Laravel. |

### 9.2 Aturan Penyimpanan Data Sensitif

1. Kata sandi memakai Argon2id.
2. Kolom PII (`name`, `phone_number`, `recipient_name`, `address_line_1`, `address_line_2`, `shipping_address_snapshot`) memakai cast `encrypted`.
3. Email memakai cast `encrypted` plus `email_bidx`, karena kolom terenkripsi tidak dapat di-`LIKE`. Semua pencarian email wajib lewat `email_bidx`.
4. Kredensial pembayaran memakai cast `encrypted` dan tidak pernah dikirim ke antarmuka.
5. Token yang disimpan memakai hash SHA-256, bukan nilai asli.
6. IP hanya disimpan sebagai hash, kecuali pada tabel penalti sementara yang dibutuhkan untuk blokir aktif.

### 9.3 Indeks Wajib

`products(slug)`, `products(category_id)`, `products(is_featured, is_published)`, `products(sku)`, `product_variants(product_id)`, `product_media(product_id, display_order)`, `orders(order_number)`, `orders(status, created_at)`, `orders(user_id)`, `order_items(order_id)`, `email_bidx` unik, `settings(group)`, `security_logs(ip_hash, created_at)`, `contact_messages(is_read, created_at)`.

---

## 10. Seeder

Seeder wajib deterministik (memakai `mt_srand` dengan nilai tetap) dan idempoten (memakai `updateOrCreate` berdasarkan kunci alami), sehingga dapat dijalankan berulang tanpa menggandakan data.

### 10.1 Urutan Eksekusi

| Urutan | Seeder | Isi | Jumlah |
| :--- | :--- | :--- | :--- |
| 1 | `SettingSeeder` | Seluruh kunci pada Bagian 7.4 dengan nilai bawaan yang wajar, nama toko "Iksan Knalpot", nomor WhatsApp contoh, alamat contoh | ~45 baris |
| 2 | `UserSeeder` | Satu super admin (`admin@iksanknalpot.test`), satu admin biasa, tiga pelanggan dengan buku alamat | 5 |
| 3 | `CategorySeeder` | Kategori: Full System, Slip-On, Header, Muffler, Racing, Aksesoris | 6 |
| 4 | `ProductSeeder` | 24 produk tersebar merata, 8 di antaranya unggulan, 2 disembunyikan, 1 tanpa stok, 1 tanpa gambar (untuk menguji peringatan dashboard) | 24 |
| 5 | `ProductVariantSeeder` | 1-3 varian per produk, mencakup kasus: varian tunggal, varian dengan penyesuaian harga negatif, varian stok nol | ~50 |
| 6 | `ProductMediaSeeder` | 2-5 gambar per produk dari berkas placeholder, ditandai untuk diganti sebelum produksi | ~90 |
| 7 | `HeroSlideSeeder` | Tiga slide, teks Indonesia dan Inggris | 3 |
| 8 | `CustomerSeeder` | Tiga pelanggan dengan alamat dan riwayat | 3 |
| 9 | `OrderSeeder` | 30 pesanan tersebar pada seluruh status dan 90 hari terakhir, lengkap dengan item, riwayat status, dan pembayaran | 30 |
| 10 | `CouponSeeder` | `IKSAN10` (10%, minimal Rp 500.000), `GRATISONGKIR` (potongan ongkir), satu kupon kedaluwarsa untuk menguji validasi | 3 |
| 11 | `ContactMessageSeeder` | Pesan kontak, 3 belum dibaca | 8 |

### 10.2 Data Contoh Produk

Nama produk harus realistis untuk toko knalpot, contoh: "Knalpot Full System NMAX V2 Titanium", "Slip-On Racing Vario 160", "Header Stainless Beat FI", "Muffler Carbon R15", "Knalpot Racing Jupiter MX", "Aksesoris Bracket Universal". Harga antara Rp 150.000 dan Rp 6.500.000. SKU berpola `IK-[KATEGORI]-[NOMOR]`.

### 10.3 Berkas Placeholder

Seeder gambar memakai berkas yang digenerate saat seeding dengan GD (warna solid plus teks nama produk), bukan berkas biner yang disimpan di repositori. Berkas diberi nama penanda dan ditandai di dashboard sebagai "foto contoh, ganti sebelum publikasi".

### 10.4 Perintah

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=ProductSeeder
php artisan db:seed --class=DemoSeeder   # hanya pesanan dan pelanggan
```

`DatabaseSeeder` memanggil seluruh seeder di atas. Menjalankan `migrate:fresh` pada lingkungan produksi harus diblokir dengan pengaman eksplisit.

---

## 11. Rate Limiting dan Pencegahan Brute Force

Seluruh pembatasan memakai `RateLimiter` Laravel dengan penyimpanan cache database, karena SQLite dan tanpa Redis.

### 11.1 Tabel Batas

| Aksi | Kunci | Batas | Respons saat terlampaui |
| :--- | :--- | :--- | :--- |
| Login admin | email + IP | 5 per menit, lalu 15 menit terkunci | 429 + halaman tunggu, dicatat ke `security_logs` |
| Login pelanggan | email + IP | 5 per menit, 20 per jam | 429 |
| Login gagal berturut-turut | IP | 10 dalam 30 menit | Blokir IP 1 jam, dicatat |
| Daftar akun | IP | 5 per jam | 429 |
| Reset kata sandi | email | 3 per jam | 429, pesan generik |
| Form kontak | IP | 3 per jam | 429 |
| Tambah ke keranjang | sesi | 60 per menit | 429 |
| Checkout | IP + sesi | 5 per 10 menit | 429 |
| Lacak pesanan | IP | 10 per menit | 429 |
| Pencarian | IP | 30 per menit | 429 |
| Unggah media admin | pengguna | 60 per jam | 429 |
| Unggah bukti transfer | IP + pesanan | 3 per jam | 429 |
| Webhook PayPal | IP penyedia | Bebas, tetapi diverifikasi tanda tangan | Tolak bila tanda tangan tidak sah |

### 11.2 Perlindungan Brute Force

1. Kegagalan login dihitung per kombinasi email dan IP, dengan penundaan bertingkat 1, 2, 4, 8 detik sebelum percobaan berikutnya diizinkan.
2. Setelah 10 kegagalan dari satu IP dalam 30 menit, IP diblokir 1 jam. Blokir dapat dibuka manual dari panel `/admin/keamanan`.
3. Setiap login berhasil memicu regenerasi ID sesi dan pemutaran token remember.
4. Akun admin wajib 2FA TOTP. Kode cadangan dibuat sekali dan ditampilkan sekali saja.
5. Form publik dilengkapi honeypot tersembunyi dan token waktu minimum 2 detik, untuk menyaring bot tanpa CAPTCHA.
6. Pesan galat login selalu seragam, tidak membedakan email tidak terdaftar dari kata sandi salah.
7. Panel `/admin/keamanan` menampilkan 100 peristiwa terakhir dan grafik percobaan per jam.

### 11.3 Header Keamanan

`Content-Security-Policy` tanpa `unsafe-inline` (memakai nonce untuk skrip inline yang diperlukan), `Strict-Transport-Security` maksimum 1 tahun, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` mematikan kamera dan mikrofon kecuali di panel admin.

Cookie sesi: `Secure`, `HttpOnly`, `SameSite=Lax`. Masa hidup sesi 120 menit dengan `expire_on_close` untuk admin.

---

## 12. Keamanan Data

1. **Sanitasi masukan.** Seluruh masukan divalidasi di sisi server dengan aturan Laravel. Semua keluaran di-escape Blade. Konten kaya dari editor admin dibersihkan dengan daftar putih tag dan atribut sebelum disimpan.
2. **CSRF.** Aktif pada seluruh rute POST, PUT, PATCH, DELETE. Form Livewire memakai token bawaan.
3. **Otorisasi.** Kebijakan per model untuk setiap aksi admin. Pemeriksaan peran dijalankan di server, bukan hanya dengan menyembunyikan tombol.
4. **IDOR.** Seluruh rute publik dan API memakai ULID. Nomor pesanan bersifat acak dan tidak berurutan.
5. **Mass assignment.** Hanya kolom yang didaftarkan eksplisit pada `fillable` atau memakai objek data terstruktur.
6. **Kebocoran berkas.** Berkas unggahan disimpan di luar akar publik. Pengiriman berkas sensitif (bukti transfer, invoice) melalui rute terkontrol dengan URL bertanda tangan berbatas waktu 15 menit.
7. **Enumerasi.** Pesan galat generik pada login, reset, dan lacak pesanan. Header `X-Powered-By` dan versi framework disembunyikan.
8. **Pencatatan.** Setiap perubahan data oleh admin dicatat di `activity_logs`. Setiap peristiwa keamanan dicatat di `security_logs`.
9. **Cadangan.** Perintah `php artisan iksan:backup` menyalin berkas database dan folder unggahan ke direktori bercap waktu, menyimpan 14 salinan terakhir, dan menolak berjalan bila ruang disk di bawah 500 MB. Dijadwalkan harian pukul 02.00.
10. **Rahasia.** Tidak ada kredensial di repositori. `.env` diabaikan. Kunci aplikasi dirotasi bila terjadi kebocoran.

---

## 13. Performa

### 13.1 Target

| Metrik | Target ponsel 4G | Target desktop |
| :--- | :--- | :--- |
| TTFB | < 400 ms | < 200 ms |
| LCP | < 2,5 s | < 1,5 s |
| CLS | < 0,1 | < 0,05 |
| INP | < 200 ms | < 100 ms |
| Bobot halaman home | < 900 KB | < 1,2 MB |
| Kueri basis data per halaman | <= 15 | <= 15 |

### 13.2 Tindakan

**Basis data.** SQLite dengan `journal_mode=WAL`, `synchronous=NORMAL`, `cache_size` dinaikkan. Seluruh relasi dimuat dengan eager loading. Tidak ada kueri di dalam perulangan Blade atau Livewire. Kueri lambat di atas 100 ms dicatat di log saat mode debug.

**Cache.** Daftar kategori, pengaturan situs, dan slide hero di-cache permanen dan diinvalidasi saat data berubah. Halaman home di-cache utuh selama 10 menit. Daftar produk di-cache per kombinasi filter dan halaman selama 5 menit. Potongan Blade untuk kartu produk di-cache selama 1 jam.

**Aset.** Dikelola Vite, keluaran diminifikasi dan diberi cap hash. CSS dikirim dalam satu berkas. JavaScript dijaga di bawah 60 KB terkompresi, tanpa pustaka besar. Font dipangkas ke karakter yang dipakai dan dimuat dengan `font-display: swap` serta `preload` untuk dua berkas pertama.

**Gambar.** Semua gambar produk disajikan sebagai WebP, dengan `srcset` empat ukuran, `loading="lazy"` dan `decoding="async"` untuk gambar di bawah lipatan, serta `fetchpriority="high"` hanya untuk gambar hero dan gambar utama produk. Lebar dan tinggi selalu ditulis eksplisit untuk mencegah pergeseran tata letak.

**Pengiriman.** Kompresi Brotli atau Gzip aktif. Header `Cache-Control` panjang untuk aset bercap hash, dan pendek untuk HTML. Gambar unggahan di-cache satu tahun di sisi peramban.

**Basis data terjadwal.** Pembersihan sesi kedaluwarsa, cache, dan `security_logs` lebih dari 90 hari dijalankan setiap malam.

---

## 14. Pemrosesan dan Optimasi Unggah Gambar

Berlaku untuk setiap unggahan berkas, termasuk logo, ikon, slide hero, dan gambar produk.

### 14.1 Validasi Berkas Masuk

| Pemeriksaan | Aturan | Tindakan bila gagal |
| :--- | :--- | :--- |
| Ukuran | Maksimum 5 MB per berkas gambar, 8 MB per video | Tolak dengan pesan |
| Tipe terdeteksi | Diperiksa dengan `finfo` pada isi berkas, bukan ekstensi | Tolak |
| Daftar putih | `image/jpeg`, `image/png`, `image/webp`, `image/avif`. Video: `video/mp4` | Tolak |
| SVG | Ditolak pada MVP | Tolak |
| Dimensi | Maksimum 6000 px pada sisi terpanjang, minimum 200 px | Tolak |
| Rasio ekstrem | Tolak bila sisi terpanjang lebih dari 20 kali sisi terpendek | Tolak |
| GIF | Ditolak pada MVP | Tolak |
| Nama berkas | Nama asli dibuang, tidak pernah dipakai sebagai jalur | Ganti dengan ULID |
| Isi ganda | Hash SHA-256 dari isi dicatat; unggahan identik berulang ditolak dengan peringatan, bukan disimpan dua kali | Tolak simpan |
| Kuota | Maksimum 200 berkas per produk, total media per produk maksimum 50 MB | Tolak |

Verifikasi ulang bahwa berkas hasil pemrosesan masih berupa gambar yang sah, untuk mencegah berkas poliglot.

### 14.2 Jalur Pemrosesan

1. Berkas pindah ke direktori karantina di luar akar publik.
2. Tipe dideteksi ulang dari isi.
3. Orientasi dibaca dari EXIF, lalu gambar diputar sesuai orientasi tersebut.
4. Gambar didekode ulang dengan GD atau Imagick. Metadata, profil warna, dan seluruh EXIF dibuang, sehingga koordinat GPS dan informasi kamera tidak pernah tersimpan.
5. Skala turun ke lebar maksimum 1600 px dengan filter berkualitas tinggi. Tidak ada pembesaran gambar kecil.
6. Encode ke WebP kualitas 82 dengan mode `method=6`. Bila AVIF tersedia, buat juga versi AVIF (opsional, Fase 2).
7. Turunan yang dihasilkan:

| Nama | Ukuran | Dipakai untuk |
| :--- | :--- | :--- |
| `thumb` | 320x240 | Keranjang, daftar admin |
| `card` | 640x480 | Kartu katalog, grid home |
| `full` | 1600x1200 | Halaman detail produk |
| `og` | 1200x630 | Pratinjau media sosial, dipotong tengah |

8. Nama berkas akhir: ULID ditambah akhiran ukuran, contoh `01JC8X...-card.webp`.
9. Berkas disimpan pada `storage/app/public/products/{product_ulid}/`, dihubungkan lewat symlink ke `public/storage`.
10. Baris `product_media` dan `media_upload_logs` ditulis, dan cache halaman produk diinvalidasi.
11. Berkas asli dihitung hash-nya dan dibuang setelah pemrosesan berhasil.

### 14.3 Pembatasan Sumber Daya

- Pemrosesan gambar dijalankan sebagai pekerjaan antrean agar permintaan HTTP tidak menunggu. Antarmuka menampilkan status unggah per berkas.
- Batas memori PHP 256 MB dan waktu eksekusi 60 detik khusus pekerjaan pemrosesan gambar.
- Bila pemrosesan gagal, berkas asli dipertahankan maksimum 24 jam untuk dicoba ulang, lalu dihapus otomatis.
- Maksimum 4 pekerjaan pemrosesan berjalan bersamaan untuk mencegah mesin kehabisan memori.
- Respons unggah menyertakan alasan kegagalan yang spesifik, bukan pesan umum.

### 14.4 Header Penyajian

Gambar disajikan dengan `Content-Type` yang benar, `Content-Disposition: inline`, dan `X-Content-Type-Options: nosniff`. Direktori unggahan tidak pernah mengeksekusi skrip.

---

## 15. Kriteria Penerimaan

**Front**

- [ ] Enam section home tampil lengkap dan urutannya sesuai Bagian 4.
- [ ] Hero mengisi penuh tinggi viewport pada ponsel, tablet, dan desktop, tanpa memicu scroll horizontal.
- [ ] Navbar memuat logo, menu, sakelar bahasa, sakelar tema, pencarian, dan ikon keranjang dengan badge jumlah yang akurat.
- [ ] Pada lebar di bawah 1024 px, muncul bar navigasi bawah dengan empat slot dan seluruh konten tetap terbaca.
- [ ] Tidak ada scroll horizontal pada lebar 320 px di seluruh halaman front.
- [ ] Sakelar bahasa mengubah seluruh teks dan isi, dan pilihannya bertahan setelah halaman dimuat ulang.
- [ ] Sakelar tema tidak menyebabkan kedip saat muat, dan mengikuti preferensi sistem bila belum dipilih manual.
- [ ] Tombol WhatsApp melayang tampil di semua halaman dan pesannya berbeda sesuai konteks halaman.
- [ ] Tombol "Lihat Semua Produk" mengarah ke `/produk`.
- [ ] Footer menarik alamat, kontak, dan media sosial dari pengaturan, dan menumpuk menjadi akordeon di mobile.

**Admin**

- [ ] Sidebar tetap tampil di lebar >= 1024 px dan dapat diringkas.
- [ ] Di bawah 1024 px, sidebar berubah menjadi drawer, dan seluruh tabel berubah menjadi daftar kartu.
- [ ] Form panjang memakai bar simpan lengket di mobile.
- [ ] Invoice, resi, dan surat jalan dapat dicetak sebagai PDF yang benar pada pratinjau cetak.
- [ ] Perubahan pada tab Web Setting langsung terlihat di situs depan dalam waktu kurang dari 10 detik.
- [ ] Kredensial PayPal tidak pernah dikirim balik ke antarmuka setelah disimpan.
- [ ] Hero builder menampilkan pratinjau ponsel dan desktop.

**Keamanan dan kualitas**

- [ ] Percobaan login ke-6 dalam satu menit ditolak dan tercatat di `security_logs`.
- [ ] Sebelas kegagalan login dari satu IP dalam 30 menit memicu blokir satu jam.
- [ ] Seluruh endpoint pada tabel Bagian 11.1 mengembalikan 429 saat batas terlampaui.
- [ ] Berkas PHP bernama `.php` yang diubah ekstensinya menjadi `.jpg` ditolak pada unggahan.
- [ ] Berkas JPEG berisi koordinat GPS, setelah diunggah, tidak lagi memuat EXIF GPS.
- [ ] Kata sandi tersimpan sebagai hash Argon2id, dan kolom nama serta telepon tidak terbaca sebagai teks biasa saat database dibuka langsung.
- [ ] Waktu muat halaman home di bawah 2,5 detik LCP pada simulasi 4G.
- [ ] `php artisan migrate:fresh --seed` berjalan bersih dari nol dan menghasilkan data yang dapat dipakai untuk seluruh alur pada Bagian 8.

---

## 16. Pertanyaan Terbuka dan Fase 2

**Perlu keputusan sebelum implementasi:**

| # | Pertanyaan | Dampak |
| :--- | :--- | :--- |
| 1 | Stack dikonfirmasi Laravel 12 + Livewire 3 + Tailwind + SQLite? Panel admin dibangun custom dengan Livewire, tanpa Filament, sesuai kebiasaan proyek sebelumnya | Menentukan struktur seluruh kode |
| 2 | Pembayaran hanya PayPal, atau perlu Midtrans atau Xendit untuk pelanggan dalam negeri? | Menentukan modul pembayaran dan biaya transaksi |
| 3 | Resi diisi manual atau perlu integrasi API kurir (JNE, J&T, SiCepat, RajaOngkir untuk ongkir otomatis)? | Berpengaruh besar pada tahap checkout dan cetak resi |
| 4 | Estimasi ongkir: tarif datar, per wilayah, atau memanggil API? | Menentukan tabel tarif dan alur checkout |
| 5 | Perkiraan jumlah produk saat peluncuran | Menentukan apakah SQLite cukup atau perlu MySQL sejak awal |
| 6 | Apakah perlu fitur ulasan dan rating produk? | Menambah satu modul dan satu tabel |
| 7 | Nama domain dan penyedia hosting | Menentukan konfigurasi HTTPS, cron, dan penyimpanan |
| 8 | Apakah pelanggan wajib mendaftar akun, atau checkout tamu sudah cukup? | Menentukan kompleksitas modul akun |

**Fase 2 (setelah MVP):**

- Preview 3D produk (`<model-viewer>`, format `.glb`).
- Preview suara knalpot dengan pemutar dan pembanding A/B.
- PWA penuh: manifest, service worker, shell offline, tombol pasang di layar utama.
- Mode AVIF dan CDN tepi.
- Integrasi kurir dan pelacakan otomatis.
- Kupon bertingkat dan program loyalitas.
- Blog atau artikel untuk SEO.
