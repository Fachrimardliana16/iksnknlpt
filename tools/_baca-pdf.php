<?php

/**
 * Membaca teks dari PDF dompdf.
 *
 * dompdf mengompres stream isinya dengan zlib, jadi kurung "(teks)" tidak
 * terbaca dari byte mentah — yang terlihat hanya deretan tak berbentuk. Alat
 * ukur yang membaca byte mentah akan menyimpulkan "nomor pesanan tidak pernah
 * tercetak" padahal yang salah adalah cara membacanya.
 *
 * Dipakai oleh uji-resi-banyak.php.
 */

$bacaTeksPdf = static function (string $pdf): string {
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m) < 1) {
        return '';
    }

    $teks = '';

    foreach ($m[1] as $aliran) {
        $isi = @zlib_decode($aliran);

        if ($isi === false) {
            continue;
        }

        // Teks di dalam operator Tj/TJ berada di dalam kurung, bentuknya
        // (ISI) atau [(ISI) -200 (LAIN)]. Yang diambil isinya saja.
        if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $isi, $t) > 0) {
            $teks .= implode("\n", $t[1])."\n";
        }
    }

    // Escape octal/unicode yang muncul pada huruf non-ASCII.
    $teks = preg_replace_callback(
        '/\\\\([0-7]{1,3})/',
        static fn (array $c): string => chr((int) octdec($c[1])),
        $teks,
    ) ?? $teks;

    // dompdf menulis teks sebagai UTF-16BE: setiap huruf diikuti \u0000.
    // Dibuang setelah escape dijalankan, bukan sebelum — escape octal justru
    // memunculkan byte nol yang harus ikut hilang.
    $teks = str_replace("\u0000", '', $teks);

    // Sisa byte yang bukan teks (peta fonta) datang dari stream lain; dipotong
    // pada karakter non-cetak pertama agar tidak mengotori pencocokan.
    return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $teks);
};

if (realpath($argv[0] ?? '') === realpath(__FILE__) && isset($argv[1])) {
    $isifile = file_get_contents($argv[1]);

    if ($isifile === false) {
        fwrite(STDERR, "Berkas tidak terbaca: {$argv[1]}\n");
        exit(1);
    }

    echo $bacaTeksPdf($isifile);
    exit(0);
}

return $bacaTeksPdf;
