<?php

namespace App\Livewire\Admin;

use App\Models\HeroSlide;
use App\Services\Hero\PenyimpanHero;
use App\Support\CacheKatalog;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Halaman sunting satu slide hero.
 *
 * Dipisah dari HeroBuilder dengan sengaja. Panel sunting dulu menumpang di
 * halaman daftar, dan itu membuat satu halaman mengerjakan dua pekerjaan yang
 * berbeda tuntutannya: daftar harus cepat dibuka dan sering dilihat, panel
 * sunting penuh isian dan jarang dipakai. Menyatukannya berarti setiap kali
 * admin membuka Hero, ia memuat seluruh form — dua bahasa, titik fokus,
 * pratinjau, unggahan — untuk sesuatu yang mungkin tidak disentuhnya.
 *
 * Halaman sendiri juga memberi alamat yang bisa disalin dan dibuka ulang, dan
 * menyimpan tidak lagi memaksa menggulir kembali ke daftar.
 */
#[Layout('layouts.admin')]
class HeroSunting extends KomponenAdmin
{
    use WithFileUploads;

    public HeroSlide $slide;

    /** @var array<string, mixed> */
    public array $form = [];

    /** Berkas gambar desktop yang menunggu disimpan. */
    public mixed $berkasGambar = null;

    /** Berkas gambar khusus ponsel. Opsional. */
    public mixed $berkasPonsel = null;

    /** Berkas video pengganti. */
    public mixed $berkasVideo = null;

    /** Hapus gambar ponsel supaya slide kembali memakai gambar desktop. */
    public bool $hapusPonsel = false;

    public string $pratinjau = 'desktop';

    public ?string $pesanSukses = null;

    public ?string $galat = null;

    public function mount(string $ulid): void
    {

        $this->slide = HeroSlide::query()->where('ulid', $ulid)->firstOrFail();
        $this->isiForm();
    }

