<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Percobaan login. Satu baris per kombinasi IP dan email.
 * IP disimpan sebagai hash SHA-256, bukan alamat mentah.
 */
class LoginAttempt extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ip_hash',
        'email_bidx',
        'attempts',
        'last_attempt_at',
        'locked_until',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function terkunci(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function sisaDetik(): int
    {
        return $this->terkunci()
            ? max(0, now()->diffInSeconds($this->locked_until, false))
            : 0;
    }
}
