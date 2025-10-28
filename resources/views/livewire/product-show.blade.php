@php
    use Illuminate\Support\Str;

    $templateItems = collect($templatePayload ?? []);

    $latestGenerationUpdatedAt = $templateItems
        ->map(fn ($item) => $item['latest']?->updated_at)
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

<div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-6 py-6 space-y-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        {{ $product->title ?: 'Untitled product' }}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-500">
                        <span>Brand: {{ $product->brand ?: '—' }}</span>
                        <span>SKU: {{ $product->sku ?: '—' }}</span>
                        <span>GTIN: {{ $product->gtin ?: '—' }}</span>
                        <span>Updated {{ $product->updated_at->diffForHumans() }}</span>
                        <span>Created {{ $product->created_at->diffForHumans() }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-gray-600 space-y-1">
                        <div>
                            <span class="font-medium text-gray-700">Brand:</span>
                            <span>{{ $product->brand ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Feed:</span>
                            <span>{{ $product->feed?->name ?: '—' }}</span>
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
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Brand</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $product->brand ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">GTIN</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $product->gtin ?: '—' }}</dd>
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
                            <p class="mt-2 text-gray-700">
                                {{ $product->description ?: 'No description provided.' }}
                            </p>
                        </div>
                    </div>
                </div>

                @if ($templateItems->isNotEmpty())
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <div class="px-6 py-5 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">AI Generated Content</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Latest outputs created for this product.
                                @if ($latestGenerationUpdatedText)
                                    Last updated {{ $latestGenerationUpdatedText }}.
                                @endif
                            </p>
                        </div>
                        <div class="px-6 py-6 text-sm text-gray-700"
                             x-data="{
                                activeKey: '{{ $templateItems->first()['key'] ?? '' }}',
                                selectKey(event) {
                                    this.activeKey = event.target.value;
                                }
                             }">
                            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <label for="template-selection" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Selected template</label>
                                    <select id="template-selection"
                                            x-model="activeKey"
                                            class="mt-1 w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach ($templateItems as $item)
                                            @php
                                                $template = $item['template'];
                                                $key = $item['key'];
                                            @endphp
                                            <option value="{{ $key }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @foreach ($templateItems as $item)
                                @php
                                    $template = $item['template'];
                                    $key = $item['key'];
                                    $latest = $item['latest'];
                                    $historyItems = $item['history'];
                                    $status = $generationStatus[$key] ?? null;
                                    $error = $generationError[$key] ?? null;
                                    $isLoading = $generationLoading[$key] ?? false;
                                    $contentType = $template->contentType();
                                    $latestContent = $generationContent[$key] ?? $latest?->content;
                                    $latestTimestamp = $latest?->updated_at ?? $latest?->created_at;
                                    $latestModel = data_get($latest?->meta, 'model');
                                @endphp
                                <section x-show="activeKey === '{{ $key }}'" x-cloak class="space-y-3">
                                        <div class="space-y-2">
                                            <h3 class="text-sm font-semibold text-gray-900">{{ $template->name }}</h3>
                                            <div class="space-y-2">
                                                <x-button type="button"
                                                          wire:click="queueGeneration({{ $template->id }})"
                                                          wire:loading.attr="disabled"
                                                          wire:target="queueGeneration">
                                                    <span wire:loading.remove
                                                          wire:target="queueGeneration">Generate</span>
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

                                        <div class="space-y-3">
                                            <h4 class="text-sm font-semibold text-gray-900">Latest</h4>
                                            @switch($contentType)
                                                @case('usps')
                                                    @php
                                                        $uspItems = collect(is_array($latestContent) ? $latestContent : [])
                                                            ->map(function ($value) {
                                                                if (is_array($value)) {
                                                                    $value = implode(' ', $value);
                                                                }

                                                                return trim((string) $value);
                                                            })
                                                            ->filter()
                                                            ->values();
                                                    @endphp
                                                    @if ($uspItems->isNotEmpty())
                                                        <ul class="grid gap-2">
                                                            @foreach ($uspItems as $usp)
                                                                <li class="flex items-start gap-2">
                                                                    <span class="mt-1 size-1.5 rounded-full bg-indigo-500"></span>
                                                                    <span class="text-gray-700">{{ $usp }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <p class="text-gray-500">No unique selling points generated yet. Use the button above to queue AI jobs.</p>
                                                    @endif
                                                    @break

                                                @case('faq')
                                                    @php
                                                        $faqEntries = collect(is_array($latestContent) ? $latestContent : [])
                                                            ->map(function ($entry) {
                                                                if (is_array($entry)) {
                                                                    $question = trim((string) ($entry['question'] ?? ''));
                                                                    $answer = trim((string) ($entry['answer'] ?? ''));
                                                                } else {
                                                                    $question = '';
                                                                    $answer = trim((string) $entry);
                                                                }

                                                                if ($question === '' && $answer === '') {
                                                                    return null;
                                                                }

                                                                return [
                                                                    'question' => $question !== '' ? $question : 'Question',
                                                                    'answer' => $answer !== '' ? $answer : 'Answer forthcoming.',
                                                                ];
                                                            })
                                                            ->filter()
                                                            ->values();
                                                    @endphp
                                                    @if ($faqEntries->isNotEmpty())
                                                        <div class="space-y-4 text-gray-700">
                                                            @foreach ($faqEntries as $faq)
                                                                <div class="space-y-1">
                                                                    <p class="font-medium text-gray-900">Q: {{ $faq['question'] }}</p>
                                                                    <p>A: {{ $faq['answer'] }}</p>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <p class="text-gray-500">No FAQ entries generated yet. Use the button above to queue AI jobs.</p>
                                                    @endif
                                                    @break

                                                @default
                                                    @php
                                                        $textContent = trim(is_string($latestContent) ? $latestContent : '');
                                                    @endphp
                                                    @if ($textContent !== '')
                                                        <p class="text-gray-700">{{ $textContent }}</p>
                                                    @else
                                                        <p class="text-gray-500">No content generated yet. Use the button above
                                                            to queue AI jobs.</p>
                                                    @endif
                                            @endswitch
                                        </div>

                                        <p class="text-xs text-gray-500">
                                            @if ($latestTimestamp)
                                                Generated {{ $latestTimestamp->diffForHumans() }}
                                            @else
                                                Generated date unavailable
                                            @endif
                                            @if (! empty($latestModel))
                                                ({{ Str::upper($latestModel) }})
                                            @endif
                                        </p>

                                        @if ($historyItems->isNotEmpty())
                                            <div class="space-y-3">
                                                <h4 class="text-sm font-semibold text-gray-900">History</h4>
                                                <div class="space-y-3">
                                                    @foreach ($historyItems as $history)
                                                        @php
                                                            $historyContent = $history->content ?? '';

                                                            if (is_array($historyContent)) {
                                                                if ($contentType === 'usps') {
                                                                    $historyContent = collect($historyContent)
                                                                        ->map(function ($value) {
                                                                            if (is_array($value)) {
                                                                                $value = implode(' ', $value);
                                                                            }

                                                                            return '• '.trim((string) $value);
                                                                        })
                                                                        ->filter()
                                                                        ->implode("\n");
                                                                } elseif ($contentType === 'faq') {
                                                                    $historyContent = collect($historyContent)
                                                                        ->map(function ($entry) {
                                                                            if (is_array($entry)) {
                                                                                $question = trim((string) ($entry['question'] ?? 'Question'));
                                                                                $answer = trim((string) ($entry['answer'] ?? 'Answer forthcoming.'));
                                                                            } else {
                                                                                $question = 'Question';
                                                                                $answer = trim((string) $entry) ?: 'Answer forthcoming.';
                                                                            }

                                                                            return "Q: {$question}\nA: {$answer}";
                                                                        })
                                                                        ->filter()
                                                                        ->implode("\n\n");
                                                                } else {
                                                                    $historyContent = json_encode($historyContent, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                                                                }
                                                            }

                                                            $historyContent = trim((string) $historyContent);
                                                            $truncatedContent = Str::limit($historyContent, 100, '');
                                                            $hasMoreContent = Str::length($historyContent) > Str::length($truncatedContent);
                                                            $historyModel = data_get($history->meta, 'model');
                                                            $historyTimestamp = $history->updated_at ?? $history->created_at;
                                                        @endphp
                                                        <article class="rounded-lg border border-gray-200 p-4 space-y-3"
                                                                 x-data="{ isExpanded: false }">
                                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                                <span class="text-xs text-gray-500">
                                                                    {{ $historyTimestamp ? $historyTimestamp->format('M j, Y H:i') : 'Unknown' }}
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    wire:click="promoteGeneration({{ $history->id }})"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="promoteGeneration"
                                                                    class="inline-flex items-center gap-1 border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-500 transition hover:border-gray-300 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-indigo-200 disabled:opacity-50"
                                                                >
                                                                    <span wire:loading.remove wire:target="promoteGeneration">Promote</span>
                                                                    <span wire:loading wire:target="promoteGeneration">Promoting…</span>
                                                                </button>
                                                            </div>
                                                            <div class="space-y-2">
                                                                <p class="text-xs text-gray-500">
                                                                    @if ($historyTimestamp)
                                                                        Generated {{ $historyTimestamp->diffForHumans() }}
                                                                    @else
                                                                        Generated date unavailable
                                                                    @endif
                                                                    @if ($historyModel)
                                                                        ({{ Str::upper($historyModel) }})
                                                                    @endif
                                                                </p>
                                                                <p class="text-gray-700" x-show="!isExpanded">
                                                                    {{ $hasMoreContent ? Str::of($truncatedContent)->trim()->append('…') : $historyContent }}
                                                                </p>
                                                                @if ($hasMoreContent)
                                                                    <template x-if="isExpanded">
                                                                        <p class="text-gray-700">{{ $historyContent }}</p>
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
