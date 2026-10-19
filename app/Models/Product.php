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
        // chaperone() memasang balik relasi product pada setiap varian yang
        // dimuat lewat produk ini.
        //
        // Tanpa itu, $variant->product pada hasil eager loading tetap dianggap
        // belum dimuat dan $variant->hargaAkhir() memicu satu kueri per varian —
        // tercatat 14 kueri untuk satu halaman beranda, semuanya mengambil baris
        // yang sama. chaperone() yang menutupnya, bukan pemanggilan with() di
        // setiap tempat, supaya tidak ada tempat yang terlupa.
        return $this->hasMany(ProductVariant::class)->chaperone();
    }

    /** @return HasMany<ProductMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('display_order');
    }

    /** @return HasOne<ProductMedia, $this> */
    public function primaryMedia(): HasOne
    {
        // chaperone() sama alasannya dengan variants(): tanpa itu, gambarUtama()
        // memicu satu kueri per produk meski relasi media sudah dimuat —
        // tercatat 8 kueri pada satu halaman beranda.
        return $this->hasOne(ProductMedia::class)->where('is_primary', true)->chaperone();
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

    /**
     * Gambar utama kartu produk.
     *
     * Memakai koleksi media yang sudah dimuat bila tersedia.
     *
     * Relasi primaryMedia sengaja TIDAK diakses lebih dulu: ia relasi terpisah,
     * sehingga memuatnya butuh kueri sendiri per produk — tercatat 8 kueri pada
     * beranda dan 12 pada daftar produk, semuanya mengambil baris media yang
     * sudah ada di memori. Koleksi media hampir selalu ikut dimuat bersama
     * produk, jadi mencari di dalamnya selalu lebih murah.
     *
     * primaryMedia hanya dipakai sebagai jalan terakhir, ketika media belum
     * dimuat sama sekali.
     */
    public function gambarUtama(): ?ProductMedia
    {
        if ($this->relationLoaded('media')) {
            return $this->media->firstWhere('is_primary', true)
                ?? $this->media->firstWhere('type', 'image')
                ?? $this->media->first();
        }

        return $this->primaryMedia
            ?? $this->media->firstWhere('type', 'image')
            ?? $this->media->first();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
