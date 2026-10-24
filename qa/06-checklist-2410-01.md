# 2410-01 — Checklist

Rencana induk: `qa/06-rencana-2410-01.md`
Aturan: satu butir baru boleh ditandai selesai hanya setelah ada bukti
eksekusi nyata (uji jalan, keluaran HTTP, atau hitungan tes) — bukan karena
kodenya sudah ditulis.

Legenda: `[ ]` belum · `[~]` sedang · `[x]` selesai + bukti · `[!]` batal/alasan

---

## Tahap 1 — Cepat, tanpa migrasi basis data

- [ ] **1.1** `PesananDaftar`: `paginate(25)` → `paginate(10)`
- [ ] **1.2** `PesanKontak`: `paginate(20)` → `paginate(10)`
- [ ] **1.3** Halaman pesanan: tombol "Unduh CSV" menuju `admin.exports.orders`
      dengan label **Report Penjualan** (eksportir sudah ada, jangan tulis baru)
- [ ] **1.4** Halaman pesanan: baris saringan dirapikan — satu input pencarian
      + "Semua pembayaran" dan "Terbaru" sebagai tombol chip, bukan `<select>`
      panjang
- [ ] **1.5** Bilah "cetak resi terpilih" disembunyikan bila belum ada centang
      (`x-show` terikat jumlah terpilih, bukan `disabled` saja)
- [ ] **1.6** Halaman produk: tabel → grid kartu, 5 menyamping × 5 ke bawah
- [ ] **1.7** Uji: suite penuh tetap hijau; `/admin/pesanan`, `/admin/pesan`,
      `/admin/produk` menjawab 200
- [ ] **1.8** Commit tahap 1

## Tahap 2 — Pengguna & peran (butir 6)

- [ ] **2.1** Komponen `PenggunaPeran` (Livewire): daftar pengguna, ubah peran,
      blokir/buka akun
- [ ] **2.2** Aturan wewenang: pengguna tidak boleh menaikkan perannya sendiri;
      `admin` biasa tidak boleh menyentuh `super_admin`
- [ ] **2.3** Rute + menu "Pengguna & Peran" di sidebar admin
- [ ] **2.4** Pemisahan jelas di UI: pelanggan (`customer`) tidak muncul di
      daftar yang bisa dikelola sebagai admin
- [ ] **2.5** Uji: percobaan menaikkan peran sendiri ditolak; percobaan admin
      biasa menyentuh super_admin ditolak; suite hijau

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
- [ ] **5.3** Batasi panjang `user_agent` yang disimpan (hindari baris memanjang
      tak terbatas)
- [ ] **5.4** Uji: pesan baru tersimpan terenkripsi; pesan lama terbaca benar;
      suite hijau

## Penutup

- [ ] **6.1** `npm run build` (setiap perubahan blade/CSS)
- [ ] **6.2** Suite penuh: `php -d max_execution_time=0 vendor/bin/phpunit`
- [ ] **6.3** Uji hidup: `/` `/produk` `/admin/login` `/admin/pesanan`
      `/admin/pesan` `/admin/produk` `/admin/pengguna` → 200
- [ ] **6.4** LAN `192.168.1.61:8100` tetap tertutup
- [ ] **6.5** Laporan `qa/07-laporan-2410-01.md`: apa yang berubah, apa yang
      dibuktikan, apa yang sisa
