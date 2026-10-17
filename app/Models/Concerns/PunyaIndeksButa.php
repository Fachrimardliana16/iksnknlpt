<?php

namespace App\Models\Concerns;

use App\Support\BlindIndex;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Sinkronisasi otomatis antara kolom ber-cast "encrypted" dan kolom indeks
 * buta pasangannya.
 *
 * Pemakaian pada model:
 *
 *     use PunyaIndeksButa;
 *
 *     protected array $indeksButa = [
 *         'email' => 'email_bidx',
 *     ];
 *
 * Setiap kali atribut sumber diisi, kolom indeks diisi pada saat yang sama,
 * sehingga tidak ada jalur kode yang dapat menyimpan email tanpa indeksnya.
 */
trait PunyaIndeksButa
{
    public static function bootPunyaIndeksButa(): void
    {
        static::saving(function ($model): void {
            foreach ($model->indeksButa() as $sumber => $kolomIndeks) {
                $nilai = $model->getAttribute($sumber);

                $model->setAttribute($kolomIndeks, BlindIndex::buat(
                    $nilai === null ? null : (string) $nilai,
                    $kolomIndeks,
                ));
            }
        });
    }

    /** @return array<string, string> */
    public function indeksButa(): array
    {
        return property_exists($this, 'indeksButa') ? $this->indeksButa : [];
    }

    /**
     * Cakupan pencarian: dipakai oleh scopeCariIndeksButa.
     *
     *     User::cariIndeksButa('email', 'budi@example.com')->first();
     */
    public function scopeCariIndeksButa($query, string $sumber, ?string $nilai)
    {
        $kolom = $this->indeksButa()[$sumber] ?? null;

        if ($kolom === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($kolom, BlindIndex::buat($nilai, $kolom));
    }
}
