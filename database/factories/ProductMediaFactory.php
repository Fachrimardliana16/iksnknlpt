<?php

namespace Database\Factories;

use App\Enums\TipeMedia;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductMedia>
 */
class ProductMediaFactory extends Factory
{
    protected $model = ProductMedia::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => TipeMedia::Gambar,
            'original_filename' => 'contoh.jpg',
            'path_original' => 'products/contoh/original.webp',
            'path_thumb' => 'products/contoh/thumb.webp',
            'path_card' => 'products/contoh/card.webp',
            'path_full' => 'products/contoh/full.webp',
            'path_og' => 'products/contoh/og.webp',
            'width' => 1600,
            'height' => 1200,
            'byte_size' => 120_000,
            'mime_detected' => 'image/webp',
            'content_hash' => hash('sha256', Str::random(16)),
            'alt_text_id' => $this->faker->sentence(5),
            'alt_text_en' => $this->faker->sentence(5),
            'is_primary' => false,
            'display_order' => 0,
        ];
    }

    public function utama(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
