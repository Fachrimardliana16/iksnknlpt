<?php

namespace App\Livewire\Admin;

use App\Enums\GrupPengaturan;
use App\Services\Gambar\PengecilGambar;
use App\Services\Pembayaran\KlienPaypal;
use App\Services\Pengaturan\PenulisPengaturan;
use App\Support\Keamanan;
use App\Support\KomponenPengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Web Setting lima tab (checklist F4-01..F4-08).
 *
 * Satu komponen menangani kelima tab supaya perpindahan tab tidak memuat ulang
 * halaman dan nilai yang belum disimpan tidak hilang saat admin berpindah.
 * Penyimpanan tetap per tab: mengubah nomor WhatsApp tidak ikut menulis ulang
 * kredensial PayPal.
 */
#[Layout('layouts.admin')]
class PengaturanSitus extends KomponenAdmin
{
    use WithFileUploads;

    /** Tab aktif. Disimpan di URL supaya dapat ditautkan langsung. */
    #[Url(as: 'tab', history: true)]
    public string $tab = 'identity';

    /** Nilai formulir tab aktif, termasuk yang belum disimpan. */
    public array $nilai = [];

    /**
     * Berkas gambar yang baru dipilih, satu per kunci pengaturan. Livewire
     * mengunggahnya ke penyimpanan sementara, bukan langsung ke disk publik,
     * sehingga berkas belum tayang sebelum admin menekan simpan.
     *
     * @var array<string, mixed>
     */
    public array $berkas = [];

    public ?string $pesanSukses = null;

    public ?string $galat = null;

    /** @var array<string, mixed>|null hasil uji koneksi PayPal */
    public ?array $hasilPaypal = null;

    public bool $sedangUji = false;

    /** Nomor versi data, dipakai untuk memuat ulang formulir setelah simpan. */
    public int $versi = 0;

    public function mount(?string $tab = null): void
    {

        if ($tab !== null && $this->tabSah($tab)) {
            $this->tab = $tab;
        }

        if (! $this->tabSah($this->tab)) {
            $this->tab = GrupPengaturan::Identitas->value;
        }

        $this->muat();
    }

    /** Pindah tab tanpa memuat ulang halaman. */
    public function pilihTab(string $tab): void
    {
        if (! $this->tabSah($tab)) {
            return;
        }

        $this->tab = $tab;
        $this->bersihkanPesan();
        $this->muat();
    }

    public function simpan(PenulisPengaturan $penulis): void
    {
        // Diperiksa di server, bukan hanya dengan menyembunyikan tombol.
        // Permintaan Livewire dapat dikirim langsung tanpa melewati antarmuka,
        // sehingga menyembunyikan tombolnya saja tidak menghalangi apa pun.
        $this->authorize('aturSitus', \App\Models\User::class);

        $this->bersihkanPesan();

        $this->validate(
            $this->aturanBerprefiks(),
            [],
            $this->labelBerprefiks(),
        );

        $this->unggahGambarTertunda($penulis);

        $nilai = KomponenPengaturan::saringKosong($this->nilai);

        if ($nilai === []) {
            $this->pesanSukses = 'Tidak ada perubahan untuk disimpan.';

            return;
        }

        try {
            $hasil = $penulis->simpan($nilai);
        } catch (Throwable $e) {
            Keamanan::catat(
                \App\Enums\TipeKejadianKeamanan::AksesTidakSah,
                request(),
                auth()->user()?->email,
                auth()->id(),
                'Gagal simpan pengaturan tab '.$this->tab,
            );
            \Illuminate\Support\Facades\Log::error('Pengaturan gagal disimpan', [
                'tab' => $this->tab,
                'sebab' => $e->getMessage(),
            ]);

            $this->galat = 'Pengaturan gagal disimpan. Periksa kembali isian pada tab ini.';

            return;
        }

        $this->versi++;

        if ($hasil['diubah'] === []) {
            $this->pesanSukses = 'Tidak ada perubahan untuk disimpan.';
        } else {
            $this->pesanSukses = count($hasil['diubah']).' pengaturan tersimpan: '.implode(', ', $hasil['diubah']).'.';
        }

        // Muat ulang dari basis data supaya yang tampil benar-benar nilai
        // tersimpan, bukan sisa formulir.
        $this->muat();

        if ($this->tab === GrupPengaturan::Pembayaran->value) {
            // Hasil uji koneksi sebelumnya tidak lagi mewakili kredensial baru.
            $this->hasilPaypal = null;
        }
    }

