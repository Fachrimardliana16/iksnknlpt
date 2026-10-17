<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Hybrid identifier: integer untuk relasi internal, ULID untuk rute publik.
 * Mencegah Insecure Direct Object Reference dan enumerasi ID.
 */
trait PunyaUlid
{
    use HasUlids;

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * ULID diisi otomatis saat pembuatan, bukan dari kunci primer.
     * Laravel perlu tahu kolom mana yang unik.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** Pencarian pada rute publik memakai ULID, bukan id. */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->where($field ?? $this->getRouteKeyName(), $value);
    }
}
