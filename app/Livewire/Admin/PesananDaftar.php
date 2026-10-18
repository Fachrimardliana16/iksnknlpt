<?php

namespace App\Livewire\Admin;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Services\StatusPesananService;
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

    public function aturStatus(string $status): void
    {
        $this->status = in_array($status, ['semua', ...StatusPesanan::values()], true) ? $status : 'semua';
        $this->resetPage();
    }

    public function aturBayar(string $bayar): void
    {
        $this->bayar = in_array($bayar, ['semua', ...StatusPembayaran::values()], true) ? $bayar : 'semua';
        $this->resetPage();
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
        $pesanan = Order::query()
            ->with(['user', 'items', 'payments'])
            ->when($this->status !== 'semua', fn ($q) => $q->where('status', $this->status))
            ->when($this->bayar !== 'semua', fn ($q) => $q->where('payment_status', $this->bayar))
            ->when(filled($this->cari), function ($q): void {
                $kata = trim($this->cari);

                // Nomor pesanan dapat dicari langsung. Email pelanggan
                // terenkripsi di kolom tamu, jadi pencariannya lewat indeks buta;
                // untuk pelanggan berakun, dicari lewat relasi namanya.
                $q->where(function ($w) use ($kata): void {
                    $w->where('order_number', 'like', '%'.$kata.'%')
                        ->orWhere('guest_email_bidx', \App\Support\BlindIndex::buat($kata, 'guest_email_bidx'))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$kata.'%'));
                });
            })
            ->when($this->urut === 'terbaru', fn ($q) => $q->orderByDesc('created_at'))
            ->when($this->urut === 'terlama', fn ($q) => $q->orderBy('created_at'))
            ->when($this->urut === 'terbesar', fn ($q) => $q->orderByDesc('grand_total'))
            ->when($this->urut === 'kedaluwarsa', fn ($q) => $q->where('status', StatusPesanan::Pending->value)->orderBy('expires_at'))
            ->paginate(20);

        return view('livewire.admin.pesanan-daftar', [
            'judul' => 'Pesanan',
            'bagian' => 'Penjualan',
            'pesanan' => $pesanan,
            'ringkas' => $this->ringkasan(),
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
