# 2410-01 — Checklist

Rencana induk: `qa/06-rencana-2410-01.md`
Aturan: satu butir baru boleh ditandai selesai hanya setelah ada bukti
eksekusi nyata (uji jalan, keluaran HTTP, atau hitungan tes) — bukan karena
kodenya sudah ditulis.

Legenda: `[ ]` belum · `[~]` sedang · `[x]` selesai + bukti · `[!]` batal/alasan

---

## Tahap 1 — Cepat, tanpa migrasi basis data · **SELESAI** (commit `2d2aec8`)

- [x] **1.1** `PesananDaftar`: `paginate(25)` → `paginate(10)`
      Bukti: `Pesanan perPage: 10  total: 31`
- [x] **1.2** `PesanKontak`: `paginate(20)` → `paginate(10)`
      Bukti: `Pesan perPage: 10  total: 8`
- [x] **1.3** Tombol **Report Penjualan** → `admin.exports.orders`
      Eksportir sudah ada; tidak ada eksportir baru ditulis.
      Bukti: `Report Penjualan: ADA`
- [x] **1.4** Saringan ringkas: 1 input + 2 chip ("Semua pembayaran", urutan)
      Bukti: chip + dropdown render; saring lewat properti Livewire jalan
      (`bayar=paid -> 28 baris`, `bayar=semua -> 31`)
- [x] **1.5** Bilah "cetak resi terpilih" disembunyikan sampai ada centang
      Bukti: `bilah-cetak hidden default: YA`; `bilah-cetak` ada di build JS
- [x] **1.6** Produk: tabel → grid kartu, `lg:grid-cols-5`, 25 per halaman
      Bukti: `grid 5 kolom: ADA`, `tabel lama hilang: SUDAH HILANG`,
      `Produk perPage: 25  total: 24`
- [x] **1.7** Suite 406/406 (1387 asersi); `/ /produk /admin/login /faq /up /kontak` → 200
- [x] **1.8** Commit tahap 1 → `2d2aec8`, working tree bersih

**Bonus ikut tercatat di tahap 1** (ditemukan sambil bekerja, bukan diminta
terpisah): import `Galat` yang hilang di `PesananDetail` (jalur
`catch (Throwable)` sebelumnya fatal), 5 kunci `lang/id/validation.php`,
hapus berkas debug `hero_admin.html` + `public/_p.php`.

## Tahap 2 — Pengguna & peran (butir 6) · **SELESAI**

- [x] **2.1** Komponen `PenggunaPeran` (Livewire): daftar akun panel, ubah peran,
      blokir/buka akun
- [x] **2.2** Aturan wewenang ditegakkan di server lewat `authorize()` +
      pemeriksaan eksplisit. Bukti: 15/15 lulus di `tools/uji-pengguna-peran.php`:
      - admin naikkan peran sendiri → peran tetap `admin`
      - admin sentuh super admin → peran super admin tetap `super_admin`
      - admin blokir super admin → tidak terblokir
      - admin blokir diri sendiri → tidak terblokir
      - super admin ubah peran & blokir → berhasil + bisa dikembalikan
- [x] **2.3** Rute `admin.users.index` (`/admin/pengguna`) + menu "Pengguna & Peran"
      di sidebar. Bukti: rute terdaftar dengan middleware
      `web, Authenticate, PastikanAdmin`
- [x] **2.4** Pemisahan jelas: halaman ini hanya menampilkan akun non-pelanggan
      (3 akun: 2 super + 1 admin), 3 pelanggan tidak muncul
- [x] **2.5** Matriks kewenangan dibaca dari `KebijakanPanel` lewat
      `Gate::forUser()` — bukan ditulis ulang di tampilan.
      Bukti: `AS AS AS -S -S -S`

**Catatan penting (bug yang ditemukan & diperbaiki saat mengerjakan):**
matriks awalnya memakai `new User(['role' => ...])`. Kolom `role` ber-cast
enum, dan cast hanya berjalan saat model dibaca dari basis data — bukan saat
dibuat dari larik. Akibatnya semua pemeriksaan mengembalikan `false` dan
matriks tampil kosong **tanpa gejala kesalahan**. Diganti
`Gate::forUser(User::query()->where(...)->first())`.

**Catatan pengujian:** Livewire menelan `AuthorizationException` di dalam
dirinya, jadi harness `Livewire::test()` tidak bisa melihat tolak otorisasi.
Karena itu yang diperiksa adalah **hasil di basis data** (peran/blokir tidak
berubah), bukan ada-tidaknya exception. "Tidak ada exception" bukan berarti
"diizinkan".

## Tahap 3 — Referral KOL (butir 9)

- [ ] **3.1** Migrasi: kolom pemilik KOL pada `coupons` (nullable + default aman,
      supaya baris lama tidak gagal)
- [ ] **3.2** Migrasi: pencatatan pemakaian kupon per pesanan (sumber performa)
- [ ] **3.3** UI: tetapkan KOL pada kupon + bentuk tautan berbagi per KOL
- [ ] **3.4** UI: halaman performa KOL (jumlah pakai, total belanja, potongan
      yang diberikan)
- [ ] **3.5** Uji: migrasi jalan pada basis data yang sudah berisi; suite hijau

## Tahap 4 — Zona pelanggan, front end (butir F1)

- [ ] **4.1** Cookie `iksan_zona`: simpan preferensi zona pengiriman pelanggan
      (bukan sekadar locale), HttpOnly + SameSite=Lax
- [ ] **4.2** Zona dipakai untuk memperkirakan ongkir / ketersediaan kirim
- [ ] **4.3** Catat persetujuan cookie (privacy notice singkat) sebelum cookie
      non-esensial ditulis
- [ ] **4.4** Uji: cookie terkirim, bertahan antar kunjungan, tidak memblokir
      halaman bila ditolak

## Tahap 5 — Keamanan pesan (butir 7)

- [ ] **5.1** Enkripsi kolom `subject` (saat ini satu-satunya kolom pesan yang
      masih teks terbuka)
- [ ] **5.2** Migrasi data: enkripsi baris `subject` yang sudah ada
- [ ] **5.3** Batasi panjang `user_agent` yang disimpan
- [ ] **5.4** Uji: pesan baru tersimpan terenkripsi; pesan lama terbaca benar;
      suite hijau

## Penutup

- [ ] **6.1** `npm run build` (setiap perubahan blade/CSS) — sudah untuk tahap 1
- [ ] **6.2** Suite penuh: `php -d max_execution_time=0 vendor/bin/phpunit`
- [ ] **6.3** Uji hidup: `/` `/produk` `/admin/login` `/admin/pesanan`
      `/admin/pesan` `/admin/produk` `/admin/pengguna` → 200
- [ ] **6.4** LAN `192.168.1.61:8100` tetap tertutup — sudah untuk tahap 1
- [ ] **6.5** Laporan `qa/07-laporan-2410-01.md`: apa yang berubah, apa yang
      dibuktikan, apa yang sisa
