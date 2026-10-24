<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Services\Produk\PengunggahMediaProduk;
use App\Support\CacheKatalog;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Daftar produk (checklist F3-02).
 *
 * Menyaring, mengurutkan, dan mengubah banyak produk sekaligus. Aksi massal
 * diperiksa di server per produk, bukan sekaligus: satu produk yang gagal
 * (misalnya variannya belum ada sehingga tidak boleh diterbitkan) tidak
 * membatalkan perubahan produk lain, dan alasannya dilaporkan per produk.
 */
#[Layout('layouts.admin')]
class ProdukDaftar extends Component
{
    use WithPagination;

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    #[Url(as: 'kategori', history: true)]
    public string $kategori = 'semua';

    #[Url(as: 'terbit', history: true)]
    public string $terbit = 'semua';

    #[Url(as: 'urut', history: true)]
    public string $urut = 'terbaru';

    #[Url(as: 'stok', history: true)]
    public string $stok = 'semua';

    public string $perHalaman = '20';

    /** @var array<int, int> */
    public array $terpilih = [];

    public bool $pilihSemuaHalaman = false;

    public ?string $pesan = null;

    /** @var array<int, string> */
    public array $catatan = [];

    public function updatedCari(): void
    {
        $this->resetPage();
        $this->terpilih = [];
    }

    public function updatedKategori(): void
    {
        $this->resetPage();
    }

    public function updatedTerbit(): void
    {
        $this->resetPage();
    }

    public function updatedStok(): void
    {
        $this->resetPage();
    }

    public function updatedUrut(): void
    {
        $this->resetPage();
    }

    public function aturKategori(string $nilai): void
    {
        $this->kategori = $nilai;
        $this->resetPage();
    }

    public function aturTerbit(string $nilai): void
    {
        $this->terbit = in_array($nilai, ['semua', 'terbit', 'draf'], true) ? $nilai : 'semua';
        $this->resetPage();
    }

    public function aturStok(string $nilai): void
    {
        $this->stok = in_array($nilai, ['semua', 'ada', 'habis', 'menipis'], true) ? $nilai : 'semua';
        $this->resetPage();
    }

    public function pilihSemua(): void
    {
        $this->terpilih = $this->kueri()->pluck('id')->all();
    }

    public function kosongkanPilihan(): void
    {
        $this->terpilih = [];
    }

    // ── Aksi massal ─────────────────────────────────────────────────

    /**
     * Menerbitkan atau menarik produk terpilih.
     *
     * Produk tanpa varian ditolak, satu per satu, dengan catatan sendiri.
     * Menerbitkan produk tanpa varian menghasilkan halaman detail yang tidak
     * bisa dibeli — pembeli sampai di sana, menekan tombol, dan gagal.
     */
    public function ubahTerbitMassal(bool $terbit): void
    {
        $this->catatan = [];
        $berhasil = 0;

        $daftar = Product::query()->whereIn('id', $this->terpilih)->withCount('variants')->get();

        foreach ($daftar as $produk) {
            if ($terbit && $produk->variants_count === 0) {
                $this->catatan[] = $produk->title_id.' dilewati: belum punya varian.';

                continue;
            }

            $produk->update([
                'is_published' => $terbit,
                'published_at' => $terbit ? ($produk->published_at ?? now()) : null,
            ]);

            $berhasil++;
        }

        CatatanAktivitas::catat($terbit ? 'produk.massal.terbit' : 'produk.massal.tarik', [
            'jumlah' => $berhasil,
        ]);

        CacheKatalog::tandaiBerubah($terbit ? 'produk massal diterbitkan' : 'produk massal ditarik');

        $this->pesan = $terbit
            ? $berhasil.' produk diterbitkan.'
            : $berhasil.' produk ditarik dari katalog.';

        $this->terpilih = [];
    }

    public function ubahUnggulanMassal(bool $unggulan): void
    {
        $berhasil = Product::query()->whereIn('id', $this->terpilih)->update(['is_featured' => $unggulan]);

        CatatanAktivitas::catat($unggulan ? 'produk.massal.unggulan' : 'produk.massal.biasa', [
            'jumlah' => $berhasil,
        ]);

        CacheKatalog::tandaiBerubah('unggulan massal diubah');

        $this->pesan = $unggulan
            ? $berhasil.' produk ditandai unggulan.'
            : $berhasil.' produk tidak lagi unggulan.';

        $this->terpilih = [];
        $this->catatan = [];
    }

