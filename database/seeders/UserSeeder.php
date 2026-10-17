<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Models\Address;
use App\Models\User;
use App\Support\BlindIndex;
use Illuminate\Database\Seeder;

/**
 * Akun admin dan pelanggan contoh.
 *
 * Idempoten: pencarian memakai email_bidx, karena kolom email terenkripsi dan
 * tidak dapat dicari langsung.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->buat('Iksan (Super Admin)', 'admin@iksanknalpot.test', '628123456789', PeranPengguna::SuperAdmin, true);
        $this->buat('Staf Toko', 'staff@iksanknalpot.test', '628123456790', PeranPengguna::Admin, true);

        $pelanggan = [
            ['Budi Santoso', 'budi@example.test', '628120000001', 'Rumah', 'Jl. Kaliurang KM 5 No. 12', 'Sleman', 'DI Yogyakarta', '55581'],
            ['Siti Rahayu', 'siti@example.test', '628120000002', 'Kantor', 'Jl. Malioboro No. 88', 'Yogyakarta', 'DI Yogyakarta', '55271'],
            ['Andi Prakoso', 'andi@example.test', '628120000003', null, null, null, null, null],
        ];

        foreach ($pelanggan as $i => [$nama, $email, $telepon, $label, $alamat, $kota, $provinsi, $pos]) {
            $user = $this->buat($nama, $email, $telepon, PeranPengguna::Customer, false);

            if ($label === null) {
                continue;
            }

            $ada = Address::query()
                ->where('user_id', $user->id)
                ->where('label', $label)
                ->exists();

            if ($ada) {
                continue;
            }

            Address::query()->create([
                'user_id' => $user->id,
                'label' => $label,
                'recipient_name' => $nama,
                'phone_number' => $telepon,
                'address_line_1' => $alamat,
                'address_line_2' => null,
                'city' => $kota,
                'state_province' => $provinsi,
                'postal_code' => $pos,
                'is_primary' => $i === 0,
            ]);
        }

        $this->command?->info('UserSeeder: '.User::query()->count().' pengguna, '.Address::query()->count().' alamat.');
    }

    private function buat(
        string $nama,
        string $email,
        string $telepon,
        PeranPengguna $peran,
        bool $duaFaktor,
    ): User {
        $user = User::query()
            ->where('email_bidx', BlindIndex::buat($email, 'email_bidx'))
            ->first()
            ?? new User;

        $user->fill([
            'name' => $nama,
            'email' => $email,
            'phone_number' => $telepon,
            'role' => $peran,
            'email_verified_at' => now(),
            'is_blocked' => false,
        ]);

        if (! $user->exists) {
            // Password hanya disetel saat pembuatan, supaya menjalankan ulang
            // seeder tidak mereset akun yang sudah diganti kata sandinya.
            $user->password = 'password';
        }

        if ($duaFaktor && $user->two_factor_confirmed_at === null) {
            $user->two_factor_confirmed_at = now();
        }

        $user->save();

        return $user;
    }
}
