<?php

namespace App\Enums;

enum StatusPesanan: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Bayar',
            self::Paid => 'Dibayar',
            self::Processing => 'Diproses',
            self::Shipped => 'Dikirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Nada lencana. Status antara memakai keluarga dingin, bukan biru framework. */
    public function nada(): string
    {
        return match ($this) {
            self::Pending => 'warn',
            self::Paid => 'brass',
            self::Processing, self::Shipped => 'steel',
            self::Completed => 'ok',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Transisi yang sah. PRD Bagian 7.3.
     *
     *   pending -> paid -> processing -> shipped -> completed
     *      |         |           |
     *      v         v           v
     *  cancelled cancelled   cancelled
     *
     * @return array<int, self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function bisaKe(self $target): bool
    {
        return in_array($target, $this->transisiSah(), true);
    }

    /** Pesanan pada status ini masih menahan stok. */
    public function menahanStok(): bool
    {
        return in_array($this, [self::Pending, self::Paid, self::Processing, self::Shipped], true);
    }

    public function final(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
