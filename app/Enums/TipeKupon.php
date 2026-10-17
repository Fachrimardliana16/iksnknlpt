<?php

namespace App\Enums;

enum TipeKupon: string
{
    case Persen = 'percent';
    case Nominal = 'fixed';
    case GratisOngkir = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Persen => 'Persentase',
            self::Nominal => 'Potongan Nominal',
            self::GratisOngkir => 'Gratis Ongkir',
        };
    }

    /**
     * Hitung potongan untuk belanja tertentu.
     *
     * @return float Potongan dalam mata uang pesanan.
     */
    public function hitung(float $belanja, float $nilai, ?float $maksDiskon, float $ongkir): float
    {
        $potongan = match ($this) {
            self::Persen => $belanja * ($nilai / 100),
            self::Nominal => $nilai,
            self::GratisOngkir => $ongkir,
        };

        if ($maksDiskon !== null) {
            $potongan = min($potongan, $maksDiskon);
        }

        // Potongan tidak pernah melebihi nilai belanja. Ongkir dihitung terpisah
        // supaya gratis ongkir tidak memakan subtotal.
        return $this === self::GratisOngkir
            ? max(0.0, $potongan)
            : max(0.0, min($potongan, $belanja));
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
