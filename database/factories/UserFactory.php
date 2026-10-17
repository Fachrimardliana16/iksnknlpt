<?php

namespace Database\Factories;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\BlindIndex;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => '628'.mt_rand(1000000000, 9999999999),
            'password' => 'password',
            'role' => PeranPengguna::Customer,
            'email_verified_at' => now(),
        ];
    }

    /**
     * Kolom email bertipe encrypted, jadi faker tidak dapat menjamin keunikan
     * setelah enkripsi. Indeks buta yang menjaminnya, dan itu diisi di sini
     * supaya factory tidak pernah membuat baris tanpa indeks.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if (blank($user->email_bidx)) {
                $user->email_bidx = BlindIndex::buat($user->email, 'email_bidx');
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => PeranPengguna::Admin,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => PeranPengguna::SuperAdmin,
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
