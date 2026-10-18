<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaUploadLog extends Model
{
    use HasFactory, PunyaUlid;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'product_id',
        'original_filename',
        'byte_size',
        'mime_declared',
        'mime_detected',
        'content_hash',
        'status',
        'rejection_reason',
        'product_media_id',
        'processing_ms',
    ];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'processing_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ProductMedia, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(ProductMedia::class, 'product_media_id');
    }

    public function ditolak(): bool
    {
        return in_array($this->status, ['rejected', 'failed'], true);
    }
}
