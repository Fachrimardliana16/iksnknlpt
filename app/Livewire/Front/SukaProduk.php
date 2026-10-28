<?php

namespace App\Livewire\Front;

use App\Models\Product;
use App\Models\ProductLike;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tombol suka pada kartu katalog.
 *
 * Bedanya dengan tombol keranjang: suka tidak menahan stok, tidak punya
 * jumlah, dan tidak menuntut varian. Yang dicatat hanyalah SIAPA menyukai APA
 * — itu yang berguna sebagai parameter iklan ("produk mana yang dibutuhkan
 * pembeli"), sementara keranjang menjawab pertanyaan berbeda ("barang apa yang
 * tertinggal").
 *
 * Tekan dua kali membatalkan, dan jumlahnya ikut turun. Dua baris untuk satu
 * produk dan satu pengunjung membuat hitungannya tidak bisa dipercaya.
 *
 * ANGGARAN KUERI. Jumlah suka dan status "sudah disukai" DITERIMA sebagai
 * properti dari halaman pemanggil, bukan dihitung di mount(). Versi pertama
 * menghitung sendiri dua kueri per kartu, dan halaman katalog berisi dua belas
 * kartu: 24 kueri tambahan yang menembus anggaran 15 kueri per halaman dan
 * membuat /produk memakai 28 kueri. Dengan properti dari induk, seluruh daftar
 * hanya menambah SATU kueri — lihat ProductLike::hitungUntuk() dan
 * ProductLike::petaDisukai(), keduanya satu kueri untuk banyak produk.
 *
 * Batas laju 60/menit per pengunjung: tombol ini satu ketukan, dan tanpa batas
 * satu skrip bisa menggerakkan angka iklan seluruh katalog.
 */
class SukaProduk extends Component
{
    public int $produkId;

    public bool $disukai = false;

    public int $jumlah = 0;

    /** Kunci pesan singkat di bawah tombol. */
    public string $pesan = '';

    /**
     * @param  bool  $disukai  keadaan awal dari induk
     * @param  int  $jumlah  jumlah suka dari induk
     */
    public function mount(int $produkId, bool $disukai = false, int $jumlah = 0): void
    {
        $this->produkId = $produkId;
        $this->disukai = $disukai;
        $this->jumlah = $jumlah;
    }

    public function alihkan(): void
    {
        $produk = Product::query()->find($this->produkId);

        if ($produk === null) {
            return;
        }

        $kunci = 'suka:'.session()->getId();
        $batas = 60;

        if (RateLimiter::tooManyAttempts($kunci, $batas)) {
            $this->pesan = 'Terlalu banyak. Tunggu sebentar.';

            return;
        }

        RateLimiter::hit($kunci, 60);

        $pengguna = auth()->user();

        $this->disukai = ProductLike::alihkan(
            $produk,
            $pengguna instanceof User ? $pengguna : null,
            session()->getId(),
        );

        // Jumlah dihitung ulang di sini, TIDAK ditambah/dikurangi: penghitung
        // lokal bisa menyimpang dari basis data kalau dua tab terbuka, dan
        // angka suka yang tampil di depan pembeli harus sama dengan yang
        // tercatat.
        $this->jumlah = ProductLike::hitungSatu($produk);

        $this->dispatch('suka-berubah', produk: $produk->id, disukai: $this->disukai);
    }

    /**
     * Dijalankan ketika komponen lain mengubah suka produk ini.
     *
     * Dipakai halaman detail dan kartu yang memuat produk yang sama: tanpa ini
     * angka di dua tempat bisa berbeda sampai halaman dimuat ulang.
     *
     * Hanya membaca ulang bila produknya SAMA — tanpa pemeriksaan itu, satu
     * ketukan pada satu kartu membuat dua belas kartu lain ikut membaca basis
     * data, dan anggaran kueri tembus lagi dari pintu yang berbeda.
     */
    #[On('suka-berubah')]
    public function sinkron(int $produk): void
    {
        if ($produk !== $this->produkId) {
            return;
        }

        $pengguna = auth()->user();

        $this->disukai = ProductLike::sudah(
            $this->produkId,
            ProductLike::kunciPemilik(
                $pengguna instanceof User ? $pengguna : null,
                session()->getId(),
            ),
        );

        $this->jumlah = ProductLike::hitungSatu($produk);
    }

    public function render()
    {
        return view('livewire.front.suka-produk');
    }
}
