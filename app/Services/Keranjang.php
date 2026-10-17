<?php

namespace App\Services;

use App\Enums\StatusPesanan;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Keranjang.
 *
 * Keranjang tamu disimpan pada baris carts dengan session_id, bukan di sesi
 * PHP. Alasannya: satu jalur kode untuk tamu dan pengguna terautentikasi, dan
 * penggabungan saat login menjadi satu operasi UPDATE, bukan penyalinan antar
 * struktur data yang berbeda.
 */
class Keranjang
{
    public const KUNCI_SESI = 'iksan.keranjang';

    private ?Cart $cache = null;

    private bool $sudahDimuat = false;

    private ?int $jumlahCache = null;

    /** Keranjang milik pengguna saat ini, atau null bila belum ada isinya. */
    public function ambil(): ?Cart
    {
        if ($this->sudahDimuat) {
            return $this->cache;
        }

        $this->sudahDimuat = true;

        $this->cache = $this->query()->with([
            'items.product.media',
            'items.variant.product',
            'coupon',
        ])->first();

        return $this->cache;
    }

    /** Keranjang yang pasti ada. Dipakai saat menambah item. */
    public function ambilAtauBuat(): Cart
    {
        return $this->ambil() ?? $this->buat();
    }

    public function jumlahItem(): int
    {
        if ($this->jumlahCache !== null) {
            return $this->jumlahCache;
        }

        $cart = $this->ambil();

        return $this->jumlahCache = $cart === null
            ? 0
            : (int) $cart->items->sum('quantity');
    }

    /** @return Collection<int, CartItem> */
    public function items(): Collection
    {
        return $this->ambil()?->items ?? collect();
    }

    public function subtotal(): float
    {
        return (float) $this->items()->sum(fn (CartItem $item) => $item->subtotal());
    }

