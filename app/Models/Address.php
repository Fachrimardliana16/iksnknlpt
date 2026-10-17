<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone_number',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'is_primary',
    ];

    protected function casts(): array
    {
        // Alamat adalah PII. Terenkripsi di tingkat aplikasi.
        return [
            'recipient_name' => 'encrypted',
            'phone_number' => 'encrypted',
            'address_line_1' => 'encrypted',
            'address_line_2' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Snapshot teks untuk disimpan ke pesanan. */
    public function keSnapshot(): array
    {
        return [
            'label' => $this->label,
            'recipient_name' => $this->recipient_name,
            'phone_number' => $this->phone_number,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'state_province' => $this->state_province,
            'postal_code' => $this->postal_code,
        ];
    }
}
