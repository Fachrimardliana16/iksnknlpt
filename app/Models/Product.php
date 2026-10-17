<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'category_id',
        'sku',
        'title_id',
        'title_en',
        'slug',
        'excerpt_id',
        'excerpt_en',
        'description_id',
        'description_en',
        'base_price',
        'weight_gram',
        'is_featured',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'weight_gram' => 'integer',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'view_count' => 'integer',
            'sold_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    // ── Relasi ──────────────────────────────────────────────────────

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ProductMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('display_order');
    }

    /** @return HasOne<ProductMedia, $this> */
    public function primaryMedia(): HasOne
    {
        return $this->hasOne(ProductMedia::class)->where('is_primary', true);
    }

    // ── Cakupan ─────────────────────────────────────────────────────

    public function scopeTerbit(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeUnggulan(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeCari(Builder $query, ?string $kata): Builder
    {
        if (blank($kata)) {
            return $query;
        }

        // Judul dan deskripsi tidak terenkripsi, jadi LIKE aman di sini.
        $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], $kata).'%';

        return $query->where(function (Builder $q) use ($pola): void {
            $q->where('title_id', 'like', $pola)
                ->orWhere('title_en', 'like', $pola)
                ->orWhere('sku', 'like', $pola)
                ->orWhere('excerpt_id', 'like', $pola);
        });
    }

    /** Stok dapat dijual = stok fisik dikurangi yang ditahan pesanan berjalan. */
    public function scopeAdaStok(Builder $query): Builder
    {
        return $query->whereHas('variants', function (Builder $q): void {
            $q->where('is_active', true)
                ->whereColumn('stock_quantity', '>', 'reserved_quantity');
        });
    }

    // ── Turunan ─────────────────────────────────────────────────────

    public function judul(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' && filled($this->title_en)
            ? $this->title_en
            : $this->title_id;
    }

    public function deskripsi(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' && filled($this->description_en)
            ? $this->description_en
            : $this->description_id;
    }

    public function ringkasan(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' ? $this->excerpt_en : $this->excerpt_id;
    }

    /** Varian aktif termurah, dipakai untuk label "mulai dari". */
    public function varianTermurah(): ?ProductVariant
    {
        return $this->variants
            ->where('is_active', true)
            ->sortBy(fn (ProductVariant $v) => (float) $v->hargaAkhir())
            ->first();
    }

    public function hargaMulai(): float
    {
        $varian = $this->varianTermurah();

        return $varian ? (float) $varian->hargaAkhir() : (float) $this->base_price;
    }

    public function adaStok(): bool
    {
        return $this->variants
            ->where('is_active', true)
            ->contains(fn (ProductVariant $v) => $v->stokTersedia() > 0);
    }

    public function totalStok(): int
    {
        return (int) $this->variants
            ->where('is_active', true)
            ->sum(fn (ProductVariant $v) => $v->stokTersedia());
    }

    public function gambarUtama(): ?ProductMedia
    {
        return $this->primaryMedia
            ?? $this->media->firstWhere('type', 'image')
            ?? $this->media->first();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
