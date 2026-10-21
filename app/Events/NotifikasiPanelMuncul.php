<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Muncul ketika notifikasi panel bertambah dan admin perlu diberi tahu.
 *
 * Dipakai untuk push notification. Yang dikirim hanyalah angka dan rincian
 * singkat, bukan koleksi notifikasi: peristiwa peringatan, bukan salinan data
 * — isi terbaru selalu dihitung ulang saat dibaca.
 */
class NotifikasiPanelMuncul
{
    use Dispatchable;

    public function __construct(
        public int $jumlah,
        public string $rinci,
        public string $url = '',
    ) {}
}
