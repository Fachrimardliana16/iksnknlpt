<?php

namespace App\Livewire\Admin;

use App\Models\Faq;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD FAQ (pertanyaan yang sering diajukan).
 *
 * Satu halaman: tabel untuk melihat dan mengubah urutan, panel samping untuk
 * menambah atau menyunting. Bentuknya sengaja meniru halaman Kategori, karena
 * pekerjaannya sama: daftar pendek yang urutannya berarti.
 *
 * Urutan diatur lewat tombol naik/turun, bukan angka yang diketik manual.
 * Menggeser satu butir hanya mengubah dua baris, dan tidak pernah
 * meninggalkan nomor kembar atau celah.
 */
#[Layout('layouts.admin')]
class FaqPanel extends Component
{
    use WithPagination;

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    public bool $formTerbuka = false;

    public ?int $suntingId = null;

    public string $kelompok = 'umum';

    public string $pertanyaan_id = '';

    public string $pertanyaan_en = '';

    public string $jawaban_id = '';

    public string $jawaban_en = '';

    public bool $aktif = true;

    public bool $diBeranda = true;

    public ?string $pesan = null;

    public function updatedCari(): void
    {
        $this->resetPage();
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
        $faq = Faq::query()->findOrFail($id);

        $this->suntingId = $faq->id;
        $this->kelompok = (string) $faq->group_key;
        $this->pertanyaan_id = (string) $faq->question_id;
        $this->pertanyaan_en = (string) $faq->question_en;
        $this->jawaban_id = (string) $faq->answer_id;
        $this->jawaban_en = (string) $faq->answer_en;
        $this->aktif = (bool) $faq->is_active;
        $this->diBeranda = (bool) $faq->tampil_di_beranda;
        $this->formTerbuka = true;

        $this->pesan = null;
        $this->resetErrorBag();
    }

    public function simpan(): void
    {
        $bersih = $this->validate([
            'kelompok' => ['required', 'string', 'in:'.implode(',', array_keys(Faq::KELOMPOK))],
            'pertanyaan_id' => ['required', 'string', 'min:5', 'max:300'],
            'pertanyaan_en' => ['nullable', 'string', 'max:300'],
            'jawaban_id' => ['required', 'string', 'min:10', 'max:5000'],
            'jawaban_en' => ['nullable', 'string', 'max:5000'],
            'aktif' => ['boolean'],
            'diBeranda' => ['boolean'],
        ], [
            'pertanyaan_id.required' => 'Pertanyaan dalam bahasa Indonesia wajib diisi.',
            'pertanyaan_id.min' => 'Pertanyaan minimal lima huruf.',
            'jawaban_id.required' => 'Jawaban dalam bahasa Indonesia wajib diisi.',
            'jawaban_id.min' => 'Jawaban minimal sepuluh huruf — jawaban satu kata tidak menolong siapa pun.',
            'kelompok.in' => 'Kelompok tidak dikenal.',
        ]);

        DB::transaction(function () use ($bersih): void {
            $atribut = [
                'group_key' => $bersih['kelompok'],
                'question_id' => $bersih['pertanyaan_id'],
                'question_en' => $bersih['pertanyaan_en'] ?: null,
                'answer_id' => $bersih['jawaban_id'],
                'answer_en' => $bersih['jawaban_en'] ?: null,
                'is_active' => $bersih['aktif'],
                'tampil_di_beranda' => $bersih['diBeranda'],
            ];

            if ($this->suntingId !== null) {
                $faq = Faq::query()->findOrFail($this->suntingId);
                $faq->update($atribut);
            } else {
                // Butir baru masuk di urutan terakhir, bukan 0: urutan 0 akan
                // menyelipkannya ke atas daftar yang sudah tertata.
                $atribut['display_order'] = (int) (Faq::query()->max('display_order') ?? 0) + 1;
                $faq = Faq::query()->create($atribut);
            }

            CatatanAktivitas::catat(
                $this->suntingId === null ? 'faq.dibuat' : 'faq.diubah',
                ['pertanyaan' => $faq->question_id, 'kelompok' => $faq->group_key],
                subjek: $faq,
            );
        });

        $this->pesan = $this->suntingId === null ? 'FAQ baru disimpan.' : 'Perubahan disimpan.';
        $this->tutupForm();
    }

    public function ubahAktif(int $id): void
    {
        $faq = Faq::query()->findOrFail($id);
        $faq->update(['is_active' => ! $faq->is_active]);

        $this->pesan = $faq->is_active ? 'FAQ diaktifkan.' : 'FAQ dinonaktifkan — tidak tampil di situs.';
    }

    public function ubahBeranda(int $id): void
    {
        $faq = Faq::query()->findOrFail($id);
        $faq->update(['tampil_di_beranda' => ! $faq->tampil_di_beranda]);

        $this->pesan = $faq->tampil_di_beranda
            ? 'FAQ tampil di beranda.'
            : 'FAQ hanya tampil di halaman FAQ.';
    }

    public function geser(int $id, string $arah): void
    {
        abort_unless(in_array($arah, ['naik', 'turun'], true), 400);

        DB::transaction(function () use ($id, $arah): void {
            $faq = Faq::query()->findOrFail($id);

            $tetangga = Faq::query()
                ->when($arah === 'naik',
                    fn ($q) => $q->where('display_order', '<', $faq->display_order)->orderByDesc('display_order'),
                    fn ($q) => $q->where('display_order', '>', $faq->display_order)->orderBy('display_order'),
                )
                ->orderBy('id')
                ->first();

            if ($tetangga === null) {
                return;
            }

            // Tukar; bila urutannya kebetulan sama (data lama), pakai nomor
            // berurutan supaya tetap berpindah dan tidak saling menimpa.
            $a = $faq->display_order;
            $b = $tetangga->display_order;

            if ($a === $b) {
                $faq->update(['display_order' => $a + ($arah === 'naik' ? -1 : 1)]);
                $tetangga->update(['display_order' => $a]);

                return;
            }

            $faq->update(['display_order' => $b]);
            $tetangga->update(['display_order' => $a]);
        });

        $this->pesan = 'Urutan diperbarui.';
    }

    public function hapus(int $id): void
    {
        $faq = Faq::query()->findOrFail($id);

        CatatanAktivitas::catat('faq.dihapus', ['pertanyaan' => $faq->question_id], subjek: $faq);

        $faq->delete();

        if ($this->suntingId === $id) {
            $this->tutupForm();
        }

        $this->pesan = 'FAQ dihapus.';
    }

    private function bersihkan(): void
    {
        $this->suntingId = null;
        $this->kelompok = 'umum';
        $this->pertanyaan_id = '';
        $this->pertanyaan_en = '';
        $this->jawaban_id = '';
        $this->jawaban_en = '';
        $this->aktif = true;
        $this->diBeranda = true;

        $this->resetErrorBag();
    }

    public function render(): View
    {
        $daftar = Faq::query()
            ->when($this->cari !== '', function ($q): void {
                $c = '%'.$this->cari.'%';
                $q->where(fn ($w) => $w->where('question_id', 'like', $c)
                    ->orWhere('question_en', 'like', $c)
                    ->orWhere('answer_id', 'like', $c));
            })
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(30);

        return view('livewire.admin.faq-panel', [
            'daftar' => $daftar,
            'kelompokTersedia' => Faq::KELOMPOK,
            'jumlahAktif' => Faq::query()->where('is_active', true)->count(),
            'jumlahBeranda' => Faq::query()->where('is_active', true)->where('tampil_di_beranda', true)->count(),
        ])->title('FAQ · Panel');
    }
}
