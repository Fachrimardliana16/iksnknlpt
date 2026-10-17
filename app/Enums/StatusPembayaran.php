<?php

namespace App\Enums;

enum StatusPembayaran: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Bayar',
            self::Pending => 'Menunggu Verifikasi',
            self::Paid => 'Lunas',
            self::Failed => 'Gagal',
            self::Refunded => 'Dana Dikembalikan',
        };
    }

    public function nada(): string
    {
        return match ($this) {
            self::Unpaid => 'neutral',
            self::Pending => 'warn',
            self::Paid => 'ok',
            self::Failed => 'danger',
            self::Refunded => 'steel',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