    /**
     * Memindahkan produk terpilih ke kategori lain.
     *
     * Berguna saat satu kategori dinonaktifkan: produknya harus dipindahkan
     * dulu, dan memindahkannya satu per satu lewat form adalah pekerjaan yang
     * tidak perlu.
     */
    public function pindahKategoriMassal(int $kategoriTujuan): void
    {
        if (! Category::query()->whereKey($kategoriTujuan)->exists()) {
            $this->addError('umum', 'Kategori tujuan tidak ditemukan.');

            return;
        }

        $berhasil = Product::query()->whereIn('id', $this->terpilih)->update(['category_id' => $kategoriTujuan]);

        CatatanAktivitas::catat('produk.massal.pindah', [
            'jumlah' => $berhasil,
            'category_id' => $kategoriTujuan,
        ]);

        CacheKatalog::tandaiBerubah('produk massal dipindah kategori');

        $this->pesan = $berhasil.' produk dipindahkan ke kategori baru.';
        $this->terpilih = [];
        $this->catatan = [];
    }

    public function hapusMassal(): void
    {
        $pengunggah = app(PengunggahMediaProduk::class);

        $this->catatan = [];
        $terhapus = 0;

        $daftar = Product::query()->whereIn('id', $this->terpilih)->with('media')->get();

        foreach ($daftar as $produk) {
            // Produk yang pernah dipesan tidak dihapus. Baris pesanan lama
            // menyimpan product_id; menghapus produknya membuat riwayat pesanan
            // kehilangan keterangan barang dan laporan penjualan jadi salah.
            $pernahDipesan = DB::table('order_items')->where('product_id', $produk->id)->exists();

            if ($pernahDipesan) {
                $this->catatan[] = $produk->title_id.' dilewati: sudah pernah dipesan. Tarik saja dari katalog.';

                continue;
            }

            DB::transaction(function () use ($produk, $pengunggah): void {
                foreach ($produk->media as $media) {
                    $pengunggah->hapusBerkas($media);
                    $media->delete();
                }

                $pengunggah->hapusFolderProduk($produk);

                $produk->variants()->delete();
                $produk->delete();
            });

            CatatanAktivitas::catat('produk.dihapus', ['sku' => $produk->sku]);
            $terhapus++;
        }

        CacheKatalog::tandaiBerubah('produk massal dihapus');

        $this->pesan = $terhapus.' produk dihapus.';
        $this->terpilih = [];
    }

    public function hapus(int $id): void
    {
        $pengunggah = app(PengunggahMediaProduk::class);

        $pernahDipesan = DB::table('order_items')->where('product_id', $id)->exists();

        if ($pernahDipesan) {
            $this->addError('umum', 'Produk ini sudah pernah dipesan, jadi tidak dihapus. Tarik saja dari katalog.');

            return;
        }

        $produk = Product::query()->with('media')->findOrFail($id);

        DB::transaction(function () use ($produk, $pengunggah): void {
            foreach ($produk->media as $media) {
                $pengunggah->hapusBerkas($media);
                $media->delete();
            }

            $pengunggah->hapusFolderProduk($produk);
            $produk->variants()->delete();
            $produk->delete();
        });

        CatatanAktivitas::catat('produk.dihapus', ['sku' => $produk->sku]);
        CacheKatalog::tandaiBerubah('produk dihapus');

        $this->pesan = 'Produk "'.$produk->title_id.'" dihapus.';
    }

    /** Menyalin produk beserta variannya sebagai draf baru. */
    public function duplikat(int $id): void
    {
        $asli = Product::query()->with('variants')->findOrFail($id);

        $produk = DB::transaction(function () use ($asli): Product {
            $slug = $asli->slug;

            // Slug harus unik. Menambahkan akhiran angka jauh lebih baik
            // daripada membiarkan penyimpanan gagal dengan galat basis data.
            $nomor = 2;

            while (Product::query()->where('slug', $slug)->exists()) {
                $slug = $asli->slug.'-'.$nomor;
                $nomor++;
            }

            $sku = $asli->sku.'-COPY';
            $nomor = 2;

            while (Product::query()->where('sku', $sku)->exists()) {
                $sku = $asli->sku.'-COPY'.$nomor;
                $nomor++;
            }

            $baru = Product::query()->create([
                'category_id' => $asli->category_id,
                'sku' => $sku,
                'title_id' => $asli->title_id.' (salinan)',
                'title_en' => $asli->title_en,
                'slug' => $slug,
                'excerpt_id' => $asli->excerpt_id,
                'excerpt_en' => $asli->excerpt_en,
                'description_id' => $asli->description_id,
                'description_en' => $asli->description_en,
                'base_price' => $asli->base_price,
                'weight_gram' => $asli->weight_gram,
                // Salinan selalu draf. Menerbitkannya otomatis akan
                // menampilkan produk kembar di katalog sebelum diperiksa.
                'is_featured' => false,
                'is_published' => false,
                'published_at' => null,
            ]);

            foreach ($asli->variants as $urutan => $varian) {
                $baru->variants()->create([
                    'sku_variant' => $sku.'-V'.($urutan + 1),
                    'variant_name_id' => $varian->variant_name_id,
                    'variant_name_en' => $varian->variant_name_en,
                    'price_adjustment' => $varian->price_adjustment,
                    // Stok tidak disalin: dua produk dengan stok yang sama
                    // menjanjikan barang yang cuma ada satu.
                    'stock_quantity' => 0,
                    'weight_gram' => $varian->weight_gram,
                    'display_order' => $urutan + 1,
                    'is_active' => $varian->is_active,
                ]);
            }

            CatatanAktivitas::catat('produk.disalin', [
                'dari' => $asli->sku,
                'ke' => $baru->sku,
            ], auth()->id(), $baru);

            return $baru;
        });

        $this->pesan = 'Produk disalin sebagai draf "'.$produk->title_id.'". Gambar tidak ikut disalin.';
    }

