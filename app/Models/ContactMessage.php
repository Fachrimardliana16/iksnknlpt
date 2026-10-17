<?php

namespace App\Models;

use App\Models\Concerns\PunyaIndeksButa;
use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory, PunyaIndeksButa, PunyaUlid;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'ip_hash',
        'user_agent',
        'is_read',
        'replied_at',
        'replied_by',
    ];

    /** @var array<string, string> */
    protected array $indeksButa = [
        'email' => 'email_bidx',
    ];

    protected function casts(): array
    {
        // Isi pesan adalah PII.
        return [
            'name' => 'encrypted',
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'message' => 'encrypted',
            'is_read' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pembalas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Pencarian nama dan isi pesan tidak dapat dilakukan di SQL karena kolomnya
     * terenkripsi. Yang diindeks hanya email lewat email_bidx.
     */
    public function scopeCari(Builder $query, ?string $kata): Builder
    {
        if (blank($kata)) {
            return $query;
        }

        $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], $kata).'%';

        return $query->where(function (Builder $q) use ($pola): void {
            $q->where('subject', 'like', $pola)
                ->orWhere('email_bidx', 'like', $pola);
        });
    }
}
