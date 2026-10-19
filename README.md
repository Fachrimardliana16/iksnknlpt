# Iksan Knalpot

Toko knalpot: katalog, keranjang, pemesanan, pembayaran transfer manual, dan
panel admin. Laravel 12 + Livewire 3 + Tailwind 4.

Spesifikasi lengkap ada di `PRD.md`, `DBSCHEMA.md`, dan `WORKFLOW.md`.
Kemajuan per tugas ada di `CHECKLIST.md`.

---

## Menjalankan dari nol

### 1. Yang harus ada di mesin

- PHP **8.2 atau lebih baru** dengan ekstensi: `pdo_sqlite`, `sqlite3`,
  `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `intl`, `gd`, `gmp`
- Composer 2
- Node.js 20 atau lebih baru

`fileinfo`, `gd`, dan `gmp` bukan pilihan: `fileinfo` dipakai memeriksa isi
berkas unggahan (bukan sekadar namanya), `gd` membuat empat turunan WebP dari
setiap gambar, dan `gmp` menghitung sidik jari pencarian pada kolom terenkripsi.

Periksa sekali jalan:

```bash
php -m | grep -E "pdo_sqlite|sqlite3|fileinfo|mbstring|openssl|curl|zip|intl|gd|gmp"
```

### 2. Pasang

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
```

Atau seluruh langkah di atas sekaligus:

```bash
composer setup
```

### 3. Nyalakan

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Pekerja antrean harus jalan di jendela terpisah. Notifikasi pesanan
(email dan WhatsApp) dikirim sebagai pekerjaan antrean — tanpa ini, pesanan
tetap masuk tetapi pemberitahuannya tidak pernah terkirim:

```bash
php artisan queue:work
```

### 4. Akun demo

Dibuat oleh `db:seed`, semuanya bersandi `password`:

| Peran | Surel |
| :--- | :--- |
| Super Admin | `admin@iksanknalpot.test` |
| Admin (staf) | `staff@iksanknalpot.test` |

Panel admin: `/admin/login`

**Ganti kedua sandi ini sebelum dipakai sungguhan.**

Yang membedakan keduanya:

- **Super Admin** — mengubah pengaturan situs (termasuk kredensial pembayaran),
  membuka blokir alamat, dan mengubah peran akun.
- **Admin** — seluruh pekerjaan harian: produk, kategori, pesanan, pelanggan,
  kupon, pesan kontak, verifikasi pembayaran.

Perbedaan ini ditegakkan di server, bukan sekadar menyembunyikan tombol.

---

## Pengaturan penting

Nilai yang sering perlu diubah ada di `/admin/pengaturan`:

- **Rekening tujuan transfer** dan nama pemiliknya
- **Nama dan deskripsi situs**, logo, favicon
- **Mode pembayaran** — `manual` (transfer + unggah bukti), `midtrans`,
  atau `both`
- **Ongkir tetap** dan **persen pajak**
- **Nomor WhatsApp** tujuan notifikasi

Kredensial (kunci Midtrans, sandi SMTP) disimpan **terenkripsi** dan tidak
pernah ditampilkan kembali — hanya bisa ditimpa atau dihapus.

---

## Pembayaran transfer manual

Alurnya:

1. Pembeli checkout, pesanan masuk berstatus `pending` / `unpaid`.
2. Pembeli membuka halaman pesanan dan mengunggah bukti transfer.
   Berkas diperiksa **isinya**, bukan namanya. Batas 3 unggahan per jam.
3. Bukti masuk ke disk privat, tidak dapat diakses lewat URL biasa.
   Admin melihatnya lewat URL bertanda tangan berumur 15 menit, dan sesi admin
   tetap wajib.
4. Admin memverifikasi dari `/admin/pembayaran`.
5. Status pesanan berubah menjadi `paid`.

Verifikasi **tidak menyentuh stok**. Stok baru berkurang saat pesanan
ditandai **Selesai**, dan tahanan stok dilepas bila pesanan dibatalkan.

Ringkasnya:

| Kejadian | Stok fisik | Stok tertahan |
| :--- | :--- | :--- |
| Pesanan dibuat | tetap | **bertambah** |
| Dibayar / diproses / dikirim | tetap | tetap |
| Selesai | **berkurang** | **berkurang** |
| Batal | tetap | **dilepas** |

---

## Menjalankan test

```bash
php artisan test
```

Test anggaran kueri ikut berjalan di sini: setiap halaman dijaga tidak melebihi
15 kueri, dan biaya kueri tidak boleh tumbuh mengikuti jumlah produk.

---

## Catatan pemasangan

**Aset frontend harus dibangun setiap kali blade berubah.** Tailwind 4 hanya
menghasilkan kelas yang benar-benar ada saat build:

```bash
npm run build
```

Tanpa langkah ini kelas baru hilang tanpa pesan galat — halaman tetap terbuka
dengan kode 200, tetapi tampilannya rusak.

**`.htaccess` di `public/` mengatur kompresi dan cache.** Server bawaan PHP
tidak melakukannya. Pada Apache, penyimpanan aset setahun dan kompresi Brotli
langsung aktif. Pada Nginx, aturan yang setara harus ditulis di konfigurasi
situs:

```nginx
gzip on;
gzip_types text/css application/javascript image/svg+xml font/woff2;

location ~* \.(css|js|woff2|webp|svg|ico)$ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
    add_header Access-Control-Allow-Origin "*";
}

location ~* \.(html|php)$ {
    add_header Cache-Control "no-store, must-revalidate";
}
```

**Penyimpanan berkas.** Aplikasi menyajikan berkas `storage/` sendiri
(disk `public` memakai `'serve' => true`). Jangan menjalankan
`php artisan storage:link` di sini — pada berkas sistem exFAT tautan simbolik
tidak didukung, dan perintah itu menghasilkan `Invalid argument` sambil
melaporkan seolah berhasil.

**Antrean.** Notifikasi tidak terkirim tanpa `queue:work`. Untuk mencoba cepat
tanpa pekerja, setel `QUEUE_CONNECTION=sync` di `.env`.
