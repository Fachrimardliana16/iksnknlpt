<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\MediaUploadLog;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\Produk\PengunggahMediaProduk;
use App\Support\CacheKatalog;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Form produk tiga tab: Informasi Dasar, Varian, Media
 * (checklist F3-03, F3-04, F3-05).
 *
 * Satu komponen, bukan tiga halaman. Tab disimpan sebagai keadaan di sisi klien
 * supaya berpindah tab tidak memuat ulang apa pun, tetapi data tetap diperiksa
 * dan disimpan di server pada setiap langkah — tab hanyalah cara menampilkan,
 * bukan tempat menyimpan.
 *
 * Produk baru dibuat lebih dulu sebagai draf (belum terbit) begitu tab pertama
 * disimpan. Tanpa itu, varian dan media tidak punya induk untuk digantung, dan
 * pengguna yang mengunggah sepuluh foto lalu menutup peramban akan kehilangan
 * semuanya.
 */
#[Layout('layouts.admin')]
class ProdukForm extends Component
{
    use WithFileUploads;

    public ?int $produkId = null;

    public string $tab = 'info';

    // ── Tab 1: informasi dasar ───────────────────────────────────────
    public ?int $category_id = null;

    public string $sku = '';

    public string $title_id = '';

    public string $title_en = '';

    public string $slug = '';

    public string $excerpt_id = '';

    public string $excerpt_en = '';

    public string $description_id = '';

    public string $description_en = '';

    public string $base_price = '';

    public string $weight_gram = '1000';

    public bool $is_featured = false;

    public bool $is_published = false;

    public bool $slugDisentuh = false;

    // ── Tab 2: varian ───────────────────────────────────────────────
    /**
     * Baris varian disimpan sebagai larik biasa, bukan komponen anak.
     * Alasannya: seluruh varian disimpan dalam satu transaksi bersama produk,
     * sehingga tidak mungkin ada produk dengan sebagian varian tersimpan.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $varian = [];

    // ── Tab 3: media ────────────────────────────────────────────────
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $berkasBaru = [];

    /**
     * Menandai bahwa peramban sedang mengunggah berkas sementara ke server.
     *
     * Livewire mengunggah berkas ke folder sementara lebih dulu, baru
     * memanggil aksi. Selama unggahan itu berjalan, $berkasBaru masih kosong,
     * sehingga aksi yang dijalankan terlalu cepat akan berhenti dengan pesan
     * yang menyesatkan — seolah-olah pengguna belum menyimpan informasi dasar.
     * Atribut ini dipakai tampilan untuk menahan tombol sampai siap.
     */
    public bool $sedangUnggah = false;

    /** @var array<int, int> Urutan id media setelah pengguna menggesernya. */
    public array $urutanMedia = [];

    public ?string $pesan = null;

    public ?string $pesanTab = null;

    /** @var array<int, string> */
    public array $ditolak = [];

    public function mount(?string $ulid = null): void
    {
        if ($ulid === null) {
            $this->siapkanProdukBaru();

            return;
        }

        $produk = Product::query()
            ->with(['media', 'variants' => fn ($q) => $q->orderBy('display_order')])
            ->where('ulid', $ulid)
            ->firstOrFail();

        $this->produkId = $produk->id;
        $this->category_id = $produk->category_id;
        $this->sku = (string) $produk->sku;
        $this->title_id = (string) $produk->title_id;
        $this->title_en = (string) $produk->title_en;
        $this->slug = (string) $produk->slug;
        $this->excerpt_id = (string) $produk->excerpt_id;
        $this->excerpt_en = (string) $produk->excerpt_en;
        $this->description_id = (string) $produk->description_id;
        $this->description_en = (string) $produk->description_en;
        $this->base_price = (string) $produk->base_price;
        $this->weight_gram = (string) $produk->weight_gram;
        $this->is_featured = (bool) $produk->is_featured;
        $this->is_published = (bool) $produk->is_published;
        $this->slugDisentuh = true;

        $this->varian = $produk->variants->map(fn (ProductVariant $v) => [
            'id' => $v->id,
            'nama_id' => (string) $v->variant_name_id,
            'nama_en' => (string) $v->variant_name_en,
            'selisih' => (string) $v->price_adjustment,
            'stok' => (int) $v->stock_quantity,
            'ditahan' => (int) $v->reserved_quantity,
            'berat' => $v->weight_gram === null ? '' : (string) $v->weight_gram,
            'aktif' => (bool) $v->is_active,
        ])->values()->all();

        $this->urutanMedia = $produk->media()->orderBy('display_order')->pluck('id')->all();
    }