    /**
     * Uji koneksi PayPal memakai kredensial TERSIMPAN, bukan yang sedang
     * diketik. Kalau admin baru mengubah kredensial dan belum menyimpan, yang
     * diuji masih yang lama — itu memang yang akan dipakai toko.
     */
    public function ujiKoneksiPaypal(KlienPaypal $klien): void
    {
        $this->bersihkanPesan();
        $this->sedangUji = true;

        try {
            $this->hasilPaypal = $klien->ujiKoneksi();
        } catch (Throwable $e) {
            $this->hasilPaypal = [
                'ok' => false,
                'pesan' => 'Pengujian gagal dijalankan: '.$e->getMessage(),
                'detail' => [],
            ];
        } finally {
            $this->sedangUji = false;
        }
    }

    /** Hapus kredensial tersimpan. Nilainya tidak pernah ditampilkan lagi. */
    public function hapusKredensial(string $kunci): void
    {
        $this->authorize('aturSitus', \App\Models\User::class);

        $this->bersihkanPesan();

        if (! in_array($kunci, KomponenPengaturan::KUNCI_RAHASIA, true)) {
            $this->galat = 'Kunci "'.$kunci.'" bukan kredensial yang dapat dihapus.';

            return;
        }

        KomponenPengaturan::hapus($kunci);

        $this->nilai[$kunci] = '';
        $this->nilai[KomponenPengaturan::kunciTerisi($kunci)] = false;
        $this->hasilPaypal = null;

        $this->pesanSukses = 'Kredensial "'.$kunci.'" dihapus. Pengaturan ini sekarang kosong.';
    }

    /**
     * Simpan berkas gambar yang dipilih sebelum nilai teks disimpan, supaya
     * kunci gambar sudah berisi jalur yang sah ketika divalidasi.
     */
    private function unggahGambarTertunda(PenulisPengaturan $penulis): void
    {
        if ($this->berkas === []) {
            return;
        }

        $pengecil = app(PengecilGambar::class);

        foreach ($this->berkas as $kunci => $unggahan) {
            if ($unggahan === null) {
                continue;
            }

            $this->validate([
                'berkas.'.$kunci => ['file', 'image', 'mimes:jpeg,png,webp,avif', 'max:5120'],
            ], [], [$kunci => 'Berkas '.$kunci]);

            $jalur = $penulis->simpanGambar($kunci, $unggahan, $pengecil);

            $this->nilai[$kunci] = $jalur;
            $this->berkas[$kunci] = null;
        }
    }

    /** Isi ulang nilai formulir untuk tab aktif. */
    private function muat(): void
    {
        $this->nilai = KomponenPengaturan::formulir($this->tab);
        $this->berkas = [];
    }

    /**
     * Livewire menuntut setiap kunci aturan menunjuk properti yang benar-benar
     * ada. Karena nilainya berada di dalam properti array $nilai, kuncinya harus
     * berawalan "nilai." — tanpa awalan itu, Livewire menolak sebelum validasi
     * dijalankan dengan galat "No property found for validation".
     *
     * @return array<string, array<int, mixed>>
     */
    private function aturanBerprefiks(): array
    {
        $aturan = [];

        foreach (KomponenPengaturan::aturan($this->tab) as $kunci => $daftar) {
            $aturan['nilai.'.$kunci] = $daftar;
        }

        return $aturan;
    }

    /** @return array<string, string> */
    private function labelBerprefiks(): array
    {
        $label = [];

        foreach (KomponenPengaturan::label($this->tab) as $kunci => $teks) {
            $label['nilai.'.$kunci] = $teks;
        }

        return $label;
    }

