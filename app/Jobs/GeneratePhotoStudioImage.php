<?php

namespace App\Jobs;

use App\Models\PhotoStudioGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageContentPartData;
use MoeMizrak\LaravelOpenrouter\DTO\ImageUrlData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\DTO\TextContentData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use RuntimeException;
use Throwable;

class GeneratePhotoStudioImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public ?int $timeout = 90;

    public function __construct(
        public int $teamId,
        public int $userId,
        public ?int $productId,
        public string $prompt,
        public string $model,
        public string $disk,
        public ?string $imageInput,
        public string $sourceType,
        public ?string $sourceReference = null,
    ) {
        //
    }

    public function handle(): void
    {
        try {
            $this->ensureDiskIsConfigured();

            $messages = $this->buildGenerationMessages($this->prompt, $this->imageInput);

            $chatData = new ChatData(
                messages: $messages,
                model: $this->model,
                temperature: 0.85,
                max_tokens: 2048,
            );

            $response = LaravelOpenRouter::chatRequest($chatData)->toArray();

            $imagePayload = $this->extractGeneratedImage($response);

            $path = $this->storeGeneratedImage(
                binary: $imagePayload['binary'],
                extension: $imagePayload['extension']
            );

            $metadata = array_filter([
                'provider' => $response['provider'] ?? null,
                'usage' => $response['usage'] ?? null,
            ]);

            PhotoStudioGeneration::create([
                'team_id' => $this->teamId,
                'user_id' => $this->userId,
                'product_id' => $this->productId,
                'source_type' => $this->sourceType,
                'source_reference' => $this->sourceReference,
                'prompt' => $this->prompt,
                'model' => $this->model,
                'storage_disk' => $this->disk,
                'storage_path' => $path,
                'response_id' => $response['id'] ?? null,
                'response_model' => $response['model'] ?? null,
                'response_metadata' => $metadata ?: null,
            ]);
        } catch (Throwable $exception) {
            Log::error('Photo Studio job failed', [
                'team_id' => $this->teamId,
                'user_id' => $this->userId,
                'product_id' => $this->productId,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * @return MessageData[]
     */
    private function buildGenerationMessages(string $prompt, ?string $imageUrl): array
    {
        $systemPrompt = <<<'PROMPT'
You are a senior CGI artist who specialises in photorealistic product renders. Generate exactly one high-resolution marketing image using the supplied prompt and reference photo.
PROMPT;

        $userContent = [
            new TextContentData(
                type: TextContentData::ALLOWED_TYPE,
                text: $prompt
            ),
        ];

        if ($imageUrl) {
            $userContent[] = new ImageContentPartData(
                type: ImageContentPartData::ALLOWED_TYPE,
                image_url: new ImageUrlData(
                    url: $imageUrl,
                    detail: 'high'
                )
            );
        }

        return [
            new MessageData(
                role: 'system',
                content: $systemPrompt
            ),
            new MessageData(
                role: 'user',
                content: $userContent
            ),
        ];
    }

    /**
     * @return array{binary: string, extension: string}
     */
    private function extractGeneratedImage(array $response): array
    {
        $choices = Arr::get($response, 'choices', []);
        $attachments = $this->normalizeAttachments(Arr::get($response, 'attachments', []));

        foreach ($choices as $choice) {
            $message = Arr::get($choice, 'message', []);
            $content = $message['content'] ?? null;

            $payload = $this->extractFromContent($content, $attachments);
            if ($payload) {
                return $payload;
            }

            if (! empty($message['images'])) {
                $payload = $this->extractFromImageEntries($message['images'], $attachments);
                if ($payload) {
                    return $payload;
                }
            }

            $payload = $this->extractFromAttachmentReferences($message['attachments'] ?? [], $attachments, true);
            if ($payload) {
                return $payload;
            }
        }

        $outputs = Arr::get($response, 'outputs', []);
        $payload = $this->extractFromOutputs($outputs, $attachments);
        if ($payload) {
            return $payload;
        }

        $payload = $this->extractFromAttachmentReferences(Arr::get($response, 'attachments', []), $attachments, true);
        if ($payload) {
            return $payload;
        }

        $dataEntries = Arr::get($response, 'data', []);
        foreach ($dataEntries as $entry) {
            if (isset($entry['b64_json'])) {
                return $this->decodeBase64Image($entry['b64_json'], $entry['mime_type'] ?? null);
            }

            if (isset($entry['url'])) {
                return $this->fetchImageFromUrl($entry['url']);
            }
        }

        $firstContent = Arr::get($choices, '0.message.content');
        if (is_string($firstContent)) {
            $inline = $this->tryDecodeInlineData($firstContent);
            if ($inline) {
                return $inline;
            }
        }

        Log::warning('Photo Studio image response missing image payload', [
            'response' => $response,
        ]);

        throw new RuntimeException('Image data missing from provider response.');
    }

    /**
     * @return array{binary: string, extension: string}
     */
    private function decodeBase64Image(string $encoded, ?string $mime = null): array
    {
        if (str_contains($encoded, ',')) {
            [$header, $body] = array_pad(explode(',', $encoded, 2), 2, null);

            if ($body !== null && str_contains((string) $header, 'base64')) {
                $encoded = $body;

                if (! $mime && preg_match('/data:(.*?);/', (string) $header, $matches)) {
                    $mime = $matches[1] ?? null;
                }
            }
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false) {
            throw new RuntimeException('Failed to decode generated image payload.');
        }

        $detectedMime = $mime ?? $this->detectMimeFromBinary($binary);

        return [
            'binary' => $binary,
            'extension' => $this->guessExtension($detectedMime),
        ];
    }

    /**
     * @param  mixed  $content
     * @param  array<string, array>  $attachments
     * @return array{binary: string, extension: string}|null
     */
    private function extractFromContent(mixed $content, array $attachments): ?array
    {
        if (is_string($content)) {
            return $this->tryDecodeInlineData($content);
        }

        if (! is_iterable($content)) {
            return null;
        }

        foreach ($content as $segment) {
            if (is_string($segment)) {
                $payload = $this->tryDecodeInlineData($segment);
                if ($payload) {
                    return $payload;
                }
                continue;
            }

            if (! is_array($segment)) {
                continue;
            }

            if (isset($segment['image']) && is_array($segment['image'])) {
                $imagePayload = $this->extractFromImageArray($segment['image']);
                if ($imagePayload) {
                    return $imagePayload;
                }
            }

            $base64 = $segment['image_base64'] ?? $segment['b64_json'] ?? ($segment['data'] ?? null);
            if ($base64) {
                return $this->decodeBase64Image($base64, $segment['mime_type'] ?? null);
            }

            $url = Arr::get($segment, 'image_url.url', $segment['url'] ?? null);
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                return $this->fetchImageFromUrl($url);
            }

            if (! empty($segment['asset_pointer'])) {
                $pointerPayload = $this->resolveAttachmentPointer($segment['asset_pointer'], $attachments);
                if ($pointerPayload) {
                    return $pointerPayload;
                }
            }

            if (! empty($segment['data']) && is_array($segment['data'])) {
                $dataPayload = $this->extractFromImageArray($segment['data'], $segment['mime_type'] ?? null);
                if ($dataPayload) {
                    return $dataPayload;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $entries
     * @param  array<string, array>  $attachments
     */
    private function extractFromImageEntries(array $entries, array $attachments): ?array
    {
        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $payload = $this->tryDecodeInlineData($entry);
                if ($payload) {
                    return $payload;
                }
                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            if (isset($entry['image_url']['url'])) {
                $url = $entry['image_url']['url'];

                if (is_string($url)) {
                    $payload = $this->tryDecodeInlineData($url);

                    if ($payload) {
                        return $payload;
                    }
                }
            }

            if (isset($entry['asset_pointer'])) {
                $payload = $this->resolveAttachmentPointer($entry['asset_pointer'], $attachments);
                if ($payload) {
                    return $payload;
                }
            }

            $payload = $this->extractFromImageArray($entry, null);
            if ($payload) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $references
     * @param  array<string, array>  $attachments
     * @return array{binary: string, extension: string}|null
     */
    private function extractFromAttachmentReferences(array $references, array $attachments, bool $allowRaw = false): ?array
    {
        foreach ($references as $reference) {
            if (is_string($reference)) {
                $payload = $this->resolveAttachmentPointer($reference, $attachments);
                if ($payload) {
                    return $payload;
                }

                continue;
            }

            if (! is_array($reference)) {
                continue;
            }

            if (! empty($reference['asset_pointer'])) {
                $payload = $this->resolveAttachmentPointer($reference['asset_pointer'], $attachments);
                if ($payload) {
                    return $payload;
                }
            }

            if ($allowRaw) {
                $payload = $this->decodeAttachmentRecord($reference);
                if ($payload) {
                    return $payload;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array>  $attachments
     * @return array<string, array>
     */
    private function normalizeAttachments(array $attachments): array
    {
        $map = [];

        foreach ($attachments as $attachment) {
            $id = $attachment['id'] ?? $attachment['name'] ?? null;

            if (! $id) {
                continue;
            }

            $map[$id] = $attachment;
            $map['attachment://'.$id] = $attachment;
            $map['asset://'.$id] = $attachment;
        }

        return $map;
    }

    /**
     * @param  array<string, array>  $attachments
     * @return array{binary: string, extension: string}|null
     */
    private function resolveAttachmentPointer(string $pointer, array $attachments): ?array
    {
        $candidate = $attachments[$pointer] ?? null;

        if (! $candidate) {
            $normalized = preg_replace('/^(attachment|asset):\/\//', '', $pointer);

            if (is_string($normalized)) {
                $candidate = $attachments[$normalized] ?? null;

                if (! $candidate && str_contains($normalized, '#')) {
                    $beforeHash = strstr($normalized, '#', true);
                    if ($beforeHash !== false) {
                        $candidate = $attachments[$beforeHash] ?? null;
                    }
                }
            }
        }

        if (! $candidate) {
            return null;
        }

        return $this->decodeAttachmentRecord($candidate);
    }

    /**
     * @return array{binary: string, extension: string}|null
     */
    private function decodeAttachmentRecord(array $attachment): ?array
    {
        if (isset($attachment['data']) && ! is_array($attachment['data'])) {
            return $this->decodeBase64Image($attachment['data'], $attachment['mime_type'] ?? $attachment['mime'] ?? null);
        }

        if (isset($attachment['data']) && is_array($attachment['data'])) {
            $payload = $this->extractFromImageArray($attachment['data'], $attachment['mime_type'] ?? $attachment['mime'] ?? null);
            if ($payload) {
                return $payload;
            }
        }

        if (isset($attachment['b64_json'])) {
            return $this->decodeBase64Image($attachment['b64_json'], $attachment['mime_type'] ?? null);
        }

        if (isset($attachment['image_base64'])) {
            return $this->decodeBase64Image($attachment['image_base64'], $attachment['mime_type'] ?? null);
        }

        if (isset($attachment['image']) && is_array($attachment['image'])) {
            return $this->extractFromImageArray($attachment['image'], $attachment['mime_type'] ?? null);
        }

        if (isset($attachment['url']) && filter_var($attachment['url'], FILTER_VALIDATE_URL)) {
            return $this->fetchImageFromUrl($attachment['url']);
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $outputs
     */
    private function extractFromOutputs(array $outputs, array $attachments): ?array
    {
        foreach ($outputs as $output) {
            if (isset($output['content'])) {
                $payload = $this->extractFromContent($output['content'], $attachments);
                if ($payload) {
                    return $payload;
                }
            }

            $payload = $this->decodeAttachmentRecord($output);
            if ($payload) {
                return $payload;
            }

            if (isset($output['attachments'])) {
                $payload = $this->extractFromAttachmentReferences($output['attachments'], $attachments, true);
                if ($payload) {
                    return $payload;
                }
            }
        }

        return null;
    }

    private function extractFromImageArray(array $image, ?string $fallbackMime = null): ?array
    {
        $mime = $image['mime_type'] ?? $image['mime'] ?? $fallbackMime;

        if (isset($image['base64'])) {
            return $this->decodeBase64Image($image['base64'], $mime);
        }

        if (isset($image['b64_json'])) {
            return $this->decodeBase64Image($image['b64_json'], $mime);
        }

        if (isset($image['data'])) {
            if (is_string($image['data'])) {
                return $this->decodeBase64Image($image['data'], $mime);
            }

            if (is_array($image['data'])) {
                return $this->extractFromImageArray($image['data'], $mime);
            }
        }

        if (isset($image['url']) && filter_var($image['url'], FILTER_VALIDATE_URL)) {
            return $this->fetchImageFromUrl($image['url']);
        }

        return null;
    }

    /**
     * @return array{binary: string, extension: string}|null
     */
    private function tryDecodeInlineData(string $content): ?array
    {
        if (preg_match('/data:(image\\/[a-zA-Z0-9.+-]+);base64,([A-Za-z0-9+\\/=\\r\\n]+)/', $content, $matches)) {
            return $this->decodeBase64Image($matches[0], $matches[1] ?? null);
        }

        if (filter_var($content, FILTER_VALIDATE_URL)) {
            return $this->fetchImageFromUrl($content);
        }

        return null;
    }

    /**
     * @return array{binary: string, extension: string}
     */
    private function fetchImageFromUrl(string $url): array
    {
        $http = Http::timeout(60);

        if ($this->requiresOpenRouterHeaders($url)) {
            $http = $http->withHeaders($this->openRouterHeaders());
        }

        $response = $http->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Unable to download the generated image asset.');
        }

        return [
            'binary' => (string) $response->body(),
            'extension' => $this->guessExtension($response->header('Content-Type')),
        ];
    }

    private function requiresOpenRouterHeaders(string $url): bool
    {
        return str_starts_with($url, 'https://openrouter.ai/');
    }

    /**
     * @return array<string, string>
     */
    private function openRouterHeaders(): array
    {
        $headers = [];

        if ($apiKey = config('laravel-openrouter.api_key')) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        if ($referer = config('laravel-openrouter.referer')) {
            $headers['HTTP-Referer'] = $referer;
        }

        if ($title = config('laravel-openrouter.title')) {
            $headers['X-Title'] = $title;
        }

        return $headers;
    }

    private function guessExtension(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    private function detectMimeFromBinary(string $binary): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = finfo_buffer($finfo, $binary) ?: null;
        finfo_close($finfo);

        return $mime;
    }

    private function storeGeneratedImage(string $binary, string $extension): string
    {
        $directory = sprintf('photo-studio/%d/%s', $this->teamId, now()->format('Y/m/d'));
        $path = $directory.'/'.Str::uuid().'.'.$extension;

        $stored = Storage::disk($this->disk)->put($path, $binary, ['visibility' => 'public']);

        if (! $stored) {
            throw new RuntimeException('Unable to store the generated image.');
        }

        return $path;
    }

    private function ensureDiskIsConfigured(): void
    {
        $disks = config('filesystems.disks', []);

        if (! array_key_exists($this->disk, $disks)) {
            throw new RuntimeException('The configured storage disk for Photo Studio is not available.');
        }
    }
}
