<?php

namespace App\Livewire\Admin;

use App\Models\Cart;
use App\Models\ProductLike;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Keranjang ditinggalkan: barang yang sudah dimasukkan pembeli tetapi belum
 * dibayar.
 *
 * Halaman ini menjawab satu pertanyaan operasional: siapa yang tertarik dan
 * apa yang tertinggal di keranjangnya. Keranjang tamu dihitung juga — di toko
 * ini checkout tidak menuntut pendaftaran akun, jadi mengabaikan keranjang
 * tamu berarti mengabaikan sebagian besar minat yang nyata.
 *
 * Yang TIDAK ditampilkan: identitas pengunjung anonim. Keranjang tamu tanpa
 * akun tidak punya nama maupun kontak, jadi yang tampil hanya sesi dan barang.
 * Menampilkan dugaan identitas di sini akan menyesatkan.
 *
 * Kartu statistik empat petak (keranjang aktif, nilai tertahan, baru,
 * mengendap) DIHAPUS. Keempatnya menjawab pertanyaan berbeda dari yang
 * dibutuhkan halaman ini — yang dibutuhkan adalah daftar barang apa yang
 * paling sering ditinggalkan, bukan angka agregat yang tidak punya tindakan
 * lanjut. Angka yang berguna kini jadi baris ringkas di kepala halaman.
 */
#[Layout('layouts.admin')]
class KeranjangTerbuka extends KomponenAdmin
{
    use WithPagination;

    /** Saring berdasarkan umur keranjang. */
    #[Url(as: 'umur', history: true)]
    public string $umur = 'semua';

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    /** Perbarui otomatis setiap 30 detik supaya angka tetap segar. */
    public bool $otomatis = false;

    public function perbarui(): void
    {
        $this->resetPage();
    }

    public function aturUmur(string $umur): void
    {
        if (! in_array($umur, ['semua', 'harian', 'tua'], true)) {
            return;
        }

        $this->umur = $umur;
        $this->resetPage();
    }

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    /**
     * Buang keranjang yang sudah kedaluwarsa.
     *
     * Barisnya dihapus, bukan diarsipkan: yang dibuang hanya keranjang tanpa
     * pesanan, dan pesanan tidak pernah merujuk balik ke keranjang. Barang di
     * dalamnya tidak pernah menahan stok — stok baru ditahan saat pesanan
     * dibuat — jadi tidak ada yang perlu dipulihkan.
     */
    public function bersihkanKedaluwarsa(): void
    {
        $jumlah = Cart::query()
            ->where('updated_at', '<=', now()->subDays(7))
            ->delete();

        $this->dispatch('notifikasi-diperbarui');

        session()->flash('pesan-admin', $jumlah.' keranjang lama dibuang.');
    }

    public function render(): View
    {
        $keranjang = Cart::query()
            ->with(['user', 'items.product', 'items.variant.product', 'coupon'])
            ->when($this->umur === 'harian', fn ($q) => $q->where('updated_at', '>=', now()->subDay()))
            ->when($this->umur === 'tua', fn ($q) => $q->where('updated_at', '<', now()->subDay()))
            ->when(filled($this->cari), function ($q): void {
                $kata = '%'.trim($this->cari).'%';

                $q->where(function ($w) use ($kata): void {
                    $w->whereHas('user', fn ($u) => $u->where('name', 'like', $kata))
                        ->orWhereHas('items.product', fn ($p) => $p->where('title_id', 'like', $kata)
                            ->orWhere('sku', 'like', $kata));
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('livewire.admin.keranjang-terbuka', [
            'judul' => 'Keranjang Ditinggalkan',
            'bagian' => 'Penjualan',
            'keranjang' => $keranjang,
            'ringkas' => $this->ringkasan(),
        ])->layout('layouts.admin', ['judul' => 'Keranjang Ditinggalkan', 'bagian' => 'Penjualan']);
    }

    /**
     * Angka ringkas di atas daftar: berapa keranjang, berapa nilainya, dan
     * berapa yang mengendap lebih dari sehari.
     *
     * @return array<string, mixed>
     */
    private function ringkasan(): array
    {
        $total = Cart::query()->count();
        $harian = Cart::query()->where('updated_at', '>=', now()->subDay())->count();

        // Nilai keranjang dihitung dari harga jual varian (harga dasar produk
        // ditambah penyesuaian varian) dikali jumlah. Satu kueri agregat,
        // bukan memuat seluruh baris ke memori.
        //
        // Harga dasar selalu positif dan penyesuaian varian boleh negatif, jadi
        // hasilnya dijepit di nol. Penjepitan dilakukan lewat MAX(), bukan
        // GREATEST(): SQLite tidak mengenal GREATEST, dan basis data proyek ini
        // SQLite. MAX() dengan dua argumen bekerja di SQLite maupun MySQL.
        $nilai = (float) DB::table('cart_items')
            ->join('product_variants', 'product_variants.id', '=', 'cart_items.product_variant_id')
            ->join('products', 'products.id', '=', 'cart_items.product_id')
            ->selectRaw('coalesce(sum(max(products.base_price + product_variants.price_adjustment, 0) * cart_items.quantity), 0) as nilai')
            ->value('nilai');

        // Varian yang paling sering tertinggal di keranjang.
        $terpopuler = DB::table('cart_items')
            ->join('product_variants', 'product_variants.id', '=', 'cart_items.product_variant_id')
            ->join('products', 'products.id', '=', 'cart_items.product_id')
            ->selectRaw('products.title_id as nama, sum(cart_items.quantity) as jumlah, count(*) as keranjang')
            ->groupBy('products.title_id')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        return [
            'total' => $total,
            'harian' => $harian,
            'mengendap' => $total - $harian,
            'nilai' => $nilai,
            'terpopuler' => $terpopuler,
            // Produk yang DISUKAI, bukan yang tertinggal di keranjang.
            //
            // Dipisah dari daftar "paling sering tertinggal" karena
            // menjawab pertanyaan berbeda: menyukai adalah sinyal minat
            // termurah (satu ketukan, tanpa stok ditahan), sementara
            // tertinggal di keranjang berarti pembeli sempat serius lalu
            // ragu. Keduanya berguna sebagai parameter iklan, tapi bukan
            // daftar yang sama.
            'disukai' => ProductLike::terpopuler(5),
        ];
    }

    /** Nama ringkas pemilik keranjang, atau penanda tamu. */
    public function pemilik(Cart $cart): string
    {
        return $cart->user?->name ?? 'Tamu';
    }

    /** Selisih waktu dalam bahasa yang mudah dibaca. */
    public function sejak(Carbon $waktu): string
    {
        return $waktu->diffForHumans(short: true);
    }

    /** Varian yang stoknya menipis atau habis, untuk ditandai di daftar. */
    public function perluPerhatian(Collection $items): bool
    {
        return $items->contains(function ($item): bool {
            $varian = $item->variant;

            return $varian instanceof ProductVariant
                && ($varian->stokTersedia() <= 0 || $varian->stokTersedia() < $item->quantity);
        });
    }
}
