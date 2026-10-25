<?php

namespace App\Livewire\Front;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Services\Keranjang;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.front')]
class KeranjangHalaman extends Component
{
    public string $kodeKupon = '';

    public ?string $pesanKupon = null;

    public string $nadaKupon = 'ok';

    /**
     * Catatan pembeli (checklist F7-03).
     *
     * Disimpan ke keranjang saat berubah, bukan menunggu tombol simpan. Yang
     * diisi di sini adalah pesan pengiriman seperti "titip di pos keamanan",
     * dan kehilangannya karena pembeli menutup peramban sebelum menekan tombol
     * adalah kerugian yang tidak sebanding dengan satu kueri tambahan.
     */
    public string $catatan = '';

    /** Pemberitahuan perubahan stok, ditampilkan sekali di atas daftar. */
    public ?string $pesanStok = null;

    public function mount(Keranjang $keranjang): void
    {
        $this->kodeKupon = $keranjang->kupon()?->code ?? '';
        $this->catatan = $keranjang->catatan();

        // Tautan berbagi KOL: ?kupon=KODE.
        //
        // Pembeli yang datang dari tautan milik KOL tidak perlu mengetik kode.
        // Yang tersimpan adalah KODE KUPON, bukan slug KOL — slug hanya
        // penanda laporan, dan menyimpannya di keranjang akan menyimpan data
        // yang tidak dipakai di jalur pembelian.
        //
        // Hanya dipasang bila keranjang belum punya kupon, supaya pembeli yang
        // sengaja mengetik kode lain tidak melihat pilihannya ditimpa saat
        // halaman dimuat ulang.
        if ($this->kodeKupon === '') {
            $dariTautan = trim((string) request()->query('kupon', ''));

            if ($dariTautan !== '') {
                $kupon = Coupon::query()->kode($dariTautan)->berlaku()->first();

                if ($kupon !== null && $kupon->alasanTidakSah($keranjang->subtotal()) === null) {
                    $keranjang->terapkanKupon($kupon);
                    $this->kodeKupon = $kupon->code;
                    $this->pesanKupon = __('site.cart.coupon_applied', ['kode' => $kupon->code]);
                    $this->nadaKupon = 'ok';
                }
            }
        }

        $this->periksaStok($keranjang);
    }

    /**
     * Stok diperiksa ulang setiap keranjang dibuka (checklist F7-04).
     *
     * Baris yang stoknya habis dihapus, yang berkurang disesuaikan. Pesan
     * sebelumnya menimpa variabel yang sama dengan pesan kupon, dan disetel
     * hanya satu kali walau ada beberapa baris terdampak — akibatnya pembeli
     * hanya melihat satu perubahan, padahal jumlah totalnya ikut berubah.
     */
    private function periksaStok(Keranjang $keranjang): void
    {
        $catatan = $keranjang->periksaStok();

        if ($catatan === []) {
            return;
        }

        $bagian = [];

        foreach ($catatan as $satu) {
            $bagian[] = $satu['jenis'] === 'habis'
                ? __('site.cart.stock_gone', ['name' => $satu['nama']])
                : __('site.cart.stock_reduced', ['name' => $satu['nama'], 'count' => $satu['jumlah']]);
        }

        $this->pesanStok = implode(' ', $bagian);
    }

    public function updatedCatatan(Keranjang $keranjang): void
    {
        if (mb_strlen($this->catatan) > 500) {
            $this->catatan = mb_substr($this->catatan, 0, 500);
        }

        $keranjang->simpanCatatan($this->catatan);
    }

