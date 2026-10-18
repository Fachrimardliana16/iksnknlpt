<?php

namespace App\Livewire\Admin;

use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Support\NotifikasiAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Lonceng notifikasi pada kepala panel.
 *
 * Isinya dihitung saat dirender, bukan disimpan di tabel tersendiri. Notifikasi
 * yang disimpan akan basi — misalnya "stok habis" yang tetap tampil setelah
 * stok diisi ulang — dan menyimpan salinan data yang sudah ada berarti dua
 * sumber kebenaran yang bisa berbeda.
 *
 * Komponen ini menyegarkan diri saat menerima peristiwa 'notifikasi-diperbarui',
 * yang dikirim komponen lain setelah tindakan yang mengubah angkanya.
 */
class LoncengNotifikasi extends Component
{
    public bool $buka = false;

    public function alihkan(): void
    {
        $this->buka = ! $this->buka;
    }

    #[On('notifikasi-diperbarui')]
    public function segarkan(): void
    {
        // Render ulang sudah cukup; isinya dihitung dari kueri.
    }

    public function render(): View
    {
        $semua = NotifikasiAdmin::semua();

        return view('livewire.admin.lonceng-notifikasi', [
            'butir' => $semua,
            'mendesak' => $semua->where('nada', 'danger')->count(),
        ]);
    }
}
