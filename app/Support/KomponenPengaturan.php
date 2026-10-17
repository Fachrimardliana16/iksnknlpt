<?php

namespace App\Support;

use App\Models\Setting;
use App\Support\Pengaturan as Pembaca;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Jembatan antara baris `settings` dan komponen pengaturan.
 *
 * Tiga hal yang ditangani di sini:
 *
 *  1. Tipe "boolean" disimpan sebagai teks "0"/"1". Livewire butuh bool
 *     sungguhan pada properti wire:model, jadi konversinya dilakukan sekali
 *     di sini, bukan di setiap sambungan view.
 *
 *  2. Pengaturan bertipe "select" divalidasi terhadap options_json, sehingga
 *     nilai di luar daftar tidak pernah masuk basis data.
 *
 *  3. Kredensial terenkripsi tidak pernah dikembalikan apa adanya. Yang
 *     dikembalikan hanya penanda apakah nilainya sudah pernah diisi.
 */
class KomponenPengaturan
{
    public const TIPE_BOOLEAN = 'boolean';

    /** Kunci yang nilainya tidak boleh dikirim balik ke antarmuka. */
    public const KUNCI_RAHASIA = [
        'paypal_client_id',
        'paypal_client_secret',
        'manual_bank_accounts',
    ];

    /**
     * Nilai formulir satu grup pengaturan.
     *
     * @return array<string, mixed>
     */
    public static function formulir(string $grup): array
    {
        $hasil = [];

        foreach (self::baris($grup) as $setting) {
            if ($setting->is_encrypted) {
                // Properti tetap ada dan bernama sama supaya formulir tidak
                // perlu bercabang, tetapi isinya selalu kosong.
                $hasil[$setting->key] = '';
                $hasil[self::kunciTerisi($setting->key)] = filled($setting->value);

                continue;
            }

            $hasil[$setting->key] = $setting->type === self::TIPE_BOOLEAN
                ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN)
                : ($setting->value ?? '');
        }

        return $hasil;
    }

    public static function kunciTerisi(string $kunci): string
    {
        return '__terisi_'.$kunci;
    }

    /**
     * Aturan validasi untuk satu grup, dibangun dari metadata kolom sehingga
     * menambah pengaturan baru tidak menuntut perubahan di komponen.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function aturan(string $grup): array
    {
        $aturan = [];

        foreach (self::baris($grup) as $setting) {
            // Rahasia tidak ikut divalidasi: kolomnya selalu kosong di formulir.
            if ($setting->is_encrypted) {
                continue;
            }

            $dasar = ['nullable'];

            $aturan[$setting->key] = match ($setting->type) {
                'email' => [...$dasar, 'email', 'max:120'],
                'url' => [...$dasar, 'url', 'max:255', 'regex:/^https:\/\//i'],
                'number' => [...$dasar, 'numeric'],
                self::TIPE_BOOLEAN => [...$dasar, 'boolean'],
                'select' => [...$dasar, 'in:'.implode(',', $setting->options_json ?? [])],
                'textarea' => [...$dasar, 'string', 'max:5000'],
                default => [...$dasar, 'string', 'max:255'],
            };
        }

        // Kolom rahasia tetap divalidasi panjangnya bila diisi, tanpa pernah
        // dibandingkan dengan nilai tersimpan.
        foreach (self::baris($grup)->where('is_encrypted', true) as $setting) {
            $aturan[$setting->key] = ['nullable', 'string', 'max:255', 'bail'];
        }

        return $aturan;
    }

    /** Label ramah untuk pesan galat, memakai kolom label. */
    public static function label(string $grup): array
    {
        return self::baris($grup)->pluck('label', 'key')->all();
    }

    /**
     * Nilai yang siap ditulis, dengan rahasia kosong dibuang agar tidak
     * menimpa kredensial yang sudah tersimpan.
     *
     * @param  array<string, mixed>  $nilai
     * @return array<string, mixed>
     */
    public static function saringKosong(array $nilai): array
    {
        $hasil = [];

        foreach ($nilai as $kunci => $isi) {
            if (str_starts_with($kunci, '__')) {
                continue;
            }

            if (in_array($kunci, self::KUNCI_RAHASIA, true) && blank($isi)) {
                // Dibiarkan kosong berarti "jangan ubah", bukan "kosongkan".
                // Mengosongkan kredensial dilakukan lewat tombol hapus khusus.
                continue;
            }

            $hasil[$kunci] = $isi;
        }

        return $hasil;
    }

    /** Apakah grup ini punya setidaknya satu kolom rahasia. */
    public static function punyaRahasia(string $grup): bool
    {
        return self::baris($grup)->where('is_encrypted', true)->isNotEmpty();
    }

    /** Hapus satu kredensial tersimpan. */
    public static function hapus(string $kunci, ?int $userId = null): void
    {
        $setting = Setting::query()->where('key', $kunci)->first();

        if ($setting === null || ! $setting->is_encrypted) {
            return;
        }

        $setting->value = null;
        $setting->updated_by = $userId ?? auth()->id();
        $setting->save();

        CatatanAktivitas::catat('setting.rahasia.dihapus', [$setting->key => CatatanAktivitas::PENANDA_RAHASIA], $userId, $setting);

        Pembaca::segarkan();
    }

    /**
     * Nilai asli satu kunci, termasuk yang terenkripsi.
     *
     * Dipakai hanya di sisi server — misalnya saat memanggil PayPal. Tidak
     * pernah dipanggil dari render view.
     */
    public static function mentah(string $kunci): ?string
    {
        try {
            $setting = Setting::query()->where('key', $kunci)->first();
        } catch (Throwable) {
            return null;
        }

        if ($setting === null) {
            return null;
        }

        $nilai = $setting->value;

        return filled($nilai) ? (string) $nilai : null;
    }

    /** @return Collection<int, Setting> */
    private static function baris(string $grup): Collection
    {
        return Setting::query()
            ->where('group_name', $grup)
            ->orderBy('id')
            ->get();
    }
}
