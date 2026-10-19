# 01 — Rencana Audit Keamanan (checklist F10-15)

Aplikasi: Iksan Knalpot (Laravel 12 + Livewire 3, SQLite)
Sasaran: seluruh permukaan yang dapat disentuh pengunjung, admin, mesin pencari, dan penyerang.
Metode: `web-application-audit` — eksekusi nyata, bukan pembacaan kode saja.

Setiap baris punya ID agar laporan dapat merujuk baris yang sama.

## A — Halaman publik

| ID | Skenario | Harapan |
| :--- | :--- | :--- |
| A1 | Beranda, katalog, detail produk, tentang, kontak, halaman statis | 200 dan terender |
| A2 | Alur belanja: detail → keranjang → checkout → pesanan sukses | 200, data tersimpan |
| A3 | `/lacak` dengan nomor sah dan nomor palsu | 200, pesan seragam |
| A4 | Slug tidak ada | 404, bukan 500 |
| A5 | `/pesanan/{ulid}/bayar` dengan ULID sah dan ULID karangan | 200 / 404 |

## B — Autentikasi dan sesi

| ID | Skenario | Harapan |
| :--- | :--- | :--- |
| B1 | `/admin` dan `/admin/pesanan` tanpa masuk | 302 ke `/admin/login` |
| B2 | Pelanggan login membuka panel | 403 |
| B3 | Masuk admin dengan kata sandi salah | ditolak, pesan seragam |
| B4 | Properti cookie sesi | HttpOnly, SameSite=Lax, umur 120 menit |

## C — Otorisasi data (IDOR)

| ID | Skenario | Harapan |
| :--- | :--- | :--- |
| C1 | Bukti bayar tanpa `signature=` | 403 |
| C2 | Bukti bayar dengan tanda tangan kedaluwarsa | 403 |
| C3 | Rute publik yang memakai kunci primer (`{id}`, `{order}`) | tidak ada |
| C4 | Halaman keamanan panel untuk pelanggan | 403 |

## D — Header dan konfigurasi

| ID | Skenario | Harapan |
| :--- | :--- | :--- |
| D1 | Header keamanan pada respons nyata | CSP+nonce, nosniff, SAMEORIGIN, Referrer, Permissions |
| D2 | `script-src` tanpa `unsafe-inline` | terpenuhi |
| D3 | HSTS pada permintaan HTTPS | `max-age=31536000` |
| D4 | `X-Powered-By` | tidak dikirim |
| D5 | `Server` tidak menyebut versi PHP | terpenuhi |

## E — Ketahanan

| ID | Skenario | Harapan |
| :--- | :--- | :--- |
| E1 | `/lacak` 12 kali berurutan | 429 sejak permintaan ke-11 |
| E2 | Pencarian 40 kali | 429 setelah 30 |
| E3 | Unggah berkas PHP berkedok `.jpg` | ditolak |
| E4 | Sanitasi HTML editor | `<script>` hilang, `<b>` tetap |
| E5 | Webhook PayPal dengan tanda tangan palsu | ditolak, pesanan tidak berubah |

## F — Ketergantungan dan perintah

| ID | Skenario | Harapan |
| :--- | :--- | :--- |
| F1 | `composer audit` | 0 kerentanan |
| F2 | `npm audit` | 0 kerentanan |
| F3 | `iksan:backup` | berkas tersimpan, sisakan 14 |
| F4 | `iksan:pangkas-data-lama` | data lama terhapus |
| F5 | Suite test penuh | hijau |
