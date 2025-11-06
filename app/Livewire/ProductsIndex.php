<?php

namespace App\Livewire;

use App\Jobs\RunProductAiTemplateJob;
use App\Models\Product;
use App\Models\ProductAiJob;
use App\Models\ProductAiTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
    public array $selectedProducts = [];
    public array $visibleProductIds = [];
    public bool $bulkSelectAll = false;
    public ?int $selectedTemplateId = null;
    public ?string $bulkStatusMessage = null;
    public ?string $bulkErrorMessage = null;
    public bool $bulkGenerating = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'brand' => ['except' => ''],
    ];

    public function mount(): void
    {
        ProductAiTemplate::syncDefaultTemplates();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->resetSelectionState();
    }

    public function updatingBrand(): void
    {
        $this->resetPage();
        $this->resetSelectionState();
    }

    public function updatedSelectedProducts(): void
    {
        $this->selectedProducts = $this->sanitizeProductIds($this->selectedProducts);
        $this->updateBulkSelectAllState();
    }

    public function updatedBulkSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedProducts = $this->sanitizeProductIds(array_merge($this->selectedProducts, $this->visibleProductIds));
        } else {
            $remaining = array_diff($this->selectedProducts, $this->visibleProductIds);
            $this->selectedProducts = $this->sanitizeProductIds($remaining);
        }

        $this->updateBulkSelectAllState();
    }

    public function bulkGenerate(): void
    {
        $this->bulkStatusMessage = null;
        $this->bulkErrorMessage = null;
        $this->bulkGenerating = true;

        $selectedIds = $this->sanitizeProductIds($this->selectedProducts);

        try {
            if (empty($selectedIds)) {
                $this->bulkErrorMessage = 'Select at least one product to queue generation.';
                return;
            }

            if (! $this->selectedTemplateId) {
                $this->bulkErrorMessage = 'Choose a template before queuing AI jobs.';
                return;
            }

            if (! config('laravel-openrouter.api_key')) {
                $this->bulkErrorMessage = 'AI provider API key is not configured, unable to queue AI jobs.';
                return;
            }

            $team = Auth::user()->currentTeam;

            $template = ProductAiTemplate::query()
                ->forTeam($team->id)
                ->active()
                ->find($this->selectedTemplateId);

            if (! $template) {
                $this->bulkErrorMessage = 'The selected template is no longer available.';
                return;
            }

            $products = Product::query()
                ->where('team_id', $team->id)
                ->whereIn('id', $selectedIds)
                ->get();

            if ($products->isEmpty()) {
                $this->bulkErrorMessage = 'Selected products are no longer available.';
                return;
            }

            $queued = 0;
            $skippedForSku = 0;

            foreach ($products as $product) {
                if (! $product->sku) {
                    $skippedForSku++;
                    continue;
                }

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
                unset($this->summaries[$product->id]);
                $queued++;
            }

            if ($queued > 0) {
                $label = Str::plural('job', $queued);
                $this->bulkStatusMessage = 'Queued '.$queued.' AI '.$label.' using template "'.$template->name.'". Track progress on the AI Jobs page.';
            }

            if ($skippedForSku > 0) {
                $this->bulkErrorMessage = 'Skipped '.$skippedForSku.' '.Str::plural('product', $skippedForSku).' without an SKU.';
            }

            if ($queued === 0 && $skippedForSku > 0) {
                $this->bulkStatusMessage = null;
                $this->bulkErrorMessage = 'All selected products were skipped because they are missing SKUs.';
            }

            $this->resetSelectionState();
        } catch (\Throwable $e) {
            report($e);
            $this->bulkErrorMessage = 'Unable to queue AI jobs. Please try again.';
        } finally {
            $this->bulkGenerating = false;
            $this->updateBulkSelectAllState();
        }
    }

    protected function resetSelectionState(): void
    {
        $this->selectedProducts = [];
        $this->bulkSelectAll = false;
    }

    protected function sanitizeProductIds(array $ids): array
    {
        return collect($ids)
            ->map(static fn ($value) => (int) $value)
            ->filter(static fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function updateVisibleProductIds(array $ids): void
    {
        $ids = $this->sanitizeProductIds($ids);

        if ($this->visibleProductIds !== $ids) {
            $this->visibleProductIds = $ids;
        }

        $this->updateBulkSelectAllState();
    }

    protected function updateBulkSelectAllState(): void
    {
        if (empty($this->visibleProductIds)) {
            if ($this->bulkSelectAll) {
                $this->bulkSelectAll = false;
            }

            return;
        }

        $shouldSelectAll = empty(array_diff($this->visibleProductIds, $this->selectedProducts));

        if ($this->bulkSelectAll !== $shouldSelectAll) {
            $this->bulkSelectAll = $shouldSelectAll;
        }
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

        $productsQuery = $this->orderProducts($this->buildProductsQuery($team->id));

        $products = (clone $productsQuery)
            ->paginate($this->perPage)
            ->withQueryString();

        $this->updateVisibleProductIds($products->pluck('id')->all());

        foreach ($products as $product) {
            if ($product->latestAiDescriptionSummary && ! array_key_exists($product->id, $this->summaries)) {
                $this->summaries[$product->id] = $product->latestAiDescriptionSummary->content ?? '';
            }
        }

        return view('livewire.products-index', [
            'products' => $products,
            'brands' => $brands,
            'templates' => $this->availableTemplates($team->id),
        ]);
    }

    protected function likeOperator(): string
    {
        return 'ILIKE';
    }

    protected function orderProducts(Builder $query): Builder
    {
        // Ensure numeric SKUs sort by value while leaving gaps for any non-numeric entries.
        if ($query->getConnection()->getDriverName() !== 'pgsql') {
            return $query->orderBy('sku');
        }

        return $query
            ->orderByRaw("NULLIF(regexp_replace(sku, '[^0-9]', '', 'g'), '')::numeric NULLS LAST")
            ->orderBy('sku');
    }

    protected function buildProductsQuery(int $teamId): Builder
    {
        $tokens = $this->searchTokens();

        return Product::query()
            ->with([
                'feed:id,name',
                'latestAiDescriptionSummary',
                'latestAiDescription',
                'latestAiUsp',
                'latestAiFaq',
                'latestAiGeneration.template',
            ])
            ->where('team_id', $teamId)
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
            });
    }

    protected function searchTokens(): Collection
    {
        return Str::of($this->search)
            ->squish()
            ->lower()
            ->explode(' ')
            ->filter();
    }

    protected function availableTemplates(int $teamId): Collection
    {
        return ProductAiTemplate::query()
            ->forTeam($teamId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
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

            if (! config('laravel-openrouter.api_key')) {
                throw new \RuntimeException('AI provider API key is not configured.');
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
