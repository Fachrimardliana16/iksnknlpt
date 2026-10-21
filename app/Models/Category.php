<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'name_id',
        'name_en',
        'description_id',
        'description_en',
        'slug',
        'image_path',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTerurut(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name_id');
    }

    /** Nama sesuai bahasa aktif. */
    public function nama(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' && filled($this->name_en)
            ? $this->name_en
            : $this->name_id;
    }

    /**
     * Deskripsi kategori sesuai bahasa aktif.
     *
     * Mengembalikan string kosong bila tidak diisi — pemanggil yang memutuskan
     * apa yang ditampilkan. Mengembalikan null di sini akan memaksa setiap
     * pemanggil menangani dua nilai kosong yang berbeda.
     */
    public function deskripsi(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $teks = $locale === 'en' && filled($this->description_en)
            ? $this->description_en
            : $this->description_id;

        return trim((string) $teks);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
