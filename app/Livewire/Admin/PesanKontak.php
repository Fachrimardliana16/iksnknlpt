<?php

namespace App\Livewire\Admin;

use App\Models\ContactMessage;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pesan dari halaman kontak.
 *
 * Isi pesan terenkripsi di tingkat aplikasi (kolom `message` ber-cast
 * encrypted), sehingga pencarian tidak dapat dilakukan di SQL. Yang dapat
 * dicari hanya subjek dan sidik jari email. Untuk mencari di dalam isi pesan,
 * penapisannya dilakukan setelah data diambil — dan itu dibatasi pada pesan
 * yang sudah tersaring umur, supaya tidak memuat seluruh tabel ke memori.
 *
 * Pesan yang dibalas tidak dihapus: pembeli yang mengirim keluhan sering
 * menulis lagi, dan riwayat percakapan sebelumnya membantu menjawab tanpa
 * bertanya ulang.
 */
#[Layout('layouts.admin')]
class PesanKontak extends Component
{
    use WithPagination;

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    #[Url(as: 'baca', history: true)]
    public string $baca = 'semua';

    #[Url(as: 'pilih', history: true)]
    public ?int $dipilih = null;

    public string $urut = 'terbaru';

    public ?string $pesan = null;

    /** @var array<int, int> */
    public array $terpilih = [];

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function aturBaca(string $nilai): void
    {
        $this->baca = in_array($nilai, ['semua', 'belum', 'sudah'], true) ? $nilai : 'semua';
        $this->resetPage();
    }

    /** Membuka pesan sekaligus menandainya sudah dibaca. */
    public function buka(int $id): void
    {
        $pesan = ContactMessage::query()->findOrFail($id);

        $this->dipilih = $pesan->id;

        if (! $pesan->is_read) {
            $pesan->update(['is_read' => true]);

            CatatanAktivitas::catat('pesan.dibaca', ['id' => $pesan->id], auth()->id(), $pesan);
        }
    }

    public function tutup(): void
    {
        $this->dipilih = null;
    }

    public function tandaiDibaca(int $id): void
    {
        $pesan = ContactMessage::query()->findOrFail($id);
        $pesan->update(['is_read' => ! $pesan->is_read]);

        CatatanAktivitas::catat(
            $pesan->is_read ? 'pesan.dibaca' : 'pesan.belum-dibaca',
            ['id' => $pesan->id],
            auth()->id(),
            $pesan,
        );

        $this->pesan = $pesan->is_read ? 'Ditandai sudah dibaca.' : 'Ditandai belum dibaca.';
    }

    public function tandaiBalas(int $id): void
    {
        $pesan = ContactMessage::query()->findOrFail($id);

        $pesan->update([
            'replied_at' => $pesan->replied_at === null ? now() : null,
            'replied_by' => $pesan->replied_at === null ? auth()->id() : null,
            // Membalas berarti membacanya juga.
            'is_read' => true,
        ]);

        CatatanAktivitas::catat('pesan.dibalas', ['id' => $pesan->id], auth()->id(), $pesan);

        $this->pesan = $pesan->replied_at === null
            ? 'Tanda sudah dibalas dilepas.'
            : 'Ditandai sudah dibalas.';
    }

    public function tandaiDibacaMassal(): void
    {
        if ($this->terpilih === []) {
            return;
        }

        $jumlah = ContactMessage::query()
            ->whereIn('id', $this->terpilih)
            ->update(['is_read' => true]);

        CatatanAktivitas::catat('pesan.massal.dibaca', ['jumlah' => $jumlah]);

        $this->pesan = $jumlah.' pesan ditandai sudah dibaca.';
        $this->terpilih = [];
    }

    public function hapus(int $id): void
    {
        $pesan = ContactMessage::query()->findOrFail($id);

        CatatanAktivitas::catat('pesan.dihapus', [
            // Subjek disimpan sebagai jejak; isinya tidak, karena berisi PII.
            'subject' => $pesan->subject,
        ], auth()->id(), $pesan);

        $pesan->delete();

        if ($this->dipilih === $id) {
            $this->dipilih = null;
        }

        $this->pesan = 'Pesan dihapus.';
    }

    public function hapusMassal(): void
    {
        if ($this->terpilih === []) {
            return;
        }

        $jumlah = ContactMessage::query()->whereIn('id', $this->terpilih)->delete();

        CatatanAktivitas::catat('pesan.massal.dihapus', ['jumlah' => $jumlah]);

        $this->pesan = $jumlah.' pesan dihapus.';
        $this->terpilih = [];
        $this->dipilih = null;
    }

    /** Alamat surel untuk membalas, dipakai tombol "balas lewat surel". */
    public function surelBalasan(int $id): ?string
    {
        $pesan = ContactMessage::query()->find($id);

        return $pesan?->email;
    }

    public function render(): View
    {
        $kueri = ContactMessage::query()
            ->when($this->cari !== '', fn ($q) => $q->cari($this->cari))
            ->when($this->baca === 'belum', fn ($q) => $q->where('is_read', false))
            ->when($this->baca === 'sudah', fn ($q) => $q->where('is_read', true))
            ->when($this->urut === 'terbaru', fn ($q) => $q->orderByDesc('id'))
            ->when($this->urut === 'terlama', fn ($q) => $q->orderBy('id'))
            ->when($this->urut === 'belum', fn ($q) => $q->orderBy('is_read')->orderByDesc('id'));

        $daftar = $kueri->paginate(10);

        return view('livewire.admin.pesan-kontak', [
            'daftar' => $daftar,
            'rinci' => $this->dipilih === null
                ? null
                : ContactMessage::query()->with('pembalas')->find($this->dipilih),
            'jumlah' => [
                'semua' => ContactMessage::query()->count(),
                'belum' => ContactMessage::query()->belumDibaca()->count(),
            ],
        ])->layout('layouts.admin', ['judul' => 'Pesan Kontak', 'bagian' => 'Penjualan']);
    }
}
