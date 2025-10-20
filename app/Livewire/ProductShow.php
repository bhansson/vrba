<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductAiJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductShow extends Component
{
    public int $productId;

    public array $generationStatus = [];
    public array $generationError = [];
    public array $generationLoading = [];
    public array $generationContent = [];

    public function mount(int $productId): void
    {
        $this->productId = $productId;
    }

    public function render()
    {
        $product = $this->product;

        return view('livewire.product-show', [
            'product' => $product,
            'generationStatus' => $this->generationStatus,
            'generationError' => $this->generationError,
            'generationLoading' => $this->generationLoading,
            'generationContent' => $this->generationContent,
            'generationHistory' => $this->buildGenerationHistory($product),
        ]);
    }

    public function queueGeneration(string $promptType): void
    {
        $product = $this->product;

        $this->generationError[$promptType] = null;
        $this->generationStatus[$promptType] = null;
        $this->generationLoading[$promptType] = true;

        try {
            $configuredGenerations = config('product-ai.generations', []);

            if (! array_key_exists($promptType, $configuredGenerations)) {
                throw new \RuntimeException('Unsupported generation type: '.$promptType);
            }

            if (! config('services.openai.api_key')) {
                throw new \RuntimeException('OpenAI API key is not configured.');
            }

            if (! $product->sku) {
                throw new \RuntimeException('Product is missing an SKU, cannot queue generation.');
            }

            $team = Auth::user()->currentTeam;

            if (! $team) {
                throw new \RuntimeException('User is missing an active team.');
            }

            $generationConfig = $configuredGenerations[$promptType];

            if (! is_array($generationConfig) || empty($generationConfig)) {
                throw new \RuntimeException('Missing AI generation configuration for prompt type: '.$promptType);
            }

            $jobClass = data_get($generationConfig, 'job');

            if (! is_string($jobClass) || ! class_exists($jobClass)) {
                throw new \RuntimeException('Invalid job class for prompt type: '.$promptType);
            }

            $jobRecord = ProductAiJob::create([
                'team_id' => $team->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'prompt_type' => $promptType,
                'status' => ProductAiJob::STATUS_QUEUED,
                'progress' => 0,
                'queued_at' => now(),
            ]);

            $jobClass::dispatch($jobRecord->id);

            if ($promptType === ProductAiJob::PROMPT_DESCRIPTION_SUMMARY) {
                $this->generationContent[$promptType] = null;
            }

            $label = data_get($generationConfig, 'label', Str::headline($promptType));

            $this->generationStatus[$promptType] = 'Queued '.$label.'. Track progress on the AI Jobs page.';
        } catch (\Throwable $e) {
            $this->generationError[$promptType] = $e->getMessage();
        } finally {
            $this->generationLoading[$promptType] = false;
        }
    }

    public function promoteGeneration(string $promptType, int $recordId): void
    {
        $this->generationError[$promptType] = null;
        $this->generationStatus[$promptType] = null;
        $this->generationLoading[$promptType] = true;

        try {
            $configuredGenerations = config('product-ai.generations', []);

            if (! array_key_exists($promptType, $configuredGenerations)) {
                throw new \RuntimeException('Unsupported generation type: '.$promptType);
            }

            $generationConfig = $configuredGenerations[$promptType];

            $modelClass = data_get($generationConfig, 'model');

            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                throw new \RuntimeException('Invalid model for prompt type: '.$promptType);
            }

            $team = Auth::user()->currentTeam;

            if (! $team) {
                throw new \RuntimeException('User is missing an active team.');
            }

            /** @var \Illuminate\Database\Eloquent\Model|null $record */
            $record = $modelClass::query()
                ->where('id', $recordId)
                ->where('product_id', $this->productId)
                ->where('team_id', $team->id)
                ->first();

            if (! $record) {
                throw new \RuntimeException('Unable to find requested generation.');
            }

            $record->forceFill([
                'updated_at' => now(),
            ])->save();

            $label = data_get($generationConfig, 'label', Str::headline($promptType));

            if ($promptType === ProductAiJob::PROMPT_DESCRIPTION_SUMMARY) {
                $this->generationContent[$promptType] = $record->content;
            }

            $this->generationStatus[$promptType] = 'Promoted '.$label.' to latest.';

            $this->dispatch('$refresh')->self();
        } catch (\Throwable $e) {
            $this->generationError[$promptType] = $e->getMessage();
        } finally {
            $this->generationLoading[$promptType] = false;
        }
    }

    public function getProductProperty(): Product
    {
        $team = Auth::user()->currentTeam;

        abort_if(! $team, 404);

        $historyLimit = $this->historyLimit();

        return Product::query()
            ->with([
                'feed:id,name',
                'latestAiDescriptionSummary',
                'latestAiDescription',
                'latestAiUsp',
                'latestAiFaq',
                'aiDescriptionSummaries' => static function ($query) use ($historyLimit) {
                    $query->latest('updated_at')->limit($historyLimit);
                },
                'aiDescriptions' => static function ($query) use ($historyLimit) {
                    $query->latest('updated_at')->limit($historyLimit);
                },
                'aiUsps' => static function ($query) use ($historyLimit) {
                    $query->latest('updated_at')->limit($historyLimit);
                },
                'aiFaqs' => static function ($query) use ($historyLimit) {
                    $query->latest('updated_at')->limit($historyLimit);
                },
            ])
            ->where('team_id', $team->id)
            ->findOrFail($this->productId);
    }

    protected function historyLimit(): int
    {
        $limit = (int) config('product-ai.defaults.history_limit', 10);

        return $limit > 0 ? $limit : 10;
    }

    protected function buildGenerationHistory(Product $product): array
    {
        $historyCollections = [
            ProductAiJob::PROMPT_DESCRIPTION_SUMMARY => $product->aiDescriptionSummaries ?? collect(),
            ProductAiJob::PROMPT_DESCRIPTION => $product->aiDescriptions ?? collect(),
            ProductAiJob::PROMPT_USPS => $product->aiUsps ?? collect(),
            ProductAiJob::PROMPT_FAQ => $product->aiFaqs ?? collect(),
        ];

        $history = [];

        foreach ($historyCollections as $promptType => $collection) {
            $sorted = collect($collection)->sortByDesc('updated_at')->values();

            $history[$promptType] = $sorted->slice(1)->values();
        }

        return $history;
    }
}
