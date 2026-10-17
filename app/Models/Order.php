<?php

namespace App\Models;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Models\Concerns\PunyaIndeksButa;
use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory, PunyaIndeksButa, PunyaUlid;

    protected $fillable = [
        'order_number',
        'user_id',
        'guest_email',
        'guest_phone',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_cost',
        'grand_total',
        'currency',
        'coupon_id',
        'shipping_address_snapshot',
        'shipping_courier',
        'shipping_service',
        'tracking_number',
        'customer_note',
        'admin_note',
        'paid_at',
        'shipped_at',
        'completed_at',
        'cancelled_at',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected array $indeksButa = [
        'guest_email' => 'guest_email_bidx',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPesanan::class,
            'payment_status' => StatusPembayaran::class,
            'payment_method' => MetodePembayaran::class,

            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'grand_total' => 'decimal:2',

            // Kolom PII. DBSCHEMA.md Bagian 9.2.
            'guest_email' => 'encrypted',
            'guest_phone' => 'encrypted',
            'shipping_address_snapshot' => 'encrypted:json',

            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // ── Relasi ──────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('created_at');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Payment, $this> */
    public function paymentTerakhir(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    // ── Cakupan ─────────────────────────────────────────────────────

    public function scopeStatus(Builder $query, StatusPesanan|string $status): Builder
    {
        return $query->where('status', $status instanceof StatusPesanan ? $status->value : $status);
    }

    public function scopePerluDibatalkan(Builder $query): Builder
    {
        return $query->where('status', StatusPesanan::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Pencarian nomor pesanan dan nama penerima.
     *
     * Nama penerima ada di dalam kolom snapshot terenkripsi, jadi tidak dapat
     * di-LIKE di SQL. Pencarian nama dilakukan setelah dekripsi di PHP
     * (lihat scopeCari dengan nama pada pemanggil), bukan di sini.
     * Yang dicari di sini hanya kolom yang memang dapat diindeks.
     */
    public function scopeCari(Builder $query, ?string $kata): Builder
    {
        if (blank($kata)) {
            return $query;
        }

        $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], $kata).'%';

        return $query->where(function (Builder $q) use ($pola): void {
            $q->where('order_number', 'like', $pola)
                ->orWhere('tracking_number', 'like', $pola)
                ->orWhere('guest_email_bidx', 'like', $pola);
        });
    }

    // ── Turunan ─────────────────────────────────────────────────────

    public function alamat(): array
    {
        return is_array($this->shipping_address_snapshot)
            ? $this->shipping_address_snapshot
            : [];
    }

    public function namaPenerima(): string
    {
        return $this->alamat()['recipient_name'] ?? $this->user?->name ?? 'Pelanggan';
    }

    public function emailPenerima(): ?string
    {
        return $this->user?->email ?? $this->guest_email;
    }

    public function teleponPenerima(): ?string
    {
        return $this->alamat()['phone_number'] ?? $this->guest_phone ?? $this->user?->phone_number;
    }

    public function jumlahItem(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function beratTotal(): int
    {
        return (int) $this->items->sum(fn (OrderItem $item) => $item->beratBaris());
    }

    /**
     * Transisi status. Validasi transisi yang sah dan pencatatan riwayat
     * dilakukan di OrderService, bukan di sini, supaya aksi lain (pemulihan
     * stok, notifikasi) selalu berjalan bersama perubahan status.
     */
    public function bisaKe(StatusPesanan $target): bool
    {
        return $this->status->bisaKe($target);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
