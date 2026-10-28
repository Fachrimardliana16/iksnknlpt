<?php

namespace App\Livewire\Admin;

use App\Models\HeroSlide;
use App\Services\Hero\PenyimpanHero;
use App\Support\CacheKatalog;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

/**
 * Hero builder (checklist F4-09, F4-10, F4-11).
 *
 * Dua hal yang sengaja dikerjakan di server, bukan di peramban:
 *
 *  - Urutan slide disimpan sebagai display_order yang selalu berurutan rapat
 *    (0,1,2,...) setelah setiap perubahan. Kalau hanya nilai tukar-menukar yang
 *    disimpan, geser berulang kali akan meninggalkan celah dan urutannya menjadi
 *    tidak dapat diprediksi.
 *
 *  - Batas maksimum slide dan minimum satu slide aktif ditegakkan saat
 *    menyimpan, bukan saat menekan tombol, karena keadaan dapat berubah di
 *    antara keduanya.
 */
#[Layout('layouts.admin')]
class HeroBuilder extends KomponenAdmin
{
    // WithFileUploads TIDAK dipakai di sini lagi: halaman ini hanya mengelola
    // daftar, urutan, dan status aktif. Semua unggahan pindah ke HeroSunting.
    public ?string $pesanSukses = null;

    public ?string $galat = null;

    // ── Daftar & urutan ──────────────────────────────────────────────

    public function tambah(): void
    {
        $this->bersihkanPesan();

        $maks = (int) config('iksan.hero.max_slides');

        if (HeroSlide::query()->count() >= $maks) {
            $this->galat = 'Jumlah slide sudah mencapai batas '.$maks.'. Hapus salah satu sebelum menambah.';

            return;
        }

        $slide = HeroSlide::create([
            'title_id' => 'Slide baru',
            'title_en' => 'New slide',
            'media_type' => 'image',
            // Jalur diisi placeholder karena kolomnya wajib. Berkas sungguhan
            // menggantikannya begitu diunggah, dan slide tanpa berkas tidak
            // akan tayang karena is_aktif-nya dinonaktifkan di bawah.
            'media_path' => '',
            'display_order' => (int) HeroSlide::query()->max('display_order') + 1,
            'is_active' => false,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
        ]);

        CatatanAktivitas::catat('hero.ditambah', ['ulid' => $slide->ulid], auth()->id(), $slide);

        CacheKatalog::tandaiBerubah('hero ditambah');

        // Diarahkan ke halaman sunting slide baru. Sebelumnya panel dibuka di
        // tempat; sekarang slide kosong tanpa gambar memang harus diisi di
        // halaman itu, jadi langsung membukanya memangkas satu langkah.
        $this->redirectRoute('admin.hero.edit', ['ulid' => $slide->ulid], navigate: true);
    }

    /** Geser satu langkah ke atas: tukar dengan tetangga terdekat. */
    public function naik(int $id): void
    {
        $this->tukarUrutan($id, -1);
    }

    public function turun(int $id): void
    {
        $this->tukarUrutan($id, 1);
    }

    /**
     * Terapkan urutan hasil seret-lepas pada panel admin.
     *
     * Id dikirim sebagai larik string oleh JavaScript; nilai yang bukan id
     * slide yang sah diabaikan, dan slide yang tidak disebut tetap dipertahankan
     * urutannya di belakang.
     *
     * @param  array<int, mixed>  $urutan
     */
    public function terapkanUrutan(array $urutan): void
    {
        $sah = HeroSlide::query()->pluck('id')->all();
        $diminta = array_values(array_unique(array_map('intval', $urutan)));
        $diminta = array_values(array_intersect($diminta, $sah));

        if ($diminta === []) {
            return;
        }

        $sisa = array_values(array_diff($sah, $diminta));
        $akhir = [...$diminta, ...$sisa];

        DB::transaction(function () use ($akhir): void {
            foreach ($akhir as $posisi => $id) {
                HeroSlide::query()->whereKey($id)->update(['display_order' => $posisi]);
            }
        });

        CatatanAktivitas::catat('hero.urutan.diubah', ['urutan' => $akhir], auth()->id());

        // Urutan di beranda mengikuti display_order, jadi penukaran harus
        // membatalkan cache 'home:isi' — kalau tidak, admin menggeser slide
        // dan melihat urutan lama selama sepuluh menit.
        CacheKatalog::tandaiBerubah('urutan hero diubah');

        $this->pesanSukses = 'Urutan slide disimpan.';
    }

