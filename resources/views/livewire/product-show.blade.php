@php use App\Models\ProductAiJob;use Illuminate\Support\Str; @endphp
<div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-6 py-6 space-y-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        {{ $product->title ?: 'Untitled product' }}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-500">
                        <span>SKU: {{ $product->sku ?: '—' }}</span>
                        <span>GTIN: {{ $product->gtin ?: '—' }}</span>
                        <span>Updated {{ $product->updated_at->diffForHumans() }}</span>
                        <span>Created {{ $product->created_at->diffForHumans() }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-gray-600 space-y-1">
                        <div>
                            <span class="font-medium text-gray-700">Feed:</span>
                            <span>{{ $product->feed?->name ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Price:</span>
                            <span>{{ $product->price !== null && $product->price !== '' ? number_format((float) $product->price, 2) : '—' }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if ($product->url)
                            <a href="{{ $product->url }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center px-4 py-2 border border-indigo-200 rounded-md text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:border-indigo-300">
                                Visit product page
                                <svg class="ml-2 size-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M17.25 6.75L6.75 17.25M17.25 6.75H9.75M17.25 6.75v7.5"/>
                                </svg>
                            </a>
                        @endif
                        @if ($product->feed?->feed_url)
                            <a href="{{ $product->feed->feed_url }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center px-4 py-2 border border-gray-200 rounded-md text-sm font-medium text-gray-600 hover:text-gray-800 hover:border-gray-300">
                                Open feed source
                                <svg class="ml-2 size-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M17.25 6.75L6.75 17.25M17.25 6.75H9.75M17.25 6.75v7.5"/>
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Product Information</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Key attributes pulled from your product feed.
                        </p>
                    </div>
                    <div class="px-6 py-6 space-y-6 text-sm text-gray-700">
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">SKU</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $product->sku ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">GTIN</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $product->gtin ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Price</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $product->price !== null && $product->price !== '' ? number_format((float) $product->price, 2) : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Product URL</dt>
                                <dd class="mt-1 text-sm text-indigo-600">
                                    @if ($product->url)
                                        <a href="{{ $product->url }}" target="_blank" rel="noopener noreferrer"
                                           class="hover:text-indigo-800 break-all">{{ $product->url }}</a>
                                    @else
                                        <span class="text-gray-900">—</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Description</h3>
                            <p class="mt-2 whitespace-pre-wrap text-gray-700">
                                {{ $product->description ?: 'No description provided.' }}
                            </p>
                        </div>
                    </div>
                </div>

                @php
                    $summaryPromptType = ProductAiJob::PROMPT_DESCRIPTION_SUMMARY;
                    $descriptionPromptType = ProductAiJob::PROMPT_DESCRIPTION;
                    $uspPromptType = ProductAiJob::PROMPT_USPS;
                    $faqPromptType = ProductAiJob::PROMPT_FAQ;

                    $latestSummaryRecord = $product->latestAiDescriptionSummary;

                    $aiGenerations = collect([
                        [
                            'key' => 'summary',
                            'prompt_type' => $summaryPromptType,
                            'label' => 'Summary',
                            'content' => $generationContent[$summaryPromptType] ?? $latestSummaryRecord?->content,
                            'generated_at' => $latestSummaryRecord?->created_at,
                            'recorded_at' => $latestSummaryRecord?->updated_at,
                            'always_show' => true,
                        ],
                        [
                            'key' => 'description',
                            'prompt_type' => $descriptionPromptType,
                            'label' => 'AI Description',
                            'content' => $product->latestAiDescription?->content,
                            'generated_at' => $product->latestAiDescription?->created_at,
                            'recorded_at' => $product->latestAiDescription?->updated_at,
                        ],
                        [
                            'key' => 'usps',
                            'prompt_type' => $uspPromptType,
                            'label' => 'Unique Selling Points',
                            'content' => $product->latestAiUsp?->content,
                            'generated_at' => $product->latestAiUsp?->created_at,
                            'recorded_at' => $product->latestAiUsp?->updated_at,
                        ],
                        [
                            'key' => 'faq',
                            'prompt_type' => $faqPromptType,
                            'label' => 'FAQ',
                            'content' => $product->latestAiFaq?->content,
                            'generated_at' => $product->latestAiFaq?->created_at,
                            'recorded_at' => $product->latestAiFaq?->updated_at,
                        ],
                    ])->filter(function ($generation) {
                        if (! empty($generation['always_show'])) {
                            return true;
                        }

                        if (! is_string($generation['content'])) {
                            return false;
                        }

                        return trim($generation['content']) !== '';
                    })->values();

                    $latestGenerationUpdatedAt = $aiGenerations
                        ->pluck('recorded_at')
                        ->filter()
                        ->reduce(function ($carry, $timestamp) {
                            if (! $carry) {
                                return $timestamp;
                            }

                            return $timestamp->gt($carry) ? $timestamp : $carry;
                        });

                    $latestGenerationUpdatedText = $latestGenerationUpdatedAt
                        ? $latestGenerationUpdatedAt->diffForHumans()
                        : null;
                @endphp

                @if ($aiGenerations->isNotEmpty())
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <div class="px-6 py-5 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">AI Generated Content</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Latest outputs created for this product.
                            </p>
                        </div>
                        <div class="px-6 py-6 text-sm text-gray-700"
                             x-data="{ activeTab: '{{ $aiGenerations->first()['key'] }}' }">
                            <div class="border-b border-gray-200">
                                <nav class="-mb-px flex space-x-6" role="tablist" aria-label="AI generation tabs">
                                    @foreach ($aiGenerations as $index => $generation)
                                        <button
                                                id="generation-tab-{{ $index }}"
                                                type="button"
                                                role="tab"
                                                @click.prevent="activeTab = '{{ $generation['key'] }}'"
                                                :aria-selected="(activeTab === '{{ $generation['key'] }}') ? 'true' : 'false'"
                                                aria-controls="generation-panel-{{ $index }}"
                                                class="px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-150 focus:outline-none focus-visible:outline-none"
                                                :class="activeTab === '{{ $generation['key'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        >
                                            {{ $generation['label'] }}
                                        </button>
                                    @endforeach
                                </nav>
                            </div>
                            <div class="mt-6">
                                @foreach ($aiGenerations as $index => $generation)
                                    @php
                                        $promptType = $generation['prompt_type'];
                                        $status = $generationStatus[$promptType] ?? null;
                                        $error = $generationError[$promptType] ?? null;
                                        $isLoading = $generationLoading[$promptType] ?? false;
                                        $historyItems = $generationHistory[$promptType] ?? collect();
                                    @endphp
                                    <section
                                            x-show="activeTab === '{{ $generation['key'] }}'"
                                            x-cloak
                                            role="tabpanel"
                                            aria-labelledby="generation-tab-{{ $index }}"
                                            id="generation-panel-{{ $index }}"
                                            class="{{ $generation['key'] === 'summary' ? 'space-y-4' : 'space-y-3' }}"
                                    >
                                        <div class="space-y-2">
                                            <h3 class="text-sm font-semibold text-gray-900">{{ $generation['label'] }}</h3>
                                            @if ($generation['key'] === 'summary')
                                                <p class="text-sm text-gray-600">
                                                    Generate a marketing-ready summary for this product.
                                                </p>
                                            @endif
                                            <div class="space-y-2">
                                                <x-button type="button"
                                                          wire:click="queueGeneration('{{ $promptType }}')"
                                                          wire:loading.attr="disabled"
                                                          wire:target="queueGeneration">
                                                    <span wire:loading.remove
                                                          wire:target="queueGeneration">Generate {{ $generation['label'] }}</span>
                                                    <span wire:loading wire:target="queueGeneration">Processing…</span>
                                                </x-button>

                                                @if ($isLoading)
                                                    <p class="text-xs text-gray-500">Processing request…</p>
                                                @endif

                                                @if ($error)
                                                    <p class="text-sm text-red-600" aria-live="polite">{{ $error }}</p>
                                                @endif

                                                @if ($status)
                                                    <p class="text-sm text-indigo-600"
                                                       aria-live="polite">{{ $status }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($generation['key'] === 'summary')
                                            <div class="space-y-3">
                                                <h4 class="text-sm font-semibold text-gray-900">Latest Summary</h4>
                                                @if ($generation['content'])
                                                    <p class="text-gray-700">{{ $generation['content'] }}</p>
                                                @else
                                                    <p class="text-gray-500">No summary generated yet. Use the button
                                                        above to queue AI jobs.</p>
                                                @endif
                                            </div>
                                        @else
                                            @if ($generation['content'])
                                                <p class="whitespace-pre-wrap text-gray-700">{{ $generation['content'] }}</p>
                                            @else
                                                <p class="text-gray-500">No content generated yet. Use the button above
                                                    to queue AI jobs.</p>
                                            @endif
                                        @endif

                                            <p class="text-xs text-gray-500">
                                                Generated {{ $product->created_at->diffForHumans() }}
                                            </p>

                                        @if ($historyItems->isNotEmpty())
                                            <div class="space-y-3">
                                                <h4 class="text-sm font-semibold text-gray-900">History</h4>
                                                <div class="space-y-3">
                                                    @foreach ($historyItems as $history)
                                                        <article class="rounded-lg border border-gray-200 p-4 space-y-3"
                                                                 x-data="{ isExpanded: false }">
                                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                                <span class="text-xs text-gray-500">
                                                                    {{ $history->created_at ? $history->created_at->format('M j, Y H:i') : 'Unknown' }}
                                                                </span>
                                                                <button
                                                                        type="button"
                                                                        wire:click="promoteGeneration('{{ $promptType }}', {{ $history->id }})"
                                                                        wire:loading.attr="disabled"
                                                                        wire:target="promoteGeneration"
                                                                        class="inline-flex items-center gap-1 border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-500 transition hover:border-gray-300 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-indigo-200 disabled:opacity-50"
                                                                >
                                                                    <span wire:loading.remove wire:target="promoteGeneration">Promote</span>
                                                                    <span wire:loading wire:target="promoteGeneration">Promoting…</span>
                                                                </button>
                                                            </div>
                                                            @php
                                                                $rawContent = $history->content ?? '';
                                                                $fullContent = preg_replace('/^\s+/u', '', $rawContent) ?? '';
                                                                $truncatedContent = \Illuminate\Support\Str::limit($fullContent, 100, '');
                                                                $hasMoreContent = \Illuminate\Support\Str::length($fullContent) > \Illuminate\Support\Str::length($truncatedContent);
                                                            @endphp
                                                            <div class="space-y-2">
                                                                <p class="whitespace-pre-wrap text-gray-700" x-show="!isExpanded">{{ $hasMoreContent ? \Illuminate\Support\Str::of($truncatedContent)->trim()->append('…') : $fullContent }}</p>
                                                                @if ($hasMoreContent)
                                                                    <template x-if="isExpanded">
                                                                        <p class="whitespace-pre-wrap text-gray-700">{{ $fullContent }}</p>
                                                                    </template>
                                                                    <button type="button"
                                                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                                            @click="isExpanded = !isExpanded">
                                                                        <span x-show="!isExpanded">Show more</span>
                                                                        <span x-show="isExpanded">Show less</span>
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        </article>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </section>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <aside class="space-y-8">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Metadata</h2>
                    </div>
                    <div class="px-6 py-6 space-y-4 text-sm text-gray-700">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Imported from feed</span>
                            <span class="font-medium">
                                {{ $product->feed?->name ?: '—' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Created</span>
                            <span class="font-medium">{{ $product->created_at->format('M j, Y g:i A') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Last updated</span>
                            <span class="font-medium">{{ $product->updated_at->format('M j, Y g:i A') }}</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
