<?php

namespace Tests\Feature\Products;

use App\Jobs\BaseProductAiJob;
use App\Livewire\ProductShow;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ProductShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_details_page_is_accessible_for_team_member(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'title' => 'Example Product Title',
            ]);

        $this->actingAs($user);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSeeText('Example Product Title')
            ->assertSeeText('Generate Summary');
    }

    public function test_product_details_page_returns_not_found_for_other_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();

        $foreignFeed = ProductFeed::factory()->create([
            'team_id' => $otherUser->currentTeam->id,
        ]);

        $foreignProduct = Product::factory()
            ->for($foreignFeed, 'feed')
            ->create([
                'team_id' => $otherUser->currentTeam->id,
            ]);

        $this->actingAs($user);

        $this->get(route('products.show', $foreignProduct))
            ->assertNotFound();
    }

    public function test_summarize_product_dispatches_jobs_from_details_page(): void
    {
        config()->set('services.openai.api_key', 'test-key');

        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
            ]);

        $this->actingAs($user);

        Livewire::test(ProductShow::class, ['productId' => $product->id])
            ->call('summarizeProduct')
            ->assertHasNoErrors();

        $promptTypes = collect(config('product-ai.actions.generate_summary', []))
            ->filter()
            ->unique()
            ->values();

        $promptTypes->each(function (string $promptType) use ($product): void {
            $this->assertDatabaseHas('product_ai_jobs', [
                'product_id' => $product->id,
                'prompt_type' => $promptType,
                'status' => ProductAiJob::STATUS_QUEUED,
            ]);

            $jobClass = data_get(config('product-ai.generations.'.$promptType, []), 'job');

            $this->assertIsString($jobClass, 'Expected job class to be configured for '.$promptType);

            Queue::assertPushed($jobClass, function (BaseProductAiJob $job) use ($product, $promptType): bool {
                $jobRecord = ProductAiJob::find($job->productAiJobId);

                return $jobRecord?->product_id === $product->id
                    && $jobRecord->prompt_type === $promptType;
            });
        });
    }
}
