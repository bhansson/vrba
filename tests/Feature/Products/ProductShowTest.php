<?php

namespace Tests\Feature\Products;

use App\Jobs\RunProductAiTemplateJob;
use App\Livewire\ProductShow;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
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
            ->assertSeeText('Description Summary');
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

    public function test_queue_generation_dispatches_job_from_details_page(): void
    {
        config()->set('laravel-openrouter.api_key', 'test-key');

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

        ProductAiTemplate::syncDefaultTemplates();

        $templates = ProductAiTemplate::query()
            ->whereIn('slug', config('product-ai.actions.generate_summary', []))
            ->get();

        $this->assertNotEmpty($templates);

        $livewire = Livewire::test(ProductShow::class, ['productId' => $product->id]);

        foreach ($templates as $template) {
            $livewire->call('queueGeneration', $template->id)
                ->assertHasNoErrors();

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
        }
    }
}
