<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductAiDescriptionSummary;
use App\Models\ProductAiJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateProductDescriptionSummary implements ShouldQueue
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

    public function __construct(public readonly int $productAiJobId)
    {
        $this->onQueue('ai');
    }

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

        $product = Product::query()
            ->with('team')
            ->findOrFail($jobRecord->product_id);

        $apiKey = config('services.openai.api_key');
        $model = config('services.openai.model', 'gpt-5');
        $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if (! $apiKey) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        try {
            $prompt = sprintf(
                "Create a concise, high-converting marketing summary (max 60 words) for the following product. Highlight differentiators and maintain an upbeat, trustworthy tone.\n\nTitle: %s\nDescription: %s",
                $product->title ?: 'Untitled product',
                Str::limit(strip_tags($product->description ?? ''), 400)
            );

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->acceptJson()
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a product marketing assistant who writes short, high-converting product summaries.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI request failed', [
                    'product_ai_job_id' => $jobRecord->id,
                    'product_id' => $product->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('Failed to generate summary (status '.$response->status().').');
            }

            $payload = $response->json();
            $summary = data_get($payload, 'choices.0.message.content');

            if (! $summary) {
                Log::warning('OpenAI response missing summary', [
                    'product_ai_job_id' => $jobRecord->id,
                    'product_id' => $product->id,
                    'response' => $payload,
                ]);

                throw new \RuntimeException('Received an empty summary from OpenAI.');
            }

            $summaryText = trim($summary);

            $jobRecord->forceFill([
                'progress' => 80,
            ])->save();

            $record = ProductAiDescriptionSummary::create([
                'team_id' => $product->team_id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'content' => $summaryText,
                'meta' => [
                    'model' => $model,
                    'job_id' => $jobRecord->id,
                ],
            ]);

            $this->trimHistory($product->id);

            $jobRecord->forceFill([
                'status' => ProductAiJob::STATUS_COMPLETED,
                'progress' => 100,
                'finished_at' => now(),
                'meta' => array_merge($jobRecord->meta ?? [], [
                    'description_summary_record_id' => $record->id,
                ]),
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

    protected function trimHistory(int $productId): void
    {
        $idsToRemove = ProductAiDescriptionSummary::query()
            ->where('product_id', $productId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip(10)
            ->pluck('id');

        if ($idsToRemove->isEmpty()) {
            return;
        }

        ProductAiDescriptionSummary::query()
            ->whereIn('id', $idsToRemove)
            ->delete();
    }
}
