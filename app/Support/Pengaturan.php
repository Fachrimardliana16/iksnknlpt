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
     * Seluruh nilai pengaturan, dimuat satu kali lalu diingat.
     *
     * Yang dimuat adalah SELURUH baris yang tidak terenkripsi, bukan hanya yang
     * ditandai is_public.
     *
     * Sebelumnya hanya baris is_public yang dimuat. Akibatnya nilai yang dipakai
     * di dalam perhitungan pesanan tetapi tidak pernah dirender — tarif ongkir,
     * persen pajak, mode pembayaran, ambang stok, prefiks nomor pesanan —
     * selalu jatuh ke nilai bawaan nol. Ongkos kirim tidak pernah terhitung,
     * pajak tidak pernah terhitung, dan pilihan metode pembayaran di halaman
     * checkout selalu menunjuk transfer manual walaupun toko disetel ke PayPal.
     * Keenamnya ditemukan oleh test F7, bukan oleh mata, karena nilainya nol dan
     * nol tidak terlihat salah di halaman.
     *
     * Yang benar-benar harus dijaga dari sisi depan adalah kredensial: kunci
     * PayPal, rekening transfer, dan apa pun yang ditandai is_encrypted. Baris
     * terenkripsi tetap tidak pernah masuk ke sini, dan cara itu juga lebih
     * kokoh: yang dibocorkan adalah rahasia, bukan bilangan.
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
                    ->where('is_encrypted', false)
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

    /**
     * Nilai khusus sisi depan.
     *
     * @return array<string, string|null>
     */
    public function publik(): array
    {
        $kunci = Setting::query()->publik()->pluck('key')->all();

        return array_intersect_key($this->semua(), array_flip($kunci));
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
     * Daftar teks yang disimpan sebagai JSON pada satu baris pengaturan.
     *
     * Dipakai untuk nilai yang panjangnya berubah (daftar kurir): disimpan di
     * pengaturan supaya dapat diubah admin tanpa menyentuh kode, sementara
     * bentuknya tetap sederhana dan tidak perlu tabel sendiri.
     *
     * @param  array<int, string>  $bawaan
     * @return array<int, string>
     */
    public function larikTeks(string $kunci, array $bawaan = []): array
    {
        $nilai = $this->ambil($kunci);

        if (! is_string($nilai) || trim($nilai) === '') {
            return $bawaan;
        }

        $larik = json_decode($nilai, true);

        if (! is_array($larik)) {
            // Nilai yang disimpan sebagai daftar berpemisah baris tetap diterima,
            // supaya kolom teks sederhana pun dapat dipakai admin.
            $larik = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $nilai) ?: []));
        }

        $bersih = array_values(array_filter(
            array_map(fn ($satu) => trim((string) $satu), $larik),
            fn (string $satu) => $satu !== '',
        ));

        return $bersih === [] ? $bawaan : $bersih;
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
