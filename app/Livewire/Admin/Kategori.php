<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Support\CacheKatalog;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD kategori (checklist F3-01).
 *
 * Dua bahasa wajib: nama dan deskripsi disimpan dalam versi Indonesia dan
 * Inggris. Nama Inggris boleh kosong dan akan jatuh ke versi Indonesia saat
 * ditampilkan, tetapi nama Indonesia tidak boleh kosong — tanpa itu tidak ada
 * yang dapat ditampilkan sama sekali.
 *
 * Urutan tampil dikelola lewat tombol naik/turun, bukan angka yang diketik
 * manual. Menggeser satu kategori hanya mengubah dua baris dan tidak pernah
 * meninggalkan celah atau nomor kembar.
 */
#[Layout('layouts.admin')]
class Kategori extends Component
{
    use WithPagination;

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    public bool $formTerbuka = false;

    public ?int $suntingId = null;

    public string $nama_id = '';

    public string $nama_en = '';

    public string $deskripsi_id = '';

    public string $deskripsi_en = '';

    public string $slug = '';

    public bool $aktif = true;

    /** Slug diubah otomatis dari nama selama pengguna belum menyentuhnya. */
    public bool $slugDisentuh = false;

    public function mount(): void
    {
        $this->slugDisentuh = false;
    }

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    /** Slug mengikuti nama Indonesia sampai pengguna mengetiknya sendiri. */
    public function updatedNamaId(string $nilai): void
    {
        if (! $this->slugDisentuh) {
            $this->slug = $this->slugUnik($nilai, $this->suntingId);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugDisentuh = true;
    }

    public function bukaForm(): void
    {
        $this->bersihkan();
        $this->suntingId = null;
        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->bersihkan();
        $this->formTerbuka = false;
    }

    public function sunting(int $id): void
    {
        $kategori = Category::query()->findOrFail($id);

        $this->suntingId = $kategori->id;
        $this->nama_id = (string) $kategori->name_id;
        $this->nama_en = (string) $kategori->name_en;
        $this->deskripsi_id = (string) $kategori->description_id;
        $this->deskripsi_en = (string) $kategori->description_en;
        $this->slug = (string) $kategori->slug;
        $this->aktif = (bool) $kategori->is_active;
        $this->slugDisentuh = true;
        $this->formTerbuka = true;

        $this->bersihkanPesan();
    }

    public function simpan(): void
    {
        $aturan = [
            'nama_id' => ['required', 'string', 'min:2', 'max:120'],
            'nama_en' => ['nullable', 'string', 'max:120'],
            'deskripsi_id' => ['nullable', 'string', 'max:2000'],
            'deskripsi_en' => ['nullable', 'string', 'max:2000'],
            'slug' => [
                'required', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($this->suntingId),
            ],
            'aktif' => ['boolean'],
        ];

        $pesan = [
            'nama_id.required' => 'Nama kategori dalam bahasa Indonesia wajib diisi.',
            'nama_id.min' => 'Nama kategori minimal dua huruf.',
            'slug.regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
            'slug.unique' => 'Slug ini sudah dipakai kategori lain.',
        ];

        $bersih = $this->validate($aturan, $pesan);

        // Kategori yang punya produk tidak boleh dinonaktifkan tanpa disadari.
        // Produknya tetap terbit, tetapi tidak lagi terjangkau dari daftar
        // kategori — pembeli melihat barang yang tidak bisa ditemukan.
        if ($this->suntingId !== null && ! $this->aktif) {
            $terpakai = Product::query()->where('category_id', $this->suntingId)->count();

            if ($terpakai > 0) {
                $this->addError(
                    'aktif',
                    "Kategori ini dipakai {$terpakai} produk. Pindahkan produknya dulu sebelum menonaktifkan.",
                );

                return;
            }
        }

        DB::transaction(function () use ($bersih): void {
            if ($this->suntingId === null) {
                $urutan = ((int) Category::query()->max('display_order')) + 1;

                $kategori = Category::query()->create([
                    'name_id' => $bersih['nama_id'],
                    'name_en' => $bersih['nama_en'] ?: null,
                    'description_id' => $bersih['deskripsi_id'] ?: null,
                    'description_en' => $bersih['deskripsi_en'] ?: null,
                    'slug' => $bersih['slug'],
                    'display_order' => $urutan,
                    'is_active' => $bersih['aktif'],
                ]);

                CatatanAktivitas::catat('kategori.dibuat', ['slug' => $kategori->slug], auth()->id(), $kategori);
                CacheKatalog::tandaiBerubah('kategori dibuat');
                $this->pesan = 'Kategori "'.$kategori->name_id.'" ditambahkan.';
            } else {
                $kategori = Category::query()->findOrFail($this->suntingId);

                $sebelum = [
                    'name_id' => $kategori->name_id,
                    'slug' => $kategori->slug,
                    'is_active' => $kategori->is_active,
                ];

                $kategori->update([
                    'name_id' => $bersih['nama_id'],
                    'name_en' => $bersih['nama_en'] ?: null,
                    'description_id' => $bersih['deskripsi_id'] ?: null,
                    'description_en' => $bersih['deskripsi_en'] ?: null,
                    'slug' => $bersih['slug'],
                    'is_active' => $bersih['aktif'],
                ]);

                CatatanAktivitas::catat('kategori.diubah', [
                    'sebelum' => $sebelum,
                    'sesudah' => [
                        'name_id' => $kategori->name_id,
                        'slug' => $kategori->slug,
                        'is_active' => $kategori->is_active,
                    ],
                ], auth()->id(), $kategori);

                CacheKatalog::tandaiBerubah('kategori diubah');

                $this->pesan = 'Kategori "'.$kategori->name_id.'" diperbarui.';
            }
        });

        $this->bersihkan();
        $this->formTerbuka = false;
    }

    public function ubahAktif(int $id): void
    {
        $kategori = Category::query()->findOrFail($id);

        if ($kategori->is_active) {
            $terpakai = Product::query()->where('category_id', $kategori->id)->count();

            if ($terpakai > 0) {
                $this->addError(
                    'umum',
                    "Kategori \"{$kategori->name_id}\" dipakai {$terpakai} produk, jadi belum bisa dinonaktifkan.",
                );

                return;
            }
        }

        $kategori->update(['is_active' => ! $kategori->is_active]);

        CatatanAktivitas::catat('kategori.status', [
            'slug' => $kategori->slug,
            'is_active' => $kategori->is_active,
        ], auth()->id(), $kategori);

        $this->pesan = $kategori->is_active
            ? 'Kategori "'.$kategori->name_id.'" diaktifkan.'
            : 'Kategori "'.$kategori->name_id.'" dinonaktifkan.';
    }

    /**
     * Menggeser kategori satu langkah, lalu merapikan seluruh nomor urut.
     *
     * Merapikan setelah setiap pergeseran menjaga agar tidak pernah ada nomor
     * kembar atau celah, sekalipun data lama sudah tidak beraturan.
     */
    public function geser(int $id, string $arah): void
    {
        DB::transaction(function () use ($id, $arah): void {
            $urutan = Category::query()->orderBy('display_order')->orderBy('id')->pluck('id')->all();
            $posisi = array_search($id, $urutan, true);

            if ($posisi === false) {
                return;
            }

            $tujuan = $arah === 'naik' ? $posisi - 1 : $posisi + 1;

            if ($tujuan < 0 || $tujuan >= count($urutan)) {
                return;
            }

            [$urutan[$posisi], $urutan[$tujuan]] = [$urutan[$tujuan], $urutan[$posisi]];

            foreach ($urutan as $nomor => $satuId) {
                Category::query()->whereKey($satuId)->update(['display_order' => $nomor + 1]);
            }
        });
    }

    public function hapus(int $id): void
    {
        $kategori = Category::query()->findOrFail($id);

        $terpakai = Product::query()->where('category_id', $kategori->id)->count();

        if ($terpakai > 0) {
            $this->addError(
                'umum',
                "Kategori \"{$kategori->name_id}\" masih dipakai {$terpakai} produk. Pindahkan produknya dulu.",
            );

            return;
        }

        CatatanAktivitas::catat('kategori.dihapus', ['slug' => $kategori->slug], auth()->id(), $kategori);

        $kategori->delete();

        CacheKatalog::tandaiBerubah('kategori dihapus');

        $this->pesan = 'Kategori "'.$kategori->name_id.'" dihapus.';
    }

    private function slugUnik(string $teks, ?int $kecualiId): string
    {
        $dasar = Str::slug($teks);

        if ($dasar === '') {
            return '';
        }

        $kandidat = $dasar;
        $nomor = 2;

        while ($this->slugDipakai($kandidat, $kecualiId)) {
            $kandidat = $dasar.'-'.$nomor;
            $nomor++;
        }

        return $kandidat;
    }

    private function slugDipakai(string $slug, ?int $kecualiId): bool
    {
        return Category::query()
            ->where('slug', $slug)
            ->when($kecualiId !== null, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists();
    }

    private function bersihkan(): void
    {
        $this->suntingId = null;
        $this->nama_id = '';
        $this->nama_en = '';
        $this->deskripsi_id = '';
        $this->deskripsi_en = '';
        $this->slug = '';
        $this->aktif = true;
        $this->slugDisentuh = false;

        $this->resetValidation();
    }

    private function bersihkanPesan(): void
    {
        $this->pesan = null;
    }

    public ?string $pesan = null;

    public function render(): View
    {
        $daftar = Category::query()
            ->when($this->cari !== '', function ($q): void {
                $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->cari).'%';

                $q->where(fn ($inner) => $inner
                    ->where('name_id', 'like', $pola)
                    ->orWhere('name_en', 'like', $pola)
                    ->orWhere('slug', 'like', $pola));
            })
            ->withCount('products')
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.admin.kategori', [
            'daftar' => $daftar,
            'jumlah' => Category::query()->count(),
            'totalProduk' => Product::query()->count(),
        ])->layout('layouts.admin', ['judul' => 'Kategori', 'bagian' => 'Katalog']);
    }
}
