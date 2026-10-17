<?php

namespace App\Livewire\Admin;

use App\Models\HeroSlide;
use App\Services\Hero\PenyimpanHero;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

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
class HeroBuilder extends Component
{
    use WithFileUploads;

    /** Slide yang sedang dibuka di panel penyuntingan. */
    public ?int $sunting = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** Berkas gambar desktop yang menunggu disimpan. */
    public mixed $berkasGambar = null;

    /** Berkas gambar khusus ponsel. */
    public mixed $berkasPonsel = null;

    /** Berkas video. */
    public mixed $berkasVideo = null;

    public string $pratinjau = 'ponsel';

    public ?string $pesanSukses = null;

    public ?string $galat = null;

    public function mount(): void
    {
        $this->kosongkanForm();
    }

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

        $this->pesanSukses = 'Slide kosong ditambahkan. Unggah gambar, lalu aktifkan.';
        $this->sunting($slide->id);
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

        $this->rapikanUrutan();

        if ($this->sunting === $id) {
            $this->sunting = null;
            $this->kosongkanForm();
        }

        $this->pesanSukses = 'Slide dihapus.';
    }

    // ── Penyuntingan ─────────────────────────────────────────────────

    public function sunting(int $id): void
    {
        $this->bersihkanPesan();

        $slide = HeroSlide::query()->find($id);

        if ($slide === null) {
            return;
        }

        $this->sunting = $slide->id;
        $this->berkasGambar = null;
        $this->berkasPonsel = null;
        $this->berkasVideo = null;

        $this->form = [
            'title_id' => $slide->title_id ?? '',
            'title_en' => $slide->title_en ?? '',
            'subtitle_id' => $slide->subtitle_id ?? '',
            'subtitle_en' => $slide->subtitle_en ?? '',
            'media_type' => $slide->media_type,
            'primary_button_label_id' => $slide->primary_button_label_id ?? '',
            'primary_button_label_en' => $slide->primary_button_label_en ?? '',
            'primary_button_url' => $slide->primary_button_url ?? '',
            'secondary_button_label_id' => $slide->secondary_button_label_id ?? '',
            'secondary_button_label_en' => $slide->secondary_button_label_en ?? '',
            'secondary_button_url' => $slide->secondary_button_url ?? '',
            'focal_point_x' => (float) $slide->focal_point_x,
            'focal_point_y' => (float) $slide->focal_point_y,
            'starts_at' => $slide->starts_at?->format('Y-m-d\TH:i') ?? '',
            'ends_at' => $slide->ends_at?->format('Y-m-d\TH:i') ?? '',
        ];
    }

    public function batalSunting(): void
    {
        $this->sunting = null;
        $this->berkasGambar = null;
        $this->berkasPonsel = null;
        $this->berkasVideo = null;
        $this->kosongkanForm();
        $this->bersihkanPesan();
    }

    public function simpan(PenyimpanHero $penyimpan): void
    {
        $this->bersihkanPesan();

        $slide = $this->sunting === null ? null : HeroSlide::query()->find($this->sunting);

        if ($slide === null) {
            $this->galat = 'Slide tidak ditemukan. Muat ulang halaman.';

            return;
        }

        try {
            $this->validate([
                'form.title_id' => ['required', 'string', 'max:120'],
                'form.title_en' => ['required', 'string', 'max:120'],
                'form.subtitle_id' => ['nullable', 'string', 'max:300'],
                'form.subtitle_en' => ['nullable', 'string', 'max:300'],
                'form.media_type' => ['required', 'in:image,video'],
                'form.primary_button_label_id' => ['nullable', 'string', 'max:40'],
                'form.primary_button_label_en' => ['nullable', 'string', 'max:40'],
                'form.primary_button_url' => ['nullable', 'url', 'max:255'],
                'form.secondary_button_label_id' => ['nullable', 'string', 'max:40'],
                'form.secondary_button_label_en' => ['nullable', 'string', 'max:40'],
                'form.secondary_button_url' => ['nullable', 'url', 'max:255'],
                'form.focal_point_x' => ['required', 'numeric', 'between:0,1'],
                'form.focal_point_y' => ['required', 'numeric', 'between:0,1'],
                'form.starts_at' => ['nullable', 'date'],
                'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
                'berkasGambar' => ['nullable', 'image', 'mimes:jpeg,png,webp,avif', 'max:5120'],
                'berkasPonsel' => ['nullable', 'image', 'mimes:jpeg,png,webp,avif', 'max:5120'],
                'berkasVideo' => ['nullable', 'file', 'max:'.(int) config('iksan.hero.video_max_kb')],
            ], [
                'form.title_id.required' => 'Judul bahasa Indonesia wajib diisi.',
                'form.title_en.required' => 'Judul bahasa Inggris wajib diisi.',
                'form.ends_at.after_or_equal' => 'Tanggal berakhir tidak boleh mendahului tanggal mulai.',
                'berkasVideo.max' => 'Ukuran video melebihi batas :max KB.',
            ]);

            if ($this->form['media_type'] === 'video') {
                if ($this->berkasVideo !== null) {
                    $slide->media_path = $penyimpan->simpanVideo($slide, $this->berkasVideo);
                }

                if (blank($slide->media_path)) {
                    throw ValidationException::withMessages([
                        'berkasVideo' => 'Slide bertipe video harus punya berkas video.',
                    ]);
                }
            } elseif ($this->berkasGambar !== null) {
                $hasil = $penyimpan->simpanGambar($slide, $this->berkasGambar, $this->berkasPonsel);

                $slide->media_path = $hasil['path'];
                $slide->media_path_mobile = $hasil['mobile'];
                $slide->poster_path = $hasil['poster'];
            }

            $slide->fill([
                'title_id' => $this->form['title_id'],
                'title_en' => $this->form['title_en'],
                'subtitle_id' => $this->form['subtitle_id'] ?: null,
                'subtitle_en' => $this->form['subtitle_en'] ?: null,
                'media_type' => $this->form['media_type'],
                'primary_button_label_id' => $this->form['primary_button_label_id'] ?: null,
                'primary_button_label_en' => $this->form['primary_button_label_en'] ?: null,
                'primary_button_url' => $this->form['primary_button_url'] ?: null,
                'secondary_button_label_id' => $this->form['secondary_button_label_id'] ?: null,
                'secondary_button_label_en' => $this->form['secondary_button_label_en'] ?: null,
                'secondary_button_url' => $this->form['secondary_button_url'] ?: null,
                'focal_point_x' => (float) $this->form['focal_point_x'],
                'focal_point_y' => (float) $this->form['focal_point_y'],
                'starts_at' => $this->form['starts_at'] ?: null,
                'ends_at' => $this->form['ends_at'] ?: null,
            ]);

            $slide->save();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->galat = 'Slide gagal disimpan: '.$e->getMessage();

            return;
        }

        CatatanAktivitas::catat('hero.disimpan', ['ulid' => $slide->ulid], auth()->id(), $slide);

        $this->berkasGambar = null;
        $this->berkasPonsel = null;
        $this->berkasVideo = null;

        $this->pesanSukses = 'Slide "'.$slide->title_id.'" tersimpan.';
        $this->sunting($slide->id);
    }

    /** Pindahkan titik fokus tanpa memuat ulang halaman. */
    public function ubahFokus(float $x, float $y): void
    {
        if ($this->sunting === null) {
            return;
        }

        $this->form['focal_point_x'] = max(0.0, min(1.0, $x));
        $this->form['focal_point_y'] = max(0.0, min(1.0, $y));

        HeroSlide::query()->whereKey($this->sunting)->update([
            'focal_point_x' => $this->form['focal_point_x'],
            'focal_point_y' => $this->form['focal_point_y'],
        ]);
    }

    public function ubahPratinjau(string $mode): void
    {
        if (in_array($mode, ['ponsel', 'desktop'], true)) {
            $this->pratinjau = $mode;
        }
    }

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

    private function kosongkanForm(): void
    {
        $this->form = [
            'title_id' => '',
            'title_en' => '',
            'subtitle_id' => '',
            'subtitle_en' => '',
            'media_type' => 'image',
            'primary_button_label_id' => '',
            'primary_button_label_en' => '',
            'primary_button_url' => '',
            'secondary_button_label_id' => '',
            'secondary_button_label_en' => '',
            'secondary_button_url' => '',
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
            'starts_at' => '',
            'ends_at' => '',
        ];
    }

    private function bersihkanPesan(): void
    {
        $this->pesanSukses = null;
        $this->galat = null;
    }

    public function render(): View
    {
        $slides = HeroSlide::query()->orderBy('display_order')->orderBy('id')->get();

        $sedang = $this->sunting === null ? null : $slides->firstWhere('id', $this->sunting);

        return view('livewire.admin.hero', [
            'judul' => 'Hero Beranda',
            'slides' => $slides,
            'sedang' => $sedang,
            'maksSlide' => (int) config('iksan.hero.max_slides'),
            'videoMaksKb' => (int) config('iksan.hero.video_max_kb'),
            'videoMaksDetik' => (int) config('iksan.hero.video_max_detik'),
        ]);
    }

    /** URL gambar untuk pratinjau. Jalur kosong berarti belum ada berkas. */
    public function urlMedia(?string $jalur): ?string
    {
        return filled($jalur) ? \App\Models\ProductMedia::urlMedia($jalur) : null;
    }
}