    public function ubahJumlah(int $itemId, int $jumlah, Keranjang $keranjang): void
    {
        $item = $this->cariItem($itemId, $keranjang);

        if ($item === null) {
            return;
        }

        if ($jumlah < 1) {
            $this->hapusItem($itemId, $keranjang);

            return;
        }

        // Jumlah yang diminta dibatasi stok tersedia DI SERVER (checklist
        // F7-04). Atribut max pada kolom masukan hanya membatasi apa yang
        // dapat diketik; nilai dari sisi klien tetap harus diperiksa.
        $maksimal = $item->stokMaksimal();

        if ($jumlah > $maksimal) {
            $this->pesanStok = __('site.cart.stock_reduced', [
                'name' => $item->product?->judul() ?? 'Produk',
                'count' => $maksimal,
            ]);
        }

        $keranjang->perbarui($item, $jumlah);
        $this->dispatch('keranjang-berubah');
    }

    public function hapusItem(int $itemId, Keranjang $keranjang): void
    {
        $item = $this->cariItem($itemId, $keranjang);

        if ($item !== null) {
            $keranjang->hapus($item);
        }

        $this->dispatch('keranjang-berubah');
    }

    public function terapkanKupon(Keranjang $keranjang): void
    {
        $kode = trim($this->kodeKupon);

        if ($kode === '') {
            $keranjang->terapkanKupon(null);
            $this->pesanKupon = null;

            return;
        }

        $kupon = Coupon::query()->kode($kode)->first();

        if ($kupon === null) {
            $keranjang->terapkanKupon(null);
            $this->pesanKupon = __('site.cart.coupon_unknown');
            $this->nadaKupon = 'danger';

            return;
        }

        $alasan = $kupon->alasanTidakSah($keranjang->subtotal());

        if ($alasan !== null) {
            $keranjang->terapkanKupon(null);
            $this->pesanKupon = $alasan;
            $this->nadaKupon = 'danger';

            return;
        }

        $keranjang->terapkanKupon($kupon);
        $this->pesanKupon = __('site.cart.coupon_applied', ['kode' => $kupon->code]);
        $this->nadaKupon = 'ok';
    }

    public function lepasKupon(Keranjang $keranjang): void
    {
        $keranjang->terapkanKupon(null);
        $this->kodeKupon = '';
        $this->pesanKupon = null;
    }

    public function lanjutCheckout(Keranjang $keranjang): void
    {
        if ($keranjang->jumlahItem() === 0) {
            return;
        }

        $this->redirect(route('front.checkout'), navigate: true);
    }

    #[On('keranjang-berubah')]
    public function segarkan(): void
    {
        //
    }

    /**
     * Item hanya boleh dicari DI DALAM keranjang pemanggil.
     *
     * `itemId` datang dari sisi klien (parameter metode Livewire, sama seperti
     * tombol hapus pada halaman keranjang). Pencarian `whereKey()` saja
     * menemukan baris milik sesi lain atau akun lain, sehingga keranjang orang
     * lain dapat diubah jumlahnya dan dihapus (IDOR). Kueri karena itu dibatasi
     * pada `cart_id` milik pemanggil — keranjang yang sudah dimuat di memori.
     */
    private function cariItem(int $itemId, Keranjang $keranjang): ?CartItem
    {
        $cart = $keranjang->ambil();

        if ($cart === null) {
            return null;
        }

        return $cart->items()
            ->whereKey($itemId)
            ->with(['variant.product', 'product'])
            ->first();
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();
        $keranjang = app(Keranjang::class);
        $keranjang->lupakan();

        return view('livewire.front.keranjang-halaman', [
            'items' => $keranjang->items(),
            'ringkasan' => $keranjang->ringkasan(),
        ])
            ->title(__('site.cart.title').' · '.$toko->teks('site_name'))
            ->layoutData([
                // Keranjang tidak boleh masuk hasil pencarian: isinya berbeda
                // untuk setiap pengunjung, dan yang terindeks adalah keranjang
                // orang lain yang kebetulan dilewati perayap.
                'kanonikal' => route('front.cart'),
                'metaOg' => [
                    'tipe' => 'website',
                    'judul' => __('site.cart.title'),
                    'deskripsi' => $toko->teks('site_description', __('site.brand.tagline')),
                    'gambar' => null,
                ],
                'tanpaIndeks' => true,
            ]);
    }
}
