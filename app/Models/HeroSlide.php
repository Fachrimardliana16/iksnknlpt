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

    /**
     * URL tombol yang AMAN dipasang pada atribut href.
     *
     * Kolom ini diisi admin, dan admin bisa salah ketik atau sebuah akun panel
     * bisa dibajak. Tanpa penyaringan, nilai seperti `javascript:alert(1)`
     * atau `file:///etc/passwd` akan masuk apa adanya ke atribut href di
     * beranda — Laravel meng-escape atributnya sehingga tidak menjadi XSS,
     * tetapi tautannya tetap menjalankan skrip atau membuka berkas lokal saat
     * pengunjung mengkliknya.
     *
     * Yang diizinkan hanya dua bentuk yang memang dipakai beranda: jalur di
     * dalam situs (diawali "/") dan URL http/https. Selain itu dikembalikan
     * null, sehingga tombolnya tidak dirender sama sekali — lebih baik tombol
     * hilang daripada mengarah ke tempat yang tidak diketahui.
     */
    public static function urlTombolAman(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Jalur di dalam situs sendiri.
        if (str_starts_with($url, '/')) {
            // "//evil.test" adalah URL protokol-relatif: peramban membacanya
            // sebagai host lain, bukan jalur. Harus ditolak.
            return str_starts_with($url, '//') ? null : $url;
        }

        $skema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($skema, ['http', 'https'], true)) {
            return null;
        }

        // Tanpa host, "http://" saja bukan tujuan yang sah.
        return filter_var($url, FILTER_VALIDATE_URL) === false ? null : $url;
    }

    /** href tombol utama, sudah disaring. Null berarti tombol tidak dirender. */
    public function hrefTombolUtama(): ?string
    {
        return self::urlTombolAman($this->primary_button_url);
    }

    /** href tombol kedua, sudah disaring. */
    public function hrefTombolKedua(): ?string
    {
        return self::urlTombolAman($this->secondary_button_url);
    }
}