    private function siapkanProdukBaru(): void
    {
        $this->produkId = null;
        $this->sku = $this->skuOtomatis();
        $this->category_id = Category::query()->aktif()->terurut()->value('id');

        // Varian pertama sengaja belum dibuat. Produk baru belum tersimpan, jadi
        // variannya tidak punya induk untuk digantung; baris default dibuat
        // begitu tab pertama disimpan.
        $this->varian = [];
    }

    /** Baris varian kosong dengan bentuk yang sama seperti hasil muat. */
    private function barisVarian(): array
    {
        return [
            'id' => null,
            'nama_id' => '',
            'nama_en' => '',
            'selisih' => '0',
            'stok' => 0,
            'ditahan' => 0,
            'berat' => '',
            'aktif' => true,
        ];
    }

    /** SKU dasar yang belum dipakai, supaya tidak gagal validasi sejak awal. */
    private function skuOtomatis(): string
    {
        $nomor = ((int) Product::query()->max('id')) + 1;
        $kandidat = sprintf('%s-%04d', config('iksan.order_prefix', 'IK'), $nomor);

        while (Product::query()->where('sku', $kandidat)->exists()) {
            $nomor++;
            $kandidat = sprintf('%s-%04d', config('iksan.order_prefix', 'IK'), $nomor);
        }

        return $kandidat;
    }

