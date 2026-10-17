<div>
    <div class="container-page py-10 lg:py-16">
        <div class="mx-auto max-w-2xl">

            <span class="torque-stripe mb-4" aria-hidden="true"><i></i><i></i><i></i></span>

            <h1 class="text-3xl font-semibold sm:text-4xl">{{ __('site.order.success_title') }}</h1>
            <p class="mt-3 text-muted">{{ __('site.order.success_body') }}</p>

            <div class="plate mt-8 p-5 lg:p-6">
                <p class="part-no">{{ __('site.order.number') }}</p>
                <p class="tech mt-1 text-2xl font-medium">{{ $order->order_number }}</p>

                <div class="measure-line my-5"></div>

                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="part-no">{{ __('site.order.status') }}</dt>
                        <dd class="mt-1">
                            <x-lencana :nada="$order->status->nada()" :teks="$order->status->label()" />
                        </dd>
                    </div>
                    <div>
                        <dt class="part-no">{{ __('site.cart.total') }}</dt>
                        <dd class="tech mt-1 text-lg">{{ rupiah($order->grand_total) }}</dd>
                    </div>
                </dl>
            </div>

            @if ($order->payment_method?->value === 'manual' && $order->payment_status->value === 'unpaid' && count($rekening) > 0)
                <div class="plate mt-6 p-5 lg:p-6">
                    <p class="part-no">02 / {{ __('site.order.manual_instructions') }}</p>

                    <p class="mt-3 text-sm text-muted">
                        Transfer sebesar <span class="tech font-medium text-ink">{{ rupiah($order->grand_total) }}</span>
                        ke salah satu rekening berikut, lalu kirim bukti transfer melalui WhatsApp.
                        Pesanan diproses setelah pembayaran diverifikasi.
                    </p>

                    <ul class="mt-4 space-y-3">
                        @foreach ($rekening as $bank)
                            <li class="flex items-center gap-3">
                                <span class="badge badge-steel">{{ $bank['bank'] ?? '-' }}</span>
                                <span class="tech text-lg">{{ $bank['number'] ?? '-' }}</span>
                                <span class="text-sm text-muted">a.n. {{ $bank['holder'] ?? '-' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="plate mt-6 p-5 lg:p-6">
                <p class="part-no">03 / {{ __('site.checkout.summary') }}</p>

                <ul class="mt-4 divide-y divide-line-soft">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-4 py-3 text-sm">
                            <span>
                                {{ $item->product_name }}
                                @if ($item->variant_name)
                                    <span class="text-muted">· {{ $item->variant_name }}</span>
                                @endif
                                <span class="tech block text-xs text-dim">{{ $item->quantity }} × {{ rupiah($item->unit_price) }}</span>
                            </span>
                            <span class="tech flex-none">{{ rupiah($item->subtotal) }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="measure-line my-4"></div>

                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted">{{ __('site.cart.subtotal') }}</span>
                        <span class="tech">{{ rupiah($order->subtotal) }}</span>
                    </div>
                    @if ((float) $order->discount_amount > 0)
                        <div class="flex justify-between text-ok">
                            <span>{{ __('site.cart.discount') }}</span>
                            <span class="tech">-{{ rupiah($order->discount_amount) }}</span>
                        </div>
                    @endif
                    @if ((float) $order->shipping_cost > 0)
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('site.cart.shipping') }}</span>
                            <span class="tech">{{ rupiah($order->shipping_cost) }}</span>
                        </div>
                    @endif
                </div>

                <div class="measure-line my-4"></div>

                <div class="flex items-baseline justify-between">
                    <span class="font-semibold">{{ __('site.cart.total') }}</span>
                    <span class="tech text-2xl font-medium">{{ rupiah($order->grand_total) }}</span>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('front.products.index') }}" class="btn btn-primary" wire:navigate>
                    {{ __('site.cart.continue_shopping') }}
                </a>
                <a href="{{ route('front.order.track') }}" class="btn btn-outline">{{ __('site.order.track') }}</a>
            </div>
        </div>
    </div>
</div>
