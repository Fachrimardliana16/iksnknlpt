<?php

namespace App\Livewire\Front;

use App\Models\Category;
use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Order;
use App\Models\Product;
use App\Support\CacheKatalog;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.front')]
class Beranda extends Component
{
    public function render(): View
    {
        $toko = Pengaturan::instance();

        // Produk unggulan diambil lewat cache (checklist F11-01).
        //
        // Halaman depan adalah halaman yang paling sering dibuka dan paling
        // jarang berubah, jadi seluruh isinya diingat selama 10 menit. Kunci
        // cache memuat nomor versi katalog, sehingga menyimpan produk dari
        // panel admin langsung membuat kunci lama tidak cocok — tanpa perlu
        // menghapus apa pun.
        //
        // Eager loading wajib ada DI DALAM cache: tanpa ini, kartu produk
        // melakukan kueri per media dan per varian, dan anggaran 15 kueri per
        // halaman terlampaui. Hasil yang tersimpan adalah model beserta
        // relasinya, sehingga halaman berikutnya tidak menyentuh basis data.
        // Ketiganya disimpan dalam SATU entri cache, bukan tiga.
        //
        // Penyimpan cache bawaan adalah basis data, jadi setiap kunci berarti
        // satu kueri pembacaan tersendiri. Tiga kunci = tiga kueri; satu entri
        // berisi ketiganya = satu kueri. Isinya tetap diingat 10 menit.
        $isi = CacheKatalog::ingat('home:isi', [], 600, fn () => [
            'unggulan' => Product::query()
                ->terbit()
                ->unggulan()
                ->with(['media', 'variants', 'category'])
                ->orderByDesc('published_at')
                ->take((int) $toko->angka('featured_count', 8))
                ->get(),

            // Hero memakai slide dari basis data. Bila tidak ada yang aktif,
            // home memakai slide bawaan berbasis teks, bukan gagal render.
            'slides' => HeroSlide::query()->tayang()->get(),

            'kategori' => Category::query()->aktif()->terurut()->take(6)->get(),

            // FAQ yang tampil di beranda (bawah CTA). Ikut cache yang sama
            // supaya beranda tetap satu entri cache = satu kueri; angkanya
            // kecil dan isinya jarang berubah.
            'faq' => Faq::query()->beranda()->get(),
        ]);

        return view('livewire.front.beranda', [
            'slides' => $isi['slides'],
            'unggulan' => $isi['unggulan'],
            'batasKatalog' => $this->batasKatalogPonsel(),
            'kategori' => $isi['kategori'],
            'faq' => $isi['faq'],
            'angka' => $this->angkaNyata(),
        ])
            // Slide hero pertama di-preload di <head>: gambar terbesar di
            // beranda tidak perlu menunggu CSS dan skrip selesai diurai.
            ->layoutData([
                'heroPertama' => $isi['slides']->first()
                    ? \App\Models\ProductMedia::urlMedia($isi['slides']->first()->media_path)
                    : null,

                // Ringkasan satu paragraf yang boleh dikutip apa adanya.
                'ringkasanAi' => $this->ringkasanAi(),

                // FAQ + identitas toko dalam SATU blok JSON-LD.
                //
                // Dulu identitas toko dipasang juga lewat 'kepala', sehingga
                // beranda memuat dua blok yang keduanya menyatakan Store —
                // data terstruktur ganda membingungkan mesin pencari dan
                // membuang kesempatan memakai blok kedua untuk FAQ.
                'faq' => $this->faq(),
            ])
            ->title($toko->teks('site_name').' · '.$toko->teks('site_tagline'));
    }

    /**
     * Jumlah kartu unggulan yang dirender sebelum "lihat semua" di ponsel.
     *
     * Delapan kartu dalam dua kolom berarti empat baris dan hampir dua layar
     * penuh hanya untuk katalog — halaman depan menjadi 6 layar panjangnya.
     * Tampilan penuh tetap tersedia lewat tombol, jadi memangkasnya di sini
     * tidak menghilangkan apa pun selain menggulir.
     */
    private function batasKatalogPonsel(): int
    {
        return max(2, (int) config('iksan.home.featured_mobile', 4));
    }

