<?php

namespace App\Models;

use App\Enums\StatusPesanan;
use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory, PunyaUlid;

    public $timestamps = false;

    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'note',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => StatusPesanan::class,
            'to_status' => StatusPesanan::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
