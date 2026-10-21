<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanda "sudah dilihat" pada satu butir notifikasi panel.
 *
 * Hanya menyimpan kunci butir dan sidik keadaannya saat dilihat; isi
 * notifikasinya sendiri tetap dihitung dari kueri (App\Support\NotifikasiAdmin).
 */
class NotificationRead extends Model
{
    /** Tidak ada kolom created_at/updated_at; waktunya ada di read_at. */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'notification_key',
        'fingerprint',
        'kind',
        'item_count',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'item_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
