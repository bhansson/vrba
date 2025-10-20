@php use Illuminate\Support\Str; @endphp

<div>
    <div class="max-w-6xl mx-auto py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Products</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Search your catalog by title, SKU, or GTIN.
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

                <div class="flex items-center space-x-2 text-sm text-gray-600 sm:justify-end">
                    <span>Show</span>
                    <select wire:model.number="perPage" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" aria-label="Results per page">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span>per page</span>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between mb-4 text-xs text-gray-500">
            <div wire:loading.inline wire:target="search,page,perPage">
                Searching…
            </div>
            <div wire:loading.remove>
                Showing {{ $products->total() }} {{ Str::plural('result', $products->total()) }}
            </div>
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="divide-y divide-gray-200">
                <div class="hidden px-4 py-3 bg-gray-50 text-xs font-semibold uppercase text-gray-600 sm:grid sm:grid-cols-12">
                    <div class="col-span-8">Title</div>
                    <div class="col-span-2">Last AI Generation</div>
                    <div class="col-span-2 text-right">Actions</div>
                </div>

                @forelse ($products as $product)
                    @php
                        $latestGeneration = collect([
                            ['label' => 'Summary', 'timestamp' => optional($product->latestAiDescriptionSummary)->updated_at],
                            ['label' => 'Description', 'timestamp' => optional($product->latestAiDescription)->updated_at],
                            ['label' => 'USPs', 'timestamp' => optional($product->latestAiUsp)->updated_at],
                            ['label' => 'FAQ', 'timestamp' => optional($product->latestAiFaq)->updated_at],
                        ])
                            ->filter(fn ($item) => $item['timestamp'])
                            ->sortByDesc('timestamp')
                            ->first();
                    @endphp
                    <div wire:key="product-{{ $product->id }}" class="flex flex-col gap-4 px-4 py-5 transition-colors sm:grid sm:grid-cols-12 sm:items-center hover:bg-gray-50">
                        <div class="sm:col-span-8">
                            <div class="text-sm font-semibold text-gray-900">
                                {{ $product->title ?: 'Untitled product' }}
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
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
                            @if ($latestGeneration)
                                <p aria-live="polite">
                                    {{ $latestGeneration['label'] }} generated {{ $latestGeneration['timestamp']->diffForHumans() }}
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
                        @if (trim($search) !== '')
                            No products match your search.
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
