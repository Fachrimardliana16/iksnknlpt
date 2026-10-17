<?php

namespace App\Livewire\Front;

use App\Models\Order;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PesananSukses extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load(['items', 'payments']);
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();

        return view('livewire.front.pesanan-sukses', [
            'rekening' => $toko->teks('payment_mode') === 'paypal'
                ? []
                : (\App\Models\Setting::query()->where('key', 'manual_bank_accounts')->first()?->larik() ?? []),
        ])->title(__('site.order.success_title').' · '.$toko->teks('site_name'));
    }
}
