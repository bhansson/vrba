<?php

namespace Database\Factories;

use App\Models\ProductAiTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAiTemplate>
 */
class ProductAiTemplateFactory extends Factory
{
    protected $model = ProductAiTemplate::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'team_id' => null,
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'description' => $this->faker->sentence(12),
            'is_default' => false,
            'is_active' => true,
            'system_prompt' => null,
            'prompt' => 'Write a short summary for {{ title }}.',
            'context' => [
                ['key' => 'title'],
                ['key' => 'description'],
            ],
            'settings' => [
                'content_type' => 'text',
                'options' => [],
            ],
        ];
    }
}
