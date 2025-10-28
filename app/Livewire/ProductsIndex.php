<?php

namespace App\Livewire;

use App\Jobs\RunProductAiTemplateJob;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsIndex extends Component
{
    use WithPagination;

    public int $perPage = 100;
    public string $search = '';
    public string $brand = '';
    public array $summaries = [];
    public array $summaryErrors = [];
    public array $summaryStatuses = [];
    public array $loadingSummary = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'brand' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBrand(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $brands = Product::query()
            ->where('team_id', $team->id)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

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
                'latestAiGeneration.template',
            ])
            ->where('team_id', $team->id)
            ->when($this->brand !== '', function ($query) {
                $query->where('brand', $this->brand);
            })
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
            'brands' => $brands,
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
            $templateSlugs = collect(config('product-ai.actions.generate_summary', []))
                ->filter(static fn ($value) => is_string($value) && $value !== '')
                ->unique()
                ->values();

            if (! config('services.openai.api_key')) {
                throw new \RuntimeException('OpenAI API key is not configured.');
            }

            if (! $product->sku) {
                throw new \RuntimeException('Product is missing an SKU, cannot queue summary.');
            }

            if ($templateSlugs->isEmpty()) {
                throw new \RuntimeException('No AI generations are configured for this action.');
            }

            ProductAiTemplate::syncDefaultTemplates();

            $templates = ProductAiTemplate::query()
                ->forTeam($team->id)
                ->active()
                ->whereIn('slug', $templateSlugs)
                ->orderBy('name')
                ->get();

            if ($templates->isEmpty()) {
                throw new \RuntimeException('No matching AI templates are available for this action.');
            }

            $queuedLabels = [];

            foreach ($templates as $template) {
                $jobRecord = ProductAiJob::create([
                    'team_id' => $team->id,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'product_ai_template_id' => $template->id,
                    'status' => ProductAiJob::STATUS_QUEUED,
                    'progress' => 0,
                    'queued_at' => now(),
                ]);

                RunProductAiTemplateJob::dispatch($jobRecord->id);

                $queuedLabels[] = $template->name;
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
