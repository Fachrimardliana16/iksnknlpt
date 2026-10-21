<?php

namespace App\Livewire\Front;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Keranjang;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Halaman detail produk (checklist F6-03 sampai F6-08).
 *
 * Halaman ini punya satu pekerjaan yang harus benar: memberi tahu pembeli
 * harga dan stok untuk varian yang sedang dipilih, lalu mengirim pilihannya ke
 * keranjang. Karena itu harga, stok, dan label tombol dihitung dari varian
 * aktif — bukan dari harga dasar produk — dan semuanya dihitung di server.
 */
#[Layout('layouts.front')]
class DetailProduk extends Component
{
    public Product $product;

    public ?int $variantId = null;

    public int $jumlah = 1;

    public ?string $pesan = null;

    public string $nada = 'ok';

    public function mount(Product $product): void
    {
        abort_unless($product->is_published, 404);

        $this->product = $product->load(['media', 'variants', 'category']);

        // Pilih varian pertama yang masih ada stoknya, supaya pembeli tidak
        // diarahkan ke varian habis secara default.
        $this->variantId = $this->product->variants
            ->where('is_active', true)
            ->sortBy('display_order')
            ->firstWhere(fn (ProductVariant $v) => $v->stokTersedia() > 0)
            ?->id
            ?? $this->product->variants->where('is_active', true)->first()?->id;

        // Dihitung sekali per tampilan, bukan per render. Penulisan per render
        // akan menghasilkan ribuan UPDATE per hari untuk satu produk populer.
        //
        // Kenaikan langsung pada kolom (increment) dipilih, bukan membaca nilai
        // lalu menulisnya kembali: dua permintaan yang tiba bersamaan dengan
        // cara baca-lalu-tulis menghasilkan satu kenaikan yang hilang.
        $this->product->increment('view_count');
    }

    public function pilihVarian(int $id): void
    {
        // Hanya varian milik produk ini yang diterima. Tanpa pemeriksaan,
        // id dari produk lain dapat dikirim ke server dan harga yang tampil
        // menjadi milik produk yang tidak sedang dibuka.
        $varian = $this->product->variants->firstWhere('id', $id);

        if ($varian === null) {
            return;
        }

        $this->variantId = $id;
        $this->jumlah = 1;
        $this->pesan = null;
    }

    /** Tombol − pada pemilih jumlah. Batasnya dijaga di server. */
    public function turunkanJumlah(): void
    {
        $this->jumlah = max(1, $this->jumlah - 1);
        $this->pesan = null;
    }

    /**
     * Tombol + pada pemilih jumlah.
     *
     * Batas atasnya stok yang dapat dijual. Sebelumnya batas ini hanya ada di
     * atribut max pada input, sehingga mengetik angka lebih besar tetap lolos;
     * checkout yang menolaknya, setelah pembeli mengisi seluruh data kirim.
     */
    public function naikkanJumlah(): void
    {
        $batas = max(1, $this->tersediaUntukDijual());

        $this->jumlah = min($batas, $this->jumlah + 1);
        $this->pesan = null;
    }

    public function tambah(Keranjang $keranjang, bool $langsungCheckout = false): void
    {
        $varian = $this->varian();

        if ($varian === null) {
            $this->pesan = __('site.product.out_of_stock');
            $this->nada = 'danger';

            return;
        }

        $akhir = $keranjang->tambah($varian, max(1, $this->jumlah));

        if ($akhir <= 0) {
            $this->pesan = __('site.product.out_of_stock');
            $this->nada = 'danger';

            return;
        }

        // Komponen lain (navbar, bar navigasi) memperbarui badge tanpa reload.
        $this->dispatch('keranjang-berubah');

        if ($langsungCheckout) {
            $this->redirect(route('front.checkout'), navigate: true);

            return;
        }

        $this->pesan = __('site.product.add_to_cart').' ('.$akhir.')';
        $this->nada = 'ok';
    }

    #[On('keranjang-berubah')]
    public function segarkan(): void
    {
        //
    }

    /**
     * Tautan WhatsApp dengan pesan yang sudah menyebut produk dan variannya.
     *
     * Nama varian ikut disebut karena harga dan ketersediaan berbeda per
     * varian; tanpa itu penjual harus bertanya ulang varian mana yang dimaksud.
     */
    public function tautanWhatsApp(): ?string
    {
        $nomor = preg_replace('/\D/', '', (string) Pengaturan::instance()?->teks('whatsapp_number'));

        if ($nomor === '') {
            return null;
        }

        $varian = $this->varian();

        $pesan = sprintf(
            'Halo %s, saya mau tanya %s (%s)%s. Stoknya masih ada?',
            Pengaturan::instance()->teks('site_name'),
            $this->product->judul(),
            $this->product->sku,
            $varian === null ? '' : ' varian '.$varian->nama(),
        );

        return 'https://wa.me/'.$nomor.'?text='.rawurlencode($pesan);
    }

    private function varian(): ?ProductVariant
    {
        if ($this->variantId === null) {
            return null;
        }

        return $this->product->variants
            ->firstWhere('id', $this->variantId);
    }

