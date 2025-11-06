<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RunProductAiTemplateJob;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductAiGeneration;
use App\Models\ProductAiTemplate;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoeMizrak\LaravelOpenrouter\DTO\ResponseData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use Tests\TestCase;

class RunProductAiTemplateJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_summary_record_and_trims_history(): void
    {
        config()->set('laravel-openrouter.api_key', 'test-key');
        config()->set('services.openrouter.model', 'test-model');

        ProductAiTemplate::syncDefaultTemplates();

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
        $summaryTemplate = ProductAiTemplate::where('slug', ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY)->firstOrFail();

        foreach (range(1, 10) as $offset) {
            $generation = ProductAiGeneration::create([
                'team_id' => $team->id,
                'product_id' => $product->id,
                'product_ai_template_id' => $summaryTemplate->id,
                'sku' => $product->sku,
                'content' => 'Legacy summary #'.$offset,
            ]);

            $generation->forceFill([
                'created_at' => now()->subMinutes(60 + $offset),
                'updated_at' => now()->subMinutes(60 + $offset),
            ])->save();
        }

        LaravelOpenRouter::shouldReceive('chatRequest')
            ->once()
            ->andReturn(ResponseData::from([
                'id' => 'test-generation',
                'model' => 'test-model',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    ['message' => ['content' => [
                        ['type' => 'text', 'text' => 'Newly generated summary.'],
                    ]]],
                ],
            ]));

        $jobRecord = ProductAiJob::create([
            'team_id' => $team->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_ai_template_id' => $summaryTemplate->id,
            'status' => ProductAiJob::STATUS_QUEUED,
            'progress' => 0,
            'queued_at' => now(),
        ]);

        $job = new RunProductAiTemplateJob($jobRecord->id);
        $job->handle();

        $this->assertDatabaseHas('product_ai_generations', [
            'product_id' => $product->id,
            'product_ai_template_id' => $summaryTemplate->id,
            'content' => 'Newly generated summary.',
        ]);

        $this->assertSame(10, ProductAiGeneration::where('product_id', $product->id)
            ->where('product_ai_template_id', $summaryTemplate->id)
            ->count());

        $jobRecord->refresh();

        $this->assertSame(ProductAiJob::STATUS_COMPLETED, $jobRecord->status);
        $this->assertSame(100, $jobRecord->progress);
        $this->assertNotNull($jobRecord->finished_at);
        $this->assertNotEmpty(data_get($jobRecord->meta, 'generation_id'));
    }
}
