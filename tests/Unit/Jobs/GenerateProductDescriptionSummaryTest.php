<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateProductDescriptionSummary;
use App\Models\Product;
use App\Models\ProductAiDescriptionSummary;
use App\Models\ProductAiJob;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateProductDescriptionSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_summary_record_and_trims_history(): void
    {
        config()->set('services.openai.api_key', 'test-key');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'sku' => 'SKU-100',
                'description' => 'Test description for the product',
            ]);

        // Seed 10 existing summaries to ensure the history trimming logic executes.
        foreach (range(1, 10) as $offset) {
            ProductAiDescriptionSummary::factory()->create([
                'team_id' => $team->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'content' => 'Legacy summary #'.$offset,
                'created_at' => now()->subMinutes(60 + $offset),
                'updated_at' => now()->subMinutes(60 + $offset),
            ]);
        }

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Newly generated summary.']],
                ],
            ], 200),
        ]);

        $jobRecord = ProductAiJob::create([
            'team_id' => $team->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'prompt_type' => ProductAiJob::PROMPT_DESCRIPTION_SUMMARY,
            'status' => ProductAiJob::STATUS_QUEUED,
            'progress' => 0,
            'queued_at' => now(),
        ]);

        $job = new GenerateProductDescriptionSummary($jobRecord->id);
        $job->handle();

        $this->assertDatabaseHas('product_ai_description_summaries', [
            'product_id' => $product->id,
            'content' => 'Newly generated summary.',
        ]);

        $this->assertSame(10, ProductAiDescriptionSummary::where('product_id', $product->id)->count());

        $jobRecord->refresh();

        $this->assertSame(ProductAiJob::STATUS_COMPLETED, $jobRecord->status);
        $this->assertSame(100, $jobRecord->progress);
        $this->assertNotNull($jobRecord->finished_at);
    }
}
