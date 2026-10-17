<?php

use App\Support\Ukuran;

if (! function_exists('csp_nonce')) {
    /** Nonce CSP untuk skrip inline. Kosong bila middleware tidak aktif. */
    function csp_nonce(): string
    {
        return app()->bound('csp.nonce') ? app('csp.nonce') : '';
    }
}

if (! function_exists('rupiah')) {
    /** Format mata uang tanpa simbol bawaan, memakai titik ribuan gaya Indonesia. */
    function rupiah(float|int|string|null $amount, string $currency = 'IDR'): string
    {
        $value = (float) $amount;

        return match (strtoupper($currency)) {
            'IDR' => 'Rp '.number_format($value, 0, ',', '.'),
            'USD' => '$'.number_format($value, 2, '.', ','),
            default => number_format($value, 2, ',', '.').' '.$currency,
        };
    }
}

if (! function_exists('ukuran_berkas')) {
    function ukuran_berkas(int|float|null $bytes): string
    {
        return Ukuran::berkas((int) $bytes);
    }
}
