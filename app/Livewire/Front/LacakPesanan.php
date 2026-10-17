<?php

namespace App\Livewire\Front;

use App\Models\Order;
use App\Support\BlindIndex;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class LacakPesanan extends Component
{
    public string $nomor = '';

    public string $email = '';

    /** @var array<int, array<string, mixed>> */
    public array $hasil = [];

    public ?string $galat = null;

    public function lacak(): void
    {
        $this->galat = null;
        $this->hasil = [];

        $kunci = 'lacak:'.request()->ip();

        if (RateLimiter::tooManyAttempts($kunci, config('iksan.rate_limits.track_order')[0])) {
            // Pesan seragam. Halaman ini tidak boleh menjadi alat enumerasi
            // nomor pesanan.
            $this->galat = __('site.order.not_found');

            return;
        }

        RateLimiter::hit($kunci, config('iksan.rate_limits.track_order')[1] * 60);

        $this->validate([
            'nomor' => 'required|string|max:40',
            'email' => 'required|email|max:120',
        ], [
            'nomor.required' => 'Nomor pesanan wajib diisi.',
            'email.required' => 'Email wajib diisi.',
        ]);

        $order = Order::query()
            ->where('order_number', trim($this->nomor))
            ->with(['items', 'statusHistory', 'payments'])
            ->first();

        // Email dibandingkan setelah dekripsi, karena kolomnya terenkripsi.
        $cocok = $order !== null
            && mb_strtolower((string) $order->emailPenerima()) === mb_strtolower(trim($this->email));

        if (! $cocok) {
            $this->galat = __('site.order.not_found');

            return;
        }

        $this->hasil = [[
            'nomor' => $order->order_number,
            'status' => $order->status,
            'status_pembayaran' => $order->payment_status,
            'total' => $order->grand_total,
            'dibuat' => $order->created_at,
            'kurir' => $order->shipping_courier,
            'resi' => $order->tracking_number,
            'item' => $order->items->count(),
            'riwayat' => $order->statusHistory->sortBy('created_at')->values()->all(),
            'url' => route('front.order.success', $order),
        ]];
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();

        return view('livewire.front.lacak-pesanan')
            ->title(__('site.order.track').' · '.$toko->teks('site_name'));
    }
}
