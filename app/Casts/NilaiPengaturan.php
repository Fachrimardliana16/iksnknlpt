<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Nilai pengaturan.
 *
 * Enkripsi bersifat per baris, ditentukan oleh kolom is_encrypted, bukan per
 * kolom. Kredensial pembayaran disimpan terenkripsi sementara nama toko tidak.
 * Cast "encrypted" bawaan Laravel tidak dapat dipakai karena keputusannya
 * bergantung pada atribut lain di baris yang sama.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class NilaiPengaturan implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! $this->terenkripsi($attributes)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Kunci berubah atau data rusak. Jangan jatuhkan seluruh halaman
            // pengaturan hanya karena satu baris tidak terbaca.
            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return [$key => null];
        }

        if (! $this->terenkripsi($attributes)) {
            return [$key => is_scalar($value) ? (string) $value : json_encode($value)];
        }

        return [$key => Crypt::encryptString(is_scalar($value) ? (string) $value : json_encode($value))];
    }

    private function terenkripsi(array $attributes): bool
    {
        return (bool) ($attributes['is_encrypted'] ?? false);
    }
}
