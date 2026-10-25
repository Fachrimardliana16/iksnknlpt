<?php

namespace App\Services\Pengaturan;

use App\Models\Setting;
use App\Services\Gambar\PengecilGambar;
use App\Support\CatatanAktivitas;
use App\Support\Pengaturan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Penulis pengaturan situs (checklist F4-01..F4-08).
 *
 * Dipisahkan dari App\Support\Pengaturan yang hanya membaca. Pemisahan ini
 * penting: pembaca dipakai di setiap permintaan halaman depan dan tidak boleh
 * membawa beban validasi maupun penulisan catatan aktivitas.
 */
class PenulisPengaturan
{
    /** Tipe gambar disimpan sebagai jalur di disk "public". */
    public const TIPE_GAMBAR = ['site_logo_light', 'site_logo_dark', 'site_favicon'];

    /**
     * Simpan sekelompok nilai.
     *
     * @param  array<string, mixed>  $nilai
     * @return array{diubah: array<int, string>, dilewati: array<int, string>}
     */
    public function simpan(array $nilai, ?int $userId = null): array
    {
        $baris = $this->baris($nilai);

        if ($baris->isEmpty()) {
            return ['diubah' => [], 'dilewati' => array_keys($nilai)];
        }

        $this->validasi($baris, $nilai);

        $lama = $baris->mapWithKeys(fn (Setting $s): array => [$s->key => $s->value])->all();

        $perubahan = [];

        DB::transaction(function () use ($baris, $nilai, $lama, $userId, &$perubahan): void {
            foreach ($baris as $setting) {
                $nilaiBaru = $this->normalisasi($setting->type, $nilai[$setting->key]);

                if ($this->sama($setting->value, $nilaiBaru)) {
                    continue;
                }

                $setting->value = $nilaiBaru;
                $setting->updated_by = $userId ?? auth()->id();
                $setting->save();

                $perubahan[$setting->key] = true;
            }
        });

        if ($perubahan === []) {
            // Tidak ada yang berubah. Catatan aktivitas juga tidak dibuat —
            // log yang penuh baris "tidak ada perubahan" tidak berguna.
            return ['diubah' => [], 'dilewati' => []];
        }

        CatatanAktivitas::setting(
            $lama,
            $baris->mapWithKeys(fn (Setting $s): array => [$s->key => $s->value])->all(),
            $this->kunciRahasia($baris),
            $userId,
        );

        // Cache dibaca ulang pada permintaan berikutnya. Tanpa langkah ini,
        // halaman depan masih menampilkan nilai lama sampai cache kedaluwarsa.
        Pengaturan::segarkan();

        return ['diubah' => array_keys($perubahan), 'dilewati' => []];
    }

    /**
     * Simpan berkas gambar untuk kunci bertipe "image".
     *
     * Berkas lama dihapus setelah yang baru tersimpan supaya tidak ada momen
     * ketika logo tidak ada sama sekali.
     *
     * @return string jalur relatif pada disk public
     */
    public function simpanGambar(string $kunci, UploadedFile $berkas, PengecilGambar $pengecil, ?int $userId = null): string
    {
        $setting = Setting::query()->where('key', $kunci)->first();

        if ($setting === null) {
            throw ValidationException::withMessages([$kunci => 'Pengaturan "'.$kunci.'" tidak dikenal.']);
        }

        $folder = 'settings';
        $namaDasar = $kunci.'-'.now()->format('YmdHis');

        $hasil = $pengecil->proses(
            $berkas->getRealPath() ?: $berkas->getPathname(),
            $folder,
            $namaDasar,
            lebarMaks: $kunci === 'site_favicon' ? 512 : 1600,
        );

        $lama = $setting->value;

        $setting->value = $hasil['paths']['full'];
        $setting->updated_by = $userId ?? auth()->id();
        $setting->save();

        CatatanAktivitas::catat('setting.gambar.diubah', [$kunci => 'berkas baru'], $userId, $setting);

        if (filled($lama) && $lama !== $setting->value) {
            foreach (['thumb', 'card', 'full', 'og'] as $turunan) {
                $this->hapusTurunan((string) $lama, $turunan);
            }
        }

        Pengaturan::segarkan();

        return $hasil['paths']['full'];
    }

