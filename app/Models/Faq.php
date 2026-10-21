<?php

namespace App\Models;

use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu butir FAQ.
 *
 * Judul dan jawaban disimpan dua bahasa. Versi Inggris boleh kosong; saat
 * kosong, yang ditampilkan adalah versi Indonesia — bukan baris kosong.
 */
class Faq extends Model
{
    use HasFactory;
    use PunyaUlid;

    protected $fillable = [
        'ulid',
        'group_key',
        'question_id',
        'question_en',
        'answer_id',
        'answer_en',
        'display_order',
        'is_active',
        'tampil_di_beranda',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'tampil_di_beranda' => 'boolean',
        ];
    }

    /** Kelompok yang dikenal. Dipakai admin dan beranda, satu sumber. */
    public const KELOMPOK = [
        'umum' => 'Umum',
        'pembayaran' => 'Pembayaran',
        'pengiriman' => 'Pengiriman',
        'produk' => 'Produk & pemasangan',
    ];

    // ULID dan nama rute publik disediakan trait PunyaUlid; tidak diulang di sini.

    /** Hanya yang aktif, urut sesuai pengaturan admin. */
    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('display_order')->orderBy('id');
    }

    public function scopeBeranda(Builder $q): Builder
    {
        return $q->aktif()->where('tampil_di_beranda', true);
    }

    public function pertanyaan(): string
    {
        return $this->pilihTeks($this->question_id, $this->question_en);
    }

    public function jawaban(): string
    {
        return $this->pilihTeks($this->answer_id, $this->answer_en);
    }

    public function labelKelompok(): string
    {
        return self::KELOMPOK[$this->group_key] ?? ucfirst($this->group_key);
    }

    /**
     * Pilih versi bahasa.
     *
     * app()->getLocale() hanya 'en' atau 'id' di situs ini, tetapi nilai lain
     * (mis. 'en_US') tidak boleh membuat halaman kosong — karena itu yang
     * dicek adalah awalan 'en', bukan kesamaan persis.
     */
    private function pilihTeks(?string $id, ?string $en): string
    {
        $inggris = str_starts_with((string) app()->getLocale(), 'en');

        if ($inggris && filled($en)) {
            return (string) $en;
        }

        return (string) $id;
    }
}
