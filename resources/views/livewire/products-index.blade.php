<div>
    <div class="max-w-6xl mx-auto py-8 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold text-gray-800">Products</h1>

            <div class="flex items-center space-x-2 text-sm text-gray-600">
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
                                    <span wire:loading wire:target="summarizeProduct({{ $product->id }})">Generating…</span>
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
                                    <p class="text-gray-500">Generating summary…</p>
                                @elseif (!empty($summaryErrors[$product->id]))
                                    <p class="text-red-600 text-sm">{{ $summaryErrors[$product->id] }}</p>
                                @elseif (!empty($summaries[$product->id]))
                                    <p class="text-gray-800">{{ $summaries[$product->id] }}</p>
                                @elseif ($product->aiGeneration?->summary)
                                    <p class="text-gray-800">{{ $product->aiGeneration->summary }}</p>
                                @else
                                    <p class="text-gray-500 text-sm">Click “Generate Summary” to create a marketing snippet for this product.</p>
                                @endif
                            </div>
                            @if ($product->aiGeneration)
                                <div class="space-y-2 text-sm">
                                    @if ($product->aiGeneration->description)
                                        <div>
                                            <span class="font-semibold">AI Description:</span>
                                            <p class="mt-1 text-gray-700 whitespace-pre-wrap">{{ $product->aiGeneration->description }}</p>
                                        </div>
                                    @endif
                                    @if ($product->aiGeneration->usps)
                                        <div>
                                            <span class="font-semibold">Unique Selling Points:</span>
                                            <p class="mt-1 text-gray-700 whitespace-pre-wrap">{{ $product->aiGeneration->usps }}</p>
                                        </div>
                                    @endif
                                    @if ($product->aiGeneration->faq)
                                        <div>
                                            <span class="font-semibold">FAQ:</span>
                                            <p class="mt-1 text-gray-700 whitespace-pre-wrap">{{ $product->aiGeneration->faq }}</p>
                                        </div>
                                    @endif
                                    <div class="text-xs text-gray-500">
                                        AI summary last updated {{ $product->aiGeneration->updated_at?->diffForHumans() ?? 'N/A' }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </details>
                @empty
                    <div class="p-6 text-sm text-gray-600">
                        No products imported yet.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
</div>
