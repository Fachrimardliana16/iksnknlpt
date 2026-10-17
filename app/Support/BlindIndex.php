<?php

namespace App\Support;

use RuntimeException;

/**
 * Blind index untuk kolom ber-cast "encrypted".
 *
 * Kolom terenkripsi menghasilkan ciphertext acak, jadi tidak dapat di-WHERE
 * maupun di-LIKE. Setiap kolom yang perlu dicari menyimpan HMAC-SHA256 dengan
 * kunci terpisah di kolom *_bidx. Kunci berbeda dari APP_KEY dengan sengaja:
 * merotasi APP_KEY tidak boleh membuat seluruh indeks tidak dapat dipakai.
 */
class BlindIndex
{
    public static function buat(?string $value, string $context = 'default'): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::normalisasi($value);
        $key = self::kunci();

        return hash_hmac('sha256', $context.':'.$normalized, $key);
    }

    /**
     * Normalisasi wajib konsisten. Tanpa ini, "Budi@Example.com" dan
     * "budi@example.com " menghasilkan indeks berbeda dan pencarian gagal senyap.
     */
    public static function normalisasi(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private static function kunci(): string
    {
        $key = config('iksan.blind_index_key');

        if (blank($key)) {
            throw new RuntimeException(
                'BLIND_INDEX_KEY belum diisi. Jalankan: php artisan tinker --execute="echo bin2hex(random_bytes(32));"'
            );
        }

        return (string) $key;
    }
}
