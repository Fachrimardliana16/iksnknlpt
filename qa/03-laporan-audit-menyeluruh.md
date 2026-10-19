# 03 — Laporan Audit Menyeluruh + Uji Skenario

Tanggal: 20 Oktober 2026
Aplikasi: Iksan Knalpot — Laravel 12.70, PHP 8.4.25, Livewire 3, SQLite
Metode: `web-application-audit` — semua angka di bawah berasal dari eksekusi, bukan pembacaan kode.

Harness yang dipakai:

| Berkas | Cakupan |
| :--- | :--- |
| `tools/skenario-customer.php` | alur pembeli ujung ke ujung lewat HTTP |
| `tools/skenario-admin.php` | pekerjaan harian admin lewat HTTP |
| `tools/audit-rute.php` | 21 rute: status, waktu, ukuran |
| `tools/audit-konsol.py` | galat konsol & pelanggaran CSP per halaman |
| `tools/ukur-f6.py` | katalog & detail di peramban nyata |
| `tools/uji-f7.php`, `tools/uji-f8.php` | keranjang/checkout, pesanan/cetak |

## Ringkasan

| Bagian | Hasil |
| :--- | :--- |
| Skenario customer | **26 / 26 lulus** |
| Skenario admin | **32 / 32 lulus** |
| Suite test | **334 lulus, 1 dilewati** (1147 asersi) |
| Cakupan rute | 21 rute: 0 galat server, 0 jejak galat bocor |
| Galat konsol / CSP | **0** di 8 halaman |
| Ketergantungan | `composer audit` 0 · `npm audit` 0 |
| Temuan baru | **1** (SK-01, diperbaiki) |

## Skenario customer (`tools/skenario-customer.php`)

Alur yang dijalankan: beranda → katalog → detail produk → tambah ke keranjang lewat
`/livewire/update` dengan snapshot hasil render server → ubah catatan → checkout →
pesanan terbentuk → halaman sukses → unggah bukti → lacak pesanan.

Bukti yang dicatat, bukan sekadar status:

- tambah ke keranjang: `cart_items 1 → 2` dari tombol pada kartu produk;
- catatan pembeli sampai ke `carts.note`, lalu ikut menjadi `orders.customer_note`;
- pesanan baru `IK-20261019-SHZMC2`, total `Rp 3.750.000`, keranjang tamu dikosongkan;
- checkout menolak data tidak sah (`email` = "bukan-email") tanpa membuat pesanan;
- checkout menolak kiriman bot saat kolom jebakan `situs` terisi — pesanan tidak bertambah;
- ULID karangan pada `/pesanan/{ulid}/bayar` → **404**, bukan membocorkan pesanan lain;
- lacak dengan email salah tidak menampilkan detail pesanan.

## Skenario admin (`tools/skenario-admin.php`)

Alur: masuk panel → 10 halaman panel → saring & cari pesanan → ekspor CSV → tandai lunas →
isi resi → cetak tiga dokumen → pindah status → sunting produk → ubah pengaturan →
halaman keamanan → keluar.

Bukti utama:

- kata sandi salah ditolak; kata sandi benar → dasbor memuat tombol Keluar;
- 10 halaman panel seluruhnya **200** (Pesanan, Keranjang terbuka, Produk, Kategori, Kupon,
  Pelanggan, Pesan kontak, Keamanan, Pengaturan, Hero);
- saringan `status=pending`: 2 pesanan tampil, 2 di basis data — cocok;
- ekspor CSV: 35 baris;
- tandai lunas dari daftar → `status=paid`, `paid_at` terisi;
- resi tersimpan (`AUDIT-RESI-77`, JNE REG);
- **Cetak**: resi `100 x 150 mm`, invoice `210 x 297 mm`, surat jalan `210 x 297 mm` —
  dibaca dari `/MediaBox` di dalam berkas PDF-nya;
- perpindahan status `paid → processing` menulis riwayat dengan pelaku `#1`;
- sunting produk tersimpan (`simpanInfo`), lalu judul dikembalikan;
- pengaturan tersimpan, lalu dikembalikan;
- keluar → panel 302 lagi.

## Temuan

### SK-01 — Pesan pengecualian diteruskan ke layar pengguna (DIPERBAIKI)

