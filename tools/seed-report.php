<?php

use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

$tabel = [
    'settings', 'users', 'addresses', 'categories', 'products', 'product_variants',
    'product_media', 'hero_slides', 'coupons', 'orders', 'order_items',
    'order_status_history', 'payments', 'contact_messages',
];

$hasil = [];

foreach ($tabel as $t) {
    $hasil[$t] = DB::table($t)->count();
}

$khusus = [
    'produk_unggulan' => Product::query()->where('is_featured', true)->count(),
    'produk_disembunyikan' => Product::query()->where('is_published', false)->count(),
    'produk_tanpa_gambar' => Product::query()->doesntHave('media')->count(),
    'varian_stok_nol' => ProductVariant::query()->where('stock_quantity', 0)->count(),
    'varian_selisih_negatif' => ProductVariant::query()->where('price_adjustment', '<', 0)->count(),
    'produk_ada_stok' => Product::query()->adaStok()->count(),
    'kupon_kedaluwarsa' => Coupon::query()->where('ends_at', '<', now())->count(),
    'pesanan_tamu' => Order::query()->whereNull('user_id')->count(),
    'pesan_belum_dibaca' => ContactMessage::query()->where('is_read', false)->count(),
    'setelan_terenkripsi' => Setting::query()->where('is_encrypted', true)->count(),
    'setelan_publik' => Setting::query()->where('is_public', true)->count(),
    'pengaturan_cache_terisi' => Pengaturan::instance()->semua() === [] ? 0 : count(Pengaturan::instance()->semua()),
    'hero_tayang' => \App\Models\HeroSlide::query()->tayang()->count(),
    'kategori_aktif' => Category::query()->aktif()->count(),
];

$perStatus = Order::query()
    ->selectRaw('status, count(*) as n')
    ->groupBy('status')
    ->pluck('n', 'status')
    ->all();

$berkasGambar = File::allFiles(storage_path('app/public/products'));
$berkasHero = File::allFiles(storage_path('app/public/hero'));

echo json_encode([
    'baris' => $hasil,
    'khusus' => $khusus,
    'pesanan_per_status' => $perStatus,
    'berkas_gambar_produk' => count($berkasGambar),
    'berkas_hero' => count($berkasHero),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
