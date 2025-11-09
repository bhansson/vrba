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

class BulkProductGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_generate_queues_jobs_for_selected_products(): void
    {
        config()->set('laravel-openrouter.api_key', 'fake-key');

        Queue::fake();

        ProductAiTemplate::syncDefaultTemplates();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $products = Product::factory()
            ->count(2)
            ->for($feed, 'feed')
            ->state([
                'team_id' => $team->id,
                'brand' => 'Acme Industries',
            ])
            ->create();

        $template = ProductAiTemplate::query()
            ->forTeam($team->id)
            ->active()
            ->orderBy('name')
            ->firstOrFail();

        $this->actingAs($user);

        Livewire::test(ProductsIndex::class)
            ->set('selectedTemplateId', $template->id)
            ->set('selectedProducts', $products->pluck('id')->all())
            ->call('bulkGenerate')
            ->assertSet('bulkErrorMessage', null)
            ->assertSet('bulkStatusMessage', 'Queued 2 AI jobs using template "'.$template->name.'". Track progress on the AI Jobs page.')
            ->assertSet('selectedProducts', [])
            ->assertSet('bulkSelectAll', false);

        Queue::assertPushed(RunProductAiTemplateJob::class, 2);

            foreach ($products as $product) {
                $this->assertDatabaseHas('product_ai_jobs', [
                    'product_id' => $product->id,
                    'product_ai_template_id' => $template->id,
                    'team_id' => $team->id,
                    'status' => ProductAiJob::STATUS_QUEUED,
                    'job_type' => ProductAiJob::TYPE_TEMPLATE,
                ]);
            }
    }

    public function test_bulk_generate_skips_products_without_skus(): void
    {
        config()->set('laravel-openrouter.api_key', 'fake-key');

        Queue::fake();

        ProductAiTemplate::syncDefaultTemplates();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $withSku = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'sku' => 'SKU-123',
                'brand' => 'Acme',
            ]);

        $withoutSku = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'sku' => '0',
                'brand' => 'Acme',
            ]);

        $template = ProductAiTemplate::query()
            ->forTeam($team->id)
            ->active()
            ->orderBy('name')
            ->firstOrFail();

        $this->actingAs($user);

        Livewire::test(ProductsIndex::class)
            ->set('selectedTemplateId', $template->id)
            ->set('selectedProducts', [$withSku->id, $withoutSku->id])
            ->call('bulkGenerate')
            ->assertSet('bulkStatusMessage', 'Queued 1 AI job using template "'.$template->name.'". Track progress on the AI Jobs page.')
            ->assertSet('bulkErrorMessage', 'Skipped 1 product without an SKU.')
            ->assertSet('selectedProducts', [])
            ->assertSet('bulkSelectAll', false);

        Queue::assertPushed(RunProductAiTemplateJob::class, 1);

        $this->assertDatabaseHas('product_ai_jobs', [
            'product_id' => $withSku->id,
            'product_ai_template_id' => $template->id,
            'team_id' => $team->id,
            'status' => ProductAiJob::STATUS_QUEUED,
            'job_type' => ProductAiJob::TYPE_TEMPLATE,
        ]);

        $this->assertDatabaseMissing('product_ai_jobs', [
            'product_id' => $withoutSku->id,
        ]);
    }

    public function test_products_index_filters_by_language(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feedEn = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'language' => 'en',
        ]);

        $feedSv = ProductFeed::factory()->create([
            'team_id' => $team->id,
            'language' => 'sv',
        ]);

        $englishProduct = Product::factory()
            ->for($feedEn, 'feed')
            ->create([
                'team_id' => $team->id,
                'title' => 'English Product',
                'brand' => 'Acme EN',
            ]);

        $swedishProduct = Product::factory()
            ->for($feedSv, 'feed')
            ->create([
                'team_id' => $team->id,
                'title' => 'Svensk Produkt',
                'brand' => 'Acme SV',
            ]);

        $this->actingAs($user);

        Livewire::test(ProductsIndex::class)
            ->assertSee($englishProduct->title)
            ->assertSee($swedishProduct->title)
            ->set('language', 'sv')
            ->assertSee($swedishProduct->title)
            ->assertDontSee($englishProduct->title);
    }
}
