<?php

namespace App\Livewire\Admin;

use App\Models\Coupon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Performa KOL — siapa membagikan kupon, dan seberapa banyak hasilnya.
 *
 * Angkanya dihitung dari baris pesanan yang sudah memakai kupon
 * (`orders.coupon_id`), bukan dari hitungan klik tautan. Bedanya penting: klik
 * hanya menunjukkan minat, sedangkan baris pesanan menunjukkan uang yang
 * benar-benar masuk. KOL dibayar dari yang kedua.
 *
 * Yang tidak tersedia di sini: klik, kunjungan, atau sumber trafik. Menghitung
 * klik menuntut penanda di setiap halaman plus tempat menyimpan datanya, dan
 * jumlah itu tetap tidak menjawab pertanyaan yang sebenarnya berguna —
 * berapa penjualan yang dihasilkan.
 */
#[Layout('layouts.admin')]
class KolPerforma extends Component
{
    use WithPagination;

    #[Url(as: 'urut', history: true)]
    public string $urut = 'penjualan';

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function aturUrut(string $nilai): void
    {
        $this->urut = in_array($nilai, ['penjualan', 'pesanan', 'nama'], true) ? $nilai : 'penjualan';
        $this->resetPage();
    }

    /**
     * Satu baris per KOL.
     *
     * Pesanan yang dihitung hanya yang benar-benar sah: dibayar atau selesai.
     * Pesanan yang dibatalkan atau kedaluwarsa ikut dihitung akan membuat KOL
     * tampak menghasilkan padahal uangnya tidak pernah masuk.
     */
    public function daftarKol(): \Illuminate\Contracts\Pagination\Paginator
    {
        $kata = trim($this->cari);

        $kueri = Coupon::query()
            ->whereNotNull('kol_slug')
            ->when($kata !== '', fn ($q) => $q->where(function ($w) use ($kata): void {
                $w->where('kol_nama', 'like', '%'.$kata.'%')
                    ->orWhere('kol_slug', 'like', '%'.$kata.'%')
                    ->orWhere('code', 'like', '%'.$kata.'%');
            }))
            ->withCount([
                'orders as pesanan_sah' => fn ($q) => $q->whereIn('status', ['paid', 'completed']),
                'orders as pesanan_total',
            ])
            ->withSum([
                'orders as nilai_penjualan' => fn ($q) => $q->whereIn('status', ['paid', 'completed']),
            ], 'grand_total')
            ->withSum([
                'orders as potongan_diberikan' => fn ($q) => $q->whereIn('status', ['paid', 'completed']),
            ], 'discount_amount');

        match ($this->urut) {
            'pesanan' => $kueri->orderByDesc('pesanan_sah'),
            'nama' => $kueri->orderBy('kol_nama'),
            default => $kueri->orderByDesc('nilai_penjualan'),
        };

        return $kueri->paginate(15);
    }

    /** Total keseluruhan, untuk kepala halaman. */
    public function ringkasan(): array
    {
        $baris = Coupon::query()
            ->whereNotNull('kol_slug')
            ->withCount([
                'orders as pesanan_sah' => fn ($q) => $q->whereIn('status', ['paid', 'completed']),
            ])
            ->withSum([
                'orders as nilai_penjualan' => fn ($q) => $q->whereIn('status', ['paid', 'completed']),
            ], 'grand_total')
            ->get();

        return [
            'kol' => $baris->count(),
            'pesanan' => (int) $baris->sum('pesanan_sah'),
            'penjualan' => (float) $baris->sum('nilai_penjualan'),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.kol-performa', [
            'judul' => 'Performa KOL',
            'bagian' => 'Penjualan',
            'daftar' => $this->daftarKol(),
            'ringkas' => $this->ringkasan(),
        ]);
    }
}
