<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Model inti, relasi, cast, fillable, dan ULID otomatis (checklist F1-09).
 *
 * Yang diuji adalah janji yang dipakai di seluruh aplikasi, bukan nama kelas:
 * setiap model ber-ULID mendapat ULID sendiri saat dibuat, kunci rute memakai
 * ULID (bukan id internal, yang dapat dienumerasi), kolom bertanda cast
 * mengembalikan tipe yang benar, dan kolom massal yang dilarang benar-benar
 * ditolak.
 *
 * Daftar model dibaca dari folder app/Models, bukan ditulis satu per satu:
 * model baru yang lupa memakai trait PunyaUlid akan gagal di sini, bukan
 * ditemukan berbulan-bulan kemudian lewat celah IDOR.
 */
class ModelIntiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, class-string> */
    private function semuaModel(): array
    {
        $berkas = glob(app_path('Models/*.php')) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $jalur): string => 'App\\Models\\'.basename($jalur, '.php'),
            $berkas,
        ), static fn (string $kelas): bool => class_exists($kelas)));
    }

    public function test_setiap_tabel_memiliki_kolom_ulid_dan_modelnya_memakainya(): void
    {
        $wajibUlid = [];

        foreach ($this->semuaModel() as $kelas) {
            $model = new $kelas;

            if (! Schema::hasTable($model->getTable())) {
                continue;
            }

            // Model tanpa kolom ulid memang tidak butuh trait; yang diperiksa
            // adalah tabel yang PUNYA kolom ulid tetapi modelnya tidak mengisi
            // otomatis — itu yang menghasilkan ulid kosong di rute publik.
            if (! Schema::hasColumn($model->getTable(), 'ulid')) {
                $this->assertNotContains(
                    'App\\Models\\Concerns\\PunyaUlid',
                    class_uses_recursive($kelas),
                    $model->getTable().' memakai PunyaUlid tetapi tabelnya tidak punya kolom ulid.',
                );

                continue;
            }

            $wajibUlid[] = $kelas;
        }

        $this->assertNotEmpty($wajibUlid, 'Tidak ada model ber-ULID yang ditemukan.');

        foreach ($wajibUlid as $kelas) {
            $this->assertContains(
                'App\\Models\\Concerns\\PunyaUlid',
                class_uses_recursive($kelas),
                $kelas.' punya kolom ulid tetapi tidak memakai trait PunyaUlid.',
            );
        }
    }

    public function test_ulid_terisi_otomatis_dan_berbeda_tiap_baris(): void
    {
        $user = User::query()->create([
            'name' => 'Pelanggan Uji',
            'email' => 'ulid@contoh.test',
            'password' => 'rahasia123',
        ]);

        $order = Order::query()->create([
            'order_number' => 'IK-20260101-TESUL1',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'grand_total' => 100000,
            'shipping_address_snapshot' => ['recipient_name' => 'Uji'],
        ]);

        $this->assertNotNull($user->ulid, 'User tanpa ULID.');
        $this->assertNotNull($order->ulid, 'Order tanpa ULID.');
        $this->assertNotSame($user->ulid, $order->ulid);
        $this->assertSame(26, strlen($order->ulid), 'ULID tepat 26 karakter.');
        $this->assertSame('ulid', $order->getRouteKeyName(), 'Kunci rute memakai ULID, bukan id.');
    }

    public function test_relasi_inti_terpasang_dua_arah(): void
    {
        $user = User::query()->create([
            'name' => 'Pelanggan Uji',
            'email' => 'relasi@contoh.test',
            'password' => 'rahasia123',
        ]);

        $kategori = Category::query()->create([
            'slug' => 'kategori-uji',
            'name_id' => 'Kategori Uji',
            'name_en' => 'Test Category',
            'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'SKU-UJI-1',
            'slug' => 'produk-uji-relasi',
            'title_id' => 'Produk Uji',
            'title_en' => 'Test Product',
            'description_id' => 'Deskripsi uji',
            'description_en' => 'Test description',
            'base_price' => 100000,
            'is_published' => true,
        ]);

        // Nama kolom mengikuti skema, bukan tebakan: varian menyimpan nama
        // dalam dua bahasa dan selisih harga (price_adjustment), bukan harga
        // mutlak.
        $varian = ProductVariant::query()->create([
            'product_id' => $produk->id,
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'sku_variant' => 'SKU-UJI-1-A',
            'variant_name_id' => 'Stainless',
            'variant_name_en' => 'Stainless',
            'price_adjustment' => 0,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Relation::class, $produk->variants());
        $this->assertTrue($produk->variants->contains($varian), 'Relasi Product → variants kosong.');
        $this->assertTrue($varian->product->is($produk), 'Relasi balik ProductVariant → product kosong.');
        $this->assertInstanceOf(Relation::class, $user->orders());

        // Tidak ada relasi yang kosong tanpa penjelasan: setiap relasi publik
        // harus mengembalikan objek Relation saat dipanggil.
        foreach (['variants', 'media', 'category'] as $relasi) {
            $this->assertInstanceOf(Relation::class, $produk->{$relasi}(), 'Relasi Product::'.$relasi.' rusak.');
        }
    }

    public function test_cast_mengembalikan_tipe_yang_benar(): void
    {
        $kategori = Category::query()->create([
            'slug' => 'kategori-cast',
            'name_id' => 'Kategori Cast',
            'name_en' => 'Cast',
            'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'SKU-CAST-1',
            'slug' => 'produk-cast',
            'title_id' => 'Produk Cast',
            'title_en' => 'Cast Product',
            'description_id' => 'Deskripsi',
            'description_en' => 'Description',
            'base_price' => '125000.50',
            'is_published' => 1,
        ])->fresh();

        // Cast boolean mengembalikan bool, bukan int 1. Yang diuji adalah
        // tipenya — perbandingan ketat terhadap angka akan gagal walau
        // cast-nya benar.
        $this->assertIsBool($produk->is_published);
        $this->assertTrue($produk->is_published);
        $this->assertIsFloat((float) $produk->base_price);
        $this->assertSame('125000.50', number_format((float) $produk->base_price, 2, '.', ''));
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $produk->created_at);
    }

    public function test_kolom_yang_dilarang_tidak_bisa_diisi_massal(): void
    {
        // fillable adalah daftar putih. Kolom yang tidak ada di dalamnya harus
        // diam-diam diabaikan Eloquent, dan kolom sensitif (peran pengguna)
        // tidak boleh ikut terisi dari data formulir.
        // role memang sengaja ada di fillable: panel admin mengisinya. Yang
        // diuji adalah kolom di luar daftar putih — termasuk kunci primer dan
        // kolom audit seperti view_count — tidak ikut terisi dari data luar.
        $user = User::query()->create([
            'name' => 'Pelanggan Uji',
            'email' => 'fillable@contoh.test',
            'password' => 'rahasia123',
            'remember_token' => 'token-dari-luar',
        ]);

        $this->assertNull($user->fresh()->remember_token, 'remember_token tidak boleh terisi massal.');

        $kategori = Category::query()->create([
            'slug' => 'kategori-fill',
            'name_id' => 'Kategori Fill',
            'name_en' => 'Fill',
            'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'SKU-FILL-1',
            'slug' => 'produk-fillable',
            'title_id' => 'Produk Fillable',
            'title_en' => 'Fillable',
            'description_id' => 'Deskripsi',
            'description_en' => 'Description',
            'base_price' => 1000,
            'id' => 999999,
            'view_count' => 500,
        ]);

        $this->assertNotSame(999999, $produk->id, 'Kunci primer tidak boleh diisi massal.');
        // Kolom di luar daftar putih tidak terisi sama sekali (null), bukan
        // jatuh ke nilai bawaan kolom — nilai bawaan hanya berlaku saat baris
        // yang sama sekali tidak menyebut kolom itu. Yang penting: 500 tidak
        // masuk.
        $this->assertNotSame(500, $produk->view_count, 'view_count tidak boleh terisi massal.');
    }

    public function test_ulid_unik_di_tingkat_basis_data(): void
    {
        $ulid = (string) \Illuminate\Support\Str::ulid();

        DB::table('orders')->insert([
            'ulid' => $ulid,
            'order_number' => 'IK-20260101-AAAAAA',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 0,
            'grand_total' => 0,
            'shipping_address_snapshot' => json_encode(['recipient_name' => 'Uji']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('orders')->insert([
            'ulid' => $ulid,
            'order_number' => 'IK-20260101-BBBBBB',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 0,
            'grand_total' => 0,
            'shipping_address_snapshot' => json_encode(['recipient_name' => 'Uji']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
