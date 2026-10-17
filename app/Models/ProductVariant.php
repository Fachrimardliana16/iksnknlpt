<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'product_id',
        'sku_variant',
        'variant_name_id',
        'variant_name_en',
        'price_adjustment',
        'stock_quantity',
        'reserved_quantity',
        'weight_gram',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'weight_gram' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function nama(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' && filled($this->variant_name_en)
            ? $this->variant_name_en
            : $this->variant_name_id;
    }

    public function hargaAkhir(): float
    {
        $harga = (float) $this->product->base_price + (float) $this->price_adjustment;

        return max(0.0, $harga);
    }

    /** Jumlah yang benar-benar dapat dijual. */
    public function stokTersedia(): int
    {
        return max(0, $this->stock_quantity - $this->reserved_quantity);
    }

    public function habis(): bool
    {
        return $this->stokTersedia() <= 0;
    }

    public function berat(): int
    {
        return $this->weight_gram ?? (int) $this->product->weight_gram;
    }
}
