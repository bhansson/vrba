@php use Illuminate\Support\Str; @endphp

<div>
    <div class="max-w-6xl mx-auto py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Products</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Search your catalog by title, SKU, GTIN, feed, or description.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:space-x-3 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-none sm:w-72">
                    <input
                        type="search"
                        placeholder="Start typing to search products…"
                        wire:model.live.debounce.400ms="search"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pl-10 pr-10 py-2"
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
                    <select wire:model.number="perPage" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
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
            <div class="min-w-full divide-y divide-gray-200">
                <div class="grid grid-cols-12 px-4 py-3 bg-gray-50 text-xs font-semibold uppercase text-gray-600">
                    <div class="col-span-1"></div>
                    <div class="col-span-7">Title</div>
                    <div class="col-span-4 text-right">Summary</div>
                </div>

                @forelse ($products as $product)
                    <details wire:key="product-{{ $product->id }}" class="group border-t border-gray-200">
                        <summary class="grid grid-cols-12 gap-2 items-center px-4 py-3 cursor-pointer bg-white hover:bg-gray-50">
                            <div class="col-span-1 text-gray-500">
                                <svg class="size-4 transform transition-transform group-open:rotate-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </div>
                            <div class="col-span-7 text-sm text-gray-800">
                                <div>{{ $product->title ?: 'Untitled product' }}</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    SKU: {{ $product->sku ?: '—' }}
                                </div>
                            </div>
                            <div class="col-span-4 flex justify-end">
                                <x-button type="button"
                                    wire:click.stop="summarizeProduct({{ $product->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="summarizeProduct({{ $product->id }})">
                                    <span wire:loading.remove wire:target="summarizeProduct({{ $product->id }})">Generate Summary</span>
                                    <span wire:loading wire:target="summarizeProduct({{ $product->id }})">Queueing…</span>
                                </x-button>
                            </div>
                        </summary>
                        <div class="px-6 pb-6 bg-gray-50 text-sm text-gray-700 space-y-3">
                            <div>
                                <span class="font-semibold">Title:</span>
                                <span>{{ $product->title ?: '—' }}</span>
                            </div>
                            <div>
                                <span class="font-semibold">SKU:</span>
                                <span>{{ $product->sku ?: '—' }}</span>
                            </div>
                            <div>
                                <span class="font-semibold">GTIN:</span>
                                <span>{{ $product->gtin ?: '—' }}</span>
                            </div>
                            <div>
                                <span class="font-semibold">Price:</span>
                                <span>{{ $product->price ? number_format((float) $product->price, 2) : '—' }}</span>
                            </div>
                            <div>
                                <span class="font-semibold">Product URL:</span>
                                @if ($product->url)
                                    <a href="{{ $product->url }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline">{{ $product->url }}</a>
                                @else
                                    <span>—</span>
                                @endif
                            </div>
                            <div>
                                <span class="font-semibold">Description:</span>
                                <p class="mt-1 text-gray-600 whitespace-pre-wrap">{{ $product->description ?: '—' }}</p>
                            </div>
                            <div>
                                <span class="font-semibold">Feed:</span>
                                <span>{{ $product->feed?->name ?: '—' }}</span>
                            </div>
                            <div class="text-xs text-gray-500">
                                Product last updated {{ $product->updated_at->diffForHumans() }}
                            </div>
                            <div class="pt-3 border-t border-gray-200">
                                <span class="font-semibold block mb-1">AI Summary:</span>
                                @if (isset($loadingSummary[$product->id]) && $loadingSummary[$product->id])
                                    <p class="text-gray-500">Queueing summary request…</p>
                                @elseif (!empty($summaryErrors[$product->id]))
                                    <p class="text-red-600 text-sm">{{ $summaryErrors[$product->id] }}</p>
                                @elseif (!empty($summaryStatuses[$product->id]))
                                    <p class="text-indigo-600 text-sm">{{ $summaryStatuses[$product->id] }}</p>
                                @elseif (!empty($summaries[$product->id]))
                                    <p class="text-gray-800">{{ $summaries[$product->id] }}</p>
                                @elseif ($product->latestAiDescriptionSummary?->content)
                                    <p class="text-gray-800">{{ $product->latestAiDescriptionSummary->content }}</p>
                                @else
                                    <p class="text-gray-500 text-sm">Click “Generate Summary” to create a marketing snippet for this product.</p>
                                @endif
                            </div>
                            @if ($product->latestAiDescription || $product->latestAiUsp || $product->latestAiFaq)
                                <div class="space-y-2 text-sm">
                                    @if ($product->latestAiDescription?->content)
                                        <div>
                                            <span class="font-semibold">AI Description:</span>
                                            <p class="mt-1 text-gray-700 whitespace-pre-wrap">{{ $product->latestAiDescription->content }}</p>
                                        </div>
                                    @endif
                                    @if ($product->latestAiUsp?->content)
                                        <div>
                                            <span class="font-semibold">Unique Selling Points:</span>
                                            <p class="mt-1 text-gray-700 whitespace-pre-wrap">{{ $product->latestAiUsp->content }}</p>
                                        </div>
                                    @endif
                                    @if ($product->latestAiFaq?->content)
                                        <div>
                                            <span class="font-semibold">FAQ:</span>
                                            <p class="mt-1 text-gray-700 whitespace-pre-wrap">{{ $product->latestAiFaq->content }}</p>
                                        </div>
                                    @endif
                                    <div class="text-xs text-gray-500">
                                        AI summary last updated {{ $product->latestAiDescriptionSummary?->created_at?->diffForHumans() ?? 'N/A' }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </details>
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