    private function tersediaUntukDijual(): int
    {
        return $this->varian()?->stokTersedia() ?? 0;
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();
        $varian = $this->varian();
        $tersedia = $this->tersediaUntukDijual();
        $harga = $varian?->hargaAkhir() ?? $this->product->hargaMulai();
        $spesifikasi = $this->product->spesifikasi();

        $terkait = Product::query()
            ->terbit()
            ->where('category_id', $this->product->category_id)
            ->whereKeyNot($this->product->id)
            ->with(['media', 'variants'])
            ->take(4)
            ->get();

        $judul = $this->product->judul();
        $mediaUtama = $this->product->gambarUtama();
        $gambarOg = $mediaUtama?->url('og');

        $deskripsiMeta = trim(strip_tags(
            $this->product->ringkasan()
                ?: mb_substr($this->product->deskripsi(), 0, 160)
        ));

        // Media yang dapat diputar (Fase 2: P2-01, P2-02). Diambil dari
        // relasi yang sudah dimuat, bukan kueri baru.
        $media3d = $this->product->media->firstWhere(fn ($m) => (($m->type->value ?? $m->type) === 'model_3d') && $m->dapatDiputar());
        $audio = $this->product->media->firstWhere(fn ($m) => (($m->type->value ?? $m->type) === 'audio') && $m->dapatDiputar());

        return view('livewire.front.detail-produk', [
            'varian' => $varian,
            'terkait' => $terkait,
            'tersedia' => $tersedia,
            'harga' => $harga,
            'spesifikasi' => $spesifikasi,
            'tautanWhatsApp' => $this->tautanWhatsApp(),
            'media3d' => $media3d,
            'audio' => $audio,
        ])
            ->title($judul.' · '.$toko->teks('site_name'))
            ->layoutData([
                'kanonikal' => route('front.products.show', $this->product->slug),
                'metaOg' => [
                    'tipe' => 'product',
                    'judul' => $judul,
                    'deskripsi' => $deskripsiMeta !== '' ? $deskripsiMeta : $toko->teks('site_tagline'),
                    'gambar' => filled($gambarOg) ? url($gambarOg) : null,
                    'alt' => $mediaUtama?->alt() ?? $judul,
                ],
                // Data terstruktur diletakkan di dalam <body> dan diteruskan
                // lewat layoutData, bukan lewat @push/@stack.
                //
                // Livewire merender komponen halaman lebih dulu, baru
                // menyuntikkan hasilnya ke layout. Isi @push dari komponen
                // karena itu tidak pernah sampai ke @stack milik layout, dan
                // seluruh blok JSON-LD hilang tanpa galat apa pun.
                'kepala' => view('partials.front.data-terstruktur', [
                    'produk' => $this->product,
                    'sku' => $this->product->variants
                        ->where('is_active', true)
                        ->map(fn (ProductVariant $v) => [
                            'sku' => $v->sku_variant,
                            'nama' => $v->nama(),
                            'harga' => number_format($v->hargaAkhir(), 2, '.', ''),
                            'stok' => $v->stokTersedia(),
                        ])
                        ->values()
                        ->all(),
                    'deskripsi' => $deskripsiMeta,
                    'gambar' => filled($gambarOg) ? url($gambarOg) : null,
                    'kategori' => $this->product->category?->nama(),
                    'toko' => $toko->teks('site_name'),
                ])->render()
                    // Data terstruktur tingkat situs (toko + breadcrumb), dan
                    // ringkasan yang boleh dikutip mesin jawaban. Digabung di
                    // sini supaya tetap satu tempat: partial di atas mengurus
                    // produk, kelas DataTerstruktur mengurus identitas toko.
                    .view('partials.front.data-terstruktur-situs', [
                        'produk' => $this->product,
                    ])->render(),

                'ringkasanAi' => $this->ringkasanAi($harga, $tersedia),
            ]);
    }

    /**
     * Satu paragraf ringkasan untuk mesin jawaban (GEO).
     *
     * Ditulis sebagai kalimat lengkap yang menjawab "apa ini, berapa harganya,
     * bisa dikirim ke mana" — bentuk yang langsung dapat dikutip. Mesin jawaban
     * mengutip kalimat, bukan tabel: ringkasan yang disusun sendiri oleh mereka
     * dari menu navigasi sering salah menyebut harga atau ketersediaan.
     */
    private function ringkasanAi(string $harga, int $tersedia): string
    {
        $nama = $this->product->judul();
        $kategori = $this->product->category?->nama();
        $toko = app(\App\Support\Pengaturan::class)->teks('site_name', 'Iksan Knalpot');

        $bagian = ["{$nama}".($kategori ? " adalah produk {$kategori}" : '')." dari {$toko}."];

        if ($harga !== '') {
            // $harga berupa angka berformat ("1250000.00"), bukan teks rupiah.
            // Menyisipkannya apa adanya menghasilkan "Harganya 1250000" —
            // terbaca sebagai nomor telepon oleh pembaca maupun mesin jawaban.
            $bagian[] = 'Harganya mulai '.rupiah((float) $harga).'.';
        }

        $bagian[] = $tersedia > 0
            ? "Tersedia {$tersedia} varian yang dapat dibeli, dikirim ke seluruh Indonesia."
            : 'Saat ini sedang tidak tersedia.';

        $deskripsi = trim(strip_tags($this->product->deskripsi()));

        if ($deskripsi !== '') {
            $bagian[] = \Illuminate\Support\Str::limit($deskripsi, 220);
        }

        return implode(' ', $bagian);
    }
}
