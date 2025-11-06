<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductAiGeneration;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
use App\Support\ProductAiContentParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GuzzleHttp\Exception\GuzzleException;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use RuntimeException;
use Throwable;

class RunProductAiTemplateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * Backoff intervals (seconds) between retries.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 600];

    public function __construct(public int $productAiJobId)
    {
        $this->productAiJobId = $productAiJobId;
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $jobRecord = ProductAiJob::query()
            ->with('template')
            ->findOrFail($this->productAiJobId);

        $template = $jobRecord->template;

        if (! $template || ! $template->is_active) {
            throw new RuntimeException('The selected AI template is no longer available.');
        }

        $jobRecord->forceFill([
            'status' => ProductAiJob::STATUS_PROCESSING,
            'attempts' => $this->attempts(),
            'started_at' => now(),
            'progress' => 10,
            'last_error' => null,
        ])->save();

        $product = Product::query()
            ->with('team')
            ->findOrFail($jobRecord->product_id);

        $apiKey = config('laravel-openrouter.api_key');

        if (! $apiKey) {
            throw new RuntimeException('AI provider API key is not configured.');
        }

        try {
            $messages = collect($this->buildMessages($template, $product))
                ->map(fn (array $message) => new MessageData(
                    role: (string) Arr::get($message, 'role', 'user'),
                    content: Arr::get($message, 'content')
                ))
                ->all();

            $options = $template->options();
            unset($options['timeout']);

            $model = Arr::get($template->settings, 'model', config('services.openrouter.model', 'openrouter/auto'));

            $chatDataPayload = array_merge($options, [
                'messages' => $messages,
                'model' => $model,
            ]);

            try {
                $response = LaravelOpenRouter::chatRequest(new ChatData(...$chatDataPayload));
            } catch (GuzzleException $exception) {
                $errorMessage = 'Failed to generate content from the AI provider.';
                $status = null;

                if (method_exists($exception, 'getResponse')) {
                $status = $exception->getResponse()?->getStatusCode();

                $errorMessage = match ($status) {
                    400 => 'The AI provider rejected the request. Check the template parameters and try again.',
                    401 => 'AI credentials are invalid or expired. Update the API key and retry.',
                    402 => 'Out of credits or the request needs fewer max tokens.',
                    403 => 'Provider flagged the request for moderation. Review the input content.',
                    408 => 'Provider timed out before the model could respond.',
                    429 => 'Provider rate limit was hit; wait a moment and try again.',
                    502 => 'The selected AI model is currently unavailable. Try again shortly.',
                    503 => 'No AI provider is available for the selected model at the moment.',
                    default => $errorMessage,
                };
            }

            Log::warning('AI provider request failed', [
                'product_ai_job_id' => $jobRecord->id,
                'product_id' => $product->id,
                'template_id' => $template->id,
                'template_slug' => $template->slug,
                'status' => $status,
                'exception' => $exception->getMessage(),
                'provider' => 'openrouter',
            ]);

            throw new RuntimeException($errorMessage, previous: $exception);
        }

        $data = $response->toArray();
        $finishReason = Str::lower((string) Arr::get($data, 'choices.0.finish_reason', ''));
        $nativeFinishReason = Str::lower((string) Arr::get($data, 'choices.0.native_finish_reason', ''));

        if (in_array($finishReason, ['length', 'max_tokens'], true) ||
            in_array($nativeFinishReason, ['max_output_tokens', 'length'], true)) {
            Log::warning('AI provider response hit token limit', [
                'product_ai_job_id' => $jobRecord->id,
                'product_id' => $product->id,
                'template_id' => $template->id,
                'template_slug' => $template->slug,
                'response' => $data,
            ]);

            throw new RuntimeException('AI response exceeded the configured token limit.');
        }

        $rawContent = Arr::get($data, 'choices.0.message.content');
        $content = $this->normalizeMessageContent($rawContent);

        if ($content === '') {
            Log::warning('AI provider response missing content', [
                'product_ai_job_id' => $jobRecord->id,
                'product_id' => $product->id,
                'template_id' => $template->id,
                'template_slug' => $template->slug,
                'response' => $data,
            ]);

            throw new RuntimeException('Received empty content from the AI provider.');
        }

        $jobRecord->forceFill([
            'progress' => 80,
        ])->save();

        $contentPayload = ProductAiContentParser::normalize($template->contentType(), $content);

        $record = ProductAiGeneration::create([
            'team_id' => $product->team_id,
            'product_id' => $product->id,
            'product_ai_template_id' => $template->id,
            'product_ai_job_id' => $jobRecord->id,
            'sku' => $product->sku,
            'content' => $contentPayload,
            'meta' => [
                'model' => $model,
                'template_slug' => $template->slug,
                'job_id' => $jobRecord->id,
            ],
        ]);

        $this->trimHistory($template, $product->id, max($template->historyLimit(), 1));

        $meta = $jobRecord->meta ?? [];
        $meta['generation_id'] = $record->id;

        $jobRecord->forceFill([
            'status' => ProductAiJob::STATUS_COMPLETED,
            'progress' => 100,
            'finished_at' => now(),
            'meta' => $meta,
        ])->save();
        } catch (Throwable $e) {
            $jobRecord->forceFill([
                'status' => ProductAiJob::STATUS_FAILED,
                'progress' => 0,
                'finished_at' => now(),
                'last_error' => Str::limit($e->getMessage(), 500),
            ])->save();

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $jobRecord = ProductAiJob::query()->find($this->productAiJobId);

        if (! $jobRecord) {
            return;
        }

        $jobRecord->forceFill([
            'status' => ProductAiJob::STATUS_FAILED,
            'progress' => 0,
            'finished_at' => now(),
            'last_error' => Str::limit($exception->getMessage(), 500),
        ])->save();
    }

    protected function buildMessages(ProductAiTemplate $template, Product $product): array
    {
        $userTemplate = trim((string) $template->prompt);

        if ($userTemplate === '') {
            throw new RuntimeException('Template prompt is empty.');
        }

        $placeholders = $this->buildPlaceholders($template, $product);
        $userPrompt = strtr($userTemplate, $placeholders);
        $systemPrompt = trim((string) $template->system_prompt);

        $messages = [];

        if ($systemPrompt !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => strtr($systemPrompt, $placeholders),
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => trim($userPrompt),
        ];

        return $messages;
    }

    protected function buildPlaceholders(ProductAiTemplate $template, Product $product): array
    {
        $placeholders = [];
        $context = $template->context ?? [];

        foreach ($context as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $key = (string) ($definition['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $placeholders['{{ '.$key.' }}'] = $this->resolveContextValue($key, $definition, $template, $product);
        }

        $pattern = '/{{\s*(.+?)\s*}}/u';
        $templates = array_filter([
            $template->prompt,
            $template->system_prompt,
        ]);

        foreach ($templates as $templateString) {
            if (! is_string($templateString)) {
                continue;
            }

            if (preg_match_all($pattern, $templateString, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $rawKey) {
                $key = trim((string) $rawKey);

                if ($key === '') {
                    continue;
                }

                $placeholder = '{{ '.$key.' }}';

                if (array_key_exists($placeholder, $placeholders)) {
                    continue;
                }

                $placeholders[$placeholder] = $this->resolveContextValue($key, [], $template, $product);
            }
        }

        return $placeholders;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function resolveContextValue(string $key, array $definition, ProductAiTemplate $template, Product $product): string
    {
        $variables = config('product-ai.context_variables', []);
        $variable = $variables[$key] ?? null;
        $attribute = $variable['attribute'] ?? $key;
        $default = $definition['default'] ?? ($variable['default'] ?? 'N/A');

        $rawValue = data_get($product, $attribute);

        if (is_string($rawValue)) {
            $rawValue = trim($rawValue);
        }

        if ($rawValue === null || $rawValue === '') {
            $rawValue = $default;
        }

        $cleanMode = $definition['clean'] ?? ($variable['clean'] ?? 'single_line');

        if ($cleanMode === 'multiline') {
            $value = $this->cleanMultiline((string) $rawValue);
        } else {
            $value = $this->cleanSingleLine((string) $rawValue);
        }

        $shouldExcerpt = (bool) ($definition['excerpt'] ?? $variable['excerpt'] ?? false);
        $limit = (int) ($definition['limit'] ?? $variable['limit'] ?? config('product-ai.defaults.description_excerpt_limit', 600));

        if ($shouldExcerpt && $limit > 0 && $rawValue !== $default) {
            $value = Str::limit($value, $limit);
        }

        if ($value === '') {
            return (string) $default;
        }

        return $value;
    }

    protected function trimHistory(ProductAiTemplate $template, int $productId, int $keep): void
    {
        $keep = $keep > 0 ? $keep : 1;

        $idsToRemove = ProductAiGeneration::query()
            ->where('product_id', $productId)
            ->where('product_ai_template_id', $template->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($keep)
            ->pluck('id');

        if ($idsToRemove->isEmpty()) {
            return;
        }

        ProductAiGeneration::query()
            ->whereIn('id', $idsToRemove)
            ->delete();
    }

    protected function cleanMultiline(string $value): string
    {
        $value = strip_tags($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return trim(preg_replace("/[ \t]+/", ' ', $value));
    }

    protected function cleanSingleLine(string $value): string
    {
        return trim(preg_replace("/\s+/", ' ', strip_tags($value)));
    }

    /**
     * @param  mixed  $content
     */
    protected function normalizeMessageContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        $segments = collect($content)->map(function ($segment) {
            if (is_string($segment)) {
                return $segment;
            }

            if (is_array($segment)) {
                if (array_key_exists('text', $segment)) {
                    return (string) $segment['text'];
                }

                if (array_key_exists('content', $segment)) {
                    return (string) $segment['content'];
                }
            }

            return '';
        })->filter()->implode('');

        return trim($segments);
    }
}
