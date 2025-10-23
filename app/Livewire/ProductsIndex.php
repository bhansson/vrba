<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductAiJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsIndex extends Component
{
    use WithPagination;

    public int $perPage = 15;
    public string $search = '';
    public array $summaries = [];
    public array $summaryErrors = [];
    public array $summaryStatuses = [];
    public array $loadingSummary = [];

    protected $queryString = [
        'perPage' => ['except' => 15],
        'search' => ['except' => ''],
    ];

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $tokens = Str::of($this->search)
            ->squish()
            ->lower()
            ->explode(' ')
            ->filter();

        $productsQuery = Product::query()
            ->with([
                'feed:id,name',
                'latestAiDescriptionSummary',
                'latestAiDescription',
                'latestAiUsp',
                'latestAiFaq',
            ])
            ->where('team_id', $team->id)
            ->when($tokens->isNotEmpty(), function ($query) use ($tokens) {
                $query->where(function ($builder) use ($tokens) {
                    foreach ($tokens as $token) {
                        $builder->where(function ($inner) use ($token) {
                            $like = '%'.$token.'%';
                            $operator = $this->likeOperator();

                            $inner->where('title', $operator, $like)
                                ->orWhere('brand', $operator, $like)
                                ->orWhere('sku', $operator, $like)
                                ->orWhere('gtin', $operator, $like);
                        });
                    }
                });
            })
        ;

        $products = $this->orderProducts($productsQuery)
            ->paginate($this->perPage)
            ->withQueryString();

        foreach ($products as $product) {
            if ($product->latestAiDescriptionSummary && ! array_key_exists($product->id, $this->summaries)) {
                $this->summaries[$product->id] = $product->latestAiDescriptionSummary->content ?? '';
            }
        }

        return view('livewire.products-index', [
            'products' => $products,
        ]);
    }

    protected function likeOperator(): string
    {
        return 'ILIKE';
    }

    protected function orderProducts(Builder $query): Builder
    {
        // Ensure numeric SKUs sort by value while leaving gaps for any non-numeric entries.
        return $query
            ->orderByRaw("NULLIF(regexp_replace(sku, '[^0-9]', '', 'g'), '')::numeric NULLS LAST")
            ->orderBy('sku');
    }

    public function summarizeProduct(int $productId): void
    {
        $team = Auth::user()->currentTeam;

        $product = Product::query()
            ->where('team_id', $team->id)
            ->findOrFail($productId);

        $this->summaryErrors[$productId] = null;
        $this->summaryStatuses[$productId] = null;
        $this->loadingSummary[$productId] = true;

        try {
            $promptTypes = collect(config('product-ai.actions.generate_summary', []))
                ->filter(static fn ($value) => is_string($value) && $value !== '')
                ->unique()
                ->values();

            if (! config('services.openai.api_key')) {
                throw new \RuntimeException('OpenAI API key is not configured.');
            }

            if (! $product->sku) {
                throw new \RuntimeException('Product is missing an SKU, cannot queue summary.');
            }

            if ($promptTypes->isEmpty()) {
                throw new \RuntimeException('No AI generations are configured for this action.');
            }

            $queuedLabels = [];

            foreach ($promptTypes as $promptType) {
                $generationConfig = config('product-ai.generations.'.$promptType);

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

                $queuedLabels[] = data_get($generationConfig, 'label', Str::headline($promptType));
            }

            unset($this->summaries[$productId]);

            if (! empty($queuedLabels)) {
                $labelList = collect($queuedLabels)->unique()->values()->join(', ', ' and ');
                $this->summaryStatuses[$productId] = 'Queued AI jobs: '.$labelList.'. Track progress on the AI Jobs page.';
            } else {
                $this->summaryStatuses[$productId] = 'AI jobs queued. Track progress on the AI Jobs page.';
            }
        } catch (\Throwable $e) {
            $this->summaryErrors[$productId] = $e->getMessage();
        } finally {
            $this->loadingSummary[$productId] = false;
        }
    }
}
