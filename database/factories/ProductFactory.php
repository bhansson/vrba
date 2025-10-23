<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFeed;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Product $product): void {
                if (! $product->team_id && $product->feed) {
                    $product->team_id = $product->feed->team_id;
                }
            })
            ->afterCreating(function (Product $product): void {
                if (! $product->team_id && $product->feed) {
                    $product->forceFill(['team_id' => $product->feed->team_id])->save();
                }
            });
    }

    public function definition(): array
    {
        return [
            'product_feed_id' => ProductFeed::factory(),
            'team_id' => Team::factory(),
            'sku' => (string) fake()->unique()->numberBetween(1000, 999999),
            'gtin' => fake()->optional()->ean13(),
            'title' => fake()->sentence(4),
            'brand' => fake()->optional()->company(),
            'description' => fake()->paragraph(),
            'url' => fake()->url(),
        ];
    }
}
