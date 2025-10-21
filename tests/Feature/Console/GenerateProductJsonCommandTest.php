<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Models\ProductAiDescriptionSummary;
use App\Models\ProductAiFaq;
use App\Models\ProductAiUsp;
use App\Models\Team;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateProductJsonCommandTest extends TestCase
{
    protected string $publicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicPath = base_path('storage/testing/public_'.Str::lower(Str::random(8)));
        File::ensureDirectoryExists($this->publicPath);
        $this->app->usePublicPath($this->publicPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicPath);

        parent::tearDown();
    }

    public function test_it_generates_json_exports_for_products(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()
            ->for($team)
            ->create([
                'sku' => 'SKU-123',
            ]);

        $summary = ProductAiDescriptionSummary::factory()
            ->for($team)
            ->for($product)
            ->create([
                'sku' => $product->sku,
                'content' => 'Generated summary content',
            ]);

        ProductAiUsp::create([
            'team_id' => $team->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'content' => [
                'Ships within 24 hours',
                'Premium materials that last',
            ],
            'meta' => null,
        ]);

        ProductAiFaq::create([
            'team_id' => $team->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'content' => [
                [
                    'question' => 'Does it include a warranty?',
                    'answer' => 'Yes, it includes a 2-year manufacturer warranty.',
                ],
                [
                    'question' => 'Is international shipping available?',
                    'answer' => 'We ship worldwide with tracked delivery.',
                ],
            ],
            'meta' => null,
        ]);

        $product->refresh();

        $this->artisan('products:generate-public-json')
            ->assertExitCode(0);

        $filePath = $this->publicPath.'/edge/'.$team->public_hash.'/SKU-123.json';
        $this->assertFileExists($filePath);

        $payload = json_decode(File::get($filePath), true);

        $this->assertSame($product->sku, $payload['sku']);
        $this->assertSame($team->public_hash, $payload['team_hash']);
        $this->assertSame($summary->content, $payload['ai']['description_summary']['content']);
        $this->assertArrayNotHasKey('meta', $payload['ai']['description_summary']);
        $this->assertSame(
            ['Ships within 24 hours', 'Premium materials that last'],
            $payload['ai']['usps']['content']
        );
        $this->assertArrayNotHasKey('meta', $payload['ai']['usps']);
        $this->assertSame(
            [
                [
                    'question' => 'Does it include a warranty?',
                    'answer' => 'Yes, it includes a 2-year manufacturer warranty.',
                ],
                [
                    'question' => 'Is international shipping available?',
                    'answer' => 'We ship worldwide with tracked delivery.',
                ],
            ],
            $payload['ai']['faq']['content']
        );
        $this->assertArrayNotHasKey('meta', $payload['ai']['faq']);
    }

    public function test_it_skips_unchanged_products(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()
            ->for($team)
            ->create([
                'sku' => 'SKU-456',
            ]);

        ProductAiDescriptionSummary::factory()
            ->for($team)
            ->for($product)
            ->create([
                'sku' => $product->sku,
                'content' => 'Initial summary',
            ]);

        $this->artisan('products:generate-public-json')
            ->assertExitCode(0);

        $filePath = $this->publicPath.'/edge/'.$team->public_hash.'/SKU-456.json';
        $this->assertFileExists($filePath);

        clearstatcache(true, $filePath);
        $modifiedBefore = filemtime($filePath);

        sleep(1);

        $this->artisan('products:generate-public-json')
            ->assertExitCode(0);

        clearstatcache(true, $filePath);
        $modifiedAfter = filemtime($filePath);

        $this->assertSame($modifiedBefore, $modifiedAfter, 'Product export should not be rewritten when unchanged.');
    }

    public function test_it_rewrites_exports_when_product_is_updated(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()
            ->for($team)
            ->create([
                'sku' => 'SKU-789',
            ]);

        ProductAiDescriptionSummary::factory()
            ->for($team)
            ->for($product)
            ->create([
                'sku' => $product->sku,
                'content' => 'First summary',
            ]);

        $this->artisan('products:generate-public-json')
            ->assertExitCode(0);

        $filePath = $this->publicPath.'/edge/'.$team->public_hash.'/SKU-789.json';
        $this->assertFileExists($filePath);

        $initialPayload = json_decode(File::get($filePath), true);
        $this->assertSame('First summary', $initialPayload['ai']['description_summary']['content']);

        clearstatcache(true, $filePath);
        $modifiedBefore = filemtime($filePath);

        sleep(1);

        ProductAiDescriptionSummary::factory()
            ->for($team)
            ->for($product)
            ->create([
                'sku' => $product->sku,
                'content' => 'Updated summary text',
            ]);

        $product->refresh();

        $this->artisan('products:generate-public-json')
            ->assertExitCode(0);

        clearstatcache(true, $filePath);
        $modifiedAfter = filemtime($filePath);

        $this->assertGreaterThan($modifiedBefore, $modifiedAfter, 'Product export should be rewritten after an update.');

        $updatedPayload = json_decode(File::get($filePath), true);
        $this->assertSame('Updated summary text', $updatedPayload['ai']['description_summary']['content']);
    }

    public function test_it_generates_missing_team_hash_before_export(): void
    {
        $team = Team::factory()->create();

        $team->forceFill(['public_hash' => null])->save();

        $product = Product::factory()
            ->for($team)
            ->create([
                'sku' => 'SKU-999',
            ]);

        ProductAiDescriptionSummary::factory()
            ->for($team)
            ->for($product)
            ->create([
                'sku' => $product->sku,
                'content' => 'Summary for missing hash team',
            ]);

        $this->artisan('products:generate-public-json')
            ->assertExitCode(0);

        $team->refresh();

        $this->assertNotNull($team->public_hash);

        $filePath = $this->publicPath.'/edge/'.$team->public_hash.'/SKU-999.json';
        $this->assertFileExists($filePath);
    }
}
