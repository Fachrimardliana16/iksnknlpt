<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Pembaca pengaturan situs.
 *
 * Nilai di-cache permanen dan diinvalidasi saat pengaturan disimpan, sehingga
 * navbar dan footer tidak menambah kueri pada setiap permintaan. Seluruh
 * pengaturan publik dimuat dengan satu kueri, bukan satu kueri per kunci.
 */
class Pengaturan
{
    public const KUNCI_CACHE = 'iksan.settings';

    /** @var array<string, mixed>|null */
    private static ?array $memori = null;

    public function __construct(private readonly CacheRepository $cache) {}

    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * Hanya nilai is_public yang dimuat, karena hanya itu yang dipakai sisi
     * depan. Kredensial pembayaran tidak pernah masuk cache ini.
     *
     * @return array<string, mixed>
     */
    public function semua(): array
    {
        if (self::$memori !== null) {
            return self::$memori;
        }

        try {
            self::$memori = $this->cache->rememberForever(self::KUNCI_CACHE, function (): array {
                return Setting::query()
                    ->publik()
                    ->pluck('value', 'key')
                    ->all();
            });
        } catch (Throwable) {
            // Saat migrasi belum berjalan, tabel belum ada. Halaman tidak boleh
            // gagal total hanya karena pengaturan belum tersedia.
            self::$memori = [];
        }

        return self::$memori;
    }

    public function ambil(string $kunci, mixed $bawaan = null): mixed
    {
        $nilai = $this->semua()[$kunci] ?? null;

        return filled($nilai) ? $nilai : $bawaan;
    }

    public function teks(string $kunci, string $bawaan = ''): string
    {
        return (string) $this->ambil($kunci, $bawaan);
    }

    public function boolean(string $kunci, bool $bawaan = false): bool
    {
        $nilai = $this->ambil($kunci);

        return $nilai === null ? $bawaan : filter_var($nilai, FILTER_VALIDATE_BOOLEAN);
    }

    public function angka(string $kunci, float $bawaan = 0.0): float
    {
        $nilai = $this->ambil($kunci);

        return $nilai === null || $nilai === '' ? $bawaan : (float) $nilai;
    }

    /**
     * Daftar media sosial yang aktif.
     *
     * @return Collection<int, array{platform: string, url: string}>
     */
    public function mediaSosial(): Collection
    {
        $platforms = ['instagram', 'facebook', 'tiktok', 'youtube', 'shopee', 'tokopedia'];

        return collect($platforms)
            ->map(fn (string $p): array => [
                'platform' => $p,
                'url' => $this->teks('social_'.$p),
                'tampil' => $this->boolean('social_'.$p.'_visible'),
            ])
            ->filter(fn (array $item): bool => $item['tampil'] && filled($item['url']))
            ->values();
    }

    /** Bersihkan cache. Dipanggil setiap kali pengaturan disimpan. */
    public static function lupakan(): void
    {
        self::$memori = null;

        try {
            app(self::class)->cache->forget(self::KUNCI_CACHE);
        } catch (Throwable) {
            // Diabaikan dengan sengaja.
        }
    }

    /** Muat ulang paksa dari basis data. Dipakai oleh seeder dan test. */
    public static function segarkan(): void
    {
        self::lupakan();
        self::instance()->semua();
    }
}
