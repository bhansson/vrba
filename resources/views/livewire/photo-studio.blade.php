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
    @endphp

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-6 py-5 sm:p-8 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">
                    Reference image
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    Upload a product shot or pick one from your catalog. The AI will analyse the image to craft a reusable generation prompt.
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <label for="photo-studio-upload" class="block text-sm font-medium text-gray-700">
                        Upload an image
                    </label>
                    <input
                        id="photo-studio-upload"
                        type="file"
                        wire:model="image"
                        accept="image/*"
                        class="mt-2 block w-full text-sm text-gray-900 border border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                            class="mt-4 rounded-xl border border-gray-200 object-cover max-h-48 w-auto max-w-full"
                        />
                    @endif
                </div>

                <div>
                    <label for="photo-studio-product" class="block text-sm font-medium text-gray-700">
                        Or use an existing product
                    </label>

                    <select
                        id="photo-studio-product"
                        wire:model.live="productId"
                        class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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

                    @if ($productImagePreview)
                        <img
                            src="{{ $productImagePreview }}"
                            alt="Product preview"
                            class="mt-4 rounded-xl border border-gray-200 object-cover max-h-48 w-auto max-w-full"
                        />
                    @elseif ($selectedProduct)
                        <p class="mt-4 text-sm text-amber-600">
                            This product does not have an image yet.
                        </p>
                    @endif
                </div>
            </div>

            <div>
                <label for="photo-studio-brief" class="block text-sm font-medium text-gray-700">
                    Creative direction (optional)
                </label>
                <textarea
                    id="photo-studio-brief"
                    wire:model.defer="creativeBrief"
                    rows="3"
                    class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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

            <x-button
                type="button"
                wire:click="extractPrompt"
                wire:loading.attr="disabled"
                class="flex items-center gap-2"
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

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-6 py-5 sm:p-8 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Prompt workspace</h3>
                    <p class="text-sm text-gray-600">
                        Your extracted prompt appears here. You can also edit or paste your own prompt before generating.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <x-secondary-button
                        type="button"
                        wire:click="generateImage"
                        wire:loading.attr="disabled"
                        class="flex items-center gap-2"
                    >
                        <span wire:loading.remove wire:target="generateImage">
                            Generate image
                        </span>
                        <span wire:loading.flex wire:target="generateImage" class="flex items-center gap-2">
                            <x-loading-spinner class="size-4" />
                            Generating…
                        </span>
                    </x-secondary-button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($promptResult)).then(() => { copied = true; setTimeout(() => copied = false, 2000); });"
                    >
                        <svg class="size-4 text-gray-500 me-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16.5v2.25A2.25 2.25 0 0010.25 21h7.5A2.25 2.25 0 0020 18.75v-7.5A2.25 2.25 0 0017.75 9h-2.25M8 16.5h-2.25A2.25 2.25 0 013.5 14.25v-7.5A2.25 2.25 0 015.75 4.5h7.5A2.25 2.25 0 0115.5 6.75V9M8 16.5h6.75A2.25 2.25 0 0017 14.25V7.5M8 16.5A2.25 2.25 0 015.75 14.25V7.5" />
                        </svg>
                        <span x-text="copied ? 'Copied!' : 'Copy to clipboard'"></span>
                    </button>
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
                    class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    placeholder="Paste or craft a prompt here if you’d like to skip extraction."
                ></textarea>
                <p class="mt-2 text-xs text-gray-500">
                    This prompt is sent to the image model when you choose Generate image.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-6 py-5 sm:p-8 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Photo gallery</h3>
                    <p class="text-sm text-gray-600">
                        Every Photo Studio generation linked to the selected product appears here once it finishes processing.
                    </p>
                </div>
                @if ($productId && ! empty($productGallery))
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        {{ count($productGallery) }} image{{ count($productGallery) === 1 ? '' : 's' }}
                    </span>
                @endif
            </div>

            @if (! $productId)
                <p class="text-sm text-gray-600">
                    Pick a product from your catalog to load its history of generated images.
                </p>
            @elseif (empty($productGallery))
                <p class="text-sm text-gray-600">
                    No Photo Studio renders yet for this product. Generate an image to start the gallery.
                </p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($productGallery as $entry)
                        <div class="flex flex-col rounded-xl border border-gray-200 bg-gray-50 p-3" wire:key="gallery-{{ $entry['id'] }}">
                            @if ($entry['url'])
                                <div class="relative group">
                                    <button
                                        type="button"
                                        class="block w-full overflow-hidden rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                                        @click="openOverlay(@js($entry))"
                                    >
                                        <img
                                            src="{{ $entry['url'] }}"
                                            alt="Generated render"
                                            class="h-48 w-full object-cover transition duration-200 hover:scale-[1.02]"
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
                                <div class="flex h-48 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white p-4 text-center text-sm text-gray-500">
                                    <div>
                                        <p>Stored on {{ $entry['disk'] }}</p>
                                        <p class="mt-1 font-mono text-xs break-all">{{ $entry['path'] }}</p>
                                    </div>
                                </div>
                            @endif
                            <div class="mt-3 flex items-center justify-end text-xs text-gray-500">
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
                        <p class="mt-1 text-sm font-medium text-gray-900" x-text="selectedEntry && selectedEntry.created_at_human ? `Added ${selectedEntry.created_at_human}` : ''"></p>
                        <p class="text-xs text-gray-500" x-text="selectedEntry && selectedEntry.created_at ? selectedEntry.created_at : ''"></p>
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
                            class="inline-flex items-center rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-300 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                        >
                            View full size
                        </a>
                        <a
                            :href="selectedEntry ? selectedEntry.download_url : '#'"
                            download
                            class="inline-flex items-center rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                        >
                            Download
                        </a>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-full border border-red-200 px-4 py-2 text-sm font-medium text-red-600 shadow-sm transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 disabled:opacity-60"
                            wire:loading.attr="disabled"
                            wire:target="deleteGeneration"
                            x-on:click.prevent="if (!selectedEntry) { return; } if (!confirm('Delete this image from the gallery?')) { return; } $wire.deleteGeneration(selectedEntry.id).then(() => { closeOverlay(); });"
                        >
                            <span class="flex items-center gap-2" wire:loading.remove wire:target="deleteGeneration">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m7 5 .867-1.3A1 1 0 0 1 8.7 3h2.6a1 1 0 0 1 .833.7L13 5m4 0H3m1 0 .588 11.18A1 1 0 0 0 5.587 17h8.826a1 1 0 0 0 .999-.82L16 5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span>Delete image</span>
                            </span>
                            <span class="flex items-center gap-2" wire:loading.flex wire:target="deleteGeneration">
                                <x-loading-spinner class="size-4" />
                                <span>Deleting…</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
