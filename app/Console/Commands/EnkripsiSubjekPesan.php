<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Enkripsi subjek pesan kontak yang masih tersimpan sebagai teks terbuka.
 *
 * Dipakai untuk baris yang ditulis sebelum migrasi 2026_10_24_000700 — dan
 * sebagai pengaman untuk lingkungan tempat migrasi sudah terlanjur lewat
 * sebelum bagian enkripsinya ada. Perintah ini AMAN DIULANG: baris yang sudah
 * terenkripsi terdeteksi dan dilewati.
 *
 * Kenapa tidak memakai model untuk menulis: kolom `subject` ber-cast encrypted,
 * sehingga `$pesan->update(['subject' => ...])` akan MENENKRIPSI nilai yang
 * diberikan. Yang ditulis di sini adalah ciphertext yang sudah jadi, jadi
 * penulisannya melewati model dan kolom cast.
 */
class EnkripsiSubjekPesan extends Command
{
    protected $signature = 'iksan:enkripsi-subjek-pesan
                            {--uji : Tampilkan yang akan diubah tanpa menulis}';

    protected $description = 'Enkripsi subjek pesan kontak yang masih teks terbuka';

    public function handle(): int
    {
        $uji = (bool) $this->option('uji');

        $diubah = 0;
        $dilewati = 0;
        $gagal = 0;

        DB::table('contact_messages')
            ->select(['id', 'subject'])
            ->orderBy('id')
            ->each(function (object $baris) use ($uji, &$diubah, &$dilewati, &$gagal): void {
                $nilai = (string) $baris->subject;

                // Baris tanpa subjek tidak punya apa pun untuk dienkripsi, dan
                // menulisnya kembali hanya memperbarui updated_at tanpa alasan.
                if ($nilai === '') {
                    $dilewati++;

                    return;
                }

                try {
                    Crypt::decryptString($nilai);

                    // Sudah terenkripsi.
                    $dilewati++;

                    return;
                } catch (DecryptException) {
                    // Teks terbuka — dienkripsi di bawah.
                }

                if ($uji) {
                    $diubah++;
                    $this->line('  akan dienkripsi: id '.$baris->id.' — '.$nilai);

                    return;
                }

                try {
                    DB::table('contact_messages')
                        ->where('id', $baris->id)
                        ->update(['subject' => Crypt::encryptString($nilai)]);

                    $diubah++;
                } catch (\Throwable $e) {
                    // Satu baris gagal tidak boleh menghentikan sisanya: sisanya
                    // tetap PII yang perlu diamankan.
                    $gagal++;
                    $this->warn('  gagal mengenkripsi id '.$baris->id.': '.$e->getMessage());
                }
            });

        $this->info(sprintf(
            '%s: %d baris %s, %d dilewati, %d gagal.',
            $uji ? 'Uji' : 'Selesai',
            $diubah,
            $uji ? 'akan diubah' : 'dienkripsi',
            $dilewati,
            $gagal,
        ));

        if ($gagal > 0) {
            return self::FAILURE;
        }

        // Pembacaan lewat model, bukan lewat basis data. Nilai yang terenkripsi
        // tidak dapat dibaca sebagai teks, jadi yang membuktikan keberhasilan
        // adalah model yang tetap mengembalikan subjek yang sama.
        if (! $uji) {
            $contoh = ContactMessage::query()->whereNotNull('subject')->first();

            if ($contoh !== null) {
                $this->line('  baca lewat model: '.$contoh->subject);
            }
        }

        return self::SUCCESS;
    }
}
