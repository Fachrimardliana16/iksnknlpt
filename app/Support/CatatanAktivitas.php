<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Catatan aktivitas panel (PRD Bagian 12.3, checklist F4-07).
 *
 * Setiap perubahan pengaturan meninggalkan satu baris di activity_logs. Nilai
 * yang terenkripsi — kredensial PayPal, rekening bank — TIDAK pernah ditulis ke
 * catatan, baik nilai lama maupun nilai baru. Yang tercatat hanya penanda
 * "[changed]" supaya jejaknya tetap ada tanpa membocorkan rahasianya.
 */
class CatatanAktivitas
{
    public const PENANDA_RAHASIA = '[changed]';

    /**
     * Bandingkan nilai lama dan baru, catat hanya kunci yang benar-benar berubah.
     *
     * @param  array<string, mixed>  $lama
     * @param  array<string, mixed>  $baru
     * @param  array<int, string>  $kunciRahasia  kunci yang nilainya tidak boleh ditulis
     */
    public static function setting(
        array $lama,
        array $baru,
        array $kunciRahasia = [],
        ?int $userId = null,
    ): ?ActivityLog {
        $perubahan = [];
        $rahasia = array_flip($kunciRahasia);

        foreach ($baru as $kunci => $nilaiBaru) {
            $nilaiLama = $lama[$kunci] ?? null;

            if (self::sama($nilaiLama, $nilaiBaru)) {
                continue;
            }

            if (isset($rahasia[$kunci])) {
                // Nilai lama pun tidak boleh ditulis: kalau kredensialnya baru
                // diganti, nilai lama yang bocor sama berbahayanya.
                $perubahan[$kunci] = self::PENANDA_RAHASIA;

                continue;
            }

            $perubahan[$kunci] = [
                'lama' => self::ringkas($nilaiLama),
                'baru' => self::ringkas($nilaiBaru),
            ];
        }

        if ($perubahan === []) {
            return null;
        }

        return self::catat('setting.diubah', $perubahan, $userId);
    }

    /**
     * @param  array<string, mixed>  $perubahan
     */
    public static function catat(
        string $aksi,
        array $perubahan = [],
        ?int $userId = null,
        ?Model $subjek = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $aksi,
            'subject_type' => $subjek?->getMorphClass(),
            'subject_id' => $subjek?->getKey(),
            'changes_json' => $perubahan,
        ]);
    }

    private static function sama(mixed $a, mixed $b): bool
    {
        // 0 dan '0' dianggap sama: nilai pengaturan selalu tersimpan sebagai
        // teks, jadi pembandingan ketat akan melaporkan perubahan palsu.
        if (is_numeric($a) && is_numeric($b)) {
            return (string) $a === (string) $b;
        }

        if (is_bool($a) || is_bool($b)) {
            return filter_var($a, FILTER_VALIDATE_BOOLEAN) === filter_var($b, FILTER_VALIDATE_BOOLEAN);
        }

        return (string) ($a ?? '') === (string) ($b ?? '');
    }

    /** Nilai panjang dipotong supaya catatan tidak membengkak. */
    private static function ringkas(mixed $nilai): mixed
    {
        if (is_bool($nilai)) {
            return $nilai;
        }

        if ($nilai === null) {
            return null;
        }

        $teks = is_scalar($nilai) ? (string) $nilai : json_encode($nilai);

        return mb_strlen($teks) > 120 ? mb_substr($teks, 0, 117).'...' : $teks;
    }
}
