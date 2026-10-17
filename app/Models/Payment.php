<?php

namespace App\Models;

use App\Enums\MetodePembayaran;
use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'order_id',
        'provider',
        'provider_transaction_id',
        'provider_status',
        'amount',
        'currency',
        'proof_path',
        'payload_json',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => MetodePembayaran::class,
            'amount' => 'decimal:2',
            'payload_json' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function terverifikasi(): bool
    {
        return $this->verified_at !== null;
    }
}
