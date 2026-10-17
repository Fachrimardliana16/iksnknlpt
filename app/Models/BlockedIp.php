<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ip_hash',
        'reason',
        'blocked_until',
    ];

    protected function casts(): array
    {
        return ['blocked_until' => 'datetime'];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('blocked_until', '>', now());
    }

    public function scopeUntukIp(Builder $query, string $ipHash): Builder
    {
        return $query->where('ip_hash', $ipHash);
    }

    public function aktif(): bool
    {
        return $this->blocked_until->isFuture();
    }
}
