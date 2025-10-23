<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductAiJob;
use App\Support\ProductAiContentParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

abstract class BaseProductAiJob implements ShouldQueue
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

    abstract protected function promptType(): string;

    public function handle(): void
    {
        $jobRecord = ProductAiJob::query()->findOrFail($this->productAiJobId);

        $jobRecord->forceFill([
            'status' => ProductAiJob::STATUS_PROCESSING,
            'attempts' => $this->attempts(),
            'started_at' => now(),
            'progress' => 10,
            'last_error' => null,
        ])->save();

        $config = $this->promptConfig();
        $modelClass = Arr::get($config, 'model');

        if (! $modelClass) {
            throw new RuntimeException('Product AI generation is missing a target model.');
        }

        $product = Product::query()
            ->with('team')
            ->findOrFail($jobRecord->product_id);

        $apiKey = config('services.openai.api_key');
        $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if (! $apiKey) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        try {
            $messages = $this->buildMessages($config, $product);
            $options = $this->mergeOptions($config);
            $timeout = (int) ($options['timeout'] ?? 30);
            unset($options['timeout']);

            $payload = array_merge([
                'model' => Arr::get($config, 'model_override', config('services.openai.model', 'gpt-5')),
                'messages' => $messages,
            ], $options);

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->acceptJson()
                ->withOptions([
                    'http_version' => CURL_HTTP_VERSION_1_1,
                ])
                ->retry(
                    3,
                    500,
                    fn (Throwable $exception) => $exception instanceof ConnectionException,
                    throw: false
                )
                ->post($baseUrl.'/chat/completions', $payload);

            if ($response->failed()) {
                Log::warning('OpenAI request failed', [
                    'product_ai_job_id' => $jobRecord->id,
                    'product_id' => $product->id,
                    'prompt_type' => $jobRecord->prompt_type,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException('Failed to generate content (status '.$response->status().').');
            }

            $data = $response->json();
            $content = trim((string) Arr::get($data, 'choices.0.message.content', ''));

            if ($content === '') {
                Log::warning('OpenAI response missing content', [
                    'product_ai_job_id' => $jobRecord->id,
                    'product_id' => $product->id,
                    'prompt_type' => $jobRecord->prompt_type,
                    'response' => $data,
                ]);

                throw new RuntimeException('Received empty content from OpenAI.');
            }

            $jobRecord->forceFill([
                'progress' => 80,
            ])->save();

            /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
            $contentPayload = ProductAiContentParser::normalizeForModel($modelClass, $content);

            $record = $modelClass::create([
                'team_id' => $product->team_id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'content' => $contentPayload,
                'meta' => [
                    'model' => $payload['model'],
                    'job_id' => $jobRecord->id,
                    'prompt_type' => $jobRecord->prompt_type,
                ],
            ]);

            $this->trimHistory($modelClass, $product->id, (int) Arr::get($config, 'history_limit', $this->defaultHistoryLimit()));

            $meta = $jobRecord->meta ?? [];

            if ($metaKey = Arr::get($config, 'meta_key')) {
                $meta[$metaKey] = $record->id;
            }

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

    protected function promptConfig(): array
    {
        $config = config('product-ai.generations.'.$this->promptType());

        if (! is_array($config) || empty($config)) {
            throw new RuntimeException(sprintf('Prompt configuration missing for [%s].', $this->promptType()));
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function buildMessages(array $config, Product $product): array
    {
        $prompts = Arr::get($config, 'prompts', []);
        $userTemplate = Arr::get($prompts, 'user');

        if (! is_string($userTemplate) || trim($userTemplate) === '') {
            throw new RuntimeException('User prompt template is not configured.');
        }

        $systemPrompt = Arr::get($prompts, 'system');
        $descriptionLimit = (int) Arr::get(
            $prompts,
            'description_excerpt_limit',
            $this->defaultDescriptionLimit()
        );

        $description = $this->cleanMultiline((string) ($product->description ?? ''));
        $descriptionExcerpt = $descriptionLimit > 0
            ? Str::limit($description, $descriptionLimit)
            : $description;

        $placeholders = [
            '{{ title }}' => $product->title ? $this->cleanSingleLine($product->title) : 'Untitled product',
            '{{ description }}' => $description !== '' ? $description : 'N/A',
            '{{ description_excerpt }}' => $descriptionExcerpt !== '' ? $descriptionExcerpt : 'N/A',
            '{{ sku }}' => $product->sku ?: 'N/A',
            '{{ gtin }}' => $product->gtin ?: 'N/A',
            '{{ url }}' => $product->url ?: 'N/A',
        ];

        $userPrompt = strtr($userTemplate, $placeholders);

        $messages = [];

        if (is_string($systemPrompt) && trim($systemPrompt) !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => trim($systemPrompt),
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => trim($userPrompt),
        ];

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function mergeOptions(array $config): array
    {
        $defaultOptions = config('product-ai.defaults.options', []);
        $customOptions = Arr::get($config, 'options', []);

        return array_filter(
            array_merge($defaultOptions, $customOptions),
            static fn ($value) => $value !== null
        );
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function trimHistory(string $modelClass, int $productId, int $keep): void
    {
        $idsToRemove = $modelClass::query()
            ->where('product_id', $productId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($keep)
            ->pluck('id');

        if ($idsToRemove->isEmpty()) {
            return;
        }

        $modelClass::query()
            ->whereIn('id', $idsToRemove)
            ->delete();
    }

    protected function defaultHistoryLimit(): int
    {
        return (int) config('product-ai.defaults.history_limit', 10);
    }

    protected function defaultDescriptionLimit(): int
    {
        return (int) config('product-ai.defaults.description_excerpt_limit', 600);
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
}
