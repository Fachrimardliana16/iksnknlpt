<?php

namespace App\Livewire\Front;

use App\Enums\MetodePembayaran;
use App\Services\Keranjang;
use App\Services\PesananService;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

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

        if (RateLimiter::tooManyAttempts($kunci.'|'.session()->getId(), config('iksan.rate_limits.checkout')[0])) {
            $this->galat = 'Terlalu banyak percobaan checkout. Coba lagi beberapa menit lagi.';

            return;
        }

        $this->validate();

        RateLimiter::hit($kunci.'|'.session()->getId(), config('iksan.rate_limits.checkout')[1] * 60);

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

        $this->dispatch('keranjang-berubah');
        $this->redirect(route('front.order.success', $order), navigate: true);
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();
        $keranjang = app(Keranjang::class);

        $mode = $toko->teks('payment_mode', 'manual');

        return view('livewire.front.checkout', [
            'ringkasan' => $keranjang->ringkasan(),
            'modePembayaran' => $mode,
            // Diberi nama berbeda dari properti $kurir. Livewire menyuntikkan
            // properti publik ke dalam view, dan nama yang sama membuat properti
            // (string) menimpa variabel ini (daftar), sehingga @foreach atasnya
            // gagal dan halaman checkout tidak dapat dirender sama sekali.
            'daftarKurir' => ['JNE', 'J&T Express', 'SiCepat', 'Pos Indonesia'],
        ])->title(__('site.checkout.title').' · '.$toko->teks('site_name'));
    }
}
