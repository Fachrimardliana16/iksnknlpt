<?php

namespace App\Enums;

enum PeranPengguna: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Pelanggan',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }

    /** Peran yang boleh membuka panel admin. */
    public function bisaMasukPanel(): bool
    {
        return $this !== self::Customer;
    }

    /** Peran yang boleh mengubah kredensial pembayaran dan mengelola admin. */
    public function bisaUbahKredensial(): bool
    {
        return $this === self::SuperAdmin;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
