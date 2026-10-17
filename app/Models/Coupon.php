<?php

namespace App\Models;

use App\Enums\TipeKupon;
use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_spend',
        'max_discount',
        'quota',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TipeKupon::class,
            'value' => 'decimal:2',
            'min_spend' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'quota' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Kode selalu dibandingkan dalam huruf besar tanpa spasi tepi. */
    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value === null
            ? null
            : mb_strtoupper(trim($value));
    }

    public function scopeKode($query, string $kode)
    {
        return $query->where('code', mb_strtoupper(trim($kode)));
    }

    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Alasan kupon tidak dapat dipakai, atau null bila sah. */
    public function alasanTidakSah(float $subtotal, float $ongkir = 0.0): ?string
    {
        if (! $this->is_active) {
            return 'Kupon tidak aktif.';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'Kupon belum berlaku.';
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'Kupon sudah kedaluwarsa.';
        }

        if ($this->quota !== null && $this->used_count >= $this->quota) {
            return 'Kuota kupon sudah habis.';
        }

        if ((float) $this->min_spend > 0 && $subtotal < (float) $this->min_spend) {
            return 'Minimal belanja '.rupiah($this->min_spend).' untuk memakai kupon ini.';
        }

        return null;
    }

    public function hitungPotongan(float $subtotal, float $ongkir = 0.0): float
    {
        return $this->type->hitung(
            $subtotal,
            (float) $this->value,
            $this->max_discount === null ? null : (float) $this->max_discount,
            $ongkir,
        );
    }

    public function potonganOngkir(float $subtotal, float $ongkir): float
    {
        return $this->type === TipeKupon::GratisOngkir
            ? $this->hitungPotongan($subtotal, $ongkir)
            : 0.0;
    }

    public function potonganBelanja(float $subtotal, float $ongkir): float
    {
        return $this->type === TipeKupon::GratisOngkir
            ? 0.0
            : $this->hitungPotongan($subtotal, $ongkir);
    }
}
