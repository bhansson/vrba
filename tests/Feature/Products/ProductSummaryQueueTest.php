<?php

namespace Tests\Feature\Products;

use App\Jobs\RunProductAiTemplateJob;
use App\Livewire\ProductsIndex;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
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
            ]);

        $this->actingAs($user);

        Livewire::test(ProductsIndex::class)
            ->call('summarizeProduct', $product->id)
            ->assertHasNoErrors();

        $templates = ProductAiTemplate::query()
            ->whereIn('slug', config('product-ai.actions.generate_summary', []))
            ->get();

        $this->assertNotEmpty($templates, 'Expected default AI templates to be available.');

        $templates->each(function (ProductAiTemplate $template) use ($product): void {
            $this->assertDatabaseHas('product_ai_jobs', [
                'product_id' => $product->id,
                'product_ai_template_id' => $template->id,
                'status' => ProductAiJob::STATUS_QUEUED,
            ]);

            Queue::assertPushed(RunProductAiTemplateJob::class, function (RunProductAiTemplateJob $job) use ($product, $template): bool {
                $jobRecord = ProductAiJob::find($job->productAiJobId);

                return $jobRecord?->product_id === $product->id
                    && $jobRecord->product_ai_template_id === $template->id;
            });
        });
    }
}
