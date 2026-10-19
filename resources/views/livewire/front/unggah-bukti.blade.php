{{-- Halaman unggah bukti transfer.

     Satu halaman, tiga keadaan: belum bayar (tampilkan rekening + formulir),
     menunggu verifikasi (tampilkan keterangan), dan lunas (tidak ada formulir).
     Menampilkan formulir pada keadaan yang pasti ditolak hanya membuat pembeli
     mengunggah berkas untuk kemudian ditolak. --}}

<div class="container-page py-8">

    <div class="mx-auto max-w-2xl space-y-5">

        <div>
            <p class="part-no">Pembayaran · transfer manual</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">
                Bukti transfer
            </h1>
            <p class="mt-1 text-sm text-muted">
                Pesanan <span class="tech">{{ $pesanan->order_number }}</span> ·
                {{ rupiah($pesanan->grand_total) }}
            </p>
        </div>

        @if ($pesan)
            <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
        @endif

        @if ($galat)
            <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $galat }}</div>
        @endif

        {{-- Keadaan: sudah lunas --}}
        @if ($sudahLunas)
            <div class="plate p-5 text-center">
                <x-ikon name="check" :size="32" class="mx-auto text-ok" />
                <p class="mt-3 font-display text-lg font-semibold">Pembayaran sudah diterima</p>
                <p class="mt-1 text-sm text-muted">
                    Pesanan ini sudah lunas dan sedang kami proses. Tidak perlu mengunggah apa pun lagi.
                </p>
                <a href="{{ route('front.order.track', ['kode' => $pesanan->order_number]) }}"
                   class="btn btn-primary mt-4">Lacak pesanan</a>
            </div>

        {{-- Keadaan: menunggu verifikasi --}}
        @elseif ($menunggu)
            <div class="plate border-l-2 border-warn p-4">
                <p class="font-medium">Bukti sudah kami terima</p>
                <p class="mt-1 text-sm text-muted">
                    Bukti transfer Anda sedang diperiksa. Kami kabari lewat surel atau WhatsApp
                    setelah pembayaran terverifikasi, biasanya dalam 1×24 jam kerja.
                </p>
            </div>

            <div class="plate p-4">
                <p class="part-no">Bukti yang sudah diunggah</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($pembayaran as $p)
                        <li class="flex items-center justify-between gap-2">
                            <span class="text-muted">
                                {{ $p->created_at?->translatedFormat('d M Y, H:i') }}
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs
                                         {{ $p->provider_status === 'terverifikasi' ? 'bg-ok/12 text-ok' : 'bg-warn/12 text-warn' }}">
                                {{ $p->provider_status === 'terverifikasi' ? 'terverifikasi' : 'menunggu' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="plate p-4 text-sm">
                <p class="text-muted">
                    Unggah ulang hanya diperlukan bila kami meminta perbaikan. Batasnya tiga kali
                    per jam per pesanan.
                </p>
            </div>
        @endif

        {{-- Keadaan: belum bayar --}}
        @unless ($sudahLunas)
            <div class="plate p-5">
                <p class="part-no">Transfer ke</p>

                @if ($rekeningLengkap)
                    <div class="mt-3 space-y-3">
                        @foreach ($rekening as $r)
                            <div class="space-y-1">
                                <p class="font-display text-lg font-semibold">{{ $r['bank'] }}</p>
                                <p class="tech text-xl">{{ $r['nomor'] }}</p>
                                <p class="text-sm text-muted">a.n. {{ $r['atas_nama'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 rounded-lg bg-sunken p-3 text-sm">
                        <p class="flex items-center justify-between">
                            <span class="text-muted">Nominal</span>
                            <span class="tech font-medium">{{ rupiah($pesanan->grand_total) }}</span>
                        </p>
                        <p class="mt-2 text-xs text-dim">
                            Transfer tepat sebesar nominal di atas agar pembayaran mudah dicocokkan.
                            Cantumkan nomor pesanan pada berita transfer bila dimungkinkan.
                        </p>
                    </div>
                @else
                    <div class="mt-3 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm">
                        <p class="font-medium text-danger">Rekening belum diatur</p>
                        <p class="mt-1 text-muted">
                            Toko belum mengisi nomor rekening tujuan. Hubungi kami lewat halaman kontak
                            untuk menyelesaikan pembayaran pesanan ini.
                        </p>
                        <a href="{{ route('front.contact') }}" class="btn btn-outline btn-sm mt-3">Hubungi kami</a>
                    </div>
                @endif
            </div>

            <form wire:submit="unggah" class="plate p-5">
                <p class="font-display text-lg font-semibold">Unggah bukti transfer</p>
                <p class="mt-1 text-sm text-muted">
                    Tangkapan layar atau foto bukti transfer. JPG, PNG, WebP, atau PDF, maksimum 5 MB.
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="bukti" class="part-no">Berkas bukti <span class="text-danger">*</span></label>
                        <input id="bukti" type="file" wire:model="bukti"
                               accept="image/jpeg,image/png,image/webp,application/pdf"
                               class="field mt-1 py-2 file:mr-3 file:rounded-md file:border-0 file:bg-accent file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-accent-fg"
                               aria-invalid="{{ $errors->has('bukti') ? 'true' : 'false' }}">
                        @error('bukti') <p class="field-error">{{ $message }}</p> @enderror

                        <p wire:loading wire:target="bukti" class="tech mt-1 text-xs text-accent">
                            Mengunggah berkas…
                        </p>
                    </div>

                    <div>
                        <label for="catatan" class="part-no">Catatan (opsional)</label>
                        <textarea id="catatan" wire:model="catatan" class="field mt-1" rows="2"
                                  placeholder="Misalnya: transfer dari rekening a.n. Budi"></textarea>
                        @error('catatan') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-4 w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="unggah">
                        <x-ikon name="upload" :size="16" class="inline" />
                        Kirim bukti
                    </span>
                    <span wire:loading wire:target="unggah">Mengirim…</span>
                </button>
            </form>

            <p class="text-center text-xs text-dim">
                Butuh bantuan?
                <a href="{{ route('front.contact') }}" class="text-accent hover:underline">Hubungi kami</a>
                atau lacak pesanan di
                <a href="{{ route('front.order.track', ['kode' => $pesanan->order_number]) }}"
                   class="text-accent hover:underline">halaman pelacakan</a>.
            </p>
        @endunless
    </div>
</div>
