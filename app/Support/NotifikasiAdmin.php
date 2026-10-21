<?php

namespace App\Support;

use App\Enums\StatusPesanan;
use App\Models\User;
use App\Models\Cart;
use App\Models\NotificationRead;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Sumber notifikasi panel admin.
 *
 * Empat hal yang perlu diketahui admin tanpa harus membuka halamannya satu per
 * satu:
 *
 *   1. Stok menipis — varian yang sisa jualnya di bawah ambang.
 *   2. Stok habis — varian yang tidak dapat dipesan sama sekali.
 *   3. Produk masuk keranjang — minat yang belum menjadi pesanan.
 *   4. Produk dibeli — pesanan yang sudah masuk tetapi belum diproses.
 *   5. Hampir batal — pesanan menunggu bayar yang mendekati batas 24 jam.
 *
 * Seluruhnya dihitung dari kueri pada saat render. Tidak ada tabel notifikasi
 * terpisah: notifikasi yang disimpan akan basi, dan menyimpan salinan data yang
 * sudah ada di basis data berarti dua sumber kebenaran yang bisa berbeda.
 *
 * Setiap butir membawa tautan, sehingga notifikasi selalu dapat ditindaklanjuti
 * — notifikasi yang tidak bisa diklik hanya menambah kekhawatiran.
 *
 * STATUS DILIHAT. Satu hal tidak dapat dihitung ulang dari kueri: apakah admin
 * sudah membuka butir ini. Karena itu setiap butir membawa `sidik` — sidik jari
 * dari keadaannya sekarang (jenis + teks + rinci). Tanda "sudah dilihat"
 * disimpan di notification_reads bersama sidik itu, dan hanya berlaku selama
 * sidiknya masih sama. Begitu isinya berubah — varian yang habis bertambah,
 * jumlah pesanan naik — sidiknya tidak cocok dan butir itu kembali dihitung
 * sebagai belum dilihat. Tanda baca yang berlaku selamanya akan membuat admin
 * melewatkan stok habis yang baru terjadi.
 */
class NotifikasiAdmin
{
    /**
     * Seluruh butir yang perlu perhatian, sudah dilengkapi status dilihat.
     *
     * Setiap butir membawa:
     *   sidik     — sidik jari keadaannya sekarang
     *   dilihat   — true bila admin ini sudah membukanya pada keadaan yang sama
     *   dilihat_pada — kapan terakhir dilihat (null bila belum)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function semua(?User $pengguna = null): Collection
    {
        $butir = collect()
            ->merge(self::stokHabis())
            ->merge(self::stokMenipis())
            ->merge(self::hampirBatal())
            ->merge(self::pesananBaru())
            ->merge(self::masukKeranjang())
            ->values();

        $pengguna ??= auth()->user();

        if ($pengguna === null) {
            // Tanpa pengguna tidak ada tanda baca yang bisa dipercaya; anggap
            // semuanya belum dilihat, jangan menebak.
            return $butir->map(fn (array $b): array => $b + [
                'sidik' => self::sidik($b),
                'dilihat' => false,
                'dilihat_pada' => null,
            ]);
        }

        $tersimpan = NotificationRead::query()
            ->where('user_id', $pengguna->getKey())
            ->whereIn('notification_key', $butir->pluck('kunci')->all())
            ->get()
            ->keyBy('notification_key');

        return $butir->map(function (array $b) use ($tersimpan): array {
            $sidik = self::sidik($b);
            $catatan = $tersimpan->get($b['kunci']);

            // Tanda baca hanya sah bila keadaannya tidak berubah sejak dilihat.
            $dilihat = $catatan !== null && hash_equals((string) $catatan->fingerprint, $sidik);

            return $b + [
                'sidik' => $sidik,
                'dilihat' => $dilihat,
                'dilihat_pada' => $dilihat ? $catatan->read_at : null,
            ];
        });
    }

    /** Butir yang BELUM dilihat pada keadaan sekarang. */
    public static function belumDilihat(?User $pengguna = null): Collection
    {
        return self::semua($pengguna)->where('dilihat', false)->values();
    }

    /** Jumlah butir yang belum dilihat. Inilah angka pada lencana lonceng. */
    public static function jumlahBelumDilihat(?User $pengguna = null): int
    {
        return self::belumDilihat($pengguna)->count();
    }

