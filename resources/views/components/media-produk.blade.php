{{--
    Pratinjau media produk: model 3D dan contoh suara (Fase 2: P2-01, P2-02).

    Dua hal yang menentukan bentuk markup ini:

    1. <model-viewer> dimuat sebagai modul dari CDN. Memuatnya berarti menambah
       satu asal ke CSP (`script-src`, `connect-src`) — tanpa itu peramban
       memblokirnya tanpa pesan yang terlihat, dan pemutarnya hanya kotak kosong.
       Karena itu skripnya dipasang lewat nonce yang sama dengan skrip lain.
    2. Audio memakai satu <audio> dengan dua sumber yang sama-sama dimuat, lalu
       A/B memindahkan posisi putar di antara keduanya. Dua elemen terpisah
       membuat perbandingan tidak adil: posisinya berbeda, dan pembeli
       membandingkan potongan yang tidak sama.

    Komponen tidak dirender bila produk tidak punya media seperti itu.
--}}

@props(['media3d' => null, 'audio' => null, 'judul' => ''])

@if ($media3d || $audio)
    <div class="mt-10 grid gap-6 lg:grid-cols-2">

        @if ($media3d)
            <div class="plate p-4" data-media-3d>
                <p class="part-no">Pratinjau 3D</p>

                {{-- model-viewer memuat .glb lewat fetch: connect-src harus
                     mengizinkan asal tempat berkasnya disimpan. Berkasnya dari
                     disk sendiri, jadi 'self' cukup. --}}
                <script type="module" nonce="{{ csp_nonce() }}"
                        src="https://cdn.jsdelivr.net/npm/@google/model-viewer@4/dist/model-viewer.min.js"></script>

                <model-viewer
                    src="{{ $media3d->urlBerkas() }}"
                    alt="{{ $media3d->keterangan ?: $judul }}"
                    poster="{{ $media3d->url('card') }}"
                    camera-controls
                    touch-action="pan-y"
                    shadow-intensity="0.8"
                    exposure="1"
                    style="width:100%;height:320px;background:transparent">
                </model-viewer>

                <p class="mt-2 text-xs text-dim">
                    Geser untuk memutar · cubit atau gulir untuk memperbesar
                </p>
            </div>
        @endif

        @if ($audio)
            <div class="plate p-4" x-data="{
                    a: '{{ $audio->urlBerkas() }}',
                    b: '{{ $audio->url('card') }}',
                    aktif: 'a',
                    posisi: 0,
                    main: false,
                    pindah(sisi) {
                        const el = this.$refs.pemutar;
                        this.posisi = el.currentTime;
                        this.aktif = sisi;
                        el.src = sisi === 'a' ? this.a : this.b;
                        el.currentTime = this.posisi;
                        if (this.main) { el.play(); }
                    }
                 }" data-media-audio>
                <p class="part-no">Contoh suara</p>

                <audio x-ref="pemutar" preload="metadata" class="mt-3 w-full"
                       src="{{ $audio->urlBerkas() }}"
                       controls
                       @play="main = true"
                       @pause="main = false"
                       @timeupdate="posisi = $el.currentTime">
                    {{-- Tanpa dukungan elemen audio, tetap ada tautan unduh. --}}
                    <a href="{{ $audio->urlBerkas() }}">Unduh contoh suara</a>
                </audio>

                <div class="mt-3 flex items-center gap-2">
                    <button type="button" class="btn btn-outline flex-1"
                            x-on:click="pindah('a')"
                            :class="aktif === 'a' ? 'btn-primary' : ''">
                        Versi A
                        @if ($audio->durasi_detik)
                            · {{ gmdate('i:s', (int) $audio->durasi_detik) }}
                        @endif
                    </button>
                    <button type="button" class="btn btn-outline flex-1"
                            x-on:click="pindah('b')"
                            :class="aktif === 'b' ? 'btn-primary' : ''"
                            @disabled(! $audio->url('card'))>
                        Versi B
                    </button>
                </div>

                <p class="mt-2 text-xs text-dim">
                    Dengarkan pada posisi yang sama saat berpindah versi.
                </p>
            </div>
        @endif
    </div>
@endif
