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
                return self::urlMedia($jalur);
            }
        }

        return null;
    }

    /**
     * URL media untuk ditampilkan di halaman.
     *
     * Disk "public" menyusun URL dari APP_URL. Pada mesin ini APP_URL masih
     * menunjuk port bawaan 8000, sementara aplikasi dilayani di 8090 dan lewat
     * terowongan. Akibatnya setiap gambar dirender dari asal yang berbeda dari
     * halamannya, lalu diblokir CSP "img-src 'self'" — gambar tidak muncul sama
     * sekali meski berkasnya ada dan permintaannya tidak pernah sampai server.
     *
     * Yang dikembalikan adalah jalur relatif, bukan URL absolut. Peramban
     * menyelesaikannya terhadap asal halaman, sehingga bekerja pada port lokal,
     * di balik terowongan, maupun di domain produksi tanpa perubahan konfigurasi.
     */
    public static function urlMedia(?string $jalur): ?string
    {
        if (blank($jalur)) {
            return null;
        }

        // Storage::disk('public')->url() menyusun URL absolut dari APP_URL, jadi
        // yang diambil hanya bagian jalurnya.
        $path = parse_url(Storage::disk('public')->url($jalur), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : null;
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
