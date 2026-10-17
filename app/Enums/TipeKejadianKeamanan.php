<?php

namespace App\Enums;

enum TipeKejadianKeamanan: string
{
    case LoginGagal = 'login_failed';
    case LoginBerhasil = 'login_success';
    case BruteForceDiblokir = 'brute_force_blocked';
    case IpDiblokir = 'ip_blocked';
    case AksesTidakSah = 'unauthorized_access';
    case BatasLaju = 'rate_limit_exceeded';
    case DuaFaktorGagal = 'two_factor_failed';
    case UnggahanDitolak = 'upload_rejected';
    case AksiAdmin = 'admin_action';

    public function label(): string
    {
        return match ($this) {
            self::LoginGagal => 'Login gagal',
            self::LoginBerhasil => 'Login berhasil',
            self::BruteForceDiblokir => 'Brute force diblokir',
            self::IpDiblokir => 'IP diblokir',
            self::AksesTidakSah => 'Akses tidak sah',
            self::BatasLaju => 'Batas laju terlampaui',
            self::DuaFaktorGagal => 'Verifikasi 2FA gagal',
            self::UnggahanDitolak => 'Unggahan ditolak',
            self::AksiAdmin => 'Aksi admin',
        };
    }

    public function nada(): string
    {
        return match ($this) {
            self::LoginGagal, self::DuaFaktorGagal => 'warn',
            self::BruteForceDiblokir, self::IpDiblokir, self::AksesTidakSah, self::BatasLaju, self::UnggahanDitolak => 'danger',
            self::LoginBerhasil, self::AksiAdmin => 'neutral',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
