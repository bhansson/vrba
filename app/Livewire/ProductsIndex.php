<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductAiGeneration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            ->with(['feed:id,name', 'aiGeneration'])
            ->where('team_id', $team->id)
            ->when($tokens->isNotEmpty(), function ($query) use ($tokens) {
                $query->where(function ($builder) use ($tokens) {
                    foreach ($tokens as $token) {
                        $builder->where(function ($inner) use ($token) {
                            $like = '%'.$token.'%';
                            $operator = $this->likeOperator();

                            $inner->where('title', $operator, $like)
                                ->orWhere('sku', $operator, $like)
                                ->orWhere('gtin', $operator, $like)
                                ->orWhere('description', $operator, $like)
                                ->orWhere('url', $operator, $like)
                                ->orWhereHas('feed', function ($feedQuery) use ($operator, $like) {
                                    $feedQuery->where('name', $operator, $like);
                                });
                        });
                    }
                });
            })
        ;

        $products = $this->orderProducts($productsQuery)
            ->paginate($this->perPage)
            ->withQueryString();

        foreach ($products as $product) {
            if ($product->aiGeneration && ! array_key_exists($product->id, $this->summaries)) {
                $this->summaries[$product->id] = $product->aiGeneration->summary ?? '';
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
        $this->loadingSummary[$productId] = true;

        try {
            $apiKey = config('services.openai.api_key');
            $model = config('services.openai.model', 'gpt-5');
            $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

            if (! $apiKey) {
                throw new \RuntimeException('OpenAI API key is not configured.');
            }

            $prompt = sprintf(
                "Create a concise, compelling marketing summary (max 60 words) for the following product. Highlight differentiators and tone it for conversion.\n\nTitle: %s\nDescription: %s",
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
                    'product_id' => $productId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('Failed to generate summary (status '.$response->status().').');
            }

            $payload = $response->json();
            $summary = data_get($payload, 'choices.0.message.content');

            if (! $summary) {
                Log::warning('OpenAI response missing summary', [
                    'product_id' => $productId,
                    'response' => $payload,
                ]);
                throw new \RuntimeException('Received an empty summary from OpenAI.');
            }

            $summaryText = trim($summary);
            $this->summaries[$productId] = $summaryText;

            if (! $product->sku) {
                throw new \RuntimeException('Product is missing an SKU, cannot store summary.');
            }

            ProductAiGeneration::updateOrCreate(
                ['product_sku' => $product->sku],
                ['summary' => $summaryText]
            );
        } catch (\Throwable $e) {
            $this->summaryErrors[$productId] = $e->getMessage();
        } finally {
            $this->loadingSummary[$productId] = false;
        }
    }
}
