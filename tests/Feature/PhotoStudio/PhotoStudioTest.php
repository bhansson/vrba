<?php

namespace Tests\Feature\PhotoStudio;

use App\Livewire\PhotoStudio;
use App\Models\Product;
use App\Models\ProductFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageContentPartData;
use MoeMizrak\LaravelOpenrouter\DTO\ResponseData;
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
            ]);

        LaravelOpenRouter::shouldReceive('chatRequest')
            ->once()
            ->with(\Mockery::on(function ($chatData) use ($product) {
                $this->assertInstanceOf(ChatData::class, $chatData);
                $this->assertSame('openai/gpt-4.1', $chatData->model);

                $userMessage = Arr::get($chatData->messages, 1);
                $this->assertNotNull($userMessage, 'User message missing from payload.');

                $imagePart = collect($userMessage->content ?? [])
                    ->first(fn ($part) => $part instanceof ImageContentPartData);

                $this->assertNotNull($imagePart, 'Image payload missing from message.');
                $this->assertSame($product->image_link, $imagePart->image_url->url);

                return true;
            }))
            ->andReturn(ResponseData::from([
                'id' => 'photo-studio-test',
                'model' => 'openrouter/openai/gpt-4.1',
                'object' => 'chat.completion',
                'created' => now()->timestamp,
                'choices' => [
                    ['message' => ['content' => 'High-end studio prompt']],
                ],
            ]));

        $this->actingAs($user);

        Livewire::test(PhotoStudio::class)
            ->set('productId', $product->id)
            ->call('extractPrompt')
            ->assertSet('promptResult', 'High-end studio prompt')
            ->assertSet('productImagePreview', $product->image_link);
    }
}
