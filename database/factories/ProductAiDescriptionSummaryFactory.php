<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductAiDescriptionSummary;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAiDescriptionSummary>
 */
class ProductAiDescriptionSummaryFactory extends Factory
{
    protected $model = ProductAiDescriptionSummary::class;

    public function configure(): static
    {
        return $this
            ->afterMaking(function (ProductAiDescriptionSummary $summary): void {
                if (! $summary->team_id && $summary->product) {
                    $summary->team_id = $summary->product->team_id;
                }
            })
            ->afterCreating(function (ProductAiDescriptionSummary $summary): void {
                if (! $summary->team_id && $summary->product) {
                    $summary->forceFill(['team_id' => $summary->product->team_id])->save();
                }
            });
    }

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'product_id' => Product::factory(),
            'sku' => (string) fake()->unique()->numberBetween(1000, 999999),
            'content' => fake()->paragraph(),
            'meta' => null,
        ];
    }
}
