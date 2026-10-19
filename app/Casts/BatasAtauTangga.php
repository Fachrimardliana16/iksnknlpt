<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu kolom, dua arti (Fase 2: P2-06).
 *
 * `coupons.max_discount` dipakai untuk dua hal:
 *
 *   - angka biasa  → batas potongan, mis. "50000"
 *   - larik JSON   → tangga bertingkat, mis. [{"min":500000,"persen":5}]
 *
 * Cast `decimal:2` bawaan mengubah larik menjadi string angka dan menghapus
 * struktur tangga tanpa galat apa pun — kupon bertingkat lalu diam-diam menjadi
 * kupon biasa tanpa potongan. Cast ini membaca bentuknya lebih dulu: yang
 * berbentuk larik dikembalikan sebagai larik, yang berbentuk angka dikembalikan
 * sebagai string desimal (sama seperti `decimal:2`, supaya perilaku kupon biasa
 * tidak berubah).
 */
class BatasAtauTangga implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $teks = trim((string) $value);

        if (str_starts_with($teks, '[')) {
            $data = json_decode($teks, true);

            if (is_array($data)) {
                return $data;
            }
        }

        // Bentuk angka: dipertahankan sebagai string dua desimal, sama dengan
        // hasil cast decimal:2 yang dipakai sebelumnya.
        return is_numeric($teks) ? number_format((float) $teks, 2, '.', '') : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
        }

        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }
}
