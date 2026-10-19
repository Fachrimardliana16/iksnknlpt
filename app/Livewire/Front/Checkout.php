<?php

namespace App\Livewire\Front;

use App\Enums\MetodePembayaran;
use App\Services\Keranjang;
use App\Services\PesananService;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.front')]
class Checkout extends Component
{
    public const KUNCI_BATAS = 'checkout';

    #[Validate('required|string|min:2|max:80')]
    public string $nama = '';

    #[Validate('required|email|max:120')]
    public string $email = '';

    #[Validate('required|string|min:8|max:20')]
    public string $telepon = '';

    #[Validate('required|string|min:5|max:180')]
    public string $alamat1 = '';

    #[Validate('nullable|string|max:180')]
    public string $alamat2 = '';

    #[Validate('required|string|min:2|max:60')]
    public string $kota = '';

    #[Validate('required|string|min:2|max:60')]
    public string $provinsi = '';

    #[Validate('required|string|min:3|max:10')]
    public string $kodePos = '';

    #[Validate('required|string|max:40')]
    public string $kurir = 'JNE';

    #[Validate('nullable|string|max:500')]
    public string $catatan = '';

    #[Validate('required|in:paypal,manual')]
    public string $metode = 'manual';

    /** Jebakan bot. Kolom ini tersembunyi dan wajib kosong. */
    public string $situs = '';

    public int $dimulaiPada = 0;

    public ?string $galat = null;

    public function mount(Keranjang $keranjang): void
    {
        if ($keranjang->jumlahItem() === 0) {
            $this->redirect(route('front.cart'), navigate: true);

            return;
        }

        $this->dimulaiPada = now()->timestamp;

        // Catatan yang sudah ditulis di halaman keranjang diteruskan ke sini
        // (checklist F7-03), sehingga pembeli tidak perlu menulisnya dua kali.
        $this->catatan = $keranjang->catatan();

        // Kurir mengikuti nilai yang tersimpan di pengaturan, bukan pilihan
        // bawaan yang ditulis di kode. Tanpa ini, mengubah daftar kurir
        // menuntut perubahan kode.
        $this->kurir = (string) (Pengaturan::instance()->teks('shipping_courier_default') ?: 'JNE');

        // Metode pembayaran mengikuti mode yang aktif. Mode "manual" tidak boleh
        // menyodorkan PayPal, dan sebaliknya.
        $this->metode = match (Pengaturan::instance()->teks('payment_mode', 'manual')) {
            'paypal' => 'paypal',
            'both' => 'paypal',
            default => 'manual',
        };

        if (auth()->check()) {
            $user = auth()->user();
            $this->nama = (string) $user->name;
            $this->email = (string) $user->email;
            $this->telepon = (string) ($user->phone_number ?? '');

            $utama = $user->addresses()->where('is_primary', true)->first()
                ?? $user->addresses()->first();

            if ($utama) {
                $this->nama = $utama->recipient_name;
                $this->telepon = $utama->phone_number;
                $this->alamat1 = $utama->address_line_1;
                $this->alamat2 = (string) $utama->address_line_2;
                $this->kota = $utama->city;
                $this->provinsi = $utama->state_province;
                $this->kodePos = $utama->postal_code;
            }
        }
    }

