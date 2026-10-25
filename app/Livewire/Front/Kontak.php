<?php

namespace App\Livewire\Front;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Kontak extends Component
{
    #[Validate('required|string|min:2|max:80')]
    public string $nama = '';

    #[Validate('required|email|max:120')]
    public string $email = '';

    #[Validate('nullable|string|max:20')]
    public string $telepon = '';

    #[Validate('nullable|string|max:120')]
    public string $subjek = '';

    #[Validate('required|string|min:10|max:2000')]
    public string $pesan = '';

    /** Jebakan bot. Wajib kosong. */
    public string $perusahaan = '';

    public int $dimulaiPada = 0;

    public bool $terkirim = false;

    public ?string $galat = null;

    public function mount(): void
    {
        $this->dimulaiPada = now()->timestamp;
    }

    public function kirim(): void
    {
        $this->galat = null;

        if ($this->perusahaan !== '' || now()->timestamp - $this->dimulaiPada < config('iksan.brute_force.min_form_seconds')) {
            $this->galat = 'Formulir dikirim terlalu cepat. Coba lagi.';

            return;
        }

        $kunci = 'kontak:'.request()->ip();

        if (RateLimiter::tooManyAttempts($kunci, config('iksan.rate_limits.contact')[0])) {
            $this->galat = 'Terlalu banyak pesan dari jaringan ini. Coba lagi nanti.';

            return;
        }

        RateLimiter::hit($kunci, config('iksan.rate_limits.contact')[1] * 60);

        $this->validate();

        ContactMessage::query()->create([
            'name' => $this->nama,
            'email' => $this->email,
            'phone' => $this->telepon ?: null,
            'subject' => $this->subjek ?: 'Pesan dari halaman kontak',
            'message' => $this->pesan,
            'ip_hash' => hash('sha256', (string) request()->ip()),

            // User agent dipangkas sebelum disimpan, bukan diandalkan pada
            // kolom basis data. Header ini panjangnya sewenang-wenang — ada
            // peramban dan ekstensi yang mengirim ratusan karakter, dan
            // memotongnya di sini berarti panjang yang tersimpan selalu dapat
            // diperkirakan.
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),

            'is_read' => false,
        ]);

        $this->reset(['nama', 'email', 'telepon', 'subjek', 'pesan', 'perusahaan']);
        $this->dimulaiPada = now()->timestamp;
        $this->terkirim = true;
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();

        return view('livewire.front.kontak', [
            'kategori' => Category::query()->aktif()->terurut()->take(6)->get(),
        ])->title(__('site.contact.title').' · '.$toko->teks('site_name'));
    }
}
