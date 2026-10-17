<?php

namespace App\Livewire\Admin;

use App\Enums\PeranPengguna;
use App\Enums\TipeKejadianKeamanan;
use App\Models\User;
use App\Support\BlindIndex;
use App\Support\Keamanan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

class Masuk extends Component
{
    #[Validate('required|email|max:120')]
    public string $email = '';

    #[Validate('required|string|min:6|max:120')]
    public string $password = '';

    public bool $ingat = false;

    /** Tahap kedua setelah kata sandi benar. */
    public bool $tahapOtp = false;

    public string $otp = '';

    public ?string $galat = null;

    public ?int $tungguDetik = null;

    /** @var array<int, string>|null */
    public ?array $kodeCadangan = null;

    public function mount(): void
    {
        if (auth()->check() && auth()->user()->admin()) {
            $this->redirect(route('admin.dashboard'), navigate: true);

            return;
        }

        if (Keamanan::ipDiblokir(request())) {
            $this->galat = 'Jaringan ini diblokir sementara karena terlalu banyak percobaan masuk.';
        }
    }

    public function masuk(): void
    {
        $this->galat = null;

        $kunci = 'admin-login:'.request()->ip();
        $batas = config('iksan.rate_limits.admin_login');

        if (RateLimiter::tooManyAttempts($kunci, $batas[0])) {
            $sisa = RateLimiter::availableIn($kunci);

            Keamanan::catat(TipeKejadianKeamanan::BatasLaju, request(), $this->email, ringkasan: 'Login admin diblokir '.$sisa.' detik');
            $this->tungguDetik = $sisa;
            $this->galat = 'Terlalu banyak percobaan masuk. Coba lagi setelah masa tunggu.';

            return;
        }

        if (Keamanan::ipDiblokir(request())) {
            $this->galat = 'Jaringan ini diblokir sementara.';

            return;
        }

        $this->validate();

        $user = User::query()
            ->where('email_bidx', BlindIndex::buat($this->email, 'email_bidx'))
            ->first();

        // Pesan galat selalu sama, tidak membedakan email tidak terdaftar dari
        // kata sandi salah. Ini mencegah enumerasi akun.
        $pesanSeragam = 'Email atau kata sandi tidak cocok.';

        if ($user === null || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($kunci, $batas[1] * 60);

            $hasil = Keamanan::catatLoginGagal(request(), $this->email);

            if ($hasil['terkunci']) {
                $this->galat = 'Jaringan ini diblokir karena terlalu banyak percobaan gagal.';

                return;
            }

            if ($user !== null && $hasil['percobaan'] > 1) {
                // Penundaan bertingkat sebelum percobaan berikutnya diizinkan.
                sleep(min($hasil['penundaan'], 4));
            }

            $this->galat = $pesanSeragam;

            return;
        }

        if ($user->is_blocked) {
            Keamanan::catat(TipeKejadianKeamanan::AksesTidakSah, request(), $this->email, $user->id, 'Akun diblokir mencoba masuk');
            $this->galat = 'Akun ini diblokir. Hubungi pemilik toko.';

            return;
        }

        $peran = $user->role instanceof PeranPengguna ? $user->role : PeranPengguna::tryFrom((string) $user->role);

        if ($peran === null || ! $peran->bisaMasukPanel()) {
            Keamanan::catat(TipeKejadianKeamanan::AksesTidakSah, request(), $this->email, $user->id, 'Bukan admin mencoba masuk panel');
            $this->galat = $pesanSeragam;

            return;
        }

        // Kata sandi benar. Bila 2FA aktif, jangan buat sesi dulu.
        if ($user->punyaDuaFaktor()) {
            session()->put('admin.2fa.user', $user->id);
            session()->put('admin.2fa.ingat', $this->ingat);

            $this->tahapOtp = true;
            $this->password = '';

            return;
        }

        $this->selesaikan($user);
    }

    public function verifikasiOtp(): void
    {
        $this->galat = null;

        $userId = session()->get('admin.2fa.user');

        if ($userId === null) {
            $this->tahapOtp = false;
            $this->galat = 'Sesi verifikasi berakhir. Masuk ulang.';

            return;
        }

        $kunci = 'admin-2fa:'.request()->ip();

        if (RateLimiter::tooManyAttempts($kunci, config('iksan.rate_limits.admin_login')[0])) {
            Keamanan::catat(TipeKejadianKeamanan::BatasLaju, request(), null, $userId, 'Batas laju 2FA');
            $this->galat = 'Terlalu banyak percobaan. Tunggu sebentar.';

            return;
        }

        RateLimiter::hit($kunci, 60);

        /** @var User|null $user */
        $user = User::query()->find($userId);

        if ($user === null) {
            $this->tahapOtp = false;

            return;
        }

        $kode = preg_replace('/\s+/', '', $this->otp) ?? '';

        $google = new Google2FA;
        $sah = strlen($kode) === 6 && $google->verifyKey((string) $user->two_factor_secret, $kode);

        if (! $sah) {
            // Kode cadangan: satu kali pakai, dihapus setelah dipakai.
            $cadangan = $user->two_factor_recovery_codes ?? [];
            $cadangan = is_string($cadangan) ? (json_decode($cadangan, true) ?: []) : $cadangan;
            $indeks = array_search($kode, $cadangan, true);

            if ($kode !== '' && $indeks !== false) {
                unset($cadangan[$indeks]);
                $user->two_factor_recovery_codes = array_values($cadangan);
                $user->save();
                $sah = true;
            }
        }

        if (! $sah) {
            Keamanan::catat(TipeKejadianKeamanan::DuaFaktorGagal, request(), $user->email, $user->id);
            $this->galat = 'Kode verifikasi tidak cocok.';

            return;
        }

        session()->forget(['admin.2fa.user', 'admin.2fa.ingat']);

        $this->selesaikan($user);
    }

    private function selesaikan(User $user, bool $ingat = false): void
    {
        auth()->login($user, $ingat || $this->ingat);

        // Regenerasi ID sesi mencegah session fixation.
        session()->regenerate();
        session()->put('admin.terakhir_aktif', now()->timestamp);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip_hash' => Keamanan::dariRequest(request()),
        ])->save();

        Keamanan::bersihkanLoginGagal(request(), $user->email);
        Keamanan::catat(TipeKejadianKeamanan::LoginBerhasil, request(), $user->email, $user->id);

        RateLimiter::clear('admin-login:'.request()->ip());

        $this->redirect(route('admin.dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.masuk')->layout('layouts.polos');
    }
}
