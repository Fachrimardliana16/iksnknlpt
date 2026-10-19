<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Header yang tidak dapat dibuang dari dalam aplikasi (checklist F10-11).
 *
 * Middleware `SecurityHeaders` sudah memanggil `remove('X-Powered-By')`, tetapi
 * header itu tetap muncul di respons nyata: "X-Powered-By: PHP/8.4.25".
 * Penyebabnya bukan middleware — PHP menulis header itu di lapisan SAPI,
 * sesudah seluruh middleware berjalan. Yang menentukan adalah `expose_php`.
 *
 * Sudah dibuktikan dari baris perintah: `php -d expose_php=0 -r
 * 'ini_get("expose_php")'` → "0", sedangkan tanpa `-d` → "1". Berkas php.ini
 * yang dipakai proses ini dapat diedit langsung, dan setelah itu header tidak
 * pernah dikirim lagi.
 */
class HeaderPhpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Nilai expose_php pada proses ini. Gagal bila masih aktif.
     *
     * Bila test ini gagal, perbaikannya BUKAN di kode aplikasi: jalankan server
     * dengan `php -d expose_php=0`, atau setel `expose_php = Off` di php.ini
     * yang dilaporkan `php --ini`.
     */
    public function test_expose_php_nonaktif_pada_proses_yang_dipakai(): void
    {
        $nilai = ini_get('expose_php');
        $nonaktif = $nilai === false || (string) $nilai === '0' || (string) $nilai === '';

        $this->assertTrue(
            $nonaktif,
            'expose_php masih aktif (nilai "'.(string) $nilai.'"): PHP akan mengirim '
            .'"X-Powered-By: PHP/'.PHP_VERSION.'" di setiap respons. Jalankan server dengan '
            .'`php -d expose_php=0`, atau setel `expose_php = Off` di php.ini.'
        );
    }
}