**Bukti.** `grep -rn getMessage() app/Livewire` menemukan tiga tempat yang menuliskan
`$e->getMessage()` ke properti yang dirender:

```
app/Livewire/Front/UnggahBukti.php:96      $this->galat = 'Bukti gagal diunggah: '.$e->getMessage();
app/Livewire/Admin/HeroBuilder.php:376     $this->galat = 'Slide gagal disimpan: '.$e->getMessage();
app/Livewire/Admin/PengaturanSitus.php:121 $this->galat = 'Pengaturan gagal disimpan: '.$e->getMessage();
```

**Risiko.** Pesan pengecualian PHP memuat jalur berkas server, nama tabel, potongan kueri, dan
pada beberapa kasus nilai kolom. Pembeli yang mengunggah berkas rusak dapat membaca struktur
server dari pesan galatnya sendiri.

**Perbaikan.** Sebab sebenarnya dicatat ke log (`Log::warning`/`Log::error`), sedangkan pengguna
menerima pesan umum. Diuji `PesanGalatAmanTest` (4 test): memeriksa properti `galat`, dan memindai
sumber untuk pola `$this->galat = … getMessage()` → tidak ada lagi.

### SK-02 — Data demo audit ditinggalkan di basis data (DIBERSIHKAN)

Pesanan uji `IK-20261019-SHZMC2`, keranjang tamu, dan perubahan `site_tagline` dari harness
dihapus/dipulihkan sesudah audit. Harness sekarang membersihkannya sendiri.

## Yang diverifikasi baik (bukan asumsi)

- **Header**: CSP ber-nonce tanpa `unsafe-inline` di `script-src`; `nosniff`, `SAMEORIGIN`,
  `Referrer-Policy`, `Permissions-Policy`; HSTS hanya pada HTTPS; `X-Powered-By` hilang
  sesudah `expose_php = Off`.
- **Konsol peramban**: 0 galat, 0 pelanggaran CSP di 8 halaman — termasuk halaman yang
  memakai Alpine dan Livewire.
- **Rute 404**: slug produk, halaman, dan bukti bayar mengembalikan 404 berbadan kecil
  (1,7 KB) — halaman galat sendiri, bukan jejak pengecualian.
- **Waktu respons**: 267–477 ms per halaman pada SQLite tanpa cache halaman.
- **Rahasia**: tidak ada kunci/rahasia tertulis di `app/`, `config/`, `routes/`; `.env` tidak
  terlacak git.
- **SQL mentah / eval**: `DB::raw|unprepared` → 0; `eval/exec/system` hanya `PDO::exec` untuk
  PRAGMA SQLite.
- **Otorisasi**: pelanggan login → 403 di seluruh panel; tamu → 302; semua komponen panel
  berada di bawah middleware `auth` + `PastikanAdmin`.

## Yang masih terbuka (sudah diketahui, bukan temuan baru)

| ID | Hal | Alasan belum dikerjakan |
| :--- | :--- | :--- |
| O-01 | `APP_ENV=local`, `APP_DEBUG=ENABLED`, `APP_URL=127.0.0.1:8000` | Ini mesin pengembangan. Di server: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` sesuai domain — tanpanya halaman galat membocorkan jejak. |
| O-02 | Penggabungan keranjang tamu (`Keranjang::gabungkanKe`) belum dipanggil dari rute | Aplikasi belum punya login pelanggan; teruji di tingkat layanan (F7-02). |
| O-03 | Batas laju checkout dijawab sebagai pesan di halaman, bukan 429 | Berjalan di dalam komponen Livewire; berbeda dari baris PRD yang menyebut 429. |
| O-04 | CDN tepi (P2-04) belum dipasang | Butuh akun/host di luar kode. |
| O-05 | Modul blog (P2-07) belum dibangun | Butuh skema `posts`, editor, sitemap; jalur SEO hari ini lewat halaman statis. |

## Perintah reproduksi

```bash
php tools/skenario-customer.php
php tools/skenario-admin.php
php tools/audit-rute.php
python tools/audit-konsol.py
php -d max_execution_time=0 artisan test
php C:/ProgramData/ComposerSetup/bin/composer.phar audit
npm audit
```
