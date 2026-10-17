<?php

namespace App\Services\Pembayaran;

use App\Support\KomponenPengaturan;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Penyedok kredensial PayPal (checklist F4-05).
 *
 * Pengujian yang jujur harus memakai kredensial tersimpan, bukan yang baru
 * diketik, sehingga admin tahu pasangan yang benar-benar terpakai sudah sah.
 * Kredensial tidak pernah dikembalikan di dalam pesan hasil — PayPal sendiri
 * mengembalikan access_token untuk client id yang salah dan itu akan terbaca
 * di layar admin kalau isi balasan diteruskan mentah.
 */
class KlienPaypal
{
    public const SANDBOX = 'https://api-m.sandbox.paypal.com';

    public const LIVE = 'https://api-m.paypal.com';

    private const TIMEOUT = 15;

    public function dasar(): string
    {
        return KomponenPengaturan::mentah('paypal_mode') === 'live' ? self::LIVE : self::SANDBOX;
    }

    public function mode(): string
    {
        return KomponenPengaturan::mentah('paypal_mode') === 'live' ? 'live' : 'sandbox';
    }

    /**
     * @return array{ok: bool, pesan: string, detail: array<string, mixed>}
     */
    public function ujiKoneksi(): array
    {
        $id = KomponenPengaturan::mentah('paypal_client_id');
        $rahasia = KomponenPengaturan::mentah('paypal_client_secret');

        $kurang = [];

        if (blank($id)) {
            $kurang[] = 'Client ID';
        }

        if (blank($rahasia)) {
            $kurang[] = 'Client Secret';
        }

        if ($kurang !== []) {
            return [
                'ok' => false,
                'pesan' => 'Kredensial belum lengkap: '.implode(' dan ', $kurang).' belum diisi.',
                'detail' => [],
            ];
        }

        $dasar = $this->dasar();

        try {
            $mulai = microtime(true);

            $respons = Http::asForm()
                ->withBasicAuth($id, $rahasia)
                ->timeout(self::TIMEOUT)
                ->post($dasar.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            $ms = (int) round((microtime(true) - $mulai) * 1000);

            if ($respons->status() === 401) {
                return [
                    'ok' => false,
                    'pesan' => 'PayPal menolak kredensial ini (401). Periksa Client ID dan Client Secret untuk mode '.$this->mode().'.',
                    'detail' => ['status' => 401, 'ms' => $ms, 'mode' => $this->mode()],
                ];
            }

            if (! $respons->successful()) {
                return [
                    'ok' => false,
                    'pesan' => 'PayPal membalas dengan status '.$respons->status().'. Kredensial belum dapat diverifikasi.',
                    'detail' => ['status' => $respons->status(), 'ms' => $ms, 'mode' => $this->mode()],
                ];
            }

            $token = (string) $respons->json('access_token');

            if ($token === '') {
                return [
                    'ok' => false,
                    'pesan' => 'PayPal membalas 200 tanpa access_token. Kredensial belum dapat dipastikan sah.',
                    'detail' => ['status' => 200, 'ms' => $ms, 'mode' => $this->mode()],
                ];
            }

            // Token tidak pernah diteruskan ke pemanggil. Yang dilaporkan hanya
            // keberadaannya dan sisa masa berlakunya.
            return [
                'ok' => true,
                'pesan' => 'Koneksi berhasil. Mode '.$this->mode().' menerima kredensial tersimpan.',
                'detail' => [
                    'status' => 200,
                    'ms' => $ms,
                    'mode' => $this->mode(),
                    'berlaku_detik' => (int) $respons->json('expires_in'),
                    'jenis_token' => (string) $respons->json('token_type'),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'pesan' => 'Tidak dapat menghubungi PayPal: '.$this->pesanAman($e->getMessage()),
                'detail' => ['mode' => $this->mode()],
            ];
        }
    }

    /**
     * Pesan galat jaringan tidak boleh memuat kredensial. Laravel menyisipkan
     * URL lengkap pada sebagian galat, dan URL PayPal tidak memuat rahasia,
     * tetapi isi pesan tetap disaring untuk berjaga.
     */
    private function pesanAman(string $pesan): string
    {
        $id = KomponenPengaturan::mentah('paypal_client_id');
        $rahasia = KomponenPengaturan::mentah('paypal_client_secret');

        foreach ([$id, $rahasia] as $rahasiaSatu) {
            if (filled($rahasiaSatu)) {
                $pesan = str_replace($rahasiaSatu, '[kredensial]', $pesan);
            }
        }

        return mb_substr($pesan, 0, 300);
    }
}
