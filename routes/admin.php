<?php

use Illuminate\Support\Facades\Route;

/*
| Rute panel admin.
|
| Seluruhnya memakai Livewire sebagai komponen halaman penuh, dengan middleware
| auth dan peran admin. Pemeriksaan peran dilakukan di middleware dan kebijakan,
| bukan dengan menyembunyikan tombol.
*/

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        //
    });