    public function buatPesanan(Keranjang $keranjang, PesananService $pesanan): void
    {
        $this->galat = null;

        // Jebakan bot dan token waktu minimum. Tanpa CAPTCHA, ini yang menyaring
        // pengiriman otomatis.
        if ($this->situs !== '' || now()->timestamp - $this->dimulaiPada < config('iksan.brute_force.min_form_seconds')) {
            $this->galat = 'Formulir dikirim terlalu cepat. Muat ulang halaman lalu coba lagi.';

            return;
        }

        $kunci = self::KUNCI_BATAS.':'.request()->ip();

        // 5 percobaan per 10 menit (PRD Bagian 11.1), dijawab sebagai 429 —
        // bukan sebagai pesan galat di dalam halaman.
        //
        // Pembatas di dalam komponen hanya dapat mengubah tampilan: responsnya
        // tetap 200, sehingga pengawas lalu lintas dan peramban tidak pernah
        // tahu ada yang sedang dibatasi. Karena `buatPesanan` dijalankan lewat
        // permintaan Livewire, abort() di sini menghasilkan respons HTTP 429
        // yang sesungguhnya.
        // Batas ini TIDAK dipasang pada GET /checkout: membuka halaman checkout
        // berkali-kali adalah hal biasa (tombol kembali, muat ulang), sedangkan
        // yang dibatasi PRD adalah pembuatan pesanannya.
        if (RateLimiter::tooManyAttempts($kunci.'|'.session()->getId(), config('iksan.rate_limits.checkout')[0])) {
            abort(429, 'Terlalu banyak percobaan checkout. Coba lagi beberapa menit lagi.');
        }

        $this->validate();

        RateLimiter::hit($kunci.'|'.session()->getId(), config('iksan.rate_limits.checkout')[1] * 60);

        // Pesanan dibuat sekali saja (checklist F7-07).
        //
        // Tanpa penanda ini, satu klik ganda atau satu percobaan ulang setelah
        // jaringan tersendat menghasilkan dua pesanan dan dua tahanan stok
        // untuk barang yang sama. Penandanya disimpan di sesi, jadi berlaku
        // per pengunjung; yang menjaganya dari penyerobotan adalah kunci
        // keranjang yang dipakai di sini.
        $kunciPesan = 'checkout:terkirim:'.session()->getId();

        if (session()->has($kunciPesan)) {
            $nomor = (string) session()->get($kunciPesan);

            $sudah = \App\Models\Order::query()->where('order_number', $nomor)->first();

            if ($sudah !== null) {
                $this->redirect(route('front.order.success', $sudah), navigate: true);

                return;
            }
        }

        try {
            $order = $pesanan->buatDariKeranjang(
                keranjang: $keranjang,
                data: [
                    'nama' => $this->nama,
                    'email' => $this->email,
                    'telepon' => $this->telepon,
                    'alamat1' => $this->alamat1,
                    'alamat2' => $this->alamat2,
                    'kota' => $this->kota,
                    'provinsi' => $this->provinsi,
                    'kodePos' => $this->kodePos,
                    'kurir' => $this->kurir,
                    'catatan' => $this->catatan,
                ],
                metode: MetodePembayaran::from($this->metode),
                user: auth()->user(),
            );
        } catch (\RuntimeException $e) {
            $this->galat = $e->getMessage();

            return;
        }

        session()->put($kunciPesan, $order->order_number);

        $this->dispatch('keranjang-berubah');
        $this->redirect(route('front.order.success', $order), navigate: true);
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();
        $keranjang = app(Keranjang::class);

        $mode = $toko->teks('payment_mode', 'manual');

        // Daftar kurir dari pengaturan, bukan dari kode (PRD Bagian 8 langkah 3
        // menyebut daftar statis; "statis" berarti tidak dihitung dari berat
        // atau tujuan, bukan berarti tidak dapat diubah tanpa menyentuh kode).
        $kurir = $toko->larikTeks('shipping_couriers', ['JNE', 'J&T Express', 'SiCepat', 'Pos Indonesia']);

        if ($this->kurir !== '' && ! in_array($this->kurir, $kurir, true)) {
            $kurir[] = $this->kurir;
        }

        return view('livewire.front.checkout', [
            'ringkasan' => $keranjang->ringkasan(),
            'modePembayaran' => $mode,
            // Hanya metode yang memang aktif. Menampilkan pilihan PayPal saat
            // mode manual berarti menawarkan pembayaran yang tidak tersedia.
            'daftarMetode' => match ($mode) {
                'paypal' => ['paypal'],
                'both' => ['paypal', 'manual'],
                default => ['manual'],
            },
            // Diberi nama berbeda dari properti $kurir. Livewire menyuntikkan
            // properti publik ke dalam view, dan nama yang sama membuat properti
            // (string) menimpa variabel ini (daftar), sehingga @foreach atasnya
            // gagal dan halaman checkout tidak dapat dirender sama sekali.
            'daftarKurir' => $kurir,
        ])
            ->title(__('site.checkout.title').' · '.$toko->teks('site_name'))
            ->layoutData([
                'kanonikal' => route('front.checkout'),
                'metaOg' => [
                    'tipe' => 'website',
                    'judul' => __('site.checkout.title'),
                    'deskripsi' => $toko->teks('site_description', __('site.brand.tagline')),
                    'gambar' => null,
                ],
                // Halaman checkout tidak boleh diindeks: isinya bergantung pada
                // keranjang pengunjung, dan yang terindeks hanya akan berupa
                // halaman kosong atau keranjang orang lain.
                'tanpaIndeks' => true,
            ]);
    }
}