    /**
     * Angka pada section About. Diambil dari basis data, bukan ditulis di
     * template. Bila datanya nol, bagian itu tidak ditampilkan sama sekali
     * daripada menampilkan nol yang menyesatkan.
     *
     * @return array<string, int>
     */
    private function angkaNyata(): array
    {
        return [
            'produk' => Product::query()->terbit()->count(),
            'pesanan' => Order::query()->where('status', 'completed')->count(),
            'kategori' => Category::query()->aktif()->count(),
        ];
    }
    /**
     * Ringkasan untuk mesin jawaban (GEO).
     *
     * Disusun dari fakta yang benar-benar ada di pengaturan: nama, kota, jam
     * buka, dan cara pemesanan. Mesin jawaban mengutip kalimat lengkap; tanpa
     * ringkasan eksplisit mereka menyusun sendiri dari menu, dan hasilnya
     * sering salah sebut layanan atau jam.
     */
    private function ringkasanAi(): string
    {
        $p = app(\App\Support\Pengaturan::class);
        $nama = $p->teks('site_name', 'Iksan Knalpot');
        $deskripsi = $p->teks('site_description', '');
        $jam = $p->teks('operational_hours', '');
        $alamat = $p->teks('address_full', '');

        $bagian = [];

        $bagian[] = $nama.($deskripsi !== '' ? ' adalah '.lcfirst($deskripsi).'.' : '.');

        if ($alamat !== '') {
            $bagian[] = 'Berlokasi di '.$alamat.'.';
        }

        if ($jam !== '') {
            $bagian[] = 'Buka '.$jam.'.';
        }

        $bagian[] = 'Melayani pembelian knalpot custom dan racing dengan pengiriman ke seluruh Indonesia, '
            .'dapat dipesan langsung lewat situs atau WhatsApp tanpa membuat akun.';

        return implode(' ', $bagian);
    }

    /**
     * FAQ dari pertanyaan yang benar-benar ditanyakan pembeli.
     *
     * Dijawab dari pengaturan, bukan dikarang: jawaban yang tidak cocok dengan
     * keadaan toko akan dikutip mesin jawaban sebagai fakta, dan itu lebih
     * merugikan daripada tidak punya FAQ.
     *
     * @return array<string, string>
     */
    private function faq(): array
    {
        $p = app(\App\Support\Pengaturan::class);

        $daftar = [];

        $daftar['Apakah bisa pesan tanpa membuat akun?'] =
            'Bisa. Keranjang di situs ini berbasis sesi, jadi pembeli dapat memesan langsung tanpa mendaftar akun.';

        $kurir = $p->teks('shipping_couriers', '');

        if ($kurir !== '') {
            $bersih = trim(str_replace(['[', ']', '"'], '', $kurir));
            $daftar['Kurir apa yang tersedia?'] = 'Pengiriman memakai '.$bersih.' ke seluruh Indonesia.';
        }

        $transfer = $p->teks('payment_mode', '');

        if ($transfer === 'manual') {
            $daftar['Bagaimana cara membayarnya?'] =
                'Pembayaran dilakukan dengan transfer bank, lalu bukti transfer diunggah lewat halaman pesanan.';
        }

        $jam = $p->teks('operational_hours', '');

        if ($jam !== '') {
            $daftar['Kapan toko buka?'] = 'Toko buka '.$jam.'.';
        }

        $wa = $p->teks('whatsapp_number', '');

        if ($wa !== '') {
            $daftar['Bagaimana cara bertanya sebelum membeli?'] =
                'Hubungi WhatsApp di nomor '.$wa.' untuk konsultasi ukuran dan jenis knalpot.';
        }

        return $daftar;
    }

}
