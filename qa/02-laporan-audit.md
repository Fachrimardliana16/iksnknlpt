# 02 — Laporan Audit Keamanan (F10-15)

Tanggal: 20 Oktober 2026
Aplikasi: Iksan Knalpot — Laravel 12 + Livewire 3, SQLite
Metode: `web-application-audit` — perintah nyata, bukan pembacaan kode saja.
Rencana: `qa/01-rencana-audit.md`

## Ringkasan

| Kelompok | Diuji | Lulus | Temuan |
| :--- | ---: | ---: | ---: |
| A — Halaman publik | 5 | 5 | 0 |
| B — Autentikasi & sesi | 4 | 4 | 0 |
| C — Otorisasi data (IDOR) | 4 | 4 | 0 |
| D — Header & konfigurasi | 5 | 5 | 1 (sudah diperbaiki) |
| E — Ketahanan | 5 | 5 | 0 |
| F — Ketergantungan & perintah | 5 | 5 | 0 |
| **Suite test penuh** | **317** | **316** | 1 dilewati (lihat C-01) |

`composer audit`: **0 kerentanan**. `npm audit`: **0 kerentanan** (158 dependensi).
Suite: `Tests: 316 passed, 1 skipped (1072 assertions)`.

## Temuan yang diperbaiki

### D-01 — `X-Powered-By: PHP/8.4.25` tetap dikirim

**Bukti.** `curl -sD - -o /dev/null http://127.0.0.1:8100/`:
```
X-Powered-By: PHP/8.4.25
```
Padahal `SecurityHeaders::handle()` sudah memanggil `$response->headers->remove('X-Powered-By')`.

**Sebab.** Header itu ditulis PHP di lapisan SAPI, setelah seluruh middleware selesai. Yang
menentukan adalah `expose_php`. Dibuktikan dari baris perintah:
`php -d expose_php=0 -r 'ini_get("expose_php")'` → `"0"`, tanpa `-d` → `"1"`.

**Perbaikan.** `expose_php = Off` pada php.ini yang dilaporkan `php --ini`
(`...\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.ini`, baris 398).
Diuji `HeaderPhpTest` — gagal sebelum, lulus sesudah.

**Untuk server produksi.** Setelan ini milik php.ini, bukan repositori. Pada pemasangan baru
yang memakai build PHP lain, nilai yang sama harus disetel ulang; `HeaderPhpTest` akan gagal
dan memberi tahu.

## Yang sudah baik (diverifikasi, bukan diasumsikan)

- **CSP dengan nonce, tanpa `unsafe-inline` di `script-src`**:
  `script-src 'self' 'nonce-<acak>' 'strict-dynamic' 'unsafe-eval' …`.
  `unsafe-eval` memang diperlukan Alpine; `unsafe-inline` tidak dipakai.
  `style-src` masih memuatnya karena Tailwind menulis atribut `style`.
- **Tanda tangan URL untuk berkas sensitif**: tanpa `signature=` → 403; tanda tangan
  kedaluwarsa → 403; hanya URL 15 menit yang sah.
- **Tidak ada rute yang mengikat kunci primer**: pemindaian `Route::getRoutes()` atas
  `parameterNames()` × `bindingFields()` → 0 pelanggaran. Pemakaian `{product:slug}`,
  `{order:ulid}`, `{payment:ulid}`.
- **Otorisasi dua lapis**: middleware `PastikanAdmin` (peran diperiksa di server, percobaan
  tidak sah dicatat) + `KebijakanPanel`. Pelanggan login → 403 pada seluruh panel.
- **Sanitasi HTML**: `<script>` dan isinya hilang, `onerror=`/`onclick=`/`href="javascript:"`
  dibuang, `<b>` dipertahankan.
- **Berkas bukti bayar** tersimpan di disk privat; satu-satunya jalan mengambilnya adalah rute
  bertanda tangan.
- **Unggahan**: tipe dideteksi dari isi berkas (`finfo`), berkas `.php` berkedok `.jpg` ditolak,
  tidak ada `.php`/`.phtml`/`.phar`/`.htaccess` di disk unggahan (0 temuan).
- **Batas laju** 9 baris PRD Bagian 11.1 terpasang dengan nilai yang sama; `/lacak` menjawab
  429 sejak permintaan ke-11.
- **Webhook PayPal** dengan tanda tangan palsu ditolak; pesanan tidak berubah; webhook ganda
  tidak menghasilkan dua baris pembayaran.

## Yang masih terbuka (bukan temuan baru, sudah dicatat sebelumnya)

### C-01 — Penggabungan keranjang tamu belum dipanggil dari rute mana pun
`Keranjang::gabungkanKe()` teruji di tingkat layanan (F7-02), tetapi aplikasi belum punya
login pelanggan sehingga tidak ada tempat memanggilnya. Bukan celah keamanan; fiturnya belum ada.

### E-01 — `checkout` menggunakan `RateLimiter` di dalam komponen, bukan middleware
Batas 5 per 10 menit berjalan, tetapi jawabannya berupa pesan di halaman (HTTP 200), bukan
429 seperti `/lacak` dan pencarian. Konsisten dengan rancangan komponen, berbeda dari baris
PRD yang menyebut 429 untuk seluruh baris tabel.

### F-01 — Suite memiliki 1 test dilewati
`KeamananMenyeluruhTest::test_berkas_php_berkedok_gambar_ditolak` sengaja dilewati karena
perilaku yang sama sudah diuji di `UnggahanGambarTest` (F3-06) dengan berkas nyata di disk —
di Windows, `createWithContent()` memakai `tmpfile()` yang tidak dapat dibaca `finfo`.

## Perintah reproduksi

```bash
# Header dan halaman
curl -sD - -o /dev/null http://127.0.0.1:8100/ | grep -iE 'content-security|x-powered|x-frame|referrer|permissions'
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8100/admin        # 302
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8100/produk/tidak-ada   # 404

# Batas laju
for i in $(seq 1 12); do curl -s -o /dev/null -w '%{http_code} ' http://127.0.0.1:8100/lacak; done

# Ketergantungan
php C:/ProgramData/ComposerSetup/bin/composer.phar audit
npm audit --json

# Suite
php -d max_execution_time=0 artisan test

# Harness sistem nyata (F6, F7, F8)
python tools/ukur-f6.py
php tools/uji-f7.php
php tools/uji-f8.php
```
