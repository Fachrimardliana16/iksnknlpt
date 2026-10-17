<?php

namespace App\Enums;

enum TipeMedia: string
{
    case Gambar = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Model3d = 'model_3d';

    public function label(): string
    {
        return match ($this) {
            self::Gambar => 'Gambar',
            self::Video => 'Video',
            self::Audio => 'Suara',
            self::Model3d => 'Model 3D',
        };
    }

    /** Varian ukuran turunan yang dibuat untuk tiap tipe. */
    public function varianTurunan(): array
    {
        return match ($this) {
            self::Gambar => ['thumb', 'card', 'full', 'og'],
            default => [],
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
