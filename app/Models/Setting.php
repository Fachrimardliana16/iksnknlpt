<?php

namespace App\Models;

use App\Casts\NilaiPengaturan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group_name',
        'type',
        'label',
        'options_json',
        'is_encrypted',
        'is_public',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => NilaiPengaturan::class,
            'options_json' => 'array',
            'is_encrypted' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function scopeGrup(Builder $query, string $group): Builder
    {
        return $query->where('group_name', $group);
    }

    public function scopePublik(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** Nilai sebagai boolean, untuk tipe 'boolean'. */
    public function boolean(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
    }

    public function angka(): float
    {
        return (float) $this->value;
    }

    /** Nilai tipe 'json' yang sudah didekode. */
    public function larik(): array
    {
        if (is_array($this->value)) {
            return $this->value;
        }

        $decoded = json_decode((string) $this->value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
