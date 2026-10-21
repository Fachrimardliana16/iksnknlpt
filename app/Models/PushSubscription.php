<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu langganan web push milik satu perangkat admin.
 *
 * Baris ini hanya ALAMAT dan kuncinya. Isi notifikasi tidak disimpan di sini —
 * dikirim sekali lalu hilang, sama seperti surel.
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'peramban',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Langganan yang sudah mati harus dibuang.
     *
     * Peramban yang logout, izin dicabut, atau perangkat yang tidak dipakai
     * lagi membuat layanan push menjawab 404/410 saat pengiriman. Menyimpan
     * baris seperti itu berarti setiap notifikasi berikutnya membuang waktu ke
     * alamat yang sudah tidak ada.
     */
    public function sudahMati(): bool
    {
        return $this->last_used_at !== null && $this->last_used_at->lt(now()->subMonths(3));
    }
}
