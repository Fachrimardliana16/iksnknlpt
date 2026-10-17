<?php

namespace App\Models;

use App\Enums\TipeKejadianKeamanan;
use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityLog extends Model
{
    use HasFactory, PunyaUlid;

    public $timestamps = false;

    protected $fillable = [
        'ip_hash',
        'event_type',
        'user_id',
        'email_attempted',
        'route',
        'user_agent',
        'payload_summary',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => TipeKejadianKeamanan::class,
            'email_attempted' => 'encrypted',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeTipe(Builder $query, TipeKejadianKeamanan|string $tipe): Builder
    {
        return $query->where('event_type', $tipe instanceof TipeKejadianKeamanan ? $tipe->value : $tipe);
    }

    public function scopeUntukIp(Builder $query, string $ipHash): Builder
    {
        return $query->where('ip_hash', $ipHash);
    }

    public function scopeTerbaru(Builder $query, int $limit = 100): Builder
    {
        return $query->latest('created_at')->limit($limit);
    }
}
