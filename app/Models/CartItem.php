<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function hargaSatuan(): float
    {
        return $this->variant?->hargaAkhir() ?? (float) $this->product?->base_price ?? 0.0;
    }

    public function subtotal(): float
    {
        return $this->hargaSatuan() * $this->quantity;
    }

    /** Jumlah yang bisa dipesan, dibatasi stok tersedia. */
    public function stokMaksimal(): int
    {
        return $this->variant?->stokTersedia() ?? 0;
    }

    public function melebihiStok(): bool
    {
        return $this->quantity > $this->stokMaksimal();
    }
}
