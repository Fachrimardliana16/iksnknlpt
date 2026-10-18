<?php

namespace App\Livewire\Admin;

use App\Enums\StatusPesanan;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Dashboard extends Component
{
    public function render(): View
    {
        $hariIni = now()->startOfDay();
        $bulanIni = now()->startOfMonth();

        $pesananHariIni = Order::query()->where('created_at', '>=', $hariIni)->count();

        $pendapatanHariIni = (float) Order::query()
            ->where('created_at', '>=', $hariIni)
            ->whereNotIn('status', [StatusPesanan::Cancelled->value])
            ->sum('grand_total');

        $menungguProses = Order::query()
            ->whereIn('status', [StatusPesanan::Paid->value, StatusPesanan::Processing->value])
            ->count();

        $ambangStok = (int) Pengaturan::instance()->angka('low_stock_threshold', 3);

        $stokMenipis = ProductVariant::query()
            ->where('is_active', true)
            ->whereRaw('(stock_quantity - reserved_quantity) <= ?', [$ambangStok])
            ->count();

        // Grafik 30 hari. Satu kueri agregat, bukan 30 kueri terpisah.
        $grafik = Order::query()
            ->selectRaw('date(created_at) as hari, count(*) as jumlah, sum(grand_total) as nilai')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->whereNotIn('status', [StatusPesanan::Cancelled->value])
            ->groupBy('hari')
            ->orderBy('hari')
            ->get()
            ->keyBy('hari');

        $seri = collect(range(29, 0))->map(function (int $mundur) use ($grafik): array {
            $tanggal = now()->subDays($mundur)->format('Y-m-d');
            $baris = $grafik->get($tanggal);

            return [
                'tanggal' => $tanggal,
                'label' => now()->subDays($mundur)->format('d/m'),
                'jumlah' => (int) ($baris->jumlah ?? 0),
                'nilai' => (float) ($baris->nilai ?? 0),
            ];
        });

        $maksNilai = max(1.0, (float) $seri->max('nilai'));

        $terbaru = Order::query()
            ->with('items')
            ->latest('created_at')
            ->take(10)
            ->get();

        $terlaris = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $bulanIni)
            ->whereNotIn('orders.status', [StatusPesanan::Cancelled->value])
            ->selectRaw('order_items.product_name as nama, sum(order_items.quantity) as terjual, sum(order_items.subtotal) as nilai')
            ->groupBy('order_items.product_name')
            ->orderByDesc('terjual')
            ->limit(5)
            ->get();

        // Peringatan yang berasal dari data nyata, bukan daftar tetap.
        $peringatan = [];

        // Sebagian modul yang dituju peringatan ini belum dibangun. Memanggil
        // route() untuk rute yang tidak ada melempar pengecualian, dan seluruh
        // dashboard gagal render — peringatan yang seharusnya membantu justru
        // mematikan halaman. Arahkan ke dashboard bila rutenya belum ada.
        $rute = fn (string $nama, array $parameter = []): string => Route::has($nama)
            ? route($nama, $parameter)
            : route('admin.dashboard');

        $pendingTua = Order::query()
            ->where('status', StatusPesanan::Pending->value)
            ->where('created_at', '<=', now()->subDay())
            ->count();

        if ($pendingTua > 0) {
            $peringatan[] = [
                'nada' => 'warn',
                'teks' => $pendingTua.' pesanan belum dibayar lebih dari 24 jam.',
                'rute' => $rute('admin.orders.index', ['status' => 'pending']),
            ];
        }

        $tanpaGambar = Product::query()->doesntHave('media')->count();

        if ($tanpaGambar > 0) {
            $peringatan[] = [
                'nada' => 'warn',
                'teks' => $tanpaGambar.' produk belum punya gambar.',
                'rute' => $rute('admin.products.index'),
            ];
        }

        $pesanBaru = ContactMessage::query()->belumDibaca()->count();

        if ($pesanBaru > 0) {
            $peringatan[] = [
                'nada' => 'steel',
                'teks' => $pesanBaru.' pesan kontak belum dibaca.',
                'rute' => $rute('admin.customers.index'),
            ];
        }

        if (blank(Pengaturan::instance()->teks('whatsapp_number'))) {
            $peringatan[] = [
                'nada' => 'danger',
                'teks' => 'Nomor WhatsApp belum diisi. Tombol WhatsApp tidak tampil di situs.',
                'rute' => $rute('admin.settings.index', ['tab' => 'contact']),
            ];
        }

        return view('livewire.admin.dashboard', [
            'judul' => 'Dashboard',
            'bagian' => 'Ringkasan',
            'kartu' => [
                ['label' => 'Pesanan hari ini', 'nilai' => (string) $pesananHariIni, 'ikon' => 'receipt'],
                ['label' => 'Pendapatan hari ini', 'nilai' => rupiah($pendapatanHariIni), 'ikon' => 'chart'],
                ['label' => 'Menunggu diproses', 'nilai' => (string) $menungguProses, 'ikon' => 'box'],
                ['label' => 'Stok menipis', 'nilai' => (string) $stokMenipis, 'ikon' => 'alert'],
            ],
            'seri' => $seri,
            'maksNilai' => $maksNilai,
            'terbaru' => $terbaru,
            'terlaris' => $terlaris,
            'peringatan' => $peringatan,
        ])->layout('layouts.admin', ['judul' => 'Dashboard', 'bagian' => 'Ringkasan']);
    }
}
