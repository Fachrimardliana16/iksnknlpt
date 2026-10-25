<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migrasi data subjek pesan kontak (checklist Tahap 5, butir 5.2).
 *
 * Yang diuji: baris teks terbuka benar-benar berubah menjadi ciphertext di
 * basis data, perintahnya aman diulang, dan baris yang sudah terenkripsi tidak
 * disentuh.
 *
 * Kenapa ini bukan sekadar uji migrasi: migrasi 2026_10_24_000700 sudah pernah
 * lewat di basis data produksi SEBELUM bagian enkripsinya ditulis. Penjaga
 * `getColumnType(...) === 'text'` membuat seluruh loop dilewati, dan delapan
 * baris subjek tetap teks terbuka. Karena itu bagian yang dibuktikan di sini
 * adalah perintah `iksan:enkripsi-subjek-pesan`, yang dapat dijalankan ulang
 * pada basis data yang sudah berisi.
 */
class EnkripsiSubjekPesanTest extends TestCase
{
    use RefreshDatabase;

    /** Baris teks terbuka, seperti keadaan sebelum migrasi data jalan. */
    private function teksTerbuka(string $subject): int
    {
        return DB::table('contact_messages')->insertGetId([
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'terenkripsi-lama',
            'email' => 'terenkripsi-lama',
            'phone' => null,
            'subject' => $subject,
            'message' => 'terenkripsi-lama',
            'ip_hash' => null,
            'user_agent' => 'Seeder/1.0',
            'is_read' => false,
            'replied_at' => null,
            'replied_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mentah(int $id): ?string
    {
        $nilai = DB::table('contact_messages')->where('id', $id)->value('subject');

        return $nilai === null ? null : (string) $nilai;
    }

    private function terenkripsi(int $id): bool
    {
        try {
            Crypt::decryptString($this->mentah($id) ?? '');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function test_subjek_teks_terbuka_menjadi_ciphertext_di_basis_data(): void
    {
        $id = $this->teksTerbuka('Tanya stok NMAX');

        $this->assertSame('Tanya stok NMAX', $this->mentah($id));

        Artisan::call('iksan:enkripsi-subjek-pesan');

        // Yang dibuktikan adalah isi basis data, bukan keluaran perintah.
        $this->assertNotSame('Tanya stok NMAX', $this->mentah($id));
        $this->assertTrue($this->terenkripsi($id));
    }

    public function test_model_tetap_membaca_subjek_yang_sudah_dienkripsi(): void
    {
        $id = $this->teksTerbuka('Keluhan pengiriman');

        Artisan::call('iksan:enkripsi-subjek-pesan');

        $this->assertSame('Keluhan pengiriman', ContactMessage::query()->findOrFail($id)->subject);
    }

    public function test_perintah_aman_diulang(): void
    {
        $id = $this->teksTerbuka('Garansi header');

        Artisan::call('iksan:enkripsi-subjek-pesan');
        $cipherPertama = $this->mentah($id);

        // Percobaan kedua tidak boleh mengenkripsi ulang: ciphertext yang
        // dienkripsi dua kali menghasilkan teks berlapis saat dibaca model.
        Artisan::call('iksan:enkripsi-subjek-pesan');

        $this->assertSame($cipherPertama, $this->mentah($id));
        $this->assertSame('Garansi header', ContactMessage::query()->findOrFail($id)->subject);
    }

    public function test_subjek_kosong_tidak_ditulis_ulang(): void
    {
        $id = DB::table('contact_messages')->insertGetId([
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'terenkripsi-lama',
            'email' => 'terenkripsi-lama',
            'phone' => null,
            'subject' => null,
            'message' => 'terenkripsi-lama',
            'ip_hash' => null,
            'user_agent' => 'Seeder/1.0',
            'is_read' => false,
            'replied_at' => null,
            'replied_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('iksan:enkripsi-subjek-pesan');

        $this->assertNull($this->mentah($id));
    }

    public function test_mode_uji_tidak_menulis_apa_pun(): void
    {
        $id = $this->teksTerbuka('Custom diameter');

        Artisan::call('iksan:enkripsi-subjek-pesan', ['--uji' => true]);

        $this->assertSame('Custom diameter', $this->mentah($id));
    }

    public function test_baris_yang_sudah_ditulis_model_tidak_disentuh(): void
    {
        // Baris baru ditulis lewat model, jadi cast encrypted sudah membuat
        // ciphertext-nya. Perintah tidak boleh mengubahnya.
        $baris = ContactMessage::query()->create([
            'name' => 'Pembeli Baru',
            'email' => 'baru@example.test',
            'subject' => 'Subjek baru',
            'message' => 'Isi pesan.',
            'is_read' => false,
        ]);

        $cipher = DB::table('contact_messages')->where('id', $baris->id)->value('subject');

        Artisan::call('iksan:enkripsi-subjek-pesan');

        $this->assertSame($cipher, DB::table('contact_messages')->where('id', $baris->id)->value('subject'));
        $this->assertSame('Subjek baru', $baris->fresh()->subject);
    }

    public function test_tidak_ada_subjek_teks_terbuka_tersisa(): void
    {
        // Yang menjadi ukuran "selesai": seluruh baris subjek terenkripsi.
        $this->teksTerbuka('Satu');
        $this->teksTerbuka('Dua');

        Artisan::call('iksan:enkripsi-subjek-pesan');

        $sisa = DB::table('contact_messages')
            ->select(['id', 'subject'])
            ->get()
            ->filter(function (object $baris): bool {
                $nilai = (string) $baris->subject;

                if ($nilai === '') {
                    return false;
                }

                try {
                    Crypt::decryptString($nilai);

                    return false;
                } catch (\Throwable) {
                    return true;
                }
            })
            ->count();

        $this->assertSame(0, $sisa);
    }
}