    /**
     * Tandai satu butir sudah dilihat oleh pengguna ini, pada keadaan sekarang.
     *
     * Dipanggil saat butir diklik. Bila nanti keadaannya berubah, sidiknya
     * tidak cocok lagi dan butir itu otomatis muncul kembali sebagai belum
     * dilihat — tanpa perlu ada yang membersihkan tanda lama.
     */
    public static function tandaiDilihat(string $kunci, ?User $pengguna = null): bool
    {
        $pengguna ??= auth()->user();

        if ($pengguna === null) {
            return false;
        }

        $butir = self::semua($pengguna)->firstWhere('kunci', $kunci);

        if ($butir === null) {
            // Butirnya sudah tidak ada (mis. stok sudah diisi ulang). Tidak ada
            // yang perlu ditandai, dan menyimpan tanda untuk kunci yang tidak
            // ada hanya menumpuk baris mati.
            return false;
        }

        NotificationRead::query()->updateOrCreate(
            ['user_id' => $pengguna->getKey(), 'notification_key' => $kunci],
            [
                'fingerprint' => $butir['sidik'],
                'kind' => $butir['jenis'] ?? null,
                'item_count' => (int) ($butir['jumlah'] ?? 0),
                'read_at' => now(),
            ],
        );

        return true;
    }

