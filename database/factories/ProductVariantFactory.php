<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku_variant' => 'IK-VAR-'.Str::upper(Str::random(6)),
            'variant_name_id' => $this->faker->randomElement(['Standar', 'Titanium', 'Carbon', 'Stainless', 'Hitam Doff']),
            'variant_name_en' => $this->faker->randomElement(['Standard', 'Titanium', 'Carbon', 'Stainless', 'Matte Black']),
            'price_adjustment' => $this->faker->randomElement([0, 50000, 150000, -25000]),
            'stock_quantity' => mt_rand(0, 40),
            'reserved_quantity' => 0,
            'display_order' => 0,
            'is_active' => true,
        ];
    }

    public function tanpaStok(): static
    {
        return $this->state(fn () => ['stock_quantity' => 0]);
    }
}
