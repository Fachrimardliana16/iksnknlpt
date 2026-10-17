# WORKFLOW.md - Iksan Knalpot

Kesepakatan kerja. Dibaca sebelum menyentuh kode. `CHECKLIST.md` adalah daftar tugas; dokumen ini menjelaskan bagaimana tiap tugas dikerjakan dan kapan dianggap selesai.

---

## 1. Prinsip

1. **Eksekusi nyata, bukan rencana.** Tiap tugas selesai hanya bila ada bukti perintah yang dijalankan berikut keluarannya. Kalimat "sudah seharusnya jalan" bukan bukti.
2. **Uji sebelum kode.** Perilaku baru dimulai dari test yang gagal.
3. **Satu tugas, satu commit.** Pesan commit menyebut ID tugas dari `CHECKLIST.md`.
4. **Tidak ada angka karangan.** Statistik, harga, stok, dan metrik diambil dari database atau dari hasil pengukuran nyata.
5. **Blokir dilaporkan, bukan ditambal.** Bila sesuatu gagal, hentikan dan laporkan apa adanya. Tidak ada hasil yang dikarang.

---

## 2. Lingkungan (terverifikasi 17 Oktober 2026)

| Alat | Versi / Jalur | Catatan |
| :--- | :--- | :--- |
| PHP | 8.4.25 `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe` | PHP 8.3 di PATH terlalu tua untuk Laravel 13. Gunakan jalur penuh. |
| Ekstensi aktif | curl, exif, fileinfo, gd, gmp, intl, mbstring, openssl, pdo_sqlite, sqlite3, zip | **Imagick tidak ada.** Seluruh pemrosesan gambar memakai GD. |
| Composer | 2.9.5, jalankan sebagai `php.exe C:/ProgramData/ComposerSetup/bin/composer.phar` | `composer` di PATH rusak. Jangan dipakai. |
| Node | v20.20.2, npm 10.8.2 | |
| Git | 2.53.0.windows.2 | Repo belum diinisialisasi di proyek ini. |
| sqlite3 CLI | tidak ada | Periksa skema lewat PHP: `php -r` dengan `SQLite3` atau `PDO`. |

**Pemeriksaan awal.** Setiap kali sesi baru dimulai, jalankan:

```bash
PHP="$LOCALAPPDATA/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"
"$PHP" -v && "$PHP" C:/ProgramData/ComposerSetup/bin/composer.phar -V
```

Bila salah satu gagal, berhenti dan perbaiki lingkungan dulu.

**Karena `composer` rusak, seluruh perintah artisan di dokumen ini ditulis sebagai:**

```bash
"$PHP" artisan ...
"$PHP" C:/ProgramData/ComposerSetup/bin/composer.phar ...
```

---

## 3. Alur Kerja per Fase

```
baca CHECKLIST fase berikutnya
        |
tandai 1-3 tugas sebagai in_progress
        |
tulis test yang gagal (RED)
        |
implementasi sampai test hijau (GREEN)
        |
rapikan tanpa mengubah perilaku (REFACTOR)
        |
jalankan perintah verifikasi tugas tersebut
        |
commit dengan ID tugas
        |
centang tugas di CHECKLIST.md
        |
ulangi
```

Fase dijalankan berurutan. Fase berikutnya tidak dimulai sebelum gerbang (Bagian 5) fase sebelumnya lulus.

---

## 4. Konvensi

### 4.1 Git

Repo diinisialisasi pada F0-01. Tanpa repo, tidak ada titik pulang.

- Cabang utama: `main`.
- Cabang kerja: `feat/f{n}-{slug}` untuk fitur, `fix/{slug}` untuk perbaikan.
- Commit mengikuti `tipe(F2-03): ringkasan`, tipe: `feat`, `fix`, `test`, `chore`, `docs`, `perf`, `refactor`, `sec`.
- Tag per fase yang lulus: `f0-selesai`, `f1-selesai`, dan seterusnya.
- `.env`, `database/*.sqlite`, `storage/app`, dan `public/storage` tidak pernah masuk repositori.

### 4.2 Struktur Direktori

```
app/
  Actions/            aksi satu tugas (BuatPesanan, ProsesGambar)
  Data/               objek data terstruktur (spatie/laravel-data)
  Enums/              StatusPesanan, StatusPembayaran, PeranPengguna
  Http/
    Controllers/Admin/
    Controllers/Front/
    Middleware/       CatatKeamanan, BlokirIp, TolakUnggahanTidakSah
    Requests/
  Jobs/               ProsesGambarProduk, KirimNotifikasiPesanan
  Livewire/
    Admin/
    Front/
  Models/
  Policies/
  Rules/              GambarAman, FileHashUnik
  Services/
    Gambar/           ImageProcessor, ImageValidator
    Pembayaran/       PaypalGateway, ManualGateway
    Pdf/              InvoicePdf, ResiPdf, SuratJalanPdf
domain            -> app/Domain bila modul makin besar
resources/views/livewire/{admin,front}
database/{migrations,seeders,factories}
```

### 4.3 Penamaan

- Tabel: jamak, huruf kecil, garis bawah. Model: tunggal, PascalCase.
- Komponen Livewire: `Admin/ProductForm`, `Front/CartDrawer`.
- Kelas yang menjadi tugas tunggal memakai kata kerja: `BuatNomorPesanan`, `KompresGambar`.
- Bahasa: nama kelas, kolom, rute, dan kunci pengaturan memakai bahasa Inggris. Seluruh teks antarmuka memakai bahasa Indonesia dan Inggris melalui kunci terjemahan, bukan string di dalam view.
- Warna, jarak, dan tipografi hanya lewat token di `resources/css/tokens.css`. Tidak ada nilai heksadesimal di dalam view.