    public function ubahTerbitSatu(int $id): void
    {
        $produk = Product::query()->withCount('variants')->findOrFail($id);

        if (! $produk->is_published && $produk->variants_count === 0) {
            $this->addError('umum', $produk->title_id.' belum punya varian, jadi belum bisa diterbitkan.');

            return;
        }

        $jadi = ! $produk->is_published;

        $produk->update([
            'is_published' => $jadi,
            'published_at' => $jadi ? ($produk->published_at ?? now()) : null,
        ]);

        CacheKatalog::tandaiProduk($produk, $jadi ? 'produk diterbitkan' : 'produk ditarik');

        $this->pesan = $jadi ? 'Produk diterbitkan.' : 'Produk ditarik dari katalog.';
    }

    // ── Kueri ───────────────────────────────────────────────────────

    private function kueri()
    {
        return Product::query()
            ->with(['category', 'primaryMedia'])
            ->withCount('variants')
            ->withSum('variants as stok_fisik', 'stock_quantity')
            ->withSum('variants as stok_ditahan', 'reserved_quantity')
            ->when($this->cari !== '', function ($q): void {
                $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->cari).'%';

                $q->where(fn ($inner) => $inner
                    ->where('title_id', 'like', $pola)
                    ->orWhere('title_en', 'like', $pola)
                    ->orWhere('sku', 'like', $pola));
            })
            ->when($this->kategori !== 'semua', fn ($q) => $q->where('category_id', (int) $this->kategori))
            ->when($this->terbit === 'terbit', fn ($q) => $q->where('is_published', true))
            ->when($this->terbit === 'draf', fn ($q) => $q->where('is_published', false))
            ->when($this->stok === 'ada', fn ($q) => $q->whereRaw(
                'COALESCE((select sum(stock_quantity - reserved_quantity) from product_variants where product_variants.product_id = products.id and is_active = 1), 0) > 0',
            ))
            ->when($this->stok === 'habis', fn ($q) => $q->whereRaw(
                'COALESCE((select sum(stock_quantity - reserved_quantity) from product_variants where product_variants.product_id = products.id and is_active = 1), 0) <= 0',
            ))
            ->when($this->stok === 'menipis', fn ($q) => $q->whereRaw(
                'COALESCE((select sum(stock_quantity - reserved_quantity) from product_variants where product_variants.product_id = products.id and is_active = 1), 0) between 1 and ?',
                [(int) config('iksan.low_stock_threshold', 3)],
            ))
            ->when($this->urut === 'terbaru', fn ($q) => $q->orderByDesc('id'))
            ->when($this->urut === 'terlama', fn ($q) => $q->orderBy('id'))
            ->when($this->urut === 'nama', fn ($q) => $q->orderBy('title_id'))
            ->when($this->urut === 'termahal', fn ($q) => $q->orderByDesc('base_price'))
            ->when($this->urut === 'termurah', fn ($q) => $q->orderBy('base_price'))
            ->when($this->urut === 'terlaris', fn ($q) => $q->orderByDesc('sold_count'))
            ->when($this->urut === 'stok', fn ($q) => $q->orderByRaw(
                'COALESCE((select sum(stock_quantity - reserved_quantity) from product_variants where product_variants.product_id = products.id and is_active = 1), 0)',
            ));
    }

    public function render(): View
    {
        $daftar = $this->kueri()->paginate(25);

        return view('livewire.admin.produk-daftar', [
            'daftar' => $daftar,
            'daftarKategori' => Category::query()->terurut()->get(),
            'jumlah' => [
                'semua' => Product::query()->count(),
                'terbit' => Product::query()->where('is_published', true)->count(),
                'draf' => Product::query()->where('is_published', false)->count(),
                'habis' => Product::query()
                    ->whereRaw('COALESCE((select sum(stock_quantity - reserved_quantity) from product_variants where product_variants.product_id = products.id and is_active = 1), 0) <= 0')
                    ->count(),
            ],
        ])->layout('layouts.admin', ['judul' => 'Produk', 'bagian' => 'Katalog']);
    }
}
