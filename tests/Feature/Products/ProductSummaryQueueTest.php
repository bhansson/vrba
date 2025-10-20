<?php

namespace Tests\Feature\Products;

use App\Jobs\BaseProductAiJob;
use App\Livewire\ProductsIndex;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSummaryQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_product_dispatches_ai_job(): void
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

        Livewire::test(ProductsIndex::class)
            ->call('summarizeProduct', $product->id)
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
