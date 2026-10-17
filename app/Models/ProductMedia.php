<?php

namespace App\Models;

use App\Enums\TipeMedia;
use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductMedia extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'product_id',
        'type',
        'original_filename',
        'path_original',
        'path_thumb',
        'path_card',
        'path_full',
        'path_og',
        'width',
        'height',
        'byte_size',
        'mime_detected',
        'content_hash',
        'alt_text_id',
        'alt_text_en',
        'is_primary',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => TipeMedia::class,
            'width' => 'integer',
            'height' => 'integer',
            'byte_size' => 'integer',
            'is_primary' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeGambar(Builder $query): Builder
    {
        return $query->where('type', TipeMedia::Gambar);
    }

    public function scopeTerurut(Builder $query): Builder
    {
        return $query->orderByDesc('is_primary')->orderBy('display_order');
    }

    /**
     * URL varian. Selalu mengembalikan jalur varian yang diminta bila ada,
     * lalu jatuh ke varian lain yang tersedia, sehingga tidak pernah
     * mengembalikan jalur kosong saat berkas asli masih ada.
     */
    public function url(string $varian = 'card'): ?string
    {
        foreach ($this->urutanFallback($varian) as $kandidat) {
            $jalur = $this->{'path_'.$kandidat} ?? null;

            if (filled($jalur)) {
                return Storage::disk('public')->url($jalur);
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function urutanFallback(string $varian): array
    {
        return match ($varian) {
            'thumb' => ['thumb', 'card', 'full', 'original'],
            'card' => ['card', 'thumb', 'full', 'original'],
            'full' => ['full', 'card', 'original'],
            'og' => ['og', 'full', 'card', 'original'],
            'original' => ['original', 'full', 'card'],
            default => ['card', 'full', 'original'],
        };
    }

    public function tinggiRasio(): float
    {
        if (! $this->width || ! $this->height) {
            return 1.3333;
        }

        return round($this->height / max(1, $this->width), 4);
    }

    public function alt(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $teks = $locale === 'en' ? $this->alt_text_en : $this->alt_text_id;

        return filled($teks) ? $teks : ($this->product?->judul($locale) ?? '');
    }
}