    /**
     * Nilai untuk ditampilkan di formulir.
     *
     * Kredensial terenkripsi TIDAK pernah dikirim balik ke antarmuka (F4-04).
     * Yang dikirim hanya penanda apakah nilainya sudah pernah diisi, sehingga
     * admin tahu kredensialnya ada tanpa bisa membacanya lagi.
     *
     * @param  array<int, string>  $kunci
     * @return array<string, mixed>
     */
    public function nilaiForm(array $kunci): array
    {
        $baris = Setting::query()->whereIn('key', $kunci)->get();

        $hasil = [];

        foreach ($baris as $setting) {
            if ($setting->is_encrypted) {
                $hasil[$setting->key] = '';
                $hasil['__terisi_'.$setting->key] = filled($setting->value);

                continue;
            }

            $hasil[$setting->key] = $setting->value;
        }

        return $hasil;
    }

    /**
     * Kunci pengaturan yang nilainya rahasia.
     *
     * @param  Collection<int, Setting>|null  $baris
     * @return array<int, string>
     */
    public function kunciRahasia(?Collection $baris = null): array
    {
        $baris ??= Setting::query()->where('is_encrypted', true)->get();

        return $baris->where('is_encrypted', true)->pluck('key')->all();
    }

    /**
     * @param  array<string, mixed>  $nilai
     * @return Collection<int, Setting>
     */
    private function baris(array $nilai): Collection
    {
        return Setting::query()->whereIn('key', array_keys($nilai))->get();
    }

    /**
     * Validasi mengikuti tipe kolom, bukan daftar aturan tetap. Kolom bertipe
     * "url" menerima https saja — "javascript:" dan "http:" ditolak (F4-03).
     *
     * @param  Collection<int, Setting>  $baris
     * @param  array<string, mixed>  $nilai
     */
    private function validasi(Collection $baris, array $nilai): void
    {
        $aturan = [];

        foreach ($baris as $setting) {
            $aturan[$setting->key] = $this->aturan($setting);
        }

        Validator::make($nilai, $aturan, [
            'url' => ':attribute harus berupa URL http atau https yang sah.',
            'regex' => ':attribute hanya boleh berisi angka.',
        ])->validate();

        // URL media sosial wajib https. Tautan http ditolak karena halaman ini
        // disajikan lewat https, dan tautan campuran diblokir peramban.
        foreach ($baris as $setting) {
            if ($setting->type !== 'url' || blank($nilai[$setting->key] ?? null)) {
                continue;
            }

            if (! str_starts_with(strtolower((string) $nilai[$setting->key]), 'https://')) {
                throw ValidationException::withMessages([
                    $setting->key => 'Alamat '.$setting->label.' harus diawali https://.',
                ]);
            }
        }
    }

    /** @return array<int, mixed> */
    private function aturan(Setting $setting): array
    {
        // Kolom kosong selalu diizinkan: mengosongkan pengaturan adalah tindakan
        // yang sah, misalnya saat menonaktifkan tautan Shopee.
        $dasar = ['nullable'];

        return match ($setting->type) {
            'email' => [...$dasar, 'email', 'max:120'],
            'url' => [...$dasar, 'url', 'max:255'],
            'number' => [...$dasar, 'regex:/^-?\d+(\.\d+)?$/'],
            // '' ikut diterima: sakelar yang dicabut di panel tiba sebagai
            // boolean false, dan Validator mengubahnya menjadi string kosong
            // sebelum aturan "in" dijalankan. Tanpa '' di daftar, setiap
            // sakelar yang dimatikan gagal tersimpan — panel menolak
            // mematikan notifikasi, dan itu tidak kelihatan sebagai galat.
            'boolean' => [...$dasar, 'in:,0,1,true,false,on,off,yes,no'],
            'select' => [...$dasar, 'in:'.implode(',', $setting->options_json ?? [])],
            'json' => [...$dasar, 'string'],
            'image' => [...$dasar, 'string', 'max:255'],
            default => [...$dasar, 'string', 'max:5000'],
        };
    }

    private function normalisasi(string $type, mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return $type === 'boolean' ? '0' : '';
        }

        return match ($type) {
            'boolean' => filter_var($nilai, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'json' => is_string($nilai) ? $nilai : (string) json_encode($nilai),
            default => is_scalar($nilai) ? (string) $nilai : (string) json_encode($nilai),
        };
    }

    private function sama(mixed $a, mixed $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        return (string) ($a ?? '') === (string) ($b ?? '');
    }

    /**
     * Turunan gambar disimpan dengan pola "<nama>-<turunan>.webp". Jalur penuh
     * dipakai sebagai dasar penghapusan.
     */
    private function hapusTurunan(string $jalur, string $turunan): void
    {
        $tanpaEkstensi = preg_replace('/-full\.webp$/', '', $jalur);

        try {
            Storage::disk('public')->delete($tanpaEkstensi.'-'.$turunan.'.webp');
        } catch (Throwable) {
            // Penghapusan berkas lama bukan alasan untuk menggagalkan simpan.
        }
    }
}
