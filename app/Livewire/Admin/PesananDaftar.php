<?php

namespace App\Livewire\Admin;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Services\StatusPesananService;
use App\Support\BlindIndex;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Daftar pesanan.
 *
 * Satu halaman untuk seluruh siklus: mencari, menyaring, melihat ringkasan
 * uang yang belum masuk, lalu membuka pesanan untuk diproses.
 */
#[Layout('layouts.admin')]
class PesananDaftar extends Component
{
    use WithPagination;

    #[Url(as: 'status', history: true)]
    public string $status = 'semua';

    #[Url(as: 'bayar', history: true)]
    public string $bayar = 'semua';

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    #[Url(as: 'urut', history: true)]
    public string $urut = 'terbaru';

    /**
     * Saringan yang datang dari URL dibersihkan saat masuk.
     *
     * Tanpa ini, nilai pada atribut #[Url] masuk apa adanya dari query string:
     * atribut itu mengisi properti langsung, tidak lewat mount(). Lihat
     * komentar pada saring() untuk akibat yang terlihat oleh admin.
     */
    public function mount(): void
    {
        $this->status = self::saring($this->status, StatusPesanan::values());
        $this->bayar = self::saring($this->bayar, StatusPembayaran::values());
    }

    public function aturStatus(string $status): void
    {
        $this->status = self::saring($status, StatusPesanan::values());
        $this->resetPage();
    }

    public function aturBayar(string $bayar): void
    {
        $this->bayar = self::saring($bayar, StatusPembayaran::values());
        $this->resetPage();
    }

    /**
     * Nilai yang boleh dipakai saringan, dari daftar yang dikenal.
     *
     * Dipakai di DUA tempat, dan itu alasannya: atribut #[Url] mengisi properti
     * langsung dari query string TANPA melalui mount(), sedangkan tombol di
     * halaman memanggil aturStatus()/aturBayar(). Bila penyaringan hanya ada di
     * method, seorang yang membuka /admin/pesanan?status=ngawur mendapat daftar
     * kosong dengan alasan yang tidak tertulis — bukan "saringan tidak dikenal",
     * melainkan "tidak ada pesanan pada saringan ini", yang mudah dibaca
     * sebagai pesanan yang hilang.
     *
     * @param  array<int, string>  $dikenal
     */
    private static function saring(string $nilai, array $dikenal): string
    {
        return in_array($nilai, ['semua', ...$dikenal], true) ? $nilai : 'semua';
    }

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedUrut(): void
    {
        $this->resetPage();
    }

    /** Terima pembayaran manual langsung dari daftar. */
    public function tandaiDibayar(int $id, StatusPesananService $layanan): void
    {
        $order = Order::query()->find($id);

        if ($order === null) {
            return;
        }

        try {
            $layanan->pindah($order, StatusPesanan::Paid, auth()->user(), 'Ditandai lunas dari daftar pesanan');
            session()->flash('pesan-admin', 'Pesanan '.$order->order_number.' ditandai lunas.');
        } catch (RuntimeException $e) {
            session()->flash('galat-admin', $e->getMessage());
        }
    }

