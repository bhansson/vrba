<?php

namespace Tests\Feature\PhotoStudio;

use App\Livewire\PhotoStudio;
use App\Jobs\GeneratePhotoStudioImage;
use App\Models\PhotoStudioGeneration;
use App\Models\ProductAiJob;
use App\Models\Product;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageContentPartData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use Tests\TestCase;

class PhotoStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('photo-studio.index'))
            ->assertOk()
            ->assertSee('Photo Studio');
    }

    public function test_user_can_extract_prompt_using_product_image(): void
    {
        config()->set('laravel-openrouter.api_key', 'test-key');
        config()->set('services.photo_studio.model', 'openai/gpt-4.1');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $product = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'image_link' => 'https://cdn.example.com/reference.jpg',
                'brand' => 'Acme',
            ]);

        $this->fakeOpenRouter(function ($chatData) use ($product) {
            $this->assertInstanceOf(ChatData::class, $chatData);
            $this->assertSame('openai/gpt-4.1', $chatData->model);

            $userMessage = Arr::get($chatData->messages, 1);
            $this->assertNotNull($userMessage, 'User message missing from payload.');

            $imagePart = collect($userMessage->content ?? [])
                ->first(fn ($part) => $part instanceof ImageContentPartData);

            $this->assertNotNull($imagePart, 'Image payload missing from message.');
            $this->assertSame($product->image_link, $imagePart->image_url->url);

            return [
                'id' => 'photo-studio-test',
                'model' => 'openrouter/openai/gpt-4.1',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    ['message' => ['content' => 'High-end studio prompt']],
                ],
            ];
        });

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->call('extractPrompt')
            ->assertSet('promptResult', 'High-end studio prompt')
            ->assertSet('productImagePreview', $product->image_link);
    }

    public function test_user_can_generate_image_and_queue_job(): void
    {
        config()->set('laravel-openrouter.api_key', 'test-key');
        config()->set('services.photo_studio.image_model', 'google/gemini-2.5-flash-image');
        config()->set('services.photo_studio.generation_disk', 's3');

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
                'image_link' => 'https://cdn.example.com/reference.jpg',
                'brand' => 'Acme',
            ]);

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->set('promptResult', 'Use this prompt as-is')
            ->call('generateImage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_ai_jobs', [
            'team_id' => $team->id,
            'product_id' => $product->id,
            'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
            'status' => ProductAiJob::STATUS_QUEUED,
        ]);

        Queue::assertPushed(GeneratePhotoStudioImage::class, function (GeneratePhotoStudioImage $job) use ($team, $user, $product) {
            $this->assertSame($team->id, $job->teamId);
            $this->assertSame($user->id, $job->userId);
            $this->assertSame($product->id, $job->productId);
            $this->assertSame('google/gemini-2.5-flash-image', $job->model);
            $this->assertSame('s3', $job->disk);
            $this->assertSame('product_image', $job->sourceType);

            return true;
        });
    }

    public function test_generate_image_uses_existing_generations_as_baseline(): void
    {
        config()->set('laravel-openrouter.api_key', 'test-key');
        config()->set('services.photo_studio.image_model', 'google/gemini-2.5-flash-image');
        config()->set('services.photo_studio.generation_disk', 's3');

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
                'brand' => 'Acme',
            ]);

        $existing = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Historic prompt',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/historic.png',
        ]);

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->set('promptResult', 'Use this prompt as-is')
            ->call('generateImage')
            ->assertSet('pendingGenerationBaselineId', $existing->id)
            ->assertSet('pendingProductId', $product->id);
    }

    public function test_user_can_generate_image_with_prompt_only(): void
    {
        config()->set('laravel-openrouter.api_key', 'test-key');
        config()->set('services.photo_studio.image_model', 'google/gemini-2.5-flash-image');
        config()->set('services.photo_studio.generation_disk', 's3');

        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('promptResult', 'Manual creative prompt')
            ->call('generateImage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_ai_jobs', [
            'team_id' => $user->currentTeam->id,
            'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
            'status' => ProductAiJob::STATUS_QUEUED,
        ]);

        Queue::assertPushed(GeneratePhotoStudioImage::class, function (GeneratePhotoStudioImage $job) use ($user) {
            $this->assertSame($user->currentTeam->id, $job->teamId);
            $this->assertSame($user->id, $job->userId);
            $this->assertNull($job->productId);
            $this->assertNull($job->imageInput);
            $this->assertSame('prompt_only', $job->sourceType);

            return true;
        });
    }

    public function test_product_gallery_lists_all_team_generations(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $productA = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme A',
            ]);

        $productB = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme B',
            ]);

        $firstGeneration = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productA->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Studio prompt',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/a-first.png',
        ]);

        $secondGeneration = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productB->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Studio prompt other product',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/b-first.png',
        ]);

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->assertSet('productGallery.0.id', $secondGeneration->id)
            ->assertSet('productGallery.1.id', $firstGeneration->id)
            ->assertSet('productGallery.0.product.id', $productB->id)
            ->assertSet('productGallery.1.product.id', $productA->id);
    }

    public function test_gallery_search_filters_by_prompt_text(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $productA = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'title' => 'Cozy Cloud Sofa',
                'sku' => 'COZY-001',
            ]);

        $productB = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'title' => 'Brutalist Arch Lamp',
                'sku' => 'BRUT-900',
            ]);

        $matching = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productA->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Cozy studio couch scene',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/matching.png',
        ]);

        $other = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productB->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Brutalist arch shot',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/other.png',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->assertSet('galleryTotal', 2);

        $component
            ->set('gallerySearch', 'cozy')
            ->assertSet('productGallery.0.id', $matching->id)
            ->assertSet('galleryTotal', 2);

        $this->assertCount(1, $component->get('productGallery'));

        $component
            ->set('gallerySearch', 'brutal')
            ->assertSet('productGallery.0.id', $other->id);

        $component
            ->set('gallerySearch', 'sofa')
            ->assertSet('productGallery.0.id', $matching->id);

        $component
            ->set('gallerySearch', 'BRUT-900')
            ->assertSet('productGallery.0.id', $other->id);

        $component->set('gallerySearch', '')
            ->assertSet('productGallery.0.id', $other->id);

        $this->assertCount(2, $component->get('productGallery'));
        $component->assertSet('galleryTotal', 2);
    }

    public function test_poll_generation_status_refreshes_latest_image_and_gallery(): void
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
                'brand' => 'Acme',
            ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id);

        $component
            ->set('pendingGenerationBaselineId', 0)
            ->set('isAwaitingGeneration', true)
            ->set('generationStatus', 'Image generation queued. Hang tight while we render your scene.')
            ->set('pendingProductId', $product->id);

        $latest = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Fresh prompt',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/new.png',
            'response_id' => 'test-response',
        ]);

        $component
            ->call('pollGenerationStatus')
            ->assertSet('isAwaitingGeneration', false)
            ->assertSet('pendingGenerationBaselineId', null)
            ->assertSet('latestGeneration.path', $latest->storage_path)
            ->assertSet('latestObservedGenerationId', $latest->id)
            ->assertSet('productGallery.0.id', $latest->id)
            ->assertSet('generationStatus', 'New image added to the gallery.')
            ->assertSet('pendingProductId', null);
    }

    public function test_poll_generation_status_waits_for_matching_product_before_finishing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $feed = ProductFeed::factory()->create([
            'team_id' => $team->id,
        ]);

        $productA = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme A',
            ]);

        $productB = Product::factory()
            ->for($feed, 'feed')
            ->create([
                'team_id' => $team->id,
                'brand' => 'Acme B',
            ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->set('productId', $productA->id)
            ->set('pendingGenerationBaselineId', 0)
            ->set('pendingProductId', $productA->id)
            ->set('isAwaitingGeneration', true);

        PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productB->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Other product prompt',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/b-run.png',
        ]);

        $component
            ->call('pollGenerationStatus')
            ->assertSet('isAwaitingGeneration', true)
            ->assertSet('generationStatus', 'Image generation in progress…');

        $matching = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $productA->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Matching prompt',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/a-run.png',
        ]);

        $component
            ->call('pollGenerationStatus')
            ->assertSet('isAwaitingGeneration', false)
            ->assertSet('generationStatus', 'New image added to the gallery.')
            ->assertSet('productGallery.0.id', $matching->id)
            ->assertSet('pendingProductId', null);
    }

    public function test_user_can_soft_delete_generation_from_gallery(): void
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
                'brand' => 'Acme',
            ]);

        $generation = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Prompt to delete',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 's3',
            'storage_path' => 'photo-studio/delete-me.png',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->assertSet('productGallery.0.id', $generation->id);

        $component
            ->call('deleteGeneration', $generation->id)
            ->assertSet('productGallery', []);

        $this->assertSoftDeleted('photo_studio_generations', [
            'id' => $generation->id,
        ]);
    }

    public function test_generate_photo_studio_image_job_persists_output(): void
    {
        config()->set('services.photo_studio.generation_disk', 's3');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $imagePayload = $this->samplePngBase64();

        $this->fakeOpenRouter(function () use ($imagePayload) {
            return [
                'id' => 'photo-studio-image',
                'model' => 'google/gemini-2.5-flash-image',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'provider' => 'OpenRouter',
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                [
                                    'type' => 'output_image',
                                    'image_base64' => $imagePayload,
                                    'mime_type' => 'image/png',
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 128,
                    'completion_tokens' => 1,
                ],
            ];
        });

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: 'google/gemini-2.5-flash-image',
            disk: 's3',
            imageInput: 'data:image/png;base64,'.base64_encode('reference'),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $generation = PhotoStudioGeneration::first();

        $this->assertNotNull($generation);
        $this->assertSame($team->id, $generation->team_id);
        $this->assertSame($user->id, $generation->user_id);
        $this->assertNull($generation->product_id);
        $this->assertSame('google/gemini-2.5-flash-image', $generation->model);
        $this->assertSame('s3', $generation->storage_disk);
        $this->assertNotEmpty($generation->storage_path);
        $this->assertStringEndsWith('.jpg', $generation->storage_path);
        $this->assertSame(1, $generation->image_width);
        $this->assertSame(1, $generation->image_height);

        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    public function test_generate_photo_studio_image_job_handles_attachment_pointers(): void
    {
        config()->set('services.photo_studio.generation_disk', 's3');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $pointerPayload = $this->samplePngBase64();

        $this->fakeOpenRouter(function () use ($pointerPayload) {
            return [
                'id' => 'photo-studio-image',
                'model' => 'google/gemini-2.5-flash-image',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                [
                                    'type' => 'output_image',
                                    'asset_pointer' => 'attachment://render-1',
                                ],
                            ],
                        ],
                    ],
                ],
                'attachments' => [
                    [
                        'id' => 'render-1',
                        'data' => [
                            'mime_type' => 'image/png',
                            'base64' => $pointerPayload,
                        ],
                    ],
                ],
            ];
        });

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: 'google/gemini-2.5-flash-image',
            disk: 's3',
            imageInput: 'data:image/png;base64,'.base64_encode('reference'),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $generation = PhotoStudioGeneration::first();

        $this->assertNotNull($generation);
        $this->assertSame('s3', $generation->storage_disk);
        $this->assertNotEmpty($generation->storage_path);

        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    public function test_generate_photo_studio_image_job_handles_inline_image_payload(): void
    {
        config()->set('services.photo_studio.generation_disk', 's3');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $inlinePayload = $this->samplePngBase64();

        $this->fakeOpenRouter(function () use ($inlinePayload) {
            return [
                'id' => 'photo-studio-image',
                'model' => 'google/gemini-2.5-flash-image',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                [
                                    'type' => 'output_image',
                                    'image' => [
                                        'mime_type' => 'image/png',
                                        'base64' => $inlinePayload,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        });

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: 'google/gemini-2.5-flash-image',
            disk: 's3',
            imageInput: 'data:image/png;base64,'.base64_encode('reference'),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $generation = PhotoStudioGeneration::first();

        $this->assertNotNull($generation);
        $this->assertSame('s3', $generation->storage_disk);
        $this->assertNotEmpty($generation->storage_path);

        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    public function test_generate_photo_studio_image_job_handles_message_image_urls(): void
    {
        config()->set('services.photo_studio.generation_disk', 's3');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $inlinePayload = $this->samplePngBase64();
        $dataUri = 'data:image/png;base64,'.$inlinePayload;

        $this->fakeOpenRouter(function () use ($dataUri) {
            return [
                'id' => 'photo-studio-image',
                'model' => 'google/gemini-2.5-flash-image',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    [
                        'message' => [
                            'content' => '',
                            'images' => [
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $dataUri,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        });

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: 'google/gemini-2.5-flash-image',
            disk: 's3',
            imageInput: 'data:image/png;base64,'.base64_encode('reference'),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $generation = PhotoStudioGeneration::first();

        $this->assertNotNull($generation);
        $this->assertSame('s3', $generation->storage_disk);
        $this->assertNotEmpty($generation->storage_path);

        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    public function test_generate_photo_studio_image_job_fetches_openrouter_file_with_headers(): void
    {
        config()->set('services.photo_studio.generation_disk', 's3');
        config()->set('laravel-openrouter.api_key', 'test-key');
        config()->set('laravel-openrouter.referer', 'https://example.com/app');
        config()->set('laravel-openrouter.title', 'VRBA Test');

        Storage::fake('s3');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $requestLog = [];

        Http::fake([
            'https://openrouter.ai/api/v1/file/*' => function ($request) use (&$requestLog) {
                $requestLog[] = $request;

                return Http::response($this->samplePngBinary(), 200, [
                    'Content-Type' => 'image/png',
                ]);
            },
        ]);

        $this->fakeOpenRouter(function () {
            return [
                'id' => 'photo-studio-image',
                'model' => 'google/gemini-2.5-flash-image',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    [
                        'message' => [
                            'content' => [
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => 'https://openrouter.ai/api/v1/file/abcd1234efgh5678',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        });

        $jobRecord = $this->createPhotoStudioJob($team->id);

        $job = new GeneratePhotoStudioImage(
            productAiJobId: $jobRecord->id,
            teamId: $team->id,
            userId: $user->id,
            productId: null,
            prompt: 'Use this prompt as-is',
            model: 'google/gemini-2.5-flash-image',
            disk: 's3',
            imageInput: 'data:image/png;base64,'.base64_encode('reference'),
            sourceType: 'uploaded_image',
            sourceReference: 'upload.png'
        );

        $job->handle();

        $this->assertNotEmpty($requestLog, 'Expected HTTP request to OpenRouter file endpoint.');
        $request = $requestLog[0];
        $this->assertSame('Bearer test-key', $request->header('Authorization')[0]);
        $this->assertSame('https://example.com/app', $request->header('HTTP-Referer')[0]);
        $this->assertSame('VRBA Test', $request->header('X-Title')[0]);

        $generation = PhotoStudioGeneration::first();
        $this->assertNotNull($generation);
        Storage::disk('s3')->assertExists($generation->storage_path);
    }

    private function createPhotoStudioJob(int $teamId, ?int $productId = null, ?string $sku = null): ProductAiJob
    {
        return ProductAiJob::create([
            'team_id' => $teamId,
            'product_id' => $productId,
            'sku' => $sku,
            'product_ai_template_id' => null,
            'job_type' => ProductAiJob::TYPE_PHOTO_STUDIO,
            'status' => ProductAiJob::STATUS_QUEUED,
            'progress' => 0,
            'queued_at' => now(),
        ]);
    }

    private function fakeOpenRouter(callable $callback): void
    {
        $original = app('laravel-openrouter');

        LaravelOpenRouter::swap(new class($callback)
        {
            public function __construct(private $callback)
            {
            }

            public function chatRequest($chatData)
            {
                $payload = call_user_func($this->callback, $chatData);

                return new class($payload)
                {
                    public function __construct(private array $payload)
                    {
                    }

                    public function toArray(): array
                    {
                        return $this->payload;
                    }
                };
            }
        });

        $this->beforeApplicationDestroyed(static function () use ($original): void {
            LaravelOpenRouter::swap($original);
        });
    }

    private function samplePngBase64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAGgwJ/lAX0NwAAAABJRU5ErkJggg==';
    }

    private function samplePngBinary(): string
    {
        $decoded = base64_decode($this->samplePngBase64(), true);

        if ($decoded === false) {
            $this->fail('Unable to decode sample PNG payload.');
        }

        return $decoded;
    }
}
