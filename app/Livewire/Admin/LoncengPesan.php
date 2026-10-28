<?php

namespace App\Livewire\Admin;

use App\Models\ContactMessage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

/**
 * Lonceng pesan pada kepala panel.
 *
 * Dipisah dari lonceng notifikasi karena keduanya menjawab pertanyaan yang
 * berbeda: lonceng notifikasi menjawab "apa yang perlu perhatian pada
 * pesanan, stok, dan keranjang", sedangkan lonceng ini menjawab "ada yang
 * menulis dari halaman kontak". Menumpuk keduanya dalam satu daftar membuat
 * pesan dari pembeli — yang biasanya perlu dibalas dengan cepat — bercampur
 * dengan angka stok.
 *
 * Daftarnya dibatasi: ini pintu masuk menuju halaman Pesan, bukan pengganti
 * halaman itu. Yang butuh daftar lengkap, saringan, dan riwayat percakapan
 * tetap membuka halamannya lewat "Lihat semua".
 *
 * Pesan yang sudah dibaca tetap tampil, tetapi redup. Menyembunyikannya
 * membuat admin mengira pesannya hilang.
 */
class LoncengPesan extends KomponenAdmin
{
    public bool $buka = false;

    public function alihkan(): void
    {
        $this->buka = ! $this->buka;
    }

    /** Jumlah pesan yang belum dibaca. Inilah angka pada lencana. */
    public function jumlahBelum(): int
    {
        return ContactMessage::query()->belumDibaca()->count();
    }

    /**
     * Beberapa pesan terbaru.
     *
     * Subject terenkripsi, jadi kolom itu tidak dapat dipakai untuk mengurutkan
     * — urutannya memakai id (baris terbaru lebih dulu), sama seperti halaman
     * Pesan.
     *
     * @return \Illuminate\Support\Collection<int, ContactMessage>
     */
    public function terbaru(): \Illuminate\Support\Collection
    {
        return ContactMessage::query()
            ->orderByDesc('id')
            ->limit(6)
            ->get();
    }

    /**
     * Buka satu pesan langsung dari lonceng, sekaligus menandainya dibaca.
     *
     * Tanda "sudah dibaca" berhenti berlaku begitu ada pesan baru: lencana
     * hanya menghitung yang BELUM dibaca, jadi pesan lama yang sudah dibaca
     * tidak pernah mengaburkan yang baru masuk.
     */
    public function bukaPesan(int $id): void
    {
        $pesan = ContactMessage::query()->find($id);

        if ($pesan === null) {
            return;
        }

        if (! $pesan->is_read) {
            $pesan->update(['is_read' => true]);

            \App\Support\CatatanAktivitas::catat('pesan.dibaca', ['id' => $pesan->id], auth()->id(), $pesan);
        }

        $this->buka = false;

        $this->redirect(route('admin.messages.index', ['pilih' => $pesan->id]), navigate: true);
    }

    /** Pesan baru masuk dari halaman kontak: lonceng ikut terhidrasi ulang. */
    #[On('pesan-dibuat')]
    public function pesanBaru(): void
    {
        // Render ulang sudah cukup; jumlahnya dihitung dari kueri.
    }

    public function render(): View
    {
        return view('livewire.admin.lonceng-pesan', [
            'belum' => $this->jumlahBelum(),
            'daftar' => $this->terbaru(),
        ]);
    }
}
