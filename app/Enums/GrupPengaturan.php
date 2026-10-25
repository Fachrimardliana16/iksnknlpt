<?php

namespace App\Enums;

enum GrupPengaturan: string
{
    case Identitas = 'identity';
    case Kontak = 'contact';
    case Sosial = 'social';
    case Pembayaran = 'payment';
    case Notifikasi = 'notification';
    case Lanjutan = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::Identitas => 'Identitas Situs',
            self::Kontak => 'Kontak dan Lokasi',
            self::Sosial => 'Media Sosial',
            self::Pembayaran => 'Pembayaran',
            self::Notifikasi => 'Notifikasi',
            self::Lanjutan => 'Lanjutan',
        };
    }

    public function ikon(): string
    {
        return match ($this) {
            self::Identitas => 'badge',
            self::Kontak => 'phone',
            self::Sosial => 'share',
            self::Pembayaran => 'card',
            self::Notifikasi => 'bell',
            self::Lanjutan => 'gear',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