    /**
     * Tambah varian ke keranjang. Jumlah dibatasi stok tersedia pada saat ini
     * juga, bukan hanya saat checkout, supaya keranjang tidak pernah menampilkan
     * jumlah yang tidak mungkin dipesan.
     *
     * @return int Jumlah akhir baris tersebut setelah ditambah.
     */
    public function tambah(ProductVariant $variant, int $jumlah = 1): int
    {
        $cart = $this->ambilAtauBuat();

        $baris = $cart->items()
            ->where('product_variant_id', $variant->id)
            ->first();

        $tersedia = $variant->stokTersedia();
        $diminta = max(1, $jumlah);
        $akhir = min($tersedia, ($baris?->quantity ?? 0) + $diminta);

        if ($akhir <= 0) {
            return 0;
        }

        if ($baris) {
            $baris->update(['quantity' => $akhir]);
        } else {
            $cart->items()->create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => $akhir,
            ]);
        }

        $this->segarkan();

        return $akhir;
    }

    public function perbarui(CartItem $item, int $jumlah): void
    {
        $maksimal = $item->stokMaksimal();
        $jumlah = max(1, min($maksimal, $jumlah));

        $item->update(['quantity' => $jumlah]);
        $this->segarkan();
    }

    public function hapus(CartItem $item): void
    {
        $item->delete();
        $this->segarkan();
    }

    public function kosongkan(): void
    {
        $cart = $this->ambil();

        if ($cart) {
            $cart->items()->delete();
            $cart->update(['coupon_id' => null]);
        }

        $this->segarkan();
    }

    public function terapkanKupon(?Coupon $coupon): void
    {
        $this->ambilAtauBuat()->update(['coupon_id' => $coupon?->id]);
        $this->segarkan();
    }

    public function kupon(): ?Coupon
    {
        $coupon = $this->ambil()?->coupon;

        if ($coupon === null) {
            return null;
        }

        // Kupon yang sudah tidak berlaku tidak boleh tetap menempel.
        return $coupon->alasanTidakSah($this->subtotal()) === null ? $coupon : null;
    }

    /**
     * Periksa ulang stok setiap kali keranjang dibuka.
     *
     * Keranjang dapat memuat jumlah yang sudah tidak tersedia karena pembelian
     * lain. Baris yang stoknya habis dihapus, yang berkurang disesuaikan.
     *
     * @return array<int, array{jenis: string, nama: string, jumlah?: int}>
     */
    public function periksaStok(): array
    {
        $catatan = [];

        foreach ($this->items() as $item) {
            $tersedia = $item->stokMaksimal();

            if ($tersedia <= 0) {
                $catatan[] = ['jenis' => 'habis', 'nama' => $item->product?->judul() ?? 'Produk'];
                $item->delete();

                continue;
            }

            if ($item->quantity > $tersedia) {
                $item->update(['quantity' => $tersedia]);
                $catatan[] = [
                    'jenis' => 'dikurangi',
                    'nama' => $item->product?->judul() ?? 'Produk',
                    'jumlah' => $tersedia,
                ];
            }
        }

        if ($catatan !== []) {
            $this->segarkan();
        }

        return $catatan;
    }

    /**
     * Pindahkan keranjang tamu ke akun saat login.
     *
     * Varian yang sama di kedua keranjang digabung, bukan diduplikasi. Indeks
     * unik (cart_id, product_variant_id) yang menjaminnya di tingkat basis data.
     */
    public function gabungkanKe(User $user): void
    {
        $sesiId = session()->getId();

        $tamu = Cart::query()->where('session_id', $sesiId)->first();
        $akun = Cart::query()->where('user_id', $user->id)->first();

        if ($tamu === null) {
            if ($akun === null) {
                return;
            }

            $this->segarkan();

            return;
        }

        if ($akun === null) {
            $tamu->update(['user_id' => $user->id, 'session_id' => null]);
            $this->segarkan();

            return;
        }

        foreach ($tamu->items as $item) {
            if ($item->product_variant_id === null) {
                continue;
            }

            $ada = $akun->items()->where('product_variant_id', $item->product_variant_id)->first();
            $tersedia = $item->variant?->stokTersedia() ?? 0;

            if ($ada) {
                $ada->update(['quantity' => min($tersedia, $ada->quantity + $item->quantity)]);
            } else {
                $akun->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => min($tersedia, $item->quantity),
                ]);
            }
        }

        if ($akun->coupon_id === null) {
            $akun->update(['coupon_id' => $tamu->coupon_id]);
        }

        $tamu->items()->delete();
        $tamu->delete();

        $this->segarkan();
    }

    // ── Internal ────────────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Builder<Cart> */
    private function query()
    {
        $userId = auth()->id();

        return Cart::query()->when(
            $userId !== null,
            fn ($q) => $q->where('user_id', $userId),
            fn ($q) => $q->whereNull('user_id')->where('session_id', session()->getId()),
        );
    }

    private function buat(): Cart
    {
        $cart = Cart::query()->create([
            'user_id' => auth()->id(),
            'session_id' => auth()->check() ? null : session()->getId(),
        ]);

        $this->segarkan();

        return $cart;
    }

    private function segarkan(): void
    {
        $this->cache = null;
        $this->sudahDimuat = false;
        $this->jumlahCache = null;
    }

    /** Ringkasan biaya. Satu tempat, dipakai keranjang dan checkout. */
    public function ringkasan(): array
    {
        $subtotal = $this->subtotal();
        $kupon = $this->kupon();
        $ongkir = (float) \App\Support\Pengaturan::instance()->angka('shipping_flat_rate', 0);
        $persenPajak = (float) \App\Support\Pengaturan::instance()->angka('tax_percent', 0);

        $potonganBelanja = $kupon?->potonganBelanja($subtotal, $ongkir) ?? 0.0;
        $potonganOngkir = $kupon?->potonganOngkir($subtotal, $ongkir) ?? 0.0;

        $dasarPajak = max(0.0, $subtotal - $potonganBelanja);
        $pajak = round($dasarPajak * ($persenPajak / 100), 2);
        $ongkirAkhir = max(0.0, $ongkir - $potonganOngkir);

        return [
            'item' => $this->jumlahItem(),
            'subtotal' => $subtotal,
            'diskon' => $potonganBelanja,
            'ongkir' => $ongkirAkhir,
            'pajak' => $pajak,
            'total' => max(0.0, $dasarPajak + $pajak + $ongkirAkhir),
            'kupon' => $kupon,
        ];
    }

    /** Hanya untuk test dan seeder. */
    public function lupakan(): void
    {
        $this->segarkan();
    }
}
