<?php

namespace App\Support;

use App\Enums\TipeKejadianKeamanan;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\SecurityLog;
use Illuminate\Http\Request;

/**
 * Pencatatan keamanan dan penegakan blokir.
 *
 * IP tidak pernah disimpan mentah. Hanya hash SHA-256 yang dicatat, sehingga
 * basis data tidak berisi daftar alamat pengunjung.
 */
class Keamanan
{
    public static function hashIp(?string $ip): string
    {
        return hash('sha256', (string) ($ip ?? ''));
    }

    public static function dariRequest(Request $request): string
    {
        return self::hashIp($request->ip());
    }

    public static function catat(
        TipeKejadianKeamanan $tipe,
        ?Request $request = null,
        ?string $email = null,
        ?int $userId = null,
        ?string $ringkasan = null,
    ): SecurityLog {
        $request ??= request();

        return SecurityLog::query()->create([
            'ip_hash' => self::dariRequest($request),
            'event_type' => $tipe,
            'user_id' => $userId ?? auth()->id(),
            'email_attempted' => $email,
            'route' => $request?->path(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
            'payload_summary' => $ringkasan,
        ]);
    }

    // ── Blokir IP ───────────────────────────────────────────────────

    public static function ipDiblokir(Request $request): bool
    {
        return BlockedIp::query()
            ->untukIp(self::dariRequest($request))
            ->aktif()
            ->exists();
    }

    public static function blokirIp(Request $request, string $alasan, int $jam): BlockedIp
    {
        $hash = self::dariRequest($request);

        $blokir = BlockedIp::query()->updateOrCreate(
            ['ip_hash' => $hash],
            [
                'reason' => $alasan,
                'blocked_until' => now()->addHours($jam),
                'created_at' => now(),
            ],
        );

        self::catat(TipeKejadianKeamanan::IpDiblokir, $request, ringkasan: $alasan);

        return $blokir;
    }

    public static function bukaBlokir(string $ipHash): int
    {
        return BlockedIp::query()->untukIp($ipHash)->delete();
    }

    // ── Percobaan login ─────────────────────────────────────────────

    /**
     * Hitung satu kegagalan login, dan kembalikan penundaan yang harus
     * ditunggu sebelum percobaan berikutnya diizinkan.
     *
     * @return array{terkunci: bool, sisaDetik: int, penundaan: int, percobaan: int}
     */
    public static function catatLoginGagal(Request $request, ?string $email): array
    {
        $ipHash = self::dariRequest($request);
        $emailBidx = $email === null ? null : BlindIndex::buat($email, 'email_bidx');

        $baris = LoginAttempt::query()->firstOrNew([
            'ip_hash' => $ipHash,
            'email_bidx' => $emailBidx,
        ]);

        $baris->attempts = ($baris->attempts ?? 0) + 1;
        $baris->last_attempt_at = now();
        $baris->save();

        $konfig = config('iksan.brute_force');
        $backoff = $konfig['backoff_seconds'];

        // Penundaan bertingkat: percobaan ke-1 langsung, ke-2 tunggu 1 detik,
        // ke-3 tunggu 2 detik, dan seterusnya sampai batas daftar.
        $penundaan = $backoff[min($baris->attempts - 1, count($backoff) - 1)];

        $gagalDalamJendela = LoginAttempt::query()
            ->where('ip_hash', $ipHash)
            ->where('last_attempt_at', '>=', now()->subMinutes($konfig['failure_window_minutes']))
            ->sum('attempts');

        $terkunci = false;

        if ($gagalDalamJendela >= $konfig['failures_before_ip_block']) {
            self::blokirIp($request, 'Terlalu banyak login gagal', $konfig['ip_block_hours']);
            self::catat(TipeKejadianKeamanan::BruteForceDiblokir, $request, $email, ringkasan: $gagalDalamJendela.' kegagalan');
            $terkunci = true;
        }

        self::catat(TipeKejadianKeamanan::LoginGagal, $request, $email);

        return [
            'terkunci' => $terkunci,
            'sisaDetik' => $terkunci ? $konfig['ip_block_hours'] * 3600 : 0,
            'penundaan' => $penundaan,
            'percobaan' => $baris->attempts,
        ];
    }

    /** Bersihkan riwayat kegagalan setelah login berhasil. */
    public static function bersihkanLoginGagal(Request $request, ?string $email): void
    {
        $emailBidx = $email === null ? null : BlindIndex::buat($email, 'email_bidx');

        LoginAttempt::query()
            ->where('ip_hash', self::dariRequest($request))
            ->where(function ($q) use ($emailBidx): void {
                $q->whereNull('email_bidx');
                if ($emailBidx !== null) {
                    $q->orWhere('email_bidx', $emailBidx);
                }
            })
            ->delete();
    }
}
