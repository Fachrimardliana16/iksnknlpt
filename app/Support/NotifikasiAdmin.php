<?php

namespace App\Support;

use App\Enums\StatusPesanan;
use App\Models\Cart;
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
 */
class NotifikasiAdmin
{
    /**
     * @return Collection<int, array{kunci: string, jenis: string, nada: string, teks: string, rinci: string, ikon: string, rute: string, jumlah: int}>
     */
    public static function semua(): Collection
    {
        return collect()
            ->merge(self::stokHabis())
            ->merge(self::stokMenipis())
            ->merge(self::hampirBatal())
            ->merge(self::pesananBaru())
            ->merge(self::masukKeranjang())
            ->values();
    }

    /** Jumlah total butir yang perlu perhatian. Dipakai lencana pada ikon. */
    public static function jumlah(): int
    {
        return self::semua()->count();
    }

    /** Berapa banyak yang berperingkat mendesak. */
    public static function jumlahMendesak(): int
    {
        return self::semua()->where('nada', 'danger')->count();
    }

    // ── Butir notifikasi ─────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private static function stokHabis(): array
    {
        $daftar = ProductVariant::query()
            ->with('product')
            ->where('is_active', true)
            ->whereRaw('(stock_quantity - reserved_quantity) <= 0')
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($daftar->isEmpty()) {
            return [];
        }

        return [[
            'kunci' => 'stok-habis',
            'jenis' => 'stok',
            'nada' => 'danger',
            'ikon' => 'alert',
            'jumlah' => $daftar->count(),
            'teks' => $daftar->count().' varian kehabisan stok',
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

        $daftar = ProductVariant::query()
            ->with('product')
            ->where('is_active', true)
            ->whereRaw('(stock_quantity - reserved_quantity) > 0')
            ->whereRaw('(stock_quantity - reserved_quantity) <= ?', [$ambang])
            ->orderByRaw('(stock_quantity - reserved_quantity) asc')
            ->limit(20)
            ->get();

        if ($daftar->isEmpty()) {
            return [];
        }

        return [[
            'kunci' => 'stok-menipis',
            'jenis' => 'stok',
            'nada' => 'warn',
            'ikon' => 'box',
            'jumlah' => $daftar->count(),
            'teks' => $daftar->count().' varian hampir habis (≤'.$ambang.' tersisa)',
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
