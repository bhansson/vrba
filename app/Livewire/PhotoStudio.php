<?php

namespace App\Livewire;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File as FileRule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageContentPartData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageUrlData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\DTO\TextContentData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use RuntimeException;
use Throwable;

class PhotoStudio extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $image = null;

    public ?int $productId = null;

    public string $creativeBrief = '';

    public ?string $promptResult = null;

    public ?string $errorMessage = null;

    public bool $isProcessing = false;

    public ?string $productImagePreview = null;

    /**
     * Light-weight product catalogue for the select element.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $products = [];

    public function mount(): void
    {
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            abort(403, 'Join or create a team to access the Photo Studio.');
        }

        $this->products = Product::query()
            ->where('team_id', $team->id)
            ->orderBy('title')
            ->limit(250)
            ->get(['id', 'title', 'sku', 'brand', 'image_link'])
            ->map(static function (Product $product): array {
                return [
                    'id' => $product->id,
                    'title' => $product->title ?: 'Untitled product #'.$product->id,
                    'sku' => $product->sku,
                    'brand' => $product->brand,
                    'image_link' => $product->image_link,
                ];
            })
            ->toArray();

        $this->syncSelectedProductPreview();
    }

    /**
     * Reset the stored prompt when the creative direction changes.
     */
    public function updatedCreativeBrief(): void
    {
        $this->promptResult = null;
    }

    /**
     * Ensure only one source of truth is active.
     */
    public function updatedProductId(): void
    {
        if ($this->productId) {
            $this->image = null;
        }

        $this->promptResult = null;
        $this->syncSelectedProductPreview();
    }

    /**
     * When a new image is uploaded, clear the product selection.
     */
    public function updatedImage(): void
    {
        $this->productId = null;
        $this->promptResult = null;
        $this->productImagePreview = null;
    }

    public function extractPrompt(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->promptResult = null;

        $this->validate();

        if (! $this->hasImageSource()) {
            $message = 'Upload an image or choose a product to continue.';
            $this->addError('image', $message);
            $this->addError('productId', $message);

            return;
        }

        if (! config('laravel-openrouter.api_key')) {
            $this->errorMessage = 'Configure an OpenRouter API key before extracting prompts.';

            return;
        }

        $this->isProcessing = true;

        try {
            [$imageUrl, $product] = $this->resolveImageSource();

            $messages = $this->buildMessages($imageUrl, $product);

            $model = config('services.photo_studio.model', 'openai/gpt-4.1');

            $chatData = new ChatData(
                messages: $messages,
                model: $model,
                max_tokens: 700,
                temperature: 0.4,
            );

            $response = LaravelOpenRouter::chatRequest($chatData);

            $content = $this->extractResponseContent($response->toArray());

            if ($content === '') {
                throw new RuntimeException('Received an empty response from the AI provider.');
            }

            $this->promptResult = $content;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Photo Studio prompt extraction failed', [
                'user_id' => Auth::id(),
                'product_id' => $this->productId,
                'exception' => $exception,
            ]);

            $this->errorMessage = 'Unable to extract a prompt right now. Please try again in a moment.';
        } finally {
            $this->isProcessing = false;
        }
    }

    public function render(): View
    {
        return view('livewire.photo-studio');
    }

    protected function rules(): array
    {
        $teamId = Auth::user()?->currentTeam?->id;

        return [
            'image' => [
                'nullable',
                FileRule::image()
                    ->max(8 * 1024), // 8 MB
            ],
            'productId' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')
                    ->where('team_id', $teamId),
            ],
            'creativeBrief' => ['nullable', 'string', 'max:600'],
        ];
    }

    private function hasImageSource(): bool
    {
        return $this->image instanceof TemporaryUploadedFile || $this->productId !== null;
    }

    /**
     * @return array{0: string, 1: Product|null}
     *
     * @throws ValidationException
     */
    private function resolveImageSource(): array
    {
        if ($this->image instanceof TemporaryUploadedFile) {
            return [$this->encodeUploadedImage($this->image), null];
        }

        $teamId = Auth::user()?->currentTeam?->id;

        $product = Product::query()
            ->where('team_id', $teamId)
            ->find($this->productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'productId' => 'The selected product is no longer available.',
            ]);
        }

        if (! $product->image_link) {
            throw ValidationException::withMessages([
                'productId' => 'The selected product does not have an image to analyse.',
            ]);
        }

        return [$product->image_link, $product];
    }

    private function encodeUploadedImage(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Failed to read the uploaded image.');
        }

        $mime = $file->getMimeType() ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /**
     * @param  string  $imageUrl
     * @param  Product|null  $product
     * @return MessageData[]
     */
    private function buildMessages(string $imageUrl, ?Product $product): array
    {
        $systemPrompt = <<<'PROMPT'
You are an expert visual art director and product photographer. The response must be plain text no longer than 300 words.
PROMPT;

        $details = $product ? sprintf(
            "Product name: %s\nBrand: %s\nSKU: %s",
            $product->title ?: 'N/A',
            $product->brand ?: 'N/A',
            $product->sku ?: 'N/A',
        ) : 'Product metadata: not provided.';

        $userText = <<<'TEXT'
Analyze the product image to understand what kind of item it is, including its approximate size, materials,
intended use, and emotional tone (e.g. sporty, safety-focused, luxury, tech, lifestyle, beauty, etc.).
Based on that understanding, create one single, high-quality image generation prompt where the same product
(referred to as “the reference product”) appears naturally and fittingly in a relevant environment, lighting
condition, and visual style that reflect its real-world context. Do not mention or describe brand names, logos,
or label text. Keep the product clearly visible and central in the scene. Do not describe the product, only
the environment to fit it.
TEXT;

        $contentParts = [
            new TextContentData(
                type: TextContentData::ALLOWED_TYPE,
                text: $userText."\n\n".$details
            ),
            $this->creativeBrief !== ''
                ? new TextContentData(
                    type: TextContentData::ALLOWED_TYPE,
                    text: 'Creative direction from the user: '.$this->creativeBrief
                )
                : null,
            new ImageContentPartData(
                type: ImageContentPartData::ALLOWED_TYPE,
                image_url: new ImageUrlData(
                    url: $imageUrl,
                    detail: 'high'
                )
            ),
        ];

        return [
            new MessageData(
                role: 'system',
                content: $systemPrompt
            ),
            new MessageData(
                role: 'user',
                content: array_values(array_filter($contentParts))
            ),
        ];
    }

    private function extractResponseContent(array $response): string
    {
        $content = Arr::get($response, 'choices.0.message.content');

        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            $text = collect($content)
                ->map(static function ($segment): string {
                    if (is_array($segment) && isset($segment['text'])) {
                        return (string) $segment['text'];
                    }

                    return is_string($segment) ? $segment : '';
                })
                ->implode("\n");

            return trim($text);
        }

        return '';
    }

    private function syncSelectedProductPreview(): void
    {
        $this->productImagePreview = null;

        if (! $this->productId) {
            return;
        }

        $teamId = Auth::user()?->currentTeam?->id;

        $product = Product::query()
            ->select('id', 'team_id', 'image_link')
            ->where('team_id', $teamId)
            ->find($this->productId);

        $this->productImagePreview = $product?->image_link;
    }
}