    public function updatedTitleId(string $nilai): void
    {
        if (! $this->slugDisentuh) {
            $this->slug = $this->slugUnik($nilai, $this->produkId);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugDisentuh = true;
    }

    public function aturTab(string $tab): void
    {
        if (in_array($tab, ['info', 'varian', 'media'], true)) {
            $this->tab = $tab;
            $this->resetValidation();
            $this->pesanTab = null;
        }
    }

    // ── Tab 1: simpan informasi dasar ───────────────────────────────

    public function simpanInfo(): void
    {
        $bersih = $this->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku' => [
                'required', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-_]+$/',
                Rule::unique('products', 'sku')->ignore($this->produkId),
            ],
            'title_id' => ['required', 'string', 'min:3', 'max:180'],
            'title_en' => ['nullable', 'string', 'max:180'],
            'slug' => [
                'required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('products', 'slug')->ignore($this->produkId),
            ],
            'excerpt_id' => ['nullable', 'string', 'max:300'],
            'excerpt_en' => ['nullable', 'string', 'max:300'],
            'description_id' => ['nullable', 'string', 'max:20000'],
            'description_en' => ['nullable', 'string', 'max:20000'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'weight_gram' => ['required', 'integer', 'min:1', 'max:300000'],
            'is_featured' => ['boolean'],
            'is_published' => ['boolean'],
        ], [
            'category_id.required' => 'Pilih kategori produk.',
            'category_id.exists' => 'Kategori yang dipilih tidak ada.',
            'sku.required' => 'SKU wajib diisi.',
            'sku.regex' => 'SKU hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'sku.unique' => 'SKU ini sudah dipakai produk lain.',
            'title_id.required' => 'Nama produk dalam bahasa Indonesia wajib diisi.',
            'title_id.min' => 'Nama produk minimal tiga huruf.',
            'slug.regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
            'slug.unique' => 'Slug ini sudah dipakai produk lain.',
            'base_price.required' => 'Harga dasar wajib diisi.',
            'base_price.numeric' => 'Harga harus berupa angka tanpa titik atau koma.',
            'weight_gram.required' => 'Berat wajib diisi karena dipakai menghitung ongkir.',
        ]);

        DB::transaction(function () use ($bersih): void {
            $data = [
                'category_id' => $bersih['category_id'],
                'sku' => $bersih['sku'],
                'title_id' => $bersih['title_id'],
                'title_en' => $bersih['title_en'] ?: null,
                'slug' => $bersih['slug'],
                'excerpt_id' => $bersih['excerpt_id'] ?: null,
                'excerpt_en' => $bersih['excerpt_en'] ?: null,
                'description_id' => $bersih['description_id'] ?: null,
                'description_en' => $bersih['description_en'] ?: null,
                'base_price' => $bersih['base_price'],
                'weight_gram' => (int) $bersih['weight_gram'],
                'is_featured' => $bersih['is_featured'],
                'is_published' => $bersih['is_published'],
            ];

            if ($this->produkId === null) {
                $produk = Product::query()->create($data + [
                    'published_at' => $bersih['is_published'] ? now() : null,
                ]);

                $this->produkId = $produk->id;

                CatatanAktivitas::catat('produk.dibuat', ['sku' => $produk->sku], auth()->id(), $produk);
                CacheKatalog::tandaiProduk($produk, 'produk dibuat');

                // Varian default benar-benar dibuat, bukan hanya disiapkan di
                // formulir. Produk tanpa varian tidak dapat dibeli sama sekali,
                // sehingga menyimpannya kosong berarti membuat produk yang pasti
                // gagal saat checkout — dan itu baru ketahuan oleh pembeli.
                $varian = $produk->variants()->create([
                    'sku_variant' => $produk->sku.'-V1',
                    'variant_name_id' => 'Standar',
                    'variant_name_en' => 'Standard',
                    'price_adjustment' => 0,
                    'stock_quantity' => 0,
                    'display_order' => 1,
                    'is_active' => true,
                ]);

                $this->varian = [$this->barisVarian()];
                $this->varian[0]['id'] = $varian->id;
            } else {
                $produk = Product::query()->findOrFail($this->produkId);

                // Waktu terbit hanya diisi sekali, saat produk pertama kali
                // diterbitkan. Menerbitkan ulang tidak boleh menggeser tanggal
                // terbit yang asli.
                if ($bersih['is_published'] && $produk->published_at === null) {
                    $data['published_at'] = now();
                }

                if (! $bersih['is_published']) {
                    $data['published_at'] = null;
                }

                $produk->update($data);

                CatatanAktivitas::catat('produk.diubah', [
                    'sku' => $produk->sku,
                    'is_published' => $produk->is_published,
                ], auth()->id(), $produk);

                CacheKatalog::tandaiProduk($produk, 'produk diubah');
            }
        });

        $this->pesan = 'Informasi dasar tersimpan.';
        $this->pesanTab = 'info';

        // Setelah tab pertama tersimpan, lanjutkan ke varian: produk tanpa
        // varian belum bisa dibeli, jadi itu langkah berikutnya yang wajar.
        $this->tab = 'varian';
    }

    // ── Tab 2: varian ───────────────────────────────────────────────

    public function tambahVarian(): void
    {
        $this->varian[] = $this->barisVarian();
        $this->pesanTab = null;
    }

    public function salinVarian(int $indeks): void
    {
        $sumber = $this->varian[$indeks] ?? null;

        if ($sumber === null) {
            return;
        }

        $this->varian[$indeks + 1] = array_merge($sumber, [
            'id' => null,
            'nama_id' => $sumber['nama_id'].' (salinan)',
            // Stok TIDAK disalin. Dua varian dengan stok yang sama akan
            // menjanjikan barang yang cuma ada satu.
            'stok' => 0,
            'ditahan' => 0,
        ]);

        $this->varian = array_values($this->varian);
        $this->pesanTab = null;
    }

    public function hapusVarian(int $indeks): void
    {
        $baris = $this->varian[$indeks] ?? null;

        if ($baris === null) {
            return;
        }

        // Varian yang sudah dipesan tidak boleh dihapus: baris pesanan lama
        // menunjuk ke sana, dan menghapusnya membuat riwayat pesanan kehilangan
        // keterangan barang apa yang dibeli.
        if ($baris['id'] !== null) {
            $terpakai = OrderItem::query()->where('product_variant_id', $baris['id'])->exists();

            if ($terpakai) {
                $this->addError('varian', 'Varian "'.$baris['nama_id'].'" sudah pernah dipesan, jadi tidak bisa dihapus. Nonaktifkan saja.');

                return;
            }
        }

        if (count($this->varian) <= 1) {
            $this->addError('varian', 'Produk harus punya minimal satu varian.');

            return;
        }

        unset($this->varian[$indeks]);
        $this->varian = array_values($this->varian);
        $this->pesanTab = null;
    }

    public function geserVarian(int $indeks, string $arah): void
    {
        $tujuan = $arah === 'naik' ? $indeks - 1 : $indeks + 1;

        if ($tujuan < 0 || $tujuan >= count($this->varian)) {
            return;
        }

        [$this->varian[$indeks], $this->varian[$tujuan]] = [$this->varian[$tujuan], $this->varian[$indeks]];
    }

    public function simpanVarian(): void
    {
        if ($this->produkId === null) {
            $this->addError('varian', 'Simpan informasi dasar dulu sebelum mengatur varian.');

            return;
        }

        if ($this->varian === []) {
            $this->addError('varian', 'Produk harus punya minimal satu varian.');

            return;
        }

        // Aturan diperiksa per baris dengan indeks, sehingga pesan galatnya
        // menunjuk ke baris yang salah — bukan satu pesan umum di atas tabel.
        $aturan = [];
        $pesan = [];

        foreach ($this->varian as $i => $baris) {
            $aturan["varian.$i.nama_id"] = ['required', 'string', 'min:1', 'max:80'];
            $aturan["varian.$i.selisih"] = ['required', 'numeric', 'min:-999999999', 'max:999999999'];
            $aturan["varian.$i.stok"] = ['required', 'integer', 'min:0', 'max:999999'];
            $aturan["varian.$i.berat"] = ['nullable', 'integer', 'min:1', 'max:300000'];
            $aturan["varian.$i.aktif"] = ['boolean'];

            $pesan["varian.$i.nama_id.required"] = 'Nama varian baris '.($i + 1).' belum diisi.';
            $pesan["varian.$i.selisih.required"] = 'Penyesuaian harga baris '.($i + 1).' belum diisi.';
            $pesan["varian.$i.selisih.numeric"] = 'Penyesuaian harga baris '.($i + 1).' harus berupa angka.';
            $pesan["varian.$i.stok.required"] = 'Stok baris '.($i + 1).' belum diisi.';
            $pesan["varian.$i.stok.min"] = 'Stok baris '.($i + 1).' tidak boleh negatif.';
        }

        $this->validate($aturan, $pesan);

        // Nama varian harus berbeda satu sama lain. Dua varian bernama sama
        // membuat pembeli tidak tahu mana yang dipilih, dan membuat baris
        // pesanan ambigu saat dibaca kembali.
        $nama = array_map(fn (array $b) => Str::lower(trim((string) $b['nama_id'])), $this->varian);
        $kembar = array_filter(array_count_values($nama), fn (int $n) => $n > 1);

        if ($kembar !== []) {
            $this->addError('varian', 'Nama varian tidak boleh kembar: "'.implode('", "', array_keys($kembar)).'".');

            return;
        }

        // Penyesuaian harga tidak boleh membuat harga akhir menjadi negatif.
        $hargaDasar = (float) ($this->base_price ?: 0);

        foreach ($this->varian as $i => $baris) {
            if ($hargaDasar + (float) $baris['selisih'] < 0) {
                $this->addError(
                    "varian.$i.selisih",
                    'Harga akhir varian baris '.($i + 1).' menjadi negatif.',
                );

                return;
            }
        }

        // Stok tidak boleh diturunkan di bawah jumlah yang sedang ditahan
        // pesanan berjalan. Kalau dilanggar, stok tersedia menjadi negatif dan
        // barang yang sudah dijanjikan ke pembeli lain ikut terjual.
        foreach ($this->varian as $i => $baris) {
            if ($baris['id'] === null) {
                continue;
            }

            $ditahan = (int) ($baris['ditahan'] ?? 0);

            if ((int) $baris['stok'] < $ditahan) {
                $this->addError(
                    "varian.$i.stok",
                    sprintf(
                        'Stok varian baris %d tidak boleh di bawah %d karena sebanyak itu sedang ditahan pesanan berjalan.',
                        $i + 1,
                        $ditahan,
                    ),
                );

                return;
            }
        }

        DB::transaction(function (): void {
            $produk = Product::query()->findOrFail($this->produkId);
            $idDipakai = [];

            foreach ($this->varian as $urutan => $baris) {
                $data = [
                    'variant_name_id' => $baris['nama_id'],
                    'variant_name_en' => $baris['nama_en'] ?: null,
                    'price_adjustment' => $baris['selisih'],
                    'stock_quantity' => (int) $baris['stok'],
                    'weight_gram' => $baris['berat'] === '' ? null : (int) $baris['berat'],
                    'display_order' => $urutan + 1,
                    'is_active' => $baris['aktif'],
                ];

                if ($baris['id'] === null) {
                    $data['sku_variant'] = $produk->sku.'-V'.($urutan + 1);
                    $varian = $produk->variants()->create($data);
                    $idDipakai[] = $varian->id;
                } else {
                    $varian = ProductVariant::query()->findOrFail($baris['id']);
                    $varian->update($data);
                    $idDipakai[] = $varian->id;
                }
            }

            // Varian yang hilang dari daftar dihapus, kecuali yang pernah
            // dipesan — penghapusan itu sudah dicegah di hapusVarian, tetapi
            // pemeriksaan diulang di sini karena keadaan bisa berubah antara
            // penghapusan di antarmuka dan penyimpanan.
            $produk->variants()
                ->whereNotIn('id', $idDipakai)
                ->get()
                ->each(function (ProductVariant $v): void {
                    if (! OrderItem::query()->where('product_variant_id', $v->id)->exists()) {
                        $v->delete();
                    }
                });
        });

        // Muat ulang supaya kolom "ditahan" dan id varian baru segar.
        $this->muatUlangVarian();

        // Stok dan harga menentukan label "habis" dan "mulai dari" di katalog.
        CacheKatalog::tandaiProduk(Product::query()->find($this->produkId), 'varian disimpan');

        $this->pesan = count($this->varian).' varian tersimpan.';
        $this->pesanTab = 'varian';
        $this->tab = 'media';
    }

    private function muatUlangVarian(): void
    {
        if ($this->produkId === null) {
            return;
        }

        $this->varian = ProductVariant::query()
            ->where('product_id', $this->produkId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'nama_id' => (string) $v->variant_name_id,
                'nama_en' => (string) $v->variant_name_en,
                'selisih' => (string) $v->price_adjustment,
                'stok' => (int) $v->stock_quantity,
                'ditahan' => (int) $v->reserved_quantity,
                'berat' => $v->weight_gram === null ? '' : (string) $v->weight_gram,
                'aktif' => (bool) $v->is_active,
            ])->values()->all();
    }

    // ── Tab 3: media ────────────────────────────────────────────────

    public function diperbaruiBerkasBaru(): void
    {
        $this->pesanTab = null;
        $this->ditolak = [];
        $this->sedangUnggah = false;
    }

    public function unggah(): void
    {
        $pengunggah = app(PengunggahMediaProduk::class);

        if ($this->produkId === null) {
            $this->addError('berkasBaru', 'Simpan informasi dasar dulu sebelum mengunggah gambar.');

            return;
        }

        if ($this->berkasBaru === []) {
            return;
        }

        $this->validate([
            'berkasBaru' => ['array', 'max:20'],
            'berkasBaru.*' => ['file', 'max:5120'],
        ], [
            'berkasBaru.max' => 'Maksimum 20 berkas sekaligus. Ulangi untuk sisanya.',
            'berkasBaru.*.max' => 'Setiap berkas maksimum 5 MB.',
            'berkasBaru.*.file' => 'Berkas tidak dapat dibaca.',
        ]);

        $produk = Product::query()->findOrFail($this->produkId);

        $hasil = $pengunggah->unggahBanyak($produk, $this->berkasBaru);

        $this->berkasBaru = [];
        $this->ditolak = $hasil['ditolak'];

        $this->pesan = $hasil['antre'].' berkas masuk antrean pemrosesan.';
        $this->pesanTab = 'media';

        // Berkas selesai diproses oleh pekerja antrean, bukan di sini. Muat
        // ulang daftar supaya yang sudah selesai langsung tampak.
        $this->muatUlangUrutanMedia();
    }

    public function hapusMedia(int $id): void
    {
        $pengunggah = app(PengunggahMediaProduk::class);

        $media = ProductMedia::query()->where('product_id', $this->produkId)->findOrFail($id);

        // Gambar yang sudah dipakai di baris pesanan tetap boleh dihapus —
        // pesanan menyimpan jalur gambarnya sendiri saat dicetak. Yang dicegah
        // adalah menghapus gambar utama tanpa menunjuk penggantinya.
        $pengunggah->hapusBerkas($media);
        $media->delete();

        $this->rapikanMedia();
        $this->muatUlangUrutanMedia();

        $this->pesan = 'Gambar dihapus.';
        $this->pesanTab = 'media';

        CacheKatalog::tandaiProduk(Product::query()->find($this->produkId), 'media dihapus');
    }

    public function jadikanUtama(int $id): void
    {
        DB::transaction(function () use ($id): void {
            ProductMedia::query()->where('product_id', $this->produkId)->update(['is_primary' => false]);

            ProductMedia::query()
                ->where('product_id', $this->produkId)
                ->whereKey($id)
                ->update(['is_primary' => true]);
        });

        $this->pesan = 'Gambar utama diperbarui.';
        $this->pesanTab = 'media';

        CacheKatalog::tandaiProduk(Product::query()->find($this->produkId), 'gambar utama berubah');
    }

    public function geserMedia(int $id, string $arah): void
    {
        $urutan = $this->urutanMedia;
        $posisi = array_search($id, $urutan, true);

        if ($posisi === false) {
            return;
        }

        $tujuan = $arah === 'naik' ? $posisi - 1 : $posisi + 1;

        if ($tujuan < 0 || $tujuan >= count($urutan)) {
            return;
        }

        [$urutan[$posisi], $urutan[$tujuan]] = [$urutan[$tujuan], $urutan[$posisi]];
        $this->urutanMedia = $urutan;

        $this->simpanUrutanMedia();
    }

    /** Dipanggil dari sisi klien setelah pengguna selesai menyeret gambar. */
    public function simpanUrutanDariKlien(array $urutan): void
    {
        $sah = ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->pluck('id')
            ->all();

        $bersih = array_values(array_filter(
            array_map('intval', $urutan),
            fn (int $id) => in_array($id, $sah, true),
        ));

        if ($bersih === []) {
            return;
        }

        $this->urutanMedia = $bersih;
        $this->simpanUrutanMedia();

        $this->pesan = 'Urutan gambar disimpan.';
        $this->pesanTab = 'media';
    }

    private function simpanUrutanMedia(): void
    {
        DB::transaction(function (): void {
            foreach ($this->urutanMedia as $nomor => $id) {
                ProductMedia::query()
                    ->where('product_id', $this->produkId)
                    ->whereKey($id)
                    ->update(['display_order' => $nomor + 1]);
            }
        });
    }

    public function simpanAlt(int $id, string $teks, string $bahasa): void
    {
        $kolom = $bahasa === 'en' ? 'alt_text_en' : 'alt_text_id';

        ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->whereKey($id)
            ->update([$kolom => $teks === '' ? null : $teks]);

        $this->pesan = 'Teks alternatif disimpan.';
        $this->pesanTab = 'media';
    }

    /** Pastikan selalu ada tepat satu gambar utama selama ada gambar. */
    private function rapikanMedia(): void
    {
        if ($this->produkId === null) {
            return;
        }

        $adaUtama = ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->where('is_primary', true)
            ->exists();

        if ($adaUtama) {
            return;
        }

        $pertama = ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->orderBy('display_order')
            ->first();

        $pertama?->update(['is_primary' => true]);
    }

    private function muatUlangUrutanMedia(): void
    {
        if ($this->produkId === null) {
            return;
        }

        $this->rapikanMedia();

        $this->urutanMedia = ProductMedia::query()
            ->where('product_id', $this->produkId)
            ->orderBy('display_order')
            ->pluck('id')
            ->all();
    }

    public function periksaAntrean(): void
    {
        $this->muatUlangUrutanMedia();
        $this->pesan = 'Daftar gambar diperbarui.';
        $this->pesanTab = 'media';
    }

    // ── Publikasi ───────────────────────────────────────────────────

    /**
     * Menerbitkan atau menarik produk.
     *
     * Produk tanpa varian tidak boleh diterbitkan: halaman detailnya akan
     * menampilkan tombol beli yang tidak bisa ditekan, dan checkout-nya gagal.
     */
    public function ubahTerbit(): void
    {
        if ($this->produkId === null) {
            $this->addError('umum', 'Simpan informasi dasar dulu.');

            return;
        }

        $produk = Product::query()->withCount('variants')->findOrFail($this->produkId);

        if (! $produk->is_published && $produk->variants_count === 0) {
            $this->addError('umum', 'Produk belum punya varian, jadi belum bisa diterbitkan.');

            return;
        }

        $jadi = ! $produk->is_published;

        $produk->update([
            'is_published' => $jadi,
            'published_at' => $jadi ? ($produk->published_at ?? now()) : null,
        ]);

        $this->is_published = $jadi;

        CatatanAktivitas::catat($jadi ? 'produk.diterbitkan' : 'produk.ditarik', [
            'sku' => $produk->sku,
        ], auth()->id(), $produk);

        CacheKatalog::tandaiProduk($produk, $jadi ? 'produk diterbitkan' : 'produk ditarik');

        $this->pesan = $jadi ? 'Produk diterbitkan.' : 'Produk ditarik dari katalog.';
        $this->pesanTab = null;
    }

    private function slugUnik(string $teks, ?int $kecualiId): string
    {
        $dasar = Str::slug($teks);

        if ($dasar === '') {
            return '';
        }

        $kandidat = $dasar;
        $nomor = 2;

        while (Product::query()
            ->where('slug', $kandidat)
            ->when($kecualiId !== null, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists()
        ) {
            $kandidat = $dasar.'-'.$nomor;
            $nomor++;
        }

        return $kandidat;
    }

    public function render(): View
    {
        $produk = $this->produkId === null ? null : Product::query()->find($this->produkId);

        $media = $this->produkId === null
            ? collect()
            : ProductMedia::query()
                ->where('product_id', $this->produkId)
                ->orderByRaw('CASE WHEN is_primary = 1 THEN 0 ELSE 1 END')
                ->orderBy('display_order')
                ->get();

        // Jumlah berkas yang masih diproses di antrean, supaya pengguna tahu
        // bahwa gambar yang belum muncul bukan hilang.
        // Hanya berkas milik produk ini. Menghitung antrean seluruh produk akan
        // menampilkan angka milik produk lain di halaman ini.
        $sedangDiproses = $this->produkId === null ? 0 : MediaUploadLog::query()
            ->where('product_id', $this->produkId)
            ->whereIn('status', ['queued', 'processing'])
            ->count();

        return view('livewire.admin.produk-form', [
            'produk' => $produk,
            'kategori' => Category::query()->terurut()->get(),
            'media' => $media,
            'sedangDiproses' => $sedangDiproses,
        ])->layout('layouts.admin', [
            'judul' => $produk === null ? 'Produk Baru' : 'Sunting '.$produk->title_id,
            'bagian' => 'Katalog',
        ]);
    }
}
