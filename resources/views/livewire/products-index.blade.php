@php use Illuminate\Support\Str; @endphp

<div>
    <div class="max-w-6xl mx-auto py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Products</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Search your catalog by title, brand, SKU, or GTIN, or narrow by brand below.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:space-x-3 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-none sm:w-72">
                    <input
                        type="search"
                        placeholder="Start typing to search products…"
                        wire:model.live.debounce.400ms="search"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pl-10 pr-10 py-2"
                        aria-label="Search products"
                    />
                    <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 013.978 9.25l3.636 3.636a.75.75 0 11-1.06 1.06l-3.636-3.636A5.5 5.5 0 119 3.5zm0 1.5a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd" />
                    </svg>
                    @if (trim($search) !== '')
                        <button
                            type="button"
                            wire:click="$set('search', '')"
                            class="absolute inset-y-0 right-2 flex items-center px-2 text-xs text-gray-500 hover:text-gray-700"
                        >
                            Clear
                        </button>
                    @endif
                </div>

                <div class="flex-1 sm:flex-none sm:w-56">
                    <label for="brand-filter" class="sr-only">Filter by brand</label>
                    <select
                        id="brand-filter"
                        wire:model.live="brand"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        aria-label="Filter by brand"
                    >
                        <option value="">All brands</option>
                        @foreach ($brands as $brandOption)
                            <option value="{{ $brandOption }}">{{ $brandOption }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between mb-4 text-xs text-gray-500">
            <div wire:loading.inline wire:target="search,page,brand">
                Searching…
            </div>
            <div wire:loading.remove>
                Showing {{ $products->total() }} {{ Str::plural('result', $products->total()) }}
            </div>
        </div>

        @if ($bulkStatusMessage)
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ $bulkStatusMessage }}
            </div>
        @endif

        @if ($bulkErrorMessage)
            <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $bulkErrorMessage }}
            </div>
        @endif

        @php
            $selectedCount = count($selectedProducts);
            $bulkButtonDisabled = $templates->isEmpty() || $selectedCount === 0 || ! $selectedTemplateId;
        @endphp

        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-600">
                    <p>Select products with the checkboxes to queue AI generation in bulk.</p>
                    <p class="mt-1 font-medium text-gray-900">
                        {{ $selectedCount }} {{ Str::plural('product', $selectedCount) }} selected
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <div class="w-full sm:w-64">
                        <label for="bulk-template" class="sr-only">Choose template</label>
                        <select
                            id="bulk-template"
                            wire:model.live="selectedTemplateId"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            @disabled($templates->isEmpty())
                        >
                            <option value="">Select template…</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button
                        type="button"
                        wire:click="bulkGenerate"
                        wire:loading.attr="disabled"
                        wire:target="bulkGenerate"
                        @disabled($bulkButtonDisabled)
                        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="bulkGenerate">Queue AI Generation</span>
                        <span wire:loading.inline wire:target="bulkGenerate">Queueing…</span>
                    </button>
                </div>
            </div>
            @if ($templates->isEmpty())
                <p class="mt-2 text-xs text-gray-500">
                    No active AI templates are available yet. Create one to enable bulk generation.
                </p>
            @endif
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="divide-y divide-gray-200">
                <div class="hidden px-4 py-3 bg-gray-50 text-xs font-semibold uppercase text-gray-600 sm:grid sm:grid-cols-12">
                    <div class="col-span-1 flex items-center">
                        <input
                            type="checkbox"
                            wire:model.live="bulkSelectAll"
                            class="size-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            aria-label="Select all visible products"
                        />
                    </div>
                    <div class="col-span-7">Title</div>
                    <div class="col-span-2">Last AI Generation</div>
                    <div class="col-span-2 text-right">Actions</div>
                </div>

                @forelse ($products as $product)
                    @php
                        $latestGenerationRecord = $product->latestAiGeneration;
                        $latestGenerationLabel = $latestGenerationRecord?->template?->name ?? 'AI Generation';
                        $latestGenerationTimestamp = $latestGenerationRecord?->updated_at ?? $latestGenerationRecord?->created_at;
                    @endphp
                    <div wire:key="product-{{ $product->id }}" class="flex flex-col gap-4 px-4 py-5 transition-colors sm:grid sm:grid-cols-12 sm:items-center hover:bg-gray-50">
                        <div class="flex items-center sm:col-span-1 sm:justify-center">
                            <input
                                type="checkbox"
                                value="{{ $product->id }}"
                                wire:model.live="selectedProducts"
                                class="size-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                aria-label="Select {{ $product->title ?: 'Untitled product' }}"
                            />
                        </div>
                        <div class="sm:col-span-7">
                            <div class="text-sm font-semibold text-gray-900">
                                {{ $product->title ?: 'Untitled product' }}
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                                <span>Brand: {{ $product->brand ?: '—' }}</span>
                                <span>SKU: {{ $product->sku ?: '—' }}</span>
                                <span>GTIN: {{ $product->gtin ?: '—' }}</span>
                                <span>Updated {{ $product->updated_at->diffForHumans() }}</span>
                            </div>
                            @if ($product->feed?->name)
                                <div class="mt-1 text-xs text-gray-500">
                                    Feed: {{ $product->feed->name }}
                                </div>
                            @endif
                        </div>
                        <div class="sm:col-span-2 text-sm text-gray-700">
                            @if ($latestGenerationRecord && $latestGenerationTimestamp)
                                <p aria-live="polite">
                                    {{ $latestGenerationLabel }} generated {{ $latestGenerationTimestamp->diffForHumans() }}
                                </p>
                            @else
                                <p class="text-gray-500">Never generated</p>
                            @endif
                        </div>
                        <div class="sm:col-span-2 flex flex-col gap-2 sm:items-end">
                            <a href="{{ route('products.show', $product) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                View details
                                <span class="sr-only">for {{ $product->title ?: 'Untitled product' }}</span>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-sm text-gray-600">
                        @if (trim($search) !== '' || $brand !== '')
                            No products match your current filters.
                        @else
                            No products imported yet.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
</div>
