<?php

namespace App\Livewire;

use App\Jobs\GeneratePhotoStudioImage;
use App\Models\PhotoStudioGeneration;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    public ?string $generatedImageUrl = null;

    /**
     * @var array{path: string, disk: string, response_id: string|null}|null
     */
    public ?array $latestGeneration = null;

    public ?string $generationStatus = null;

    /**
     * Gallery of previously generated images for the selected product.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $productGallery = [];

    public ?int $latestObservedGenerationId = null;

    public ?int $pendingGenerationBaselineId = null;

    public bool $isAwaitingGeneration = false;

    public ?int $pendingProductId = null;

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
        $this->refreshLatestGeneration();
        $this->refreshProductGallery();
    }

    /**
     * Reset the stored prompt when the creative direction changes.
     */
    public function updatedCreativeBrief(): void
    {
        $this->promptResult = null;
        $this->resetGenerationPreview();
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
        $this->resetGenerationPreview();
        $this->syncSelectedProductPreview();
        $this->refreshProductGallery();
    }

    /**
     * When a new image is uploaded, clear the product selection.
     */
    public function updatedImage(): void
    {
        $this->productId = null;
        $this->promptResult = null;
        $this->resetGenerationPreview();
        $this->productImagePreview = null;
        $this->refreshProductGallery();
    }

    public function extractPrompt(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->promptResult = null;
        $this->resetGenerationPreview();

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

    public function generateImage(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->generationStatus = null;

        if (! config('laravel-openrouter.api_key')) {
            $this->errorMessage = 'Configure an OpenRouter API key before generating images.';

            return;
        }

        if (! $this->promptResult) {
            $this->errorMessage = 'Prompt is missing.';

            return;
        }

        $this->validate();

        $disk = config('services.photo_studio.generation_disk', 's3');
        $availableDisks = config('filesystems.disks', []);

        if (! array_key_exists($disk, $availableDisks)) {
            $this->errorMessage = 'The configured storage disk for Photo Studio is not available.';

            return;
        }

        $team = Auth::user()?->currentTeam;

        if (! $team) {
            abort(403, 'Join or create a team to access the Photo Studio.');
        }

        try {
            $previousGenerationId = PhotoStudioGeneration::query()
                ->where('team_id', $team->id)
                ->where('product_id', $this->productId)
                ->max('id');

            $imageInput = null;
            $product = null;
            $sourceType = 'prompt_only';
            $sourceReference = null;

            if ($this->hasImageSource()) {
                [$imageInput, $product] = $this->resolveImageSource();
                $sourceType = $this->image instanceof TemporaryUploadedFile ? 'uploaded_image' : 'product_image';
                $sourceReference = $this->image instanceof TemporaryUploadedFile
                    ? ($this->image->getClientOriginalName() ?: $this->image->getFilename())
                    : $product?->image_link;
            }

            $model = config('services.photo_studio.image_model', 'google/gemini-2.5-flash-image');

            $this->resetGenerationPreview();

            GeneratePhotoStudioImage::dispatch(
                teamId: $team->id,
                userId: Auth::id(),
                productId: $product?->id,
                prompt: $this->promptResult,
                model: $model,
                disk: $disk,
                imageInput: $imageInput,
                sourceType: $sourceType,
                sourceReference: $sourceReference,
            );

            $this->pendingGenerationBaselineId = $previousGenerationId ?? 0;
            $this->isAwaitingGeneration = true;
            $this->pendingProductId = $product?->id;
            $this->generationStatus = 'Image generation queued. Hang tight while we render your scene.';
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Photo Studio image generation failed', [
                'user_id' => Auth::id(),
                'product_id' => $this->productId,
                'exception' => $exception,
            ]);

            $this->errorMessage = 'Unable to generate an image right now. Please try again in a moment.';
        }
    }

    public function pollGenerationStatus(): void
    {
        if (! $this->isAwaitingGeneration) {
            return;
        }

        $teamId = Auth::user()?->currentTeam?->id;

        if (! $teamId) {
            $this->isAwaitingGeneration = false;

            return;
        }

        $baseline = $this->pendingGenerationBaselineId ?? 0;

        $latest = PhotoStudioGeneration::query()
            ->where('team_id', $teamId)
            ->where('id', '>', $baseline)
            ->when(
                $this->pendingProductId === null,
                static function ($query): void {
                    $query->whereNull('product_id');
                },
                function ($query): void {
                    $query->where('product_id', $this->pendingProductId);
                }
            )
            ->latest()
            ->first();

        if (! $latest) {
            $this->generationStatus = 'Image generation in progress…';

            return;
        }

        $shouldConfirmGallery = $this->pendingProductId !== null && $this->pendingProductId === $this->productId;

        if ($shouldConfirmGallery) {
            $this->refreshProductGallery();
            $latestGalleryId = $this->productGallery[0]['id'] ?? null;

            if ($latestGalleryId !== $latest->id) {
                $this->generationStatus = 'Image generation in progress…';

                return;
            }
        }

        $this->refreshLatestGeneration();

        $this->isAwaitingGeneration = false;
        $this->pendingGenerationBaselineId = null;
        $this->generationStatus = $shouldConfirmGallery
            ? 'New render added to the gallery.'
            : 'New render finished.';
        $this->pendingProductId = null;

        if ($shouldConfirmGallery) {
            $this->refreshProductGallery();
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
You are an expert visual art director and product photographer. The response must be plain text no longer than 300 words. ONLY output the prompt text, no titles, pre text or comments, nothing else.
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

    private function resolveDiskUrl(string $disk, string $path): ?string
    {
        try {
            return Storage::disk($disk)->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    private function resetGenerationPreview(): void
    {
        $this->generatedImageUrl = null;
        $this->latestGeneration = null;
        $this->latestObservedGenerationId = null;
        $this->isAwaitingGeneration = false;
        $this->pendingGenerationBaselineId = null;
        $this->generationStatus = null;
        $this->pendingProductId = null;
    }

    private function refreshLatestGeneration(): void
    {
        $teamId = Auth::user()?->currentTeam?->id;

        if (! $teamId) {
            $this->resetGenerationPreview();

            return;
        }

        $latest = PhotoStudioGeneration::query()
            ->where('team_id', $teamId)
            ->latest()
            ->first();

        if (! $latest) {
            $this->resetGenerationPreview();

            return;
        }

        $this->latestGeneration = [
            'path' => $latest->storage_path,
            'disk' => $latest->storage_disk,
            'response_id' => $latest->response_id,
        ];

        $this->generatedImageUrl = $this->resolveDiskUrl($latest->storage_disk, $latest->storage_path);
        $this->latestObservedGenerationId = $latest->id;
    }

    private function refreshProductGallery(): void
    {
        $this->productGallery = [];

        if (! $this->productId) {
            return;
        }

        $teamId = Auth::user()?->currentTeam?->id;

        if (! $teamId) {
            return;
        }

        $generations = PhotoStudioGeneration::query()
            ->where('team_id', $teamId)
            ->where('product_id', $this->productId)
            ->latest()
            ->get();

        $this->productGallery = $generations
            ->map(function (PhotoStudioGeneration $generation): array {
                return [
                    'id' => $generation->id,
                    'url' => $this->resolveDiskUrl($generation->storage_disk, $generation->storage_path),
                    'disk' => $generation->storage_disk,
                    'path' => $generation->storage_path,
                    'created_at' => optional($generation->created_at)->toDateTimeString(),
                    'created_at_human' => optional($generation->created_at)->diffForHumans(),
                ];
            })
            ->toArray();
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
