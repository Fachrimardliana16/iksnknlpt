<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $nama = $this->faker->unique()->words(2, true);

        return [
            'name_id' => Str::title($nama),
            'name_en' => Str::title($nama),
            'description_id' => $this->faker->sentence(12),
            'description_en' => $this->faker->sentence(12),
            'slug' => Str::slug($nama).'-'.Str::lower(Str::random(4)),
            'display_order' => 0,
            'is_active' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
