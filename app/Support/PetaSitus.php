<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Support\Pengaturan;

/**
 * Membangun peta situs (sitemap) dan data terstruktur tingkat situs.
 *
 * Kenapa dibuat sendiri, bukan memakai paket: kebutuhan proyek ini sempit —
 * satu berkas XML untuk produk, kategori, dan halaman statis, plus daftar URL
 * yang boleh dirayapi. Satu ketergantungan tambahan untuk dua hal sekecil itu
 * hanya menambah permukaan pembaruan yang harus dijaga.
 *
 * Aturan yang dipegang:
 *
 *  - HANYA halaman yang memang ingin diindeks. Keranjang, checkout, lacak,
 *    pesanan, unggah bukti, dan seluruh panel admin TIDAK masuk. Halaman
 *    transaksional yang terindeks akan muncul di hasil pencarian dengan isi
 *    kosong bagi pengunjung, dan itu menurunkan mutu domain.
 *  - `lastmod` memakai waktu perubahan sebenarnya, bukan waktu permintaan.
 *    `lastmod` yang selalu "sekarang" membuat mesin pencari berhenti
 *    mempercayai nilai itu, lalu seluruh peta situs dirayapi ulang terus.
 *  - Produk yang tidak terbit TIDAK dimasukkan, dan produk tanpa stok tetap
 *    dimasukkan — halaman itu masih layak ditemukan, hanya tanpa penawaran.
 */
class PetaSitus
{
    /** Awalan jalur yang tidak boleh dirayapi sama sekali. */
    public const TERLARANG = [
        '/admin',
        '/livewire',
        '/keranjang',
        '/checkout',
        '/lacak',
        '/pesanan',
        '/bukti-bayar',
        '/lang',
    ];

    /**
     * Semua URL yang boleh diindeks, terurut dari yang paling penting.
     *
     * @return array<int, array{lok: string, ubah: string, prioritas: string, frekuensi: string}>
     */
    public static function url(): array
    {
        $hasil = [];

        $terbaruProduk = Product::query()
            ->where('is_published', true)
            ->max('updated_at');

        $hasil[] = [
            'lok' => route('front.home'),
            'ubah' => self::waktu($terbaruProduk),
            'prioritas' => '1.0',
            'frekuensi' => 'daily',
        ];

        $hasil[] = [
            'lok' => route('front.products.index'),
            'ubah' => self::waktu($terbaruProduk),
            'prioritas' => '0.9',
            'frekuensi' => 'daily',
        ];

        // Kategori: halaman arahan yang berguna untuk "knalpot vario" dsb.
        foreach (Category::query()->where('is_active', true)->orderBy('display_order')->get() as $k) {
            $hasil[] = [
                'lok' => route('front.category', $k->slug),
                'ubah' => self::waktu($k->updated_at),
                'prioritas' => '0.8',
                'frekuensi' => 'weekly',
            ];
        }

        foreach (Product::query()
            ->where('is_published', true)
            ->orderByDesc('updated_at')
            ->cursor() as $p) {
            $hasil[] = [
                'lok' => route('front.products.show', $p->slug),
                'ubah' => self::waktu($p->updated_at),
                'prioritas' => '0.7',
                'frekuensi' => 'weekly',
            ];
        }

        foreach ([
            ['front.about', '0.5', 'monthly'],
            ['front.contact', '0.5', 'monthly'],
        ] as [$rute, $prioritas, $frekuensi]) {
            $hasil[] = [
                'lok' => route($rute),
                'ubah' => self::waktu(null),
                'prioritas' => $prioritas,
                'frekuensi' => $frekuensi,
            ];
        }

        // Halaman statis (syarat, privasi, dsb) dibaca dari rute yang terdaftar,
        // bukan dari daftar tetap di sini — menambah halaman tidak boleh
        // menuntut perubahan berkas ini juga.
        foreach (self::halamanStatis() as $slug) {
            $hasil[] = [
                'lok' => route('front.page', $slug),
                'ubah' => self::waktu(null),
                'prioritas' => '0.3',
                'frekuensi' => 'yearly',
            ];
        }

        return $hasil;
    }

    /** @return array<int, string> */
    private static function halamanStatis(): array
    {
        // Kebijakan privasi dan syarat ketentuan adalah dua halaman yang
        // kehadirannya diperiksa mesin pencari untuk situs toko.
        return ['syarat-ketentuan', 'kebijakan-privasi'];
    }

    private static function waktu($nilai): string
    {
        if ($nilai === null) {
            return now()->toAtomString();
        }

        return \Illuminate\Support\Carbon::parse($nilai)->toAtomString();
    }

    /** Isi sitemap.xml. */
    public static function xml(): string
    {
        $baris = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $baris[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach (self::url() as $u) {
            $baris[] = '  <url>';
            $baris[] = '    <loc>'.htmlspecialchars($u['lok'], ENT_XML1).'</loc>';
            $baris[] = '    <lastmod>'.$u['ubah'].'</lastmod>';
            $baris[] = '    <changefreq>'.$u['frekuensi'].'</changefreq>';
            $baris[] = '    <priority>'.$u['prioritas'].'</priority>';
            $baris[] = '  </url>';
        }

        $baris[] = '</urlset>';

        return implode("\n", $baris)."\n";
    }

    /** Isi robots.txt, dibangun dari rute yang sama agar tidak pernah berbeda. */
    public static function robots(): string
    {
        $baris = ['User-agent: *'];

        foreach (self::TERLARANG as $larang) {
            $baris[] = 'Disallow: '.$larang;
        }

        // Parameter pencarian dan urutan tidak menghasilkan halaman baru yang
        // layak diindeks; membiarkannya hanya membuat isi ganda.
        $baris[] = 'Disallow: /*?q=';
        $baris[] = 'Disallow: /*?urut=';
        $baris[] = 'Disallow: /*?halaman=';
        $baris[] = '';
        $baris[] = 'Sitemap: '.url('/sitemap.xml');

        return implode("\n", $baris)."\n";
    }
}
