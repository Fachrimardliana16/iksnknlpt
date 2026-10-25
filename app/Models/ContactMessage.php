<?php

namespace App\Models;

use App\Models\Concerns\PunyaIndeksButa;
use App\Models\Concerns\PunyaUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory, PunyaIndeksButa, PunyaUlid;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'ip_hash',
        'user_agent',
        'is_read',
        'replied_at',
        'replied_by',
    ];

    /** @var array<string, string> */
    protected array $indeksButa = [
        'email' => 'email_bidx',
    ];

    protected function casts(): array
    {
        // Isi pesan adalah PII.
        return [
            'name' => 'encrypted',
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'message' => 'encrypted',

            // Subjek ikut terenkripsi (checklist Tahap 5). Isinya ditulis
            // pengunjung bebas dan sering memuat nama orang atau nomor
            // pesanan — kolom terakhir yang masih teks terbuka.
            'subject' => 'encrypted',

            'is_read' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pembalas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Pencarian pesan.
     *
     * Semua kolom isi (nama, email, telepon, subjek, pesan) terenkripsi, jadi
     * yang dapat disaring di basis data hanyalah sidik jari email. Pencarian
     * subjek dan isi dilakukan setelah data diambil — lihat cocokPencarian().
     */
    public function scopeCari(Builder $query, ?string $kata): Builder
    {
        if (blank($kata)) {
            return $query;
        }

        $kata = trim($kata);

        // Sidik jari email hanya boleh dipakai bila kata pencarian BENAR-BENAR
        // alamat surel. Blind index adalah HMAC: satu karakter berbeda
        // menghasilkan hash yang sama sekali tak berkaitan, jadi memaksanya
        // pada "stok NMAX" menghasilkan pencarian yang tidak pernah cocok —
        // halaman tampil "Tidak ada pesan yang cocok" padahal pesannya ada.
        //
        // Karena itu kata yang bukan surel TIDAK mempersempit apa pun di SQL:
        // seluruh baris ikut terbaca lalu disaring di PHP lewat
        // cocokPencarian() (lihat PONI di render()).
        if (! filter_var($kata, FILTER_VALIDATE_EMAIL)) {
            return $query;
        }

        // Sidik jari email dicari PERSIS, bukan dengan LIKE: LIKE pada HMAC
        // tidak pernah cocok karena satu karakter berbeda menghasilkan hash
        // yang sama sekali tak berkaitan.
        return $query->where('email_bidx', \App\Support\BlindIndex::buat($kata, 'email_bidx'));
    }

    /**
     * Apakah baris ini cocok dengan kata pencarian, di luar SQL.
     *
     * Dipakai untuk subjek dan isi pesan: keduanya terenkripsi, sehingga
     * pencarian teks tidak dapat dilakukan di basis data. Hanya satu baris yang
     * diperiksa per pemanggilan, jadi biayanya sebanding dengan jumlah baris
     * yang sudah tampil di halaman itu.
     *
     * Email ikut diperiksa di sini meskipun basis data sudah mencarinya lewat
     * sidik jari: baris yang cocok di SQL akan DIBUANG di sini kalau surel tidak
     * ikut diperiksa, dan pencarian surel berhasil menemukan nol pesan.
     */
    public function cocokPencarian(string $kata): bool
    {
        $kata = mb_strtolower(trim($kata));

        if ($kata === '') {
            return true;
        }

        return str_contains(mb_strtolower((string) $this->subject), $kata)
            || str_contains(mb_strtolower((string) $this->message), $kata)
            || str_contains(mb_strtolower((string) $this->name), $kata)
            || str_contains(mb_strtolower((string) $this->email), $kata);
    }

    /**
     * User agent dipangkas sebelum disimpan.
     *
     * Header ini panjangnya sewenang-wenang — sebagian peramban dan ekstensi
     * mengirim ratusan karakter. Kolomnya VARCHAR(255), dan tanpa pemangkasan
     * di sini nilai panjang akan dipotong basis data atau ditolak, tergantung
     * versi MySQL. Yang dipotong di aplikasi selalu dapat diperkirakan.
     */
    public function setUserAgentAttribute(?string $value): void
    {
        $nilai = $value === null ? null : mb_substr($value, 0, 255);

        $this->attributes['user_agent'] = ($nilai === null || trim($nilai) === '') ? null : $nilai;
    }

    /**
     * Baca subjek, termasuk baris yang ditulis sebelum kolom ini terenkripsi.
     *
     * Migrasi 2026_10_24_000700 mengubah `subject` menjadi TEXT dan model
     * mulai menyimpannya terenkripsi, tetapi baris lama tetap berupa teks
     * terbuka. Membaca kolom itu langsung memicu DecryptException dan membuat
     * halaman pesan kontak gagal total — satu baris usang cukup untuk
     * mematikan seluruh panel.
     *
     * Karena itu pembacaan dibungkus: nilai yang tidak dapat didekripsi
     * dikembalikan apa adanya. Isinya bukan rahasia — justru sudah tersimpan
     * tanpa enkripsi, dan tidak menampilkannya berarti menyembunyikan data
     * milik pengelola toko sendiri.
     */
    public function getSubjectAttribute(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            $hasil = \Illuminate\Support\Facades\Crypt::decryptString((string) $value);

            return is_string($hasil) ? $hasil : (string) $value;
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return (string) $value;
        }
    }
}
