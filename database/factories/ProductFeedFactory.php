<?php

namespace Database\Factories;

use App\Models\ProductFeed;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFeed>
 */
class ProductFeedFactory extends Factory
{
    protected $model = ProductFeed::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->company().' Feed',
            'feed_url' => fake()->optional()->url(),
            'language' => fake()->randomElement(array_keys(ProductFeed::languageOptions())),
            'field_mappings' => [
                'sku' => 'g:id',
                'title' => 'g:title',
                'description' => 'g:description',
                'url' => 'g:link',
                'image_link' => 'g:image_link',
                'additional_image_link' => 'g:additional_image_link',
            ],
        ];
    }
}