    public function render(): View
    {
        $kata = trim($this->cari);

        /*
        | Penyaringan.
        |
        | Nomor pesanan dan email tamu dapat dicek di SQL: nomor berindeks,
        | email tamu berindeks buta. Nama penerima tidak — namanya berada di
        | dalam snapshot alamat yang terenkripsi, sehingga tidak ada polanya di
        | basis data. Menyaringnya berarti membaca lebih dulu, mendekripsi di
        | PHP, lalu mencocokkan; itulah yang terjadi di blok kedua.
        */
        $saringan = fn ($q) => $q
            ->when($this->status !== 'semua', fn ($x) => $x->where('status', $this->status))
            ->when($this->bayar !== 'semua', fn ($x) => $x->where('payment_status', $this->bayar))
            ->when($kata !== '', function ($x) use ($kata): void {
                $x->where(function ($w) use ($kata): void {
                    $w->where('order_number', 'like', '%'.$kata.'%')
                        ->orWhere('guest_email_bidx', BlindIndex::buat($kata, 'guest_email_bidx'))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$kata.'%'));
                });
            });

        $urutkan = fn ($q) => $q
            ->when($this->urut === 'terbaru', fn ($x) => $x->orderByDesc('created_at'))
            ->when($this->urut === 'terlama', fn ($x) => $x->orderBy('created_at'))
            ->when($this->urut === 'terbesar', fn ($x) => $x->orderByDesc('grand_total'))
            ->when($this->urut === 'kedaluwarsa', fn ($x) => $x->where('status', StatusPesanan::Pending->value)->orderBy('expires_at'));

        // Nama penerima cocok, tetapi tidak lolos saringan kolom. Baris-baris
        // itu diambil terpisah dan ditempel di depan hasil halaman pertama —
        // tanpa ini, mencari "Budi" yang hanya tertulis di alamat kirim tidak
        // menemukan apa pun, dan admin menyimpulkan pesanannya tidak ada.
        $cocokNama = collect();

        if ($kata !== '') {
            $lain = $saringan(Order::query()->with(['user', 'items', 'payments']))
                ->when($this->urut === 'terbaru', fn ($x) => $x->orderByDesc('created_at'))
                ->when($this->urut === 'terlama', fn ($x) => $x->orderBy('created_at'))
                ->when($this->urut === 'terbesar', fn ($x) => $x->orderByDesc('grand_total'))
                ->when($this->urut === 'kedaluwarsa', fn ($x) => $x->orderBy('expires_at'))
                ->limit(300)
                ->get();

            $cocokNama = $lain
                ->filter(fn (Order $o): bool => mb_stripos($o->namaPenerima(), $kata) !== false)
                ->values();
        }

        $dariKolom = $saringan(Order::query()->with(['user', 'items', 'payments']));
        $dariKolom = $urutkan($dariKolom);

        $idCocokNama = $cocokNama->pluck('id')->all();

        if ($idCocokNama !== []) {
            $dariKolom->whereNotIn('id', $idCocokNama);
        }

        $halaman = $dariKolom->paginate(25);

        // Hasil nama ditempel di halaman pertama, di atas hasil kolom.
        if ($idCocokNama !== [] && $halaman->currentPage() === 1) {
            $halaman->setCollection(
                $cocokNama->concat($halaman->getCollection())->take($halaman->perPage())
            );
        }

        return view('livewire.admin.pesanan-daftar', [
            'judul' => 'Pesanan',
            'bagian' => 'Penjualan',
            'pesanan' => $halaman,
            'ringkas' => $this->ringkasan(),
            'cocokNama' => $cocokNama->count(),
        ])->layout('layouts.admin', ['judul' => 'Pesanan', 'bagian' => 'Penjualan']);
    }

    /**
     * Angka yang menentukan prioritas kerja hari ini: berapa yang perlu
     * dikirim, berapa uang yang belum masuk, dan berapa yang hampir batal.
     *
     * @return array<string, mixed>
     */
    private function ringkasan(): array
    {
        $hitungan = Order::query()
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $perluDikirim = Order::query()
            ->whereIn('status', [StatusPesanan::Paid->value, StatusPesanan::Processing->value])
            ->count();

        $belumBayar = Order::query()
            ->where('status', StatusPesanan::Pending->value)
            ->count();

        $nilaiBelumMasuk = (float) Order::query()
            ->where('payment_status', '!=', StatusPembayaran::Paid->value)
            ->whereNotIn('status', [StatusPesanan::Cancelled->value])
            ->sum('grand_total');

        return [
            'perluDikirim' => $perluDikirim,
            'belumBayar' => $belumBayar,
            'nilaiBelumMasuk' => $nilaiBelumMasuk,
            'selesaiBulanIni' => Order::query()
                ->where('status', StatusPesanan::Completed->value)
                ->where('completed_at', '>=', now()->startOfMonth())
                ->count(),
            'perStatus' => $hitungan,
        ];
    }

    /** Nama pembeli, baik dari akun maupun dari checkout tamu. */
    public function pembeli(Order $order): string
    {
        return $order->namaPenerima() ?: ($order->user?->name ?? 'Pembeli');
    }

    public function kontak(Order $order): ?string
    {
        return $order->emailPenerima() ?? $order->user?->email;
    }

    /** Berapa jam lagi pesanan ini batal otomatis. Null bila tidak berlaku. */
    public function sisaJam(Order $order): ?float
    {
        if ($order->status !== StatusPesanan::Pending || $order->expires_at === null) {
            return null;
        }

        if ($order->expires_at->isPast()) {
            return 0.0;
        }

        return round(now()->diffInMinutes($order->expires_at) / 60, 1);
    }
}
