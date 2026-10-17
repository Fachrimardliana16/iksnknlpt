<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    use HasFactory, PunyaUlid;

    protected $fillable = [
        'title_id',
        'title_en',
        'subtitle_id',
        'subtitle_en',
        'media_type',
        'media_path',
        'media_path_mobile',
        'poster_path',
        'focal_point_x',
        'focal_point_y',
        'primary_button_label_id',
        'primary_button_label_en',
        'primary_button_url',
        'secondary_button_label_id',
        'secondary_button_label_en',
        'secondary_button_url',
        'starts_at',
        'ends_at',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'focal_point_x' => 'float',
            'focal_point_y' => 'float',
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** Slide yang boleh tayang sekarang. */
    public function scopeTayang(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('display_order');
    }

    public function judul(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' ? $this->title_en : $this->title_id;
    }

    public function subjudul(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' ? $this->subtitle_en : $this->subtitle_id;
    }

    public function labelTombolUtama(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' ? $this->primary_button_label_en : $this->primary_button_label_id;
    }

    public function labelTombolKedua(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' ? $this->secondary_button_label_en : $this->secondary_button_label_id;
    }

    /** Posisi fokus sebagai nilai background-position CSS. */
    public function posisiFokus(): string
    {
        return round($this->focal_point_x * 100, 2).'% '.round($this->focal_point_y * 100, 2).'%';
    }
}
