<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => $this->faker->randomElement(['Rumah', 'Kantor', 'Bengkel']),
            'recipient_name' => $this->faker->name(),
            'phone_number' => '628'.mt_rand(1000000000, 9999999999),
            'address_line_1' => $this->faker->streetAddress(),
            'address_line_2' => $this->faker->optional(0.4)->secondaryAddress(),
            'city' => $this->faker->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Medan', 'Makassar']),
            'state_province' => $this->faker->randomElement(['DKI Jakarta', 'Jawa Barat', 'Jawa Timur', 'DI Yogyakarta', 'Sumatera Utara', 'Sulawesi Selatan']),
            'postal_code' => $this->faker->numerify('#####'),
            'is_primary' => false,
        ];
    }

    public function utama(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
