<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\Order;
use App\Support\Pengaturan;
use Illuminate\Support\Collection;

/**
 * Kupon bertingkat dan program loyalitas (Fase 2: P2-06).
 *
 * Dua bentuk yang ditambahkan di luar kupon biasa:
 *
 * 1. **Bertingkat** — potongan makin besar saat belanja makin besar. Nilainya
 *    disimpan di `max_discount` sebagai larik JSON bertingkat:
 *    [{"min": 500000, "persen": 5}, {"min": 1500000, "persen": 10}].
 *    Pembeli yang belanjanya melewati dua tangga mendapat yang tertinggi, bukan
 *    dijumlahkan: menjumlahkan berarti diskon dapat melebihi nilai belanjanya.
 * 2. **Loyalitas** — pelanggan yang sudah menyelesaikan sejumlah pesanan
 *    mendapat satu kupon otomatis. Ambang dan nilainya dari pengaturan, sehingga
 *    dapat diubah tanpa menyentuh kode.
 *
 * Keduanya sengaja tidak menumpuk dengan kupon lain: kupon yang sedang dipakai
 * tetap yang menentukan, dan kupon loyalitas dipakai pada pesanan berikutnya.
 */
class KuponLanjutan
{
    /**
     * Potongan untuk kupon bertingkat, atau null bila kupon ini bukan bertingkat.
     */
    public static function potonganBertingkat(Coupon $kupon, float $subtotal): ?float
    {
        $tangga = self::tangga($kupon);

        if ($tangga === []) {
            return null;
        }

        $terpilih = null;

        foreach ($tangga as $anak) {
            $min = (float) ($anak['min'] ?? 0);

            if ($subtotal >= $min) {
                // Yang dipakai adalah tangga tertinggi yang tercapai.
                if ($terpilih === null || $min >= (float) ($terpilih['min'] ?? 0)) {
                    $terpilih = $anak;
                }
            }
        }

        if ($terpilih === null) {
            return null;
        }

        $persen = (float) ($terpilih['persen'] ?? 0);
        $nominal = (float) ($terpilih['nominal'] ?? 0);

        $potongan = $persen > 0 ? $subtotal * $persen / 100 : $nominal;

        // Tidak pernah melebihi nilai belanja: potongan yang lebih besar dari
        // subtotal berarti toko menambahkan uang ke pesanan.
        return min($potongan, $subtotal);
    }

    /**
     * Larik tangga dari sebuah kupon.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function tangga(Coupon $kupon): array
    {
        // Cast BatasAtauTangga sudah mengubah larik JSON menjadi larik PHP;
        // bentuk angka kembali sebagai string desimal.
        $data = $kupon->max_discount;

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter(
            $data,
            static fn ($anak): bool => is_array($anak) && isset($anak['min']),
        ));
    }

    /** Apakah kupon ini memakai tangga, bukan batas potongan biasa. */
    public static function bertingkat(Coupon $kupon): bool
    {
        return self::tangga($kupon) !== [];
    }

    /**
     * Membuat kupon loyalitas untuk pelanggan, bila ia memenuhi ambang.
     *
     * Dipanggil setelah pesanan selesai. Yang dihitung adalah pesanan yang
     * benar-benar selesai — bukan pesanan yang dibatalkan atau masih berjalan.
     *
     * @return Coupon|null kupon yang baru dibuat, atau null bila belum berhak
     */
    public static function beriKuponLoyalitas(int $userId): ?Coupon
    {
        $ambang = (int) Pengaturan::instance()?->angka('loyalty_order_threshold', 3) ?: 3;
        $persen = (float) Pengaturan::instance()?->angka('loyalty_discount_percent', 10) ?: 10.0;
        $masaHari = (int) Pengaturan::instance()?->angka('loyalty_valid_days', 30) ?: 30;

        $selesai = Order::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->count();

        if ($selesai === 0 || $selesai % $ambang !== 0) {
            // Diberikan sekali setiap kelipatan ambang: pada ambang 3, kupon
            // jatuh di pesanan ke-3, ke-6, ke-9 — bukan setiap pesanan
            // sesudahnya.
            return null;
        }

        // Satu pelanggan satu kupon aktif: pemberian berulang pada hari yang
        // sama akan menumpuk kode yang tidak pernah terpakai.
        $sudahAda = Coupon::query()
            ->where('is_active', true)
            ->where('code', 'like', 'LOYAL-'.$userId.'-%')
            ->where('ends_at', '>', now())
            ->exists();

        if ($sudahAda) {
            return null;
        }

        $kode = 'LOYAL-'.$userId.'-'.strtoupper(\Illuminate\Support\Str::random(6));

        return Coupon::query()->create([
            'code' => $kode,
            'type' => 'percent',
            'value' => $persen,
            'min_spend' => 0,
            'max_discount' => null,
            'quota' => 1,
            'used_count' => 0,
            'starts_at' => now(),
            'ends_at' => now()->addDays($masaHari),
            'is_active' => true,
        ]);
    }

    /** Kupon loyalitas aktif milik seorang pelanggan, untuk ditampilkan. */
    public static function kuponAktif(int $userId): Collection
    {
        return Coupon::query()
            ->where('is_active', true)
            ->where('code', 'like', 'LOYAL-'.$userId.'-%')
            ->where('ends_at', '>', now())
            ->whereColumn('used_count', '<', 'quota')
            ->orderByDesc('created_at')
            ->get();
    }
}
