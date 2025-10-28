<?php

namespace Database\Factories;

use App\Models\ProductAiGeneration;
use App\Models\ProductAiTemplate;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAiGeneration>
 */
class ProductAiGenerationFactory extends Factory
{
    protected $model = ProductAiGeneration::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'product_id' => Product::factory(),
            'product_ai_template_id' => ProductAiTemplate::factory(),
            'product_ai_job_id' => null,
            'sku' => $this->faker->unique()->ean13(),
            'content' => 'Sample generation content',
            'meta' => [],
        ];
    }

    public function forTemplate(ProductAiTemplate $template): self
    {
        return $this->state(function () use ($template) {
            return [
                'team_id' => $template->team_id,
                'product_ai_template_id' => $template->id,
            ];
        });
    }

    public function forProduct(Product $product): self
    {
        return $this->state(function () use ($product) {
            return [
                'team_id' => $product->team_id,
                'product_id' => $product->id,
                'sku' => $product->sku,
            ];
        });
    }
}