### 4.4 Aturan Basis Data

- Setiap relasi dimuat eksplisit. Tidak ada kueri di dalam perulangan view.
- Anggaran 15 kueri per halaman. Diuji dengan pencatat kueri pada test.
- Kolom terenkripsi tidak pernah dipakai di klausa `WHERE` atau `LIKE`. Pencarian memakai indeks buta (`*_bidx`) atau penyaringan di PHP setelah dekripsi.
- Operasi tulis yang menyentuh stok dibungkus transaksi singkat.

---

## 5. Gerbang (Gate)

Tidak lanjut fase berikutnya bila salah satu perintah ini gagal.

| Gerbang | Kapan | Perintah |
| :--- | :--- | :--- |
| **G0** | setelah F0 | `"$PHP" artisan serve` jalan, halaman awal terbuka, `npm run build` tanpa galat |
| **G1** | setelah F3 | `"$PHP" artisan migrate:fresh --seed` bersih dari nol, ulang dua kali menghasilkan data sama |
| **G2** | setelah F6 | seluruh rute front mengembalikan 200, tidak ada scroll horizontal pada 320 px |
| **G3** | setelah F8 | seluruh rute admin mengembalikan 200 untuk admin, 403 untuk tamu |
| **G4** | setelah F9 | alur pesanan ujung ke ujung lulus di mode sandbox |
| **G5** | setelah F11 | seluruh test hijau, checklist penerimaan PRD Bagian 15 tercentang dengan bukti |

Perintah gerbang standar:

```bash
"$PHP" artisan test --parallel
"$PHP" artisan migrate:fresh --seed
npm run build
```

---

## 6. Definisi Selesai

Sebuah tugas dinyatakan selesai bila **semua** benar:

- [ ] Kode ada di repositori dan sudah di-commit dengan ID tugas.
- [ ] Test untuk perilaku tersebut ada dan hijau.
- [ ] Perintah verifikasi pada tugas itu dijalankan, dan keluarannya tercatat.
- [ ] Tidak ada galat baru pada log (`storage/logs/laravel.log`) saat menjalankan alur tersebut.
- [ ] Teks antarmuka ada dalam dua bahasa.
- [ ] Tampilan diperiksa pada 320 px, 768 px, dan 1440 px.
- [ ] Baris di `CHECKLIST.md` dicentang.

Sebuah **fase** dinyatakan selesai bila seluruh tugasnya selesai dan gerbangnya lulus.

---

## 7. Pemetaan Keterampilan

| Kebutuhan | Keterampilan |
| :--- | :--- |
| Menulis fitur dengan test lebih dulu | `test-driven-development` |
| Menelusuri bug | `systematic-debugging` |
| Memeriksa sebelum commit | `requesting-code-review` |
| Menjalankan aplikasi Laravel lokal dan mengekspos ke jaringan | `laravel-app-setup` |
| Mengukur ukuran dan rasio kode | `codebase-inspection` |
| Menguji aplikasi dari sisi pengguna akhir | `dogfood` |
| Audit menyeluruh keamanan dan kualitas | `web-application-audit` |
| Mendelegasikan pekerjaan besar ke agen lain | `claude-code`, `codex`, `opencode` |

Keterampilan penulisan kode dipakai pada tugas berukuran sedang ke atas. Perubahan satu baris tidak perlu.

---

## 8. Risiko yang Sudah Terlihat

| # | Risiko | Dampak | Tindakan |
| :--- | :--- | :--- | :--- |
| 1 | SQLite menulis secara berurutan. Keranjang dan checkout menulis bersamaan | Galat "database is locked" | `busy_timeout=5000`, transaksi pendek, WAL aktif. Bila kenyataannya padat, pindah ke MySQL sebelum peluncuran |
| 2 | Imagick tidak tersedia, hanya GD | Kualitas WebP dan penanganan EXIF terbatas | GD dipakai sejak awal, jalur Imagick disiapkan sebagai adaptor agar bisa ditukar |
| 3 | `composer` di PATH rusak | Perintah gagal tanpa alasan jelas | Selalu pakai jalur penuh `composer.phar` |
| 4 | Kolom terenkripsi tidak bisa dicari | Pencarian pelanggan dan pesanan gagal senyap | Indeks buta wajib untuk setiap kolom yang perlu dicari |
| 5 | Konsistensi stok antara keranjang dan pesanan | Overselling | Stok dipotong saat pesanan dibuat, dilindungi transaksi dan kolom `reserved_quantity` |
| 6 | Rate limit berbasis cache database pada SQLite | Penulisan tambahan per permintaan | Batasi hanya pada rute sensitif, bukan seluruh situs |
| 7 | Panel admin dibangun manual tanpa Filament | Waktu pengembangan lebih panjang | Komponen tabel dan form dibuat sekali sebagai komponen dasar, dipakai ulang di seluruh modul |
| 8 | Proyek sebelumnya tidak punya repo | Tidak ada titik pulang | F0-01 wajib sebelum baris kode pertama |

---

## 9. Pelaporan

Setiap akhir fase, laporkan empat hal saja:

1. Tugas yang selesai, dengan ID.
2. Perintah verifikasi yang dijalankan dan hasilnya, apa adanya.
3. Yang gagal atau tertunda, beserta sebabnya.
4. Tugas berikutnya.

Tidak ada ringkasan ulang proses. Tidak ada klaim tanpa keluaran perintah.
