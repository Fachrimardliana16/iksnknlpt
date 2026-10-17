<?php

namespace App\Services\Hero;

use App\Services\Gambar\PengecilGambar;
use App\Services\Video\PembacaDurasiVideo;
use App\Support\CatatanAktivitas;
use App\Models\HeroSlide;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Penyimpan berkas slide hero (checklist F4-09, F4-11).
 *
 * Gambar dipecah menjadi empat turunan seperti media produk. Video disimpan
 * apa adanya karena pengodean ulang video di luar jangkauan GD, dengan batas
 * ukuran dan durasi yang diperiksa di server.
 */
class PenyimpanHero
{
    public function __construct(
        private readonly PengecilGambar $pengecil,
        private readonly PembacaDurasiVideo $pembacaDurasi,
    ) {}

    /**
     * @return array{path: string, mobile: string|null, poster: string|null}
     */
    public function simpanGambar(HeroSlide $slide, UploadedFile $berkas, UploadedFile|null $ponsel = null): array
    {
        $konfig = config('iksan.hero');

        $namaDasar = 'hero-'.$slide->ulid;

        $hasil = $this->pengecil->proses(
            $berkas->getRealPath() ?: $berkas->getPathname(),
            'hero',
            $namaDasar,
            lebarMaks: $konfig['gambar_lebar_maks'],
        );

        $ponselPath = null;

        if ($ponsel !== null) {
            $hasilPonsel = $this->pengecil->proses(
                $ponsel->getRealPath() ?: $ponsel->getPathname(),
                'hero',
                $namaDasar.'-ponsel',
                lebarMaks: 1280,
            );

            $ponselPath = $hasilPonsel['paths']['full'];
        }

        return [
            'path' => $hasil['paths']['full'],
            'mobile' => $ponselPath,
            // Poster video memakai turunan "og" — 1200x630, rasio yang sama
            // dengan pratinjau tautan sosial.
            'poster' => $hasil['paths']['og'],
        ];
    }

    /**
     * Video hero: mp4 saja, maksimum 8 MB dan 15 detik.
     *
     * Durasi diperiksa dari metadata di dalam berkas, bukan dari apa yang
     * dilaporkan peramban. Berkas 15 detik yang diganti namanya menjadi .mp4
     * tetap tertolak karena mime-nya tidak cocok.
     */
    public function simpanVideo(HeroSlide $slide, UploadedFile $berkas): string
    {
        $konfig = config('iksan.hero');

        $ukuranKb = (int) ceil($berkas->getSize() / 1024);

        if ($ukuranKb > $konfig['video_max_kb']) {
            throw ValidationException::withMessages([
                'video' => sprintf('Ukuran video %d KB melebihi batas %d KB.', $ukuranKb, $konfig['video_max_kb']),
            ]);
        }

        $jalur = $berkas->getRealPath() ?: $berkas->getPathname();
        $mime = (string) @mime_content_type($jalur);

        if (! in_array($mime, config('iksan.uploads.allowed_video_mimes'), true)) {
            throw ValidationException::withMessages([
                'video' => 'Video harus berkode MP4. Tipe terdeteksi "'.($mime ?: 'tidak dikenal').'".',
            ]);
        }

        $detik = $this->pembacaDurasi->detik($jalur);

        if ($detik === null) {
            throw ValidationException::withMessages([
                'video' => 'Durasi video tidak dapat dibaca. Unggah berkas MP4 yang utuh.',
            ]);
        }

        if ($detik > $konfig['video_max_detik']) {
            throw ValidationException::withMessages([
                'video' => sprintf('Durasi video %.1f detik melebihi batas %d detik.', $detik, $konfig['video_max_detik']),
            ]);
        }

        $relatif = 'hero/hero-'.$slide->ulid.'.mp4';
        Storage::disk('public')->put($relatif, (string) file_get_contents($jalur));

        return $relatif;
    }

    /** Hapus seluruh berkas milik satu slide, termasuk turunannya. */
    public function hapusBerkas(HeroSlide $slide): void
    {
        foreach ([$slide->media_path, $slide->media_path_mobile, $slide->poster_path] as $jalur) {
            if (blank($jalur)) {
                continue;
            }

            try {
                $tanpaEkstensi = (string) preg_replace('/-(thumb|card|full|og)\.webp$/', '', (string) $jalur);
                $tanpaEkstensi = (string) preg_replace('/-ponsel-(thumb|card|full|og)\.webp$/', '', $tanpaEkstensi);

                foreach (['thumb', 'card', 'full', 'og'] as $turunan) {
                    Storage::disk('public')->delete($tanpaEkstensi.'-'.$turunan.'.webp');
                }

                if (str_ends_with((string) $jalur, '.mp4')) {
                    Storage::disk('public')->delete((string) $jalur);
                }
            } catch (Throwable) {
                // Berkas yang sudah tidak ada bukan masalah.
            }
        }

        CatatanAktivitas::catat('hero.berkas.dihapus', [], auth()->id(), $slide);
    }
}
