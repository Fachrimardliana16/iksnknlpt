<?php

namespace App\Enums;

enum MetodePembayaran: string
{
    case Paypal = 'paypal';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Paypal => 'PayPal',
            self::Manual => 'Transfer Manual',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
