<?php

namespace App\Livewire\Front;

use App\Models\Faq;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman /faq: seluruh pertanyaan yang aktif, termasuk yang tidak ditampilkan
 * di beranda.
 *
 * Dipisahkan dari beranda karena beranda hanya memuat sebagian (yang ditandai
 * "tampil di beranda"), sementara jawaban panjang — misalnya soal klaim garansi
 * atau pengiriman luar negeri — tidak layak memenuhi halaman depan.
 */
#[Layout('layouts.front')]
class FaqHalaman extends Component
{
    public function render(): View
    {
        $toko = Pengaturan::instance();

        return view('livewire.front.faq-halaman', [
            'daftar' => Faq::query()->aktif()->get(),
        ])
            ->title(__('site.faq.title').' · '.$toko->teks('site_name'))
            ->layoutData([
                // Route::has() dulu: halaman FAQ tidak selalu terdaftar (saat tes
            // memuat komponen ini terpisah), dan route() melempar galat yang
            // membuat halaman gagal dirender sama sekali.
            'kanonikal' => \Illuminate\Support\Facades\Route::has('front.faq')
                ? route('front.faq')
                : url()->current(),
                'metaOg' => [
                    'tipe' => 'website',
                    'judul' => __('site.faq.title'),
                    'deskripsi' => __('site.faq.meta'),
                    'gambar' => null,
                ],
            ]);
    }
}
