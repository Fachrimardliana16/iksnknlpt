<?php

namespace App\Services\Video;

/**
 * Pembaca durasi video tanpa pustaka luar (checklist F4-11).
 *
 * Batas 15 detik harus ditegakkan di server. Membacanya di peramban saja tidak
 * cukup: berkas dapat dikirim langsung ke titik akhir unggah tanpa melewati
 * formulir. getID3 tidak tersedia di lingkungan ini, jadi kotak mvhd pada
 * berkas ISO-BMFF (mp4/mov) dibaca langsung.
 *
 * Kotak mvhd menyimpan dua angka: timescale (satuan per detik) dan duration.
 * Durasi detik = duration / timescale.
 */
class PembacaDurasiVideo
{
    /** @var array<int, string> */
    private const WADAH = ['moov', 'trak', 'mdia', 'edts', 'udta', 'meta', 'ilst'];

    /**
     * @return float|null durasi dalam detik, atau null bila tidak terbaca
     */
    public function detik(string $jalur): ?float
    {
        $isi = @file_get_contents($jalur);

        if ($isi === false || strlen($isi) < 16) {
            // Di Windows, berkas sementara dapat dikunci antivirus. Pembacaan
            // gagal bukan alasan melempar — pemanggil memutuskan artinya.
            return null;
        }

        return $this->dariIsi($isi);
    }

    /** Dipakai test dengan isi langsung, tanpa berkas. */
    public function dariIsi(string $isi): ?float
    {
        $mvhd = $this->cariMvhd($isi, 0, strlen($isi));

        if ($mvhd === null) {
            return null;
        }

        $versi = ord($isi[$mvhd]);

        if ($versi === 1) {
            // Versi 1: creation(8) modification(8) timescale(4) duration(8)
            $timescale = $this->uint32($isi, $mvhd + 20);
            $durasi = $this->uint64($isi, $mvhd + 24);
        } else {
            // Versi 0: creation(4) modification(4) timescale(4) duration(4)
            $timescale = $this->uint32($isi, $mvhd + 12);
            $durasi = $this->uint32($isi, $mvhd + 16);
        }

        if ($timescale === 0 || $durasi === null || $durasi === 0) {
            return null;
        }

        return $durasi / $timescale;
    }

    /**
     * Telusuri kotak secara rekursif sampai mvhd ditemukan.
     *
     * @return int|null posisi byte awal isi mvhd (setelah header 8/16 byte)
     */
    private function cariMvhd(string $isi, int $mulai, int $akhir): ?int
    {
        $posisi = $mulai;

        while ($posisi + 8 <= $akhir) {
            $ukuran = $this->uint32($isi, $posisi);
            $tipe = substr($isi, $posisi + 4, 4);

            $header = 8;

            if ($ukuran === 1) {
                // Ukuran 64-bit menyusul di 8 byte berikutnya.
                $ukuran = $this->uint64($isi, $posisi + 8) ?? 0;
                $header = 16;
            } elseif ($ukuran === 0) {
                // Kotak terakhir, memanjang sampai akhir berkas.
                $ukuran = $akhir - $posisi;
            }

            if ($ukuran < $header || $posisi + $ukuran > $akhir) {
                return null;
            }

            if ($tipe === 'mvhd') {
                return $posisi + $header;
            }

            if (in_array($tipe, self::WADAH, true)) {
                $dalam = $this->cariMvhd($isi, $posisi + $header, $posisi + $ukuran);

                if ($dalam !== null) {
                    return $dalam;
                }
            }

            $posisi += $ukuran;
        }

        return null;
    }

    private function uint32(string $isi, int $posisi): int
    {
        if ($posisi + 4 > strlen($isi)) {
            return 0;
        }

        return unpack('N', substr($isi, $posisi, 4))[1];
    }

    private function uint64(string $isi, int $posisi): ?int
    {
        if ($posisi + 8 > strlen($isi)) {
            return null;
        }

        $bagian = unpack('N2', substr($isi, $posisi, 8));

        // Durasi di atas 2^53 tidak realistis untuk video promosi; nilai yang
        // melampaui itu tetap dijepit agar tidak meluap.
        return ($bagian[1] * 4294967296) + $bagian[2];
    }
}
