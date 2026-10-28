<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suka produk — sinyal minat yang tidak berarti keranjang.
 *
 * Bedanya dengan cart_items: menekan suka tidak menahan stok, tidak punya
 * jumlah, dan tidak menuntut varian. Karena itu tabelnya hanya mencatat SIAPA
 * menyukai APA, bukan berapa banyak.
 *
 * Yang belum ada dan sengaja belum ditambahkan: daftar "produk yang saya sukai"
 * untuk pembeli. Yang diminta adalah tombolnya dan pencatatannya; halaman
 * koleksi pribadi baru berguna bila ada yang memakainya.
 */
class ProductLike extends Model
{
    use PunyaUlid;

    protected $fillable = ['product_id', 'user_id', 'session_id'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Suka produk. Tekan dua kali = batal, bukan dua baris.
     *
     * Nilai kembalian menyatakan keadaan AKHIR supaya pemanggil tidak perlu
     * membaca ulang basis data hanya untuk tahu tombolnya jadi apa.
     */
    public static function alihkan(Product $produk, ?User $pengguna, string $sesi): bool
    {
        $kunci = $pengguna !== null
            ? ['product_id' => $produk->id, 'user_id' => $pengguna->id]
            : ['product_id' => $produk->id, 'session_id' => $sesi];

        $ada = static::query()->where($kunci)->exists();

        if ($ada) {
            static::query()->where($kunci)->delete();

            return false;
        }

        static::query()->create($kunci);

        return true;
    }

    /** @param array<string, mixed> $kunci */
    public static function sudah(int $produkId, array $kunci): bool
    {
        return static::query()
            ->where('product_id', $produkId)
            ->where($kunci)
            ->exists();
    }

    /**
     * Kunci pemilik suka untuk pengunjung saat ini.
     *
     * @return array<string, mixed>
     */
    public static function kunciPemilik(?User $pengguna, string $sesi): array
    {
        return $pengguna !== null
            ? ['user_id' => $pengguna->id]
            : ['session_id' => $sesi];
    }

    /**
     * Jumlah suka per produk untuk sekumpulan produk.
     *
     * Satu kueri untuk seluruh daftar, bukan satu per kartu: halaman katalog
     * memuat dua puluh kartu, dan dua puluh kueri tambahan hanya untuk angka
     * kecil di bawah gambar tidak sebanding.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $produk
     * @return array<int, int>
     */
    public static function hitungUntuk($produk): array
    {
        $id = $produk->pluck('id')->all();

        if ($id === []) {
            return [];
        }

        return static::query()
            ->whereIn('product_id', $id)
            ->selectRaw('product_id, count(*) as jumlah')
            ->groupBy('product_id')
            ->pluck('jumlah', 'product_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** @param array<int, int> $hitungan */
    public static function untuk(Product $produk, array $hitungan): int
    {
        return $hitungan[$produk->id] ?? 0;
    }

    /**
     * Produk mana yang sudah disukai pengunjung ini.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $produk
     * @param  array<string, mixed>  $kunci
     * @return array<int, bool>
     */
    public static function petaDisukai($produk, array $kunci): array
    {
        $id = $produk->pluck('id')->all();

        if ($id === []) {
            return [];
        }

        $baris = static::query()
            ->whereIn('product_id', $id)
            ->where($kunci)
            ->pluck('product_id')
            ->all();

        return array_fill_keys($baris, true);
    }

    /** Hitungan suka satu produk, untuk halaman detail. */
    public static function hitungSatu(Product $produk): int
    {
        return (int) static::query()->where('product_id', $produk->id)->count();
    }

    /**
     * Properti suka untuk sekumpulan produk, siap dikirim ke <livewire:...>.
     *
     * Satu kueri untuk hitungan, satu kueri untuk status "sudah disukai" —
     * bukan dua per kartu. Halaman katalog memuat dua belas kartu; versi per
     * kartu menambah 24 kueri dan menembus anggaran 15 kueri per halaman.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $produk
     * @return array<string, array<int, int>|array<int, bool>>
     */
    public static function propsUntuk($produk, ?User $pengguna, string $sesi): array
    {
        return [
            'hitungan' => static::hitungUntuk($produk),
            'disukai' => static::petaDisukai($produk, static::kunciPemilik($pengguna, $sesi)),
        ];
    }

    /**
     * Produk paling banyak disukai, untuk panel admin.
     *
     * Dipakai sebagai parameter iklan: produk yang dibutuhkan pembeli, bukan
     * produk yang menurut toko bagus.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function terpopuler(int $batas = 10)
    {
        return static::query()
            ->join('products', 'products.id', '=', 'product_likes.product_id')
            ->selectRaw('products.title_id as nama, products.sku as sku, count(*) as jumlah')
            ->groupBy('products.id', 'products.title_id', 'products.sku')
            ->orderByDesc('jumlah')
            ->limit($batas)
            ->get();
    }

    /** @param  Builder<static>  $query */
    public function scopeUntukProduk(Builder $query, int $produkId): void
    {
        $query->where('product_id', $produkId);
    }
}