    private function tukarUrutan(int $id, int $arah): void
    {
        $this->bersihkanPesan();

        $semua = HeroSlide::query()->orderBy('display_order')->orderBy('id')->get();

        $posisi = $semua->search(fn (HeroSlide $s): bool => $s->id === $id);

        if ($posisi === false) {
            return;
        }

        $tujuan = $posisi + $arah;

        if ($tujuan < 0 || $tujuan >= $semua->count()) {
            return;
        }

        $this->terapkanUrutan(
            $semua->pluck('id')
                ->map(fn (int $nilai, int $i): int => $i === $posisi ? $semua[$tujuan]->id : ($i === $tujuan ? $id : $nilai))
                ->all(),
        );
    }

    // ── Aktif/nonaktif ───────────────────────────────────────────────

    public function ubahAktif(int $id): void
    {
        $this->bersihkanPesan();

        $slide = HeroSlide::query()->find($id);

        if ($slide === null) {
            return;
        }

        if ($slide->is_active) {
            // Minimal satu slide harus tetap aktif, kalau tidak hero di halaman
            // depan menjadi kosong tanpa penjelasan.
            $lainAktif = HeroSlide::query()
                ->where('is_active', true)
                ->whereKeyNot($id)
                ->count();

            if ($lainAktif < (int) config('iksan.hero.min_slide_aktif')) {
                $this->galat = 'Minimal satu slide harus aktif. Aktifkan slide lain sebelum menonaktifkan yang ini.';

                return;
            }

            $slide->is_active = false;
            $slide->save();

            CacheKatalog::tandaiBerubah('hero dinonaktifkan');

            $this->pesanSukses = 'Slide dinonaktifkan.';

            return;
        }

        if (blank($slide->media_path)) {
            $this->galat = 'Slide ini belum punya berkas. Unggah gambar atau video sebelum mengaktifkan.';

            return;
        }

        $slide->is_active = true;
        $slide->save();

        CatatanAktivitas::catat('hero.diaktifkan', ['ulid' => $slide->ulid], auth()->id(), $slide);

        CacheKatalog::tandaiBerubah('hero diaktifkan');

        $this->pesanSukses = 'Slide diaktifkan.';
    }

    public function hapus(int $id): void
    {
        $this->bersihkanPesan();

        $slide = HeroSlide::query()->find($id);

        if ($slide === null) {
            return;
        }

        if ($slide->is_active) {
            $lainAktif = HeroSlide::query()
                ->where('is_active', true)
                ->whereKeyNot($id)
                ->count();

            if ($lainAktif < (int) config('iksan.hero.min_slide_aktif')) {
                $this->galat = 'Slide ini satu-satunya yang aktif. Aktifkan slide lain sebelum menghapusnya.';

                return;
            }
        }

        app(PenyimpanHero::class)->hapusBerkas($slide);

        CatatanAktivitas::catat('hero.dihapus', ['ulid' => $slide->ulid], auth()->id());

        $slide->delete();

        CacheKatalog::tandaiBerubah('hero dihapus');

        $this->rapikanUrutan();

        $this->pesanSukses = 'Slide dihapus.';
    }

    // ── Penyuntingan ─────────────────────────────────────────────────






    // ── Pembantu ─────────────────────────────────────────────────────

    /**
     * Setelah penghapusan atau penukaran, display_order dirapatkan menjadi
     * 0..n-1 supaya tidak ada celah yang membuat urutan tidak terduga.
     */
    private function rapikanUrutan(): void
    {
        $ids = HeroSlide::query()->orderBy('display_order')->orderBy('id')->pluck('id')->all();

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $posisi => $id) {
                HeroSlide::query()->whereKey($id)->update(['display_order' => $posisi]);
            }
        });
    }


    private function bersihkanPesan(): void
    {
        $this->pesanSukses = null;
        $this->galat = null;
    }

    public function render(): View
    {
        $slides = HeroSlide::query()->orderBy('display_order')->orderBy('id')->get();

        return view('livewire.admin.hero', [
            'judul' => 'Hero Beranda',
            'slides' => $slides,
            'maksSlide' => (int) config('iksan.hero.max_slides'),
        ])->layout('layouts.admin', ['judul' => 'Hero Beranda', 'bagian' => 'Situs']);
    }

    /** URL gambar untuk pratinjau. Jalur kosong berarti belum ada berkas. */
    public function urlMedia(?string $jalur): ?string
    {
        return filled($jalur) ? \App\Models\ProductMedia::urlMedia($jalur) : null;
    }
}