    private function bersihkanPesan(): void
    {
        $this->pesanSukses = null;
        $this->galat = null;
    }

    private function tabSah(string $tab): bool
    {
        return in_array($tab, GrupPengaturan::values(), true);
    }

    public function render(): View
    {
        $grup = GrupPengaturan::from($this->tab);

        return view('livewire.admin.pengaturan', [
            'judul' => 'Pengaturan Situs',
            'daftarTab' => collect(GrupPengaturan::cases())->map(fn (GrupPengaturan $g): array => [
                'nilai' => $g->value,
                'label' => $g->label(),
                'ikon' => $g->ikon(),
            ]),
            'grup' => $grup,
            'baris' => $this->barisTampil($grup),
        ])->layout('layouts.admin', ['judul' => 'Pengaturan Situs', 'bagian' => 'Situs']);
    }

    /**
     * Metadata kolom untuk tab aktif. Nilai rahasia diganti penanda kosong dan
     * tidak pernah ikut ke tampilan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function barisTampil(GrupPengaturan $grup): Collection
    {
        return Setting::query()
            ->where('group_name', $grup->value)
            ->orderBy('id')
            ->get()
            ->map(fn (Setting $s): array => [
                'kunci' => $s->key,
                'label' => $s->label,
                'tipe' => $s->type,
                'opsi' => $s->options_json ?? [],
                'rahasia' => $s->is_encrypted,
                'terisi' => $s->is_encrypted && filled($s->value),
                'bantuan' => $this->bantuan($s->key),
            ]);
    }

    /** Keterangan tambahan di bawah kolom. Tidak semua kunci punya. */
    private function bantuan(string $kunci): ?string
    {
        return match ($kunci) {
            'whatsapp_number' => 'Format internasional tanpa tanda plus, contoh 628123456789.',
            'whatsapp_message_template' => 'Placeholder tersedia: {nama_produk}, {url_produk}, {nama_toko}.',
            'maps_embed' => 'Tempel kode sematan dari Google Maps, atau isi koordinat di bawah.',
            'paypal_client_id', 'paypal_client_secret' => 'Nilai tersimpan tidak dapat ditampilkan lagi. Ketik ulang hanya bila ingin menggantinya.',
            'manual_bank_accounts' => 'Simpan sebagai larik JSON: [{"bank":"BCA","nomor":"123","atas_nama":"Nama"}].',
            'order_prefix' => 'Dipakai di depan nomor pesanan, contoh IK-20261017-0001.',
            'low_stock_threshold' => 'Peringatan stok menipis muncul saat sisa stok mencapai angka ini.',
            'maintenance_mode' => 'Saat aktif, pengunjung melihat halaman pemeliharaan. Panel admin tetap dapat diakses.',
            'notif_item_stok_habis' => 'Varian yang benar-benar tidak dapat dipesan lagi. Paling mendesak.',
            'notif_item_stok_menipis' => 'Varian yang sisanya di bawah ambang stok menipis.',
            'notif_item_pesanan_siap_kirim' => 'Pesanan lunas yang belum dikirim.',
            'notif_item_hampir_batal' => 'Pesanan menunggu bayar yang mendekati batas 24 jam.',
            'notif_item_masuk_keranjang' => 'Keranjang yang masih aktif dalam 24 jam terakhir.',
            'notif_kirim_dibuat' => 'Dikirim ke pembeli saat pesanan baru masuk.',
            'notif_kirim_dibayar' => 'Dikirim ke pembeli setelah pembayaran diverifikasi.',
            'notif_kirim_dikirim' => 'Dikirim ke pembeli saat pesanan berubah menjadi dikirim.',
            'notif_push_perangkat' => 'Notifikasi panel muncul di peramban walau panel tidak terbuka. Perlu izin peramban, satu kali per perangkat.',
            'notif_push_surel_cadangan' => 'Bila push tidak sampai — belum ada perangkat berlangganan atau layanan push bermasalah — kirim surel ke admin sebagai gantinya.',
            default => null,
        };
    }
}
