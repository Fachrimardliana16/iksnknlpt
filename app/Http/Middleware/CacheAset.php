<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache-Control yang jujur untuk aset statis.
 *
 * Laravel mengirim `Cache-Control: max-age=0, must-revalidate, no-cache,
 * no-store, private` pada SEMUA respons, termasuk CSS, JS, dan gambar produk.
 * Akibatnya setiap kunjungan mengunduh ulang 94 KB CSS dan seluruh gambar:
 * pengunjung yang membuka tiga halaman membayar aset yang sama tiga kali, dan
 * halaman yang seharusnya selesai di bawah satu detik tertahan di jaringan.
 *
 * Dua aturan di sini, dan keduanya aman karena nama berkasnya berubah saat
 * isinya berubah:
 *
 * 1. Aset hasil build (/build/assets/*) diberi nama ber-hash oleh Vite. Isinya
 *    tidak pernah berubah selama namanya sama, jadi boleh disimpan setahun.
 * 2. Gambar produk dan hero memakai nama yang stabil, jadi tidak boleh
 *    disimpan setahun: diganti 30 hari dengan revalidasi. Bila gambarnya
 *    diganti admin, pengunjung melihatnya paling lambat sehari kemudian.
 *
 * Halaman HTML TIDAK disentuh: keranjang dan checkout berbeda per pengunjung,
 * dan menyimpannya berarti menampilkan isi keranjang orang lain.
 */
class CacheAset
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->is('admin*', 'livewire*')) {
            return $response;
        }

        $jalur = $request->path();

        if (str_starts_with($jalur, 'build/assets/')) {
            // Ber-hash: aman disimpan lama, dan tidak perlu divalidasi ulang.
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

            // Catatan terukur di mesin ini: server PHP bawaan (`artisan serve`)
            // menyajikan /build/assets langsung dari public/ tanpa melewati
            // middleware, jadi header di bawah hanya berlaku di server yang
            // melewatkan seluruh permintaan ke PHP (nginx fastcgi, Apache).
            // Untuk mesin pengembangan, kompresi dan cache aset build diatur
            // di lapisan web server — lihat qa/05-optimasi.md.
            $this->pasangEtag($response);
            $this->mampatkan($request, $response);

            return $response;
        }

        if (str_starts_with($jalur, 'storage/')
            || in_array($jalur, ['manifest.webmanifest', 'sw.js', 'offline.html'], true)
            || preg_match('#^ikon-pwa-\d+\.png$#', $jalur) === 1
        ) {
            // Nama stabil: disimpan, tetapi divalidasi ulang lewat ETag.
            $response->headers->set('Cache-Control', 'public, max-age=2592000, must-revalidate');
            $this->pasangEtag($response);
            $this->jawab304($request, $response);
            $this->mampatkan($request, $response);

            return $response;
        }

        $this->mampatkan($request, $response);

        return $response;
    }

    /**
     * Kompresi respons tekstual bila server tidak melakukannya.
     *
     * Server PHP bawaan (`php artisan serve`) tidak punya modul kompresi, dan
     * tanpa itu HTML 90 KB dan CSS 94 KB dikirim apa adanya. Diukur di mesin
     * ini: dokumen katalog turun dari 90.386 ke sekitar 12.000 bita dengan gzip.
     *
     * Di balik nginx/Apache, header Content-Encoding sudah ada dan metode ini
     * tidak melakukan apa pun — gzip ganda justru merusak isi.
     */
    private function mampatkan(Request $request, Response $response): void
    {
        if ($response->headers->has('Content-Encoding')) {
            return;
        }

        // Jawaban 304 tidak punya isi; mengompresnya tidak bermakna.
        if ($response->getStatusCode() === 304) {
            return;
        }

        if (! str_contains((string) $request->headers->get('Accept-Encoding'), 'gzip')) {
            return;
        }

        $tipe = (string) $response->headers->get('Content-Type');

        if (preg_match('#^text/|json|javascript|xml|svg|webmanifest#', $tipe) !== 1) {
            return;
        }

        $isi = $response->getContent();

        // Di bawah 1 KB, tabel header gzip lebih besar daripada isinya.
        if (! is_string($isi) || strlen($isi) < 1024) {
            return;
        }

        $mampat = gzencode($isi, 6);

        if ($mampat === false) {
            return;
        }

        $response->setContent($mampat);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($mampat));
        $response->headers->set('Vary', 'Accept-Encoding');
    }

    /**
     * Menjawab 304 bila peramban sudah memegang versi yang sama.
     *
     * Tanpa ini, "must-revalidate" berarti mengunduh ulang seluruh berkas di
     * setiap kunjungan — sama saja dengan tidak menyimpan sama sekali. Yang
     * dihemat bukan hanya bita, tetapi juga waktu tunggu: 304 hanya beberapa
     * ratus bita, bukan 94 KB CSS atau 6 KB gambar.
     */
    private function jawab304(Request $request, Response $response): void
    {
        $etag = $response->getEtag();
        $diminta = (string) $request->headers->get('If-None-Match', '');

        if ($etag === null || $diminta === '') {
            return;
        }

        // Header dapat memuat beberapa ETag dipisah koma, dan "W/" untuk
        // ETag lemah yang ditulis peramban.
        $cocok = collect(explode(',', $diminta))
            ->map(static fn (string $s): string => trim(str_replace('W/', '', $s), " \t\""))
            ->contains(trim($etag, "\""));

        if ($cocok) {
            $response->setStatusCode(304);
            $response->setContent(null);
            $response->headers->remove('Content-Length');
            $response->headers->remove('Content-Type');
        }
    }

    /**
     * ETag dari ukuran dan waktu ubah berkas.
     *
     * Tanpa ETag, "must-revalidate" berarti mengunduh ulang seluruh berkas
     * setiap kunjungan — sama saja dengan tidak menyimpan. Dengan ETag,
     * peramban mengirim If-None-Match dan server menjawab 304 tanpa isi.
     */
    private function pasangEtag(Response $response): void
    {
        if ($response->headers->has('ETag')) {
            return;
        }

        // Berkas yang disajikan Laravel (rute /storage/{path}) datang sebagai
        // StreamedResponse: isinya belum ada di memori, dan memanggil
        // getContent() akan mengosongkannya. Header-nya diambil dari
        // Content-Length + Last-Modified yang sudah dipasang pengendali berkas.
        $ukuran = (string) $response->headers->get('Content-Length', '');
        $diubah = (string) $response->headers->get('Last-Modified', '');

        if ($ukuran !== '' || $diubah !== '') {
            $response->setEtag(md5($ukuran.'|'.$diubah));

            return;
        }

        if ($response instanceof BinaryFileResponse) {
            $berkas = $response->getFile()->getPathname();

            if (is_file($berkas)) {
                $response->setEtag(md5(filesize($berkas).'|'.filemtime($berkas)));
            }

            return;
        }

        $isi = $response->getContent();

        if (is_string($isi) && $isi !== '') {
            $response->setEtag(md5((string) strlen($isi)));
        }
    }
}
