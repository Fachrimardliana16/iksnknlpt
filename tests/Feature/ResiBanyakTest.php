<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ResiBanyak;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cetak resi banyak (F8: satu berkas, banyak label).
 *
 * Bug yang mendorong tes ini: memilih tiga pesanan menghasilkan berkas dengan
 * SEMBILAN halaman. Sebabnya bukan break ganda, melainkan view label yang tidak
 * membawa gaya sama sekali — kelas .rapat, .tebal, .baris, .kotak-resi tidak
 * punya definisi, dompdf memakai ukuran huruf dan margin bawaannya, dan isi
 * satu label meluap menjadi tiga halaman.
 *
 * Yang dijaga di sini adalah sifat yang terlihat admin: BERAPA HALAMAN berkas
 * itu, dan NOMOR MANA yang benar-benar tercetak. Bukan hanya "200 OK".
 */
class ResiBanyakTest extends TestCase
{
    use RefreshDatabase;

    private const KERTAS = '100mm 150mm';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'admin@contoh.test',
        ]);
    }

    private User $admin;

    /** @param array<string, mixed> $atribut */
    private function pesanan(array $atribut = []): Order
    {
        static $urutan = 0;

        $urutan++;

        return Order::query()->create($atribut + [
            'order_number' => 'IK-T'.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT),
            'status' => 'paid',
            'payment_status' => 'paid',
            'subtotal' => 100000,
            'grand_total' => 100000,
            'shipping_address_snapshot' => ['recipient_name' => 'Penerima Uji', 'phone_number' => '0812'],
            'shipping_courier' => 'JNE',
            'shipping_service' => 'REG',
        ]);
    }

    /**
     * Jumlah halaman PDF, dibaca dari objek halaman di dalam berkasnya.
     *
     * Bukan dari "/Pages": hitungan "semua kata Page" ikut menghitung
     * /MediaBox dan objek lain yang mengandung kata itu.
     */
    private function halaman(string $pdf): int
    {
        preg_match_all('/\/Type\s*\/Page[^s]/', $pdf, $m);

        return count($m[0]);
    }

    /**
     * Teks yang benar-benar tercetak.
     *
     * dompdf mengompres stream isinya dengan zlib dan menulis huruf sebagai
     * UTF-16BE. Membaca byte mentah menemukan nol huruf dan menghasilkan
     * kesimpulan palsu ("nomor tidak pernah tercetak"), jadi stream dibuka
     * lebih dulu dan byte nol dibuang sesudahnya.
     */
    private function teks(string $pdf): string
    {
        $hasil = '';

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $aliran) > 0) {
            foreach ($aliran[1] as $satu) {
                $isi = @zlib_decode($satu);

                if ($isi === false) {
                    continue;
                }

                if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $isi, $t) > 0) {
                    $hasil .= implode("\n", $t[1])."\n";
                }
            }
        }

        // dompdf menulis teks sebagai UTF-16BE: setiap huruf diikuti byte nol.
        // Byte nol itu harus dibuang SEBELUM dicocokkan — kalau tidak, "IK-T001"
        // tersimpan sebagai "I\0K\0-\0T\0..." dan tidak pernah cocok.
        //
        // Peta fonta (bukan teks) ikut terbawa di stream lain dan memuat byte
        // acak; dibuang sampai batas non-cetak pertama.
        $hasil = str_replace("\u0000", '', $hasil);

        $hasil = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $hasil);

        // Sisanya bisa jadi UTF-16BE murni (huruf non-ASCII). Kalau ada byte
        // nol tersisa, itu bukan teks label — potong di situ.
        $batas = strpos($hasil, "\u0000");

        return $batas === false ? $hasil : substr($hasil, 0, $batas);
    }

    private function cetak(array $ulid): \Symfony\Component\HttpFoundation\Response
    {
        $this->actingAs($this->admin);

        $respons = (new ResiBanyak)(
            request()->merge(['ulid' => implode(',', $ulid)]),
        );

        // Pesan galat disimpan di sesi; harus dapat dibaca di luar permintaan.
        return $respons;
    }

    public function test_tiga_pesanan_menghasilkan_tiga_halaman(): void
    {
        $pesanan = collect([$this->pesanan(), $this->pesanan(), $this->pesanan()]);

        $respons = $this->cetak($pesanan->pluck('ulid')->all());
        $isi = (string) $respons->getContent();

        $this->assertSame(200, $respons->getStatusCode());
        $this->assertStringStartsWith('%PDF', $isi);

        // Inilah inti bug lama: tiga halaman, bukan sembilan.
        $this->assertSame(
            $pesanan->count(),
            $this->halaman($isi),
            'Berkas resi banyak harus punya satu halaman per pesanan.',
        );
    }

    public function test_semua_nomor_pesanan_benar_benar_tercetak(): void
    {
        $pesanan = collect([$this->pesanan(), $this->pesanan(), $this->pesanan()]);

        $teks = $this->teks((string) $this->cetak($pesanan->pluck('ulid')->all())->getContent());

        foreach ($pesanan as $satu) {
            $this->assertStringContainsString(
                $satu->order_number,
                $teks,
                'Nomor '.$satu->order_number.' tercetak di berkas.',
            );
        }
    }

    public function test_satu_pesanan_menghasilkan_satu_halaman(): void
    {
        $satu = $this->pesanan();

        $isi = (string) $this->cetak([$satu->ulid])->getContent();

        $this->assertSame(1, $this->halaman($isi));
    }

    public function test_urutan_cetak_mengikuti_urutan_pilihan(): void
    {
        // Label dicetak pada urutan yang dipilih admin, bukan urutan basis data.
        // Kalau urutannya mengikuti id, admin yang menyusun paket satu per satu
        // mendapat label terbalik dan menempelkannya pada paket yang salah.
        $a = $this->pesanan();
        $b = $this->pesanan();
        $c = $this->pesanan();

        $teks = $this->teks((string) $this->cetak([$c->ulid, $a->ulid, $b->ulid])->getContent());

        $this->assertTrue(
            strpos($teks, $c->order_number) < strpos($teks, $a->order_number)
            && strpos($teks, $a->order_number) < strpos($teks, $b->order_number),
            'Urutan label harus mengikuti urutan ULID yang dikirim.',
        );
    }

    public function test_pesanan_dibatalkan_membawa_tanda_air_salinan(): void
    {
        $batal = $this->pesanan(['status' => 'cancelled']);

        $teks = $this->teks((string) $this->cetak([$batal->ulid])->getContent());

        $this->assertStringContainsString('SALINAN', $teks);
    }

    public function test_pesanan_biasa_tanpa_tanda_air_salinan(): void
    {
        $teks = $this->teks((string) $this->cetak([$this->pesanan()->ulid])->getContent());

        // "SALINAN" ada sebagai teks biasa pada tanda air, jadi yang diperiksa
        // adalah kalimat penuhnya — bukan satu kata yang bisa muncul di tempat lain.
        $this->assertStringNotContainsString('PESANAN INI SUDAH DIBATALKAN', $teks);
    }

    public function test_lebih_dari_batas_ditolak_dengan_pesan(): void
    {
        // ULID berbeda-beda: salinan dari satu ULID dianggap satu pilihan oleh
        // array_unique(), sehingga mengirim 101 salinan tetap sah.
        for ($i = 0; $i < ResiBanyak::BATAS + 1; $i++) {
            $this->pesanan();
        }

        $ulid = Order::query()->pluck('ulid')->all();

        $respons = $this->cetak($ulid);

        $this->assertSame(302, $respons->getStatusCode());
        $this->assertStringContainsString('Maksimal', (string) session('galat-admin', ''));
    }

    public function test_tanpa_pilihan_ditolak_dengan_pesan_yang_bisa_dibaca(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->cetak([]);
    }

    public function test_ulid_yang_tidak_ada_ditolak_bukan_berkas_kosong(): void
    {
        $respons = $this->cetak(['ULID-YANG-TIDAK-ADA']);

        $this->assertSame(302, $respons->getStatusCode());
        $this->assertStringContainsString('tidak ditemukan', (string) session('galat-admin', ''));
    }

    public function test_waktu_cetak_dan_catatan_aktivitas_dicatat(): void
    {
        $satu = $this->pesanan();
        $dua = $this->pesanan();

        $this->cetak([$satu->ulid, $dua->ulid]);

        $this->assertNotNull($satu->refresh()->printed_at);

        // Dua pesanan = dua catatan. Satu catatan untuk dua pesanan berarti
        // riwayat tidak dapat dipakai untuk melacak label yang mana.
        $catatan = ActivityLog::query()
            ->where('action', 'resi.dicetak')
            ->get();

        $this->assertCount(2, $catatan);

        // Catatan menyebut jumlah label dalam berkas itu: "resi ini berasal
        // dari berkas berisi dua label" berguna saat satu label terlepas.
        $this->assertSame(2, $catatan->first()->changes_json['jumlah_label'] ?? null);

        // Masing-masing tertuju pada pesanannya sendiri, bukan catatan umum.
        $this->assertTrue(
            $catatan->pluck('subject_id')->sort()->values()
                ->all() === collect([$satu, $dua])->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_nama_berkas_menyebut_jumlah_pesanan(): void
    {
        $pesanan = collect([$this->pesanan(), $this->pesanan()]);

        $respons = $this->cetak($pesanan->pluck('ulid')->all());

        $this->assertStringContainsString(
            'resi-2-pesanan',
            $respons->headers->get('Content-Disposition'),
        );
    }

    public function test_nama_berkas_satu_pesanan_memakai_nomornya(): void
    {
        $satu = $this->pesanan();

        $respons = $this->cetak([$satu->ulid]);

        $this->assertStringContainsString(
            'resi-'.$satu->order_number.'.pdf',
            $respons->headers->get('Content-Disposition'),
        );
    }
}
