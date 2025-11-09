<div
    class="space-y-6"
    x-data="{
        overlayOpen: false,
        selectedEntry: null,
        openOverlay(entry) {
            this.selectedEntry = entry;
            this.overlayOpen = true;
            document.body.classList.add('overflow-hidden');
        },
        closeOverlay() {
            this.overlayOpen = false;
            this.selectedEntry = null;
            document.body.classList.remove('overflow-hidden');
        },
    }"
    @keydown.escape.window="closeOverlay()"
>
    @php
        $selectedProduct = collect($products)->firstWhere('id', $productId);
        $hasReferenceSource = (bool) ($image || $productId);
        $hasPromptText = filled($promptResult);
        $galleryHasEntries = ! empty($productGallery);
        $referencePreference = 'product';

    @endphp

    <div class="bg-white shadow sm:rounded-2xl">
        <div class="space-y-5 px-6 py-5 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Review and manage your renders</h3>
                    <p class="text-sm text-gray-600">
                        Every Photo Studio generation created by your team appears here once it finishes processing.
                    </p>
                </div>
                @if ($galleryHasEntries || $isAwaitingGeneration)
                    <div class="flex flex-col items-end text-right">
                        @if ($galleryHasEntries)
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                {{ count($productGallery) }} image{{ count($productGallery) === 1 ? '' : 's' }}
                            </span>
                        @endif
                        @if ($isAwaitingGeneration)
                            <span class="mt-1 inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700" wire:poll.3s="pollGenerationStatus">
                                <x-loading-spinner class="size-3" />
                                New render processing
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            @if (! $galleryHasEntries)
                <div class="rounded-2xl border border-dashed border-indigo-200 bg-indigo-50/60 p-6 text-center text-sm text-indigo-900">
                    <p class="font-semibold text-indigo-900">This gallery is waiting for its first render.</p>
                    <p class="mt-1">
                        Generate an image to seed the gallery. Each run automatically appears here with download links and prompt context, no matter which product you selected.
                    </p>
                </div>
            @else
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 justify-items-center">
                    @foreach ($productGallery as $entry)
                        <div class="flex flex-col rounded-2xl border border-gray-200 bg-gray-50 p-5 w-full max-w-64 sm:max-w-80" wire:key="gallery-{{ $entry['id'] }}">
                            @if ($entry['url'])
                                <div class="group relative aspect-square">
                                    <button
                                        type="button"
                                        class="block h-full w-full overflow-hidden rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                                        @click="openOverlay(@js($entry))"
                                    >
                                        <img
                                            src="{{ $entry['url'] }}"
                                            alt="Generated render"
                                            class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]"
                                        />
                                        <span class="sr-only">Open gallery details for this image</span>
                                    </button>
                                    <a
                                        href="{{ route('photo-studio.gallery.download', $entry['id']) }}"
                                        download
                                        class="absolute right-2 top-2 inline-flex items-center rounded-full border border-white/70 bg-white/90 p-1 text-gray-600 shadow-sm ring-1 ring-black/10 transition hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                                        title="Download image"
                                    >
                                        <span class="sr-only">Download image</span>
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M10 3v8m0 0 3-3m-3 3-3-3M4.5 13.5v1.25A1.25 1.25 0 0 0 5.75 16h8.5a1.25 1.25 0 0 0 1.25-1.25V13.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </a>
                                </div>
                            @else
                                <div class="flex aspect-square items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white p-4 text-center text-sm text-gray-500">
                                    <div>
                                        <p>Stored on {{ $entry['disk'] }}</p>
                                        <p class="mt-1 break-all font-mono text-xs">{{ $entry['path'] }}</p>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3 text-xs text-gray-500">
                                @if (! empty($entry['product_label']))
                                    <p class="text-sm font-semibold text-gray-800">{{ $entry['product_label'] }}</p>
                                    @if (! empty($entry['product_brand']) || ! empty($entry['product_sku']))
                                        <p class="text-xs text-gray-500">
                                            @if (! empty($entry['product_brand']))
                                                <span>{{ $entry['product_brand'] }}</span>
                                            @endif
                                            @if (! empty($entry['product_brand']) && ! empty($entry['product_sku']))
                                                <span class="mx-1 text-gray-400">&middot;</span>
                                            @endif
                                            @if (! empty($entry['product_sku']))
                                                <span class="text-gray-400">{{ $entry['product_sku'] }}</span>
                                            @endif
                                        </p>
                                    @endif
                                @else
                                    <span>Generated without a catalog product</span>
                                @endif
                            </div>

                            @if (! empty($entry['prompt']))
                                <p class="mt-2 text-sm text-gray-700" title="{{ $entry['prompt'] }}">
                                    “{{ \Illuminate\Support\Str::limit($entry['prompt'], 110) }}”
                                </p>
                            @endif

                            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                <span>{{ $entry['model'] ?: 'Unknown model' }}</span>
                                @if (! empty($entry['created_at_human']))
                                    <span>{{ $entry['created_at_human'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>


    <div class="bg-white shadow sm:rounded-2xl">
        <div class="space-y-6 px-6 py-5 sm:p-8">
            <div class="space-y-2">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Choose a clear product photo
                    </h3>
                    @if ($selectedProduct)
                        <span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ $selectedProduct['title'] }}
                        </span>
                    @endif
                </div>
                <p class="text-sm text-gray-600">
                    Upload a shot or pull one from the catalog&mdash;the cleaner the reference, the stronger the generated prompt.
                </p>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M16.667 5.833 8.75 13.75l-3.75-3.75" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Use a single subject image with uncluttered lighting.
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M16.667 5.833 8.75 13.75l-3.75-3.75" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Switch to an existing product anytime&mdash;we’ll keep the latest prompt in sync.
                    </li>
                </ul>
            </div>

            <fieldset class="space-y-4" x-data="{ referencePanel: @js($referencePreference) }">
                <legend class="text-sm font-semibold text-gray-900">Provide your reference</legend>
                <p class="text-sm text-gray-600">
                    Upload a new photo or pull the primary image from a catalog product&mdash;whichever is fastest right now.
                </p>
                <div class="inline-flex rounded-full border border-gray-200 bg-gray-50 p-1 text-sm font-semibold text-gray-600" role="tablist">
                    <button
                        type="button"
                        class="rounded-full px-4 py-2 transition"
                        :class="referencePanel === 'upload' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'"
                        role="tab"
                        @click="referencePanel = 'upload'"
                    >
                        Upload image
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-4 py-2 transition"
                        :class="referencePanel === 'product' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'"
                        role="tab"
                        @click="referencePanel = 'product'"
                    >
                        Catalog product
                    </button>
                </div>
                <div class="space-y-5">
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm" x-show="referencePanel === 'upload'" x-transition x-cloak>
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">Upload an image</p>
                            @if ($image)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    Selected
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-600">
                            Preferred for ad-hoc shots or items not yet in your catalog.
                        </p>

                        <label for="photo-studio-upload" class="mt-4 block text-sm font-medium text-gray-700">
                            Image file
                        </label>
                        <input
                            id="photo-studio-upload"
                            type="file"
                            wire:model="image"
                            accept="image/*"
                            class="mt-2 block w-full rounded-md border border-gray-300 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p class="mt-2 text-xs text-gray-500">
                            JPG, PNG or WEBP up to 8&nbsp;MB.
                        </p>

                        @error('image')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div wire:loading.flex wire:target="image" class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                            <x-loading-spinner class="size-4" />
                            Uploading…
                        </div>

                        @if ($image)
                            <img
                                src="{{ $image->temporaryUrl() }}"
                                alt="Uploaded preview"
                                class="mt-4 max-h-48 w-auto max-w-full rounded-xl border border-gray-200 object-cover"
                            />
                        @endif
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm" x-show="referencePanel === 'product'" x-transition x-cloak>
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">Use a catalog product</p>
                            @if ($selectedProduct && ! $image)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    Selected
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-600">
                            Snag the primary image from any synced product feed entry.
                        </p>

                        <label for="photo-studio-product" class="mt-4 block text-sm font-medium text-gray-700">
                            Product
                        </label>
                        <select
                            id="photo-studio-product"
                            wire:model.live="productId"
                            class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select a product…</option>
                            @foreach ($products as $product)
                                <option value="{{ $product['id'] }}">
                                    {{ $product['title'] }}
                                    @if (! empty($product['sku']))
                                        &mdash; {{ $product['sku'] }}
                                    @endif
                                </option>
                            @endforeach
                        </select>

                        @error('productId')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @if ($selectedProduct)
                            <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-4">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                    <div class="shrink-0 w-full sm:max-w-xs">
                                        @if ($productImagePreview)
                                            <img
                                                src="{{ $productImagePreview }}"
                                                alt="Product preview"
                                                class="w-full rounded-lg border border-gray-200 object-cover"
                                            />
                                        @else
                                            <div class="flex h-32 items-center justify-center rounded-lg border border-dashed border-amber-200 bg-white px-3 text-center text-sm text-amber-700">
                                                This product does not have an image yet.
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 space-y-3">
                                        <p class="text-base font-semibold text-gray-900">
                                            {{ $selectedProduct['title'] }}
                                        </p>
                                        <dl class="space-y-3 text-sm text-gray-700">
                                            <div>
                                                <dt class="font-medium text-gray-500">SKU</dt>
                                                <dd class="font-semibold text-gray-900">{{ $selectedProduct['sku'] ?: '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-medium text-gray-500">Brand</dt>
                                                <dd class="font-semibold text-gray-900">{{ $selectedProduct['brand'] ?: '—' }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </fieldset>

            <div>
                <label for="photo-studio-brief" class="block text-sm font-medium text-gray-700">
                    Creative direction (optional)
                </label>
                <textarea
                    id="photo-studio-brief"
                    wire:model.defer="creativeBrief"
                    rows="3"
                    class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Example: Emphasise natural window lighting and add subtle studio props like folded towels."
                ></textarea>
                <p class="mt-2 text-xs text-gray-500">
                    Up to 600 characters. These notes are added to the AI request for extra guidance.
                </p>

                @error('creativeBrief')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if ($generationStatus)
                <div class="rounded-md bg-indigo-50 p-4 text-sm text-indigo-800" @if ($isAwaitingGeneration) wire:poll.3s="pollGenerationStatus" @endif>
                    <div class="flex items-center gap-2">
                        @if ($isAwaitingGeneration)
                            <x-loading-spinner class="size-4" />
                        @else
                            <svg class="size-4 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        @endif
                        <span>{{ $generationStatus }}</span>
                    </div>
                </div>
            @elseif ($isAwaitingGeneration)
                <div class="rounded-md bg-indigo-50 p-4 text-sm text-indigo-800" wire:poll.3s="pollGenerationStatus">
                    <div class="flex items-center gap-2">
                        <x-loading-spinner class="size-4" />
                        <span>Image generation in progress…</span>
                    </div>
                </div>
            @endif

            <div class="flex flex-col gap-4 rounded-2xl border border-dashed border-indigo-200 bg-indigo-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3 text-sm text-indigo-900">
                    <svg class="mt-1 h-5 w-5 text-indigo-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div>
                        <p class="font-semibold">
                            {{ $hasReferenceSource ? 'Reference locked in.' : 'Choose an image source to continue.' }}
                        </p>
                        <p class="text-indigo-900/80">
                            {{ $hasReferenceSource ? 'We’ll analyse the photo to draft a reusable prompt.' : 'Upload a file or switch to a catalog product first.' }}
                        </p>
                    </div>
                </div>
                <x-button
                    type="button"
                    wire:click="extractPrompt"
                    wire:loading.attr="disabled"
                    :disabled="! $hasReferenceSource"
                    class="flex items-center gap-2 whitespace-nowrap"
                >
                    <span wire:loading.remove wire:target="extractPrompt,productId,image">
                        Extract prompt
                    </span>
                    <span wire:loading.flex wire:target="extractPrompt" class="flex items-center gap-2">
                        <x-loading-spinner class="size-4" />
                        Processing…
                    </span>
                </x-button>
            </div>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-2xl">
        <div class="space-y-5 px-6 py-5 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Shape the wording before you generate</h3>
                    <p class="text-sm text-gray-600">
                        Prompts extracted from the reference appear below&mdash;edit, combine, or paste your own instructions.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-button
                        type="button"
                        wire:click="generateImage"
                        wire:loading.attr="disabled"
                        :disabled="! $hasPromptText"
                        class="flex items-center gap-2 whitespace-nowrap"
                    >
                        <span wire:loading.remove wire:target="generateImage">
                            Generate image
                        </span>
                        <span wire:loading.flex wire:target="generateImage" class="flex items-center gap-2">
                            <x-loading-spinner class="size-4" />
                            Generating…
                        </span>
                    </x-button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        x-data="{ copied: false }"
                        x-on:click="if (@js($hasPromptText)) { navigator.clipboard.writeText(@js($promptResult)).then(() => { copied = true; setTimeout(() => copied = false, 2000); }); }"
                        :disabled="! $hasPromptText"
                    >
                        <svg class="me-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16.5v2.25A2.25 2.25 0 0010.25 21h7.5A2.25 2.25 0 0020 18.75v-7.5A2.25 2.25 0 0017.75 9h-2.25M8 16.5h-2.25A2.25 2.25 0 013.5 14.25v-7.5A2.25 2.25 0 015.75 4.5h7.5A2.25 2.25 0 0115.5 6.75V9M8 16.5h6.75A2.25 2.25 0 0017 14.25V7.5M8 16.5A2.25 2.25 0 015.75 14.25V7.5" />
                        </svg>
                        <span x-text="copied ? 'Copied!' : 'Copy prompt'"></span>
                    </button>
                </div>
            </div>

            <div
                @class([
                    'rounded-xl border p-4 text-sm',
                    $hasPromptText ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900',
                ])
            >
                <div class="flex items-start gap-3">
                    @if ($hasPromptText)
                        <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="m5 10 3 3 7-7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div>
                            <p class="font-semibold">Prompt ready.</p>
                            <p class="text-emerald-900/80">Preview or refine it below before sending it to the model.</p>
                        </div>
                    @else
                        <svg class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M10 3.333 3.333 16.667h13.334L10 3.333Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="m10 8.333.008 3.334" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9.992 13.333h.016" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div>
                            <p class="font-semibold">No prompt yet.</p>
                            <p class="text-amber-900/80">Run Extract prompt above or paste your own copy into the workspace.</p>
                        </div>
                    @endif
                </div>
            </div>

            @if ($errorMessage)
                <div class="rounded-md bg-red-50 p-4">
                    <div class="flex">
                        <div class="shrink-0">
                            <svg class="size-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM9 5a1 1 0 012 0v5a1 1 0 01-2 0V5zm1 8a1.25 1.25 0 100 2.5A1.25 1.25 0 0010 13z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ms-3">
                            <h3 class="text-sm font-medium text-red-800">
                                {{ $errorMessage }}
                            </h3>
                        </div>
                    </div>
                </div>
            @endif

            <div>
                <label for="photo-studio-prompt" class="block text-sm font-medium text-gray-700">
                    Prompt text
                </label>
                <textarea
                    id="photo-studio-prompt"
                    wire:model.defer="promptResult"
                    rows="6"
                    class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Paste or craft a prompt here if you’d like to skip extraction."
                ></textarea>
                <p class="mt-2 text-xs text-gray-500">
                    This prompt is sent to the image model when you choose Generate image.
                </p>
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="overlayOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8 sm:px-6"
        role="dialog"
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-gray-900/70" @click="closeOverlay()" aria-hidden="true"></div>

        <div
            class="relative z-10 w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl"
            @click.stop
        >
            <button
                type="button"
                class="absolute right-4 top-4 inline-flex size-9 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow ring-1 ring-black/10 transition hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                @click="closeOverlay()"
            >
                <span class="sr-only">Close gallery details</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="m6 6 8 8m0-8-8 8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div class="bg-gray-100">
                    <img
                        :src="selectedEntry ? selectedEntry.url : ''"
                        :alt="selectedEntry ? 'Generated render' : ''"
                        class="h-full w-full object-contain bg-gray-900/5"
                    />
                </div>
                <div class="space-y-5 p-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Image details</p>
                        <p class="text-xs text-gray-500" x-text="selectedEntry && selectedEntry.created_at ? selectedEntry.created_at : ''"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Product</p>
                        <p class="mt-2 text-sm font-semibold text-gray-900" x-text="selectedEntry && selectedEntry.product_label ? selectedEntry.product_label : 'Generated without a catalog product'"></p>
                        <p
                            class="text-xs text-gray-500"
                            x-show="selectedEntry && (selectedEntry.product_brand || selectedEntry.product_sku)"
                        >
                            <template x-if="selectedEntry && selectedEntry.product_brand">
                                <span x-text="selectedEntry.product_brand"></span>
                            </template>
                            <template x-if="selectedEntry && selectedEntry.product_brand && selectedEntry.product_sku">
                                <span class="mx-1 text-gray-400">&middot;</span>
                            </template>
                            <template x-if="selectedEntry && selectedEntry.product_sku">
                                <span class="text-gray-400" x-text="selectedEntry.product_sku"></span>
                            </template>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Prompt</p>
                        <p
                            class="mt-2 max-h-48 whitespace-pre-line overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800"
                            x-text="selectedEntry && selectedEntry.prompt ? selectedEntry.prompt : 'Prompt unavailable for this render.'"
                        ></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Model</p>
                        <p class="mt-2 text-sm text-gray-900" x-text="selectedEntry && selectedEntry.model ? selectedEntry.model : 'Unknown model'"></p>
                    </div>
                    <div class="flex flex-wrap gap-3 pt-1">
                        <a
                            :href="selectedEntry ? selectedEntry.url : '#'"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex size-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 shadow-sm transition hover:border-gray-300 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                        >
                            <span class="sr-only">View full size</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M18 10s-3-4-8-4-8 4-8 4 3 4 8 4 8-4 8-4Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M10 8a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                        <a
                            :href="selectedEntry ? selectedEntry.download_url : '#'"
                            download
                            class="inline-flex size-10 items-center justify-center rounded-full bg-indigo-600 text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                        >
                            <span class="sr-only">Download image</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M10 3v8m0 0 3-3m-3 3-3-3M4.5 13.5v1.25A1.25 1.25 0 0 0 5.75 16h8.5a1.25 1.25 0 0 0 1.25-1.25V13.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                        <button
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-full border border-red-200 text-red-600 shadow-sm transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 disabled:opacity-60"
                            wire:loading.attr="disabled"
                            wire:target="deleteGeneration"
                            x-on:click.prevent="if (!selectedEntry) { return; } if (!confirm('Delete this image from the gallery?')) { return; } $wire.deleteGeneration(selectedEntry.id).then(() => { closeOverlay(); });"
                        >
                            <span class="sr-only">Delete image</span>
                            <span class="flex items-center justify-center" wire:loading.remove wire:target="deleteGeneration">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m7 5 .867-1.3A1 1 0 0 1 8.7 3h2.6a1 1 0 0 1 .833.7L13 5m4 0H3m1 0 .588 11.18A1 1 0 0 0 5.587 17h8.826a1 1 0 0 0 .999-.82L16 5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <span class="flex items-center justify-center" wire:loading.flex wire:target="deleteGeneration">
                                <x-loading-spinner class="size-4" />
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
