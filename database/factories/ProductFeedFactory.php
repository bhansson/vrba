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
            'field_mappings' => [
                'sku' => 'g:id',
                'title' => 'g:title',
                'description' => 'g:description',
                'url' => 'g:link',
                'price' => 'g:price',
            ],
        ];
    }
}
