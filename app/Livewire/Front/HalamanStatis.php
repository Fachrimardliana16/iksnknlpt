<?php

namespace App\Livewire\Front;

use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HalamanStatis extends Component
{
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();

        // Dua halaman hukum minimum untuk MVP. Isinya ditarik dari pengaturan
        // supaya admin dapat mengganti tanpa deploy.
        $halaman = [
            'kebijakan-privasi' => [
                'judul' => __('site.footer.privacy'),
                'isi' => 'Data yang kami simpan: nama, email, nomor WhatsApp, dan alamat pengiriman, '
                    .'hanya untuk memproses pesanan. Seluruh data pribadi disimpan dalam bentuk terenkripsi. '
                    .'Alamat IP dicatat sebagai hash, bukan alamat asli. Kami tidak menjual data pelanggan '
                    .'kepada pihak ketiga. Permintaan penghapusan data dapat dikirim ke '.$toko->teks('contact_email'),
            ],
            'syarat-ketentuan' => [
                'judul' => __('site.footer.terms'),
                'isi' => 'Pesanan dianggap sah setelah pembayaran diterima. Pesanan yang belum dibayar dalam '
                    .'24 jam dibatalkan otomatis dan stok dikembalikan. Garansi berlaku untuk cacat produksi, '
                    .'bukan untuk kerusakan akibat pemasangan yang salah. Produk seri racing hanya untuk '
                    .'pemakaian lintasan.',
            ],
        ];

        abort_unless(isset($halaman[$this->slug]), 404);

        return view('livewire.front.halaman-statis', [
            'judul' => $halaman[$this->slug]['judul'],
            'isi' => $halaman[$this->slug]['isi'],
        ])->title($halaman[$this->slug]['judul'].' · '.$toko->teks('site_name'));
    }
}
