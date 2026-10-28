{{-- Lonceng notifikasi panel.

     Isinya dihitung dari kueri saat dirender, jadi tidak ada notifikasi basi.
     Setiap butir membawa tautan; notifikasi yang tidak dapat ditindaklanjuti
     hanya menambah kekhawatiran tanpa menambah kemampuan.

     Butir yang sudah dilihat tetap tampil, tetapi redup dan tanpa lencana.
     Menyembunyikannya akan membuat admin mengira notifikasinya hilang. --}}
<div class="relative" wire:key="lonceng-notifikasi">

    <button type="button" class="btn-icon relative" wire:click="alihkan"
            aria-expanded="{{ $buka ? 'true' : 'false' }}"
            title="{{ $belum > 0 ? $belum.' notifikasi belum dilihat' : 'Notifikasi' }}"
            aria-label="{{ $belum > 0 ? $belum.' notifikasi belum dilihat' : 'Notifikasi' }}">
        <x-ikon name="bell" :size="19" />

        @if ($belum > 0)
            {{-- Lencana menghitung yang BELUM DILIHAT saja. Membuka satu butir
                 mengurangi angkanya satu. --}}
            <span class="dot-count" aria-hidden="true">{{ $belum > 9 ? '9+' : $belum }}</span>
        @endif

        @if ($mendesak > 0)
            {{-- Cincin berdenyut hanya untuk hal yang benar-benar mendesak dan
                 belum dilihat: stok habis dan pesanan yang hampir batal. --}}
            <span class="absolute inset-1 animate-ping rounded-full bg-danger/25" aria-hidden="true"></span>
            <span class="visually-hidden">{{ $mendesak }} notifikasi mendesak</span>
        @endif
    </button>

    @if ($buka)
        {{-- Penutup klik-di-luar tanpa Alpine: satu lapisan transparan yang
             menutupi layar, di bawah panel. --}}
        <div class="fixed inset-0 z-40" wire:click="alihkan" aria-hidden="true"></div>

        {{-- Panel lonceng memakai posisi FIXED, bukan absolute.

             Alasannya: tombol lonceng berada di tengah header, dan panel
             lebarnya min(92vw, 22rem). Dengan absolute + right-0, panel
             diposisikan relatif terhadap TOMBOLnya, bukan layar — di ponsel
             panelnya meluber ke kiri tepi viewport dan terpotong.

             Fixed + right-0 + left-0 membuat panel menempel pada tepi layar
             dan tetap terpusat di ponsel, sedangkan di desktop `max-w-md`
             menahannya supaya tidak selebar layar. --}}
        <div class="fixed inset-x-0 top-full z-50 mx-auto mt-2 w-[min(92vw,22rem)] px-4 sm:px-0"
             role="dialog" aria-label="Daftar notifikasi">
            <div class="plate-up">

            <div class="flex items-center justify-between gap-2 border-b border-line-soft px-4 py-3">
                <p class="part-no">
                    Perlu perhatian
                    @if ($belum > 0)
                        <span class="text-dim">· {{ $belum }} belum dilihat</span>
                    @endif
                </p>

                <span class="flex items-center gap-1">
                    @if ($belum > 0)
                        <button type="button" class="text-xs font-medium text-accent hover:underline"
                                wire:click="tandaiSemua">
                            Tandai dibaca
                        </button>
                    @endif

                    <button type="button" class="btn-icon !h-8 !w-8" wire:click="segarkan" title="Segarkan">
                        <x-ikon name="refresh" :size="15" />
                        <span class="visually-hidden">Segarkan</span>
                    </button>

                    {{-- Pengaturan notifikasi. Dulu butir menu sidebar tersendiri;
                         halaman itu mengelola perangkat push, yang bukan pekerjaan
                         harian. Sekarang hanya terjangkau dari sini — tempat
                         orang berpikir tentang notifikasi ketika ingin mengubah
                         perilakunya. Rutenya tetap ada dan tetap di luar
                         "Situs": yang hilang dari sidebar hanya tautannya. --}}
                    <a href="{{ route('admin.notifications.index') }}" wire:navigate
                       class="btn-icon !h-8 !w-8" title="Pengaturan notifikasi">
                        <x-ikon name="sliders" :size="15" />
                        <span class="visually-hidden">Pengaturan notifikasi</span>
                    </a>
                </span>
            </div>

            @if ($butir->isEmpty())
                <div class="p-6 text-center">
                    <x-ikon name="check" :size="26" class="mx-auto text-ok" />
                    <p class="mt-2 text-sm font-medium">Tidak ada yang perlu ditangani.</p>
                    <p class="mt-1 text-xs text-muted">
                        Stok aman, tidak ada pesanan menunggu, tidak ada keranjang mengendap.
                    </p>
                </div>
            @else
                <ul class="max-h-[70vh] divide-y divide-line-soft overflow-y-auto">
                    @foreach ($butir as $b)
                        <li @class(['bg-sunken/40' => $b['dilihat']])>
                            {{-- wire:navigate ditambah wire:click: tanda baca disimpan
                                 lebih dulu, peramban menyusul. --}}
                            <a href="{{ $b['rute'] }}" wire:navigate
                               wire:click="bukaButir('{{ $b['kunci'] }}')"
                               class="flex items-start gap-3 px-4 py-3 hover:bg-sunken">

                                <span class="mt-0.5 flex-none rounded-[var(--r-kecil)] p-1.5
                                    @if ($b['dilihat'])
                                        bg-line-soft/60 text-dim
                                    @else
                                        @switch($b['nada'])
                                            @case('danger') bg-danger/12 text-danger @break
                                            @case('warn') bg-warn/12 text-warn @break
                                            @case('steel') bg-steel/12 text-steel @break
                                            @default bg-accent/12 text-accent
                                        @endswitch
                                    @endif">
                                    <x-ikon :name="$b['ikon']" :size="17" />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium @if ($b['dilihat']) text-muted @endif">
                                        {{ $b['teks'] }}
                                    </span>

                                    @if (filled($b['rinci']))
                                        <span class="mt-0.5 block text-xs text-muted">{{ $b['rinci'] }}</span>
                                    @endif

                                    @if ($b['dilihat'])
                                        <span class="mt-1 block text-[0.6875rem] text-dim">
                                            Sudah dilihat
                                            @if ($b['dilihat_pada'])
                                                · {{ $b['dilihat_pada']->diffForHumans() }}
                                            @endif
                                        </span>
                                    @endif
                                </span>

                                @if ($b['dilihat'])
                                    <x-ikon name="check" :size="15" class="mt-1 flex-none text-ok" />
                                @else
                                    {{-- Titik penanda butir baru: satu-satunya alasan
                                         orang tahu ada yang harus dibuka. --}}
                                    <span class="mt-2 h-2 w-2 flex-none rounded-full bg-accent"
                                          aria-label="Belum dilihat"></span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
            </div>
        </div>
    @endif
</div>