    /** Tandai SEMUA butir yang sedang tampil sudah dilihat. */
    public static function tandaiSemuaDilihat(?User $pengguna = null): int
    {
        $pengguna ??= auth()->user();

        if ($pengguna === null) {
            return 0;
        }

        $n = 0;

        foreach (self::belumDilihat($pengguna) as $butir) {
            if (self::tandaiDilihat($butir['kunci'], $pengguna)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Sidik jari keadaan butir.
     *
     * Sengaja memakai isi yang DILIHAT admin (teks dan rinci), bukan seluruh
     * larik: tautan tidak ikut berubah tanpa isinya berubah, dan menyertakan
     * id acak akan membuat sidik selalu berbeda sehingga notifikasinya tidak
     * pernah bisa dianggap terbaca.
     */
    private static function sidik(array $butir): string
    {
        return sha1(implode('|', [
            (string) ($butir['kunci'] ?? ''),
            (string) ($butir['jenis'] ?? ''),
            (string) ($butir['jumlah'] ?? ''),
            (string) ($butir['teks'] ?? ''),
            (string) ($butir['rinci'] ?? ''),
        ]));
    }

    // ── Butir notifikasi ─────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private static function stokHabis(): array
    {
        $kueri = ProductVariant::query()
            ->with('product')
            ->where('is_active', true)
            ->whereRaw('(stock_quantity - reserved_quantity) <= 0')
            ->orderBy('id');

        // HITUNG seluruhnya, ambil contohnya saja yang dibatasi.
        //
        // Dulu `limit(20)` dipakai untuk membatasi KUERI, lalu ->count() pada
        // hasilnya dipakai sebagai jumlah — sehingga toko dengan 1000 varian
        // habis melaporkan "20 varian kehabisan stok". Angka lencana yang
        // salah lebih buruk daripada tidak ada lencana.
        $jumlah = (clone $kueri)->count();

        if ($jumlah === 0) {
            return [];
        }

        $daftar = $kueri->limit(20)->get();

        return [[
            'kunci' => 'stok-habis',
            'jenis' => 'stok',
            'nada' => 'danger',
            'ikon' => 'alert',
            'jumlah' => $jumlah,
            'teks' => $jumlah.' varian kehabisan stok',
            'rinci' => self::contohNama($daftar),
            'rute' => \Illuminate\Support\Facades\Route::has('admin.products.index')
                ? route('admin.products.index', ['stok' => 'habis'])
                : route('admin.dashboard'),
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    private static function stokMenipis(): array
    {
        $ambang = (int) Pengaturan::instance()->angka('low_stock_threshold', 3);

        $kueri = ProductVariant::query()
            ->with('product')
            ->where('is_active', true)
            ->whereRaw('(stock_quantity - reserved_quantity) > 0')
            ->whereRaw('(stock_quantity - reserved_quantity) <= ?', [$ambang])
            ->orderByRaw('(stock_quantity - reserved_quantity) asc');

        // Sama seperti stokHabis(): jumlah dihitung penuh, contohnya dibatasi.
        $jumlah = (clone $kueri)->count();

        if ($jumlah === 0) {
            return [];
        }

        $daftar = $kueri->limit(20)->get();

        return [[
            'kunci' => 'stok-menipis',
            'jenis' => 'stok',
            'nada' => 'warn',
            'ikon' => 'box',
            'jumlah' => $jumlah,
            'teks' => $jumlah.' varian hampir habis (≤'.$ambang.' tersisa)',
            'rinci' => self::contohNama($daftar),
            'rute' => \Illuminate\Support\Facades\Route::has('admin.products.index')
                ? route('admin.products.index', ['stok' => 'menipis'])
                : route('admin.dashboard'),
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    private static function hampirBatal(): array
    {
        $daftar = Order::query()
            ->where('status', StatusPesanan::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addHours(6))
            ->orderBy('expires_at')
            ->get();

        if ($daftar->isEmpty()) {
            return [];
        }

        $lewat = $daftar->where('expires_at', '<=', now())->count();
        $menuju = $daftar->count() - $lewat;

        return [[
            'kunci' => 'hampir-batal',
            'jenis' => 'pesanan',
            'nada' => 'danger',
            'ikon' => 'clock',
            'jumlah' => $daftar->count(),
            'teks' => $daftar->count().' pesanan hampir batal otomatis',
            'rinci' => $lewat > 0
                ? $lewat.' sudah lewat batas, '.$menuju.' kurang dari 6 jam lagi'
                : $menuju.' tersisa kurang dari 6 jam',
            'rute' => route('admin.orders.index', ['bayar' => 'unpaid']),
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    private static function pesananBaru(): array
    {
        // Pesanan yang sudah dibayar tetapi belum dikirim: inilah "produk
        // dibeli" yang perlu ditindaklanjuti admin.
        $jumlah = Order::query()
            ->whereIn('status', [StatusPesanan::Paid->value, StatusPesanan::Processing->value])
            ->count();

        if ($jumlah === 0) {
            return [];
        }

        $terbaru = Order::query()
            ->whereIn('status', [StatusPesanan::Paid->value, StatusPesanan::Processing->value])
            ->orderByDesc('paid_at')
            ->first();

        return [[
            'kunci' => 'pesanan-perlu-dikirim',
            'jenis' => 'pesanan',
            'nada' => 'steel',
            'ikon' => 'box',
            'jumlah' => $jumlah,
            'teks' => $jumlah.' pesanan siap dikirim',
            'rinci' => $terbaru
                ? 'Terakhir: '.$terbaru->order_number.' · '.rupiah($terbaru->grand_total)
                : '',
            'rute' => route('admin.orders.index', ['status' => 'paid']),
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    private static function masukKeranjang(): array
    {
        $sehari = now()->subDay();

        $jumlah = \App\Models\Cart::query()->where('updated_at', '>=', $sehari)->count();

        if ($jumlah === 0) {
            return [];
        }

        $barang = \Illuminate\Support\Facades\DB::table('cart_items')
            ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->where('carts.updated_at', '>=', $sehari)
            ->sum('cart_items.quantity');

        return [[
            'kunci' => 'masuk-keranjang',
            'jenis' => 'keranjang',
            'nada' => 'accent',
            'ikon' => 'cart',
            'jumlah' => $jumlah,
            'teks' => $jumlah.' keranjang aktif dalam 24 jam',
            'rinci' => (int) $barang.' barang tertahan di keranjang, belum dibayar',
            'rute' => route('admin.carts.index', ['umur' => 'harian']),
        ]];
    }

    /** Ambil beberapa nama pertama untuk keterangan singkat. */
    private static function contohNama(Collection $varian): string
    {
        return $varian
            ->take(3)
            ->map(function (ProductVariant $v): string {
                $judul = $v->product?->judul() ?? '(produk terhapus)';

                return mb_strimwidth($judul, 0, 28, '…').' — '.$v->stokTersedia().' tersisa';
            })
            ->implode(' · ');
    }
}
