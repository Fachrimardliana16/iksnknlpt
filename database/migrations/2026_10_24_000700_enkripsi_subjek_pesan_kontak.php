<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Enkripsi subjek pesan kontak.
 *
 * `subject` adalah satu-satunya kolom pesan kontak yang masih tersimpan sebagai
 * teks terbuka, sementara `name`, `email`, `phone`, dan `message` sudah
 * ber-cast encrypted. Isinya ditulis pengunjung bebas: "Pesan untuk Pak Budi
 * soal knalpot Vario", "Keluhan pesanan IK-2026xxxx", atau nama orang lain.
 * Itu PII, dan basis data yang bocor berarti isinya terbaca begitu saja.
 *
 * Migrasi ini mengubah kolom menjadi TEXT, karena ciphertext Laravel selalu
 * lebih panjang dari teks aslinya dan tidak muat di VARCHAR(255), lalu
 * mengenkripsi baris yang sudah ada.
 *
 * Kenapa enkripsi baris lama dilakukan DI SINI dan tidak dibiarkan apa adanya:
 * model membangun teks yang ditampilkan di panel dari nilai yang dibaca, dan
 * ContactMessage::getSubjectAttribute() memang membaca nilai lama apa adanya —
 * tapi berarti halaman Pesan Kontak tetap menampilkan isi PII dari basis data
 * yang sudah bocor sampai baris itu ditulis ulang. Delapan baris yang ada
 * sekarang adalah buktinya: semuanya masih teks terbuka.
 *
 * Kenapa bukan menggantung pada encryptString() di dalam migrasi:
 * kunci aplikasi yang sedang berjalan berbeda-beda antar lingkungan, dan migrasi
 * yang menulis ciphertext dengan kunci produksi tidak dapat didekripsi di
 * lingkungan lain. Karena itu hanya baris yang BELUM terenkripsi yang disentuh;
 * ciphertext yang sudah ada dibiarkan (percobaan decrypt memastikannya).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hanya perubahan skema yang dijaga "sudah pernah". Bagian enkripsi
        // data di bawah TIDAK dijaga jenis kolom: pada basis data yang kolomnya
        // sudah TEXT tetapi barisnya masih teks terbuka, penjaga itu membuat
        // seluruh loop dilewati dan baris lama tidak pernah terenkripsi — itu
        // yang terjadi di sini (8 baris, 0 terenkripsi). Loop data sendiri sudah
        // aman diulang: baris yang sudah terenkripsi terdeteksi dan dilewati.
        if (Schema::getColumnType('contact_messages', 'subject') !== 'text') {
            Schema::table('contact_messages', function (Blueprint $table): void {
                $table->text('subject')->nullable()->change();
            });
        }

        // Enkripsi baris yang sudah ada. Hanya yang belum terenkripsi.
        //
        // Pemeriksaannya adalah percobaan decrypt, bukan panjang teks: baris
        // yang terenkripsi dimulai "eyJpdiI6" tetapi menebak dari awalan berarti
        // baris lama yang kebetulan mirip ikut terlewati. Nilai yang tidak dapat
        // didekripsi adalah teks terbuka, dan itu yang dienkripsi di sini.
        DB::table('contact_messages')
            ->select(['id', 'subject'])
            ->orderBy('id')
            ->each(function (object $baris): void {
                $nilai = (string) $baris->subject;

                if ($nilai === '') {
                    return;
                }

                try {
                    Crypt::decryptString($nilai);

                    return;
                } catch (DecryptException) {
                    // Teks terbuka — jatuh ke penulisan di bawah.
                }

                // Mass assignment dimatikan: kolomnya ter-cast encrypted, dan
                // yang ditulis harus ciphertext, bukan teks aslinya.
                DB::table('contact_messages')
                    ->where('id', $baris->id)
                    ->update(['subject' => Crypt::encryptString($nilai)]);
            });
    }

    public function down(): void
    {
        // Kembali ke string(255) berarti memotong baris yang sudah terenkripsi.
        // Potongan ciphertext tidak dapat didekripsi, jadi jalur ini sengaja
        // TIDAK dipasang: migrasi balik yang merusak data lebih buruk daripada
        // tidak ada migrasi balik.
        //
        // Bila benar-benar perlu kembali, lakukan lewat dump basis data, bukan
        // lewat down().
    }
};
