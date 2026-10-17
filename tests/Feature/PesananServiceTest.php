<?php

namespace Tests\Feature;

use App\Enums\StatusPesanan;
use App\Enums\StatusPembayaran;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PesananService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F7-07 dan F7-08: stok tidak boleh minus dan nomor pesanan tidak berurutan.
 */
class PesananServiceTest extends TestCase
{
    use RefreshDatabase;

    private function varian(int $stok): ProductVariant
    {
        $produk = Product::factory()->create(['base_price' => 1_000_000]);

        return ProductVariant::factory()->create([
            'product_id' => $produk->id,
            'price_adjustment' => 0,
            'stock_quantity' => $stok,
            'reserved_quantity' => 0,
        ]);
    }

    private function keranjangDengan(ProductVariant $varian, int $jumlah)
    {
        $keranjang = app(\App\Services\Keranjang::class);
        $keranjang->tambah($varian, $jumlah);

        return $keranjang;
    }

    private function data(): array
    {
        return [
            'nama' => 'Pembeli Uji',
            'email' => 'uji@example.test',
            'telepon' => '628120000009',
            'alamat1' => 'Jl. Uji No. 1',
            'alamat2' => null,
            'kota' => 'Sleman',
            'provinsi' => 'DI Yogyakarta',
            'kodePos' => '55581',
            'kurir' => 'JNE',
            'catatan' => null,
        ];
    }

    public function test_pesanan_menahan_stok_tanpa_mengurangi_stok_fisik(): void
    {
        $varian = $this->varian(10);
        $keranjang = $this->keranjangDengan($varian, 3);

        $order = app(PesananService::class)->buatDariKeranjang(
            $keranjang,
            $this->data(),
            \App\Enums\MetodePembayaran::Manual,
        );

        $varian->refresh();

        // Stok fisik belum berkurang, karena barang belum keluar gudang.
        // Yang bertambah adalah tahanan, sehingga stok dapat dijual berkurang.
        $this->assertSame(10, $varian->stock_quantity);
        $this->assertSame(3, $varian->reserved_quantity);
        $this->assertSame(7, $varian->stokTersedia());
        $this->assertSame(StatusPesanan::Pending, $order->status);
        $this->assertSame(StatusPembayaran::Unpaid, $order->payment_status);
        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items->first()->quantity);
        $this->assertSame(1, $order->statusHistory()->count());
    }

    public function test_pesanan_selesai_mengurangi_stok_fisik_dan_melepas_tahanan(): void
    {
        $varian = $this->varian(10);

        $order = app(PesananService::class)->buatDariKeranjang(
            $this->keranjangDengan($varian, 3),
            $this->data(),
            \App\Enums\MetodePembayaran::Manual,
        );

        $status = app(\App\Services\StatusPesananService::class);

        $order = $status->pindah($order, StatusPesanan::Paid);
        $order = $status->pindah($order, StatusPesanan::Processing);
        $order = $status->pindah($order, StatusPesanan::Shipped);
        $order = $status->pindah($order, StatusPesanan::Completed);

        $varian->refresh();

        $this->assertSame(7, $varian->stock_quantity);
        $this->assertSame(0, $varian->reserved_quantity);
        $this->assertSame(7, $varian->stokTersedia());
        $this->assertSame(StatusPesanan::Completed, $order->status);
        $this->assertSame(5, $order->statusHistory()->count());
    }

    public function test_pesanan_dibatalkan_mengembalikan_stok(): void
    {
        $varian = $this->varian(10);

        $order = app(PesananService::class)->buatDariKeranjang(
            $this->keranjangDengan($varian, 4),
            $this->data(),
            \App\Enums\MetodePembayaran::Manual,
        );

        $this->assertSame(6, $varian->fresh()->stokTersedia());

        $order = app(\App\Services\StatusPesananService::class)
            ->pindah($order, StatusPesanan::Cancelled, catatan: 'Uji pembatalan');

        $varian->refresh();

        $this->assertSame(10, $varian->stock_quantity);
        $this->assertSame(0, $varian->reserved_quantity);
        $this->assertSame(10, $varian->stokTersedia());
        $this->assertSame(StatusPesanan::Cancelled, $order->status);
    }

    public function test_transisi_tidak_sah_ditolak(): void
    {
        $varian = $this->varian(5);

        $order = app(PesananService::class)->buatDariKeranjang(
            $this->keranjangDengan($varian, 1),
            $this->data(),
            \App\Enums\MetodePembayaran::Manual,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak dapat berpindah/');

        // Pending tidak boleh langsung ke Completed.
        app(\App\Services\StatusPesananService::class)
            ->pindah($order, StatusPesanan::Completed);
    }

    public function test_tidak_bisa_oversell(): void
    {
        $varian = $this->varian(2);

        // Tambah 2, lalu coba tambah 5 lagi. Keranjang membatasi pada stok.
        $keranjang = $this->keranjangDengan($varian, 2);
        $akhir = $keranjang->tambah($varian, 5);

        $this->assertSame(2, $akhir, 'Keranjang tidak boleh melebihi stok yang tersedia.');

        $order = app(PesananService::class)->buatDariKeranjang(
            $keranjang,
            $this->data(),
            \App\Enums\MetodePembayaran::Manual,
        );

        $this->assertSame(0, $varian->fresh()->stokTersedia());
        $this->assertSame(2, $order->items->first()->quantity);
    }

    public function test_pesanan_kedua_ditolak_bila_stok_sudah_diambil(): void
    {
        $varian = $this->varian(2);

        $pesanan = app(PesananService::class);
        $pesanan->buatDariKeranjang(
            $this->keranjangDengan($varian, 2),
            $this->data(),
            \App\Enums\MetodePembayaran::Manual,
        );

        // Keranjang baru, stok sudah nol.
        $keranjang = app(\App\Services\Keranjang::class);
        $keranjang->tambah($varian->fresh(), 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Keranjang kosong|Stok/');

        $pesanan->buatDariKeranjang(
            $keranjang,
            $this->data(),
            \App\Enums\MetodePembayaran::Manual,
        );
    }

    public function test_nomor_pesanan_tidak_berurutan_dan_unik(): void
    {
        $pesanan = app(PesananService::class);
        $nomor = [];

        for ($i = 0; $i < 5; $i++) {
            $varian = $this->varian(5);
            $order = $pesanan->buatDariKeranjang(
                $this->keranjangDengan($varian, 1),
                $this->data(),
                \App\Enums\MetodePembayaran::Manual,
            );
            $nomor[] = $order->order_number;
        }

        $this->assertCount(5, array_unique($nomor), 'Nomor pesanan harus unik.');
        $this->assertMatchesRegularExpression('/^IK-\d{8}-[A-Z0-9]{6}$/', $nomor[0]);
    }

    public function test_keranjang_dikosongkan_setelah_pesanan_dibuat(): void
    {
        $varian = $this->varian(5);
        $keranjang = $this->keranjangDengan($varian, 2);

        $this->assertSame(2, $keranjang->jumlahItem());

        app(PesananService::class)->buatDariKeranjang(
            $keranjang,
            $this->data(),
            \App\Enums\MetodePembayaran::Manual,
        );

        $keranjang->lupakan();

        $this->assertSame(0, $keranjang->jumlahItem());
    }
}
