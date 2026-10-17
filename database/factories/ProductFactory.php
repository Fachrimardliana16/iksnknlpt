<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $judul = Str::title($this->faker->unique()->words(3, true));
        $slug = Str::slug($judul);

        return [
            'category_id' => Category::factory(),
            'sku' => 'IK-'.Str::upper(Str::random(3)).'-'.mt_rand(1000, 9999),
            'title_id' => $judul,
            'title_en' => $judul,
            'slug' => $slug.'-'.Str::lower(Str::random(5)),
            'excerpt_id' => $this->faker->sentence(10),
            'excerpt_en' => $this->faker->sentence(10),
            'description_id' => $this->faker->paragraphs(3, true),
            'description_en' => $this->faker->paragraphs(3, true),
            'base_price' => mt_rand(150, 6500) * 1000,
            'weight_gram' => mt_rand(5, 40) * 100,
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    public function unggulan(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function disembunyikan(): static
    {
        return $this->state(fn () => ['is_published' => false, 'published_at' => null]);
    }
}