    public function simpan(PenyimpanHero $penyimpan): void
    {
        $this->pesanSukses = null;
        $this->galat = null;

        try {
            $this->validate([
                'form.title_id' => ['required', 'string', 'max:120'],
                'form.title_en' => ['required', 'string', 'max:120'],
                'form.subtitle_id' => ['nullable', 'string', 'max:300'],
                'form.subtitle_en' => ['nullable', 'string', 'max:300'],
                'form.media_type' => ['required', 'in:image,video'],
                'form.primary_button_label_id' => ['nullable', 'string', 'max:40'],
                'form.primary_button_label_en' => ['nullable', 'string', 'max:40'],
                // URL tombol TIDAK wajib. Slide yang hanya menampilkan gambar
                // adalah keadaan yang sah, dan tombol tanpa tujuan lebih baik
                // tidak ada daripada mengarah ke halaman kosong. Yang dijaga
                // hanya bentuknya bila diisi.
                'form.primary_button_url' => ['nullable', 'string', 'max:255'],
                'form.secondary_button_label_id' => ['nullable', 'string', 'max:40'],
                'form.secondary_button_label_en' => ['nullable', 'string', 'max:40'],
                'form.secondary_button_url' => ['nullable', 'string', 'max:255'],
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->galat = 'Ada isian yang perlu diperbaiki.';

            throw $e;
        }

        // URL tombol diperiksa lewat HeroSlide::urlTombolAman(), bukan aturan
        // `url` maupun FILTER_VALIDATE_URL.
        //
        // Dua-duanya menerima `file:///etc/passwd` — terukur, bukan dugaan.
        // Aturan `url` juga menolak jalur relatif seperti "/produk" yang justru
        // paling wajar dipakai di sini. Model menyediakan satu penyaring yang
        // sama dengan yang dipakai saat merender href di beranda, sehingga
        // tidak mungkin lolos validasi tetapi berbahaya saat ditampilkan.
        foreach (['primary_button_url', 'secondary_button_url'] as $kunci) {
            $nilai = trim((string) ($this->form[$kunci] ?? ''));

            if ($nilai === '') {
                continue;
            }

            if (\App\Models\HeroSlide::urlTombolAman($nilai) === null) {
                $this->addError(
                    'form.'.$kunci,
                    'Tulis alamat https://… atau jalur di dalam situs seperti /produk.',
                );
                $this->galat = 'Ada isian yang perlu diperbaiki.';

                return;
            }
        }

        try {
            if ($this->form['media_type'] === 'video') {
                if ($this->berkasVideo !== null) {
                    $this->slide->media_path = $penyimpan->simpanVideo($this->slide, $this->berkasVideo);
                }

                if (blank($this->slide->media_path)) {
                    $this->addError('berkasVideo', 'Slide bertipe video harus punya berkas video.');
                    $this->galat = 'Slide video belum punya berkas.';

                    return;
                }
            } else {
                $this->simpanGambar($penyimpan);
            }

            $this->slide->fill([
                'title_id' => $this->form['title_id'],
                'title_en' => $this->form['title_en'],
                'subtitle_id' => $this->form['subtitle_id'] ?: null,
                'subtitle_en' => $this->form['subtitle_en'] ?: null,
                'media_type' => $this->form['media_type'],
                'primary_button_label_id' => $this->form['primary_button_label_id'] ?: null,
                'primary_button_label_en' => $this->form['primary_button_label_en'] ?: null,
                'primary_button_url' => trim((string) $this->form['primary_button_url']) ?: null,
                'secondary_button_label_id' => $this->form['secondary_button_label_id'] ?: null,
                'secondary_button_label_en' => $this->form['secondary_button_label_en'] ?: null,
                'secondary_button_url' => trim((string) $this->form['secondary_button_url']) ?: null,
                'focal_point_x' => (float) $this->form['focal_point_x'],
                'focal_point_y' => (float) $this->form['focal_point_y'],
                'starts_at' => $this->form['starts_at'] ?: null,
                'ends_at' => $this->form['ends_at'] ?: null,
            ]);

            $this->slide->save();
        } catch (Throwable $e) {
            Log::error('Slide hero gagal disimpan', ['sebab' => $e->getMessage()]);

            $this->galat = 'Slide gagal disimpan. Periksa kembali isian dan berkas gambarnya.';

            return;
        }

        CatatanAktivitas::catat('hero.disimpan', ['ulid' => $this->slide->ulid], auth()->id(), $this->slide);

        // WAJIB: beranda menyimpan daftar slide di cache 'home:isi' selama 600
        // detik. Tanpa panggilan ini, gambar yang baru diunggah tersimpan di
        // disk tetapi hero di halaman depan tetap menampilkan gambar lama
        // sampai cache kedaluwarsa — gejalanya "gambar sudah saya ganti tapi
        // tidak muncul", tanpa satu pun galat di log.
        CacheKatalog::tandaiBerubah('hero disimpan');

        $this->berkasGambar = null;
        $this->berkasPonsel = null;
        $this->berkasVideo = null;
        $this->hapusPonsel = false;

        $this->slide->refresh();
        $this->isiForm();

        $this->pesanSukses = 'Slide "'.$this->slide->title_id.'" tersimpan.';
    }

    /**
     * Menyimpan gambar, dengan gambar ponsel sebagai pelengkap — bukan syarat.
     *
     * Versi lama menimpa media_path_mobile dengan null setiap kali gambar
     * desktop diunggah tanpa gambar ponsel. Akibatnya mengganti gambar desktop
     * menghapus gambar ponsel yang sudah ada, padahal keduanya seharusnya
     * berdiri sendiri: ponsel boleh punya gambar sendiri, dan bila tidak
     * punya, slide memakai gambar desktop apa adanya.
     */
    private function simpanGambar(PenyimpanHero $penyimpan): void
    {
        if ($this->berkasGambar === null && $this->berkasPonsel === null) {
            // Tidak ada berkas baru: pertahankan yang lama, kecuali admin
            // secara eksplisit meminta gambar ponsel dibuang.
            if ($this->hapusPonsel) {
                $this->slide->media_path_mobile = null;
            }

            return;
        }

        if ($this->berkasGambar !== null) {
            $hasil = $penyimpan->simpanGambar($this->slide, $this->berkasGambar, $this->berkasPonsel);

            $this->slide->media_path = $hasil['path'];
            $this->slide->poster_path = $hasil['poster'];

            // Hanya ditimpa bila memang ada berkas ponsel baru; kalau tidak,
            // nilai lama dipertahankan.
            if ($hasil['mobile'] !== null) {
                $this->slide->media_path_mobile = $hasil['mobile'];
            }
        } elseif ($this->berkasPonsel !== null) {
            // Hanya gambar ponsel yang diganti: gambar desktop tidak disentuh.
            $hasil = $penyimpan->simpanGambar($this->slide, $this->berkasPonsel);

            $this->slide->media_path_mobile = $hasil['path'];
        }

        if ($this->hapusPonsel) {
            $this->slide->media_path_mobile = null;
        }
    }

    public function ubahPratinjau(string $mode): void
    {
        if (in_array($mode, ['ponsel', 'desktop'], true)) {
            $this->pratinjau = $mode;
        }
    }

    /** Kembali ke daftar tanpa menyimpan. */
    public function batal(): void
    {
        $this->redirectRoute('admin.hero.index', navigate: true);
    }

    private function isiForm(): void
    {
        $this->form = [
            'title_id' => $this->slide->title_id ?? '',
            'title_en' => $this->slide->title_en ?? '',
            'subtitle_id' => $this->slide->subtitle_id ?? '',
            'subtitle_en' => $this->slide->subtitle_en ?? '',
            'media_type' => $this->slide->media_type,
            'primary_button_label_id' => $this->slide->primary_button_label_id ?? '',
            'primary_button_label_en' => $this->slide->primary_button_label_en ?? '',
            'primary_button_url' => $this->slide->primary_button_url ?? '',
            'secondary_button_label_id' => $this->slide->secondary_button_label_id ?? '',
            'secondary_button_label_en' => $this->slide->secondary_button_label_en ?? '',
            'secondary_button_url' => $this->slide->secondary_button_url ?? '',
            'focal_point_x' => (float) $this->slide->focal_point_x,
            'focal_point_y' => (float) $this->slide->focal_point_y,
            'starts_at' => $this->slide->starts_at?->format('Y-m-d\TH:i') ?? '',
            'ends_at' => $this->slide->ends_at?->format('Y-m-d\TH:i') ?? '',
        ];
    }

    /** URL gambar untuk pratinjau. Jalur kosong berarti belum ada berkas. */
    public function urlMedia(?string $jalur): ?string
    {
        return filled($jalur) ? \App\Models\ProductMedia::urlMedia($jalur) : null;
    }

    public function render(): View
    {
        return view('livewire.admin.hero-sunting', [
            'judul' => 'Sunting Slide',
            'videoMaksKb' => (int) config('iksan.hero.video_max_kb'),
            'videoMaksDetik' => (int) config('iksan.hero.video_max_detik'),
        ])->layout('layouts.admin', ['judul' => 'Sunting Slide', 'bagian' => 'Situs']);
    }
}
