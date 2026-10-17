<?php

namespace App\Livewire\Front;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Services\Keranjang;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class KeranjangHalaman extends Component
{
    public string $kodeKupon = '';

    public ?string $pesanKupon = null;

    public string $nadaKupon = 'ok';

    public function mount(Keranjang $keranjang): void
    {
        $this->kodeKupon = $keranjang->kupon()?->code ?? '';

        // Stok diperiksa ulang setiap keranjang dibuka. Baris yang stoknya
        // habis dihapus, yang berkurang disesuaikan, dengan pemberitahuan.
        foreach ($keranjang->periksaStok() as $catatan) {
            $this->pesanKupon = $catatan['jenis'] === 'habis'
                ? __('site.cart.stock_gone', ['name' => $catatan['nama']])
                : __('site.cart.stock_reduced', ['count' => $catatan['jumlah']]);

            $this->nadaKupon = 'warn';
        }
    }

    public function ubahJumlah(int $itemId, int $jumlah, Keranjang $keranjang): void
    {
        $item = $this->cariItem($itemId);

        if ($item === null) {
            return;
        }

        if ($jumlah < 1) {
            $this->hapusItem($itemId, $keranjang);

            return;
        }

        $keranjang->perbarui($item, $jumlah);
        $this->dispatch('keranjang-berubah');
    }

    public function hapusItem(int $itemId, Keranjang $keranjang): void
    {
        $item = $this->cariItem($itemId);

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
            $this->pesanKupon = 'Kode kupon tidak dikenal.';
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
        $this->pesanKupon = 'Kupon '.$kupon->code.' dipakai.';
        $this->nadaKupon = 'ok';
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

    private function cariItem(int $itemId): ?CartItem
    {
        return CartItem::query()->whereKey($itemId)->with(['variant.product', 'product'])->first();
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();
        $keranjang = app(Keranjang::class);
        $keranjang->lupakan();

        return view('livewire.front.keranjang-halaman', [
            'items' => $keranjang->items(),
            'ringkasan' => $keranjang->ringkasan(),
        ])->title(__('site.cart.title').' · '.$toko->teks('site_name'));
    }
}
