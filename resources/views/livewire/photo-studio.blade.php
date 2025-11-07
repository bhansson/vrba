<div class="space-y-6">
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
                    Up to 600 characters. These notes are appended to the AI request for extra guidance.
                </p>

                @error('creativeBrief')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
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

            <div class="flex flex-wrap items-center gap-4">
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
    </div>

    @if ($promptResult)
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-5 sm:p-8 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Extracted prompt</h3>
                        <p class="text-sm text-gray-600">
                            Paste this into your preferred image generation workflow as the reference prompt.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($promptResult)).then(() => { copied = true; setTimeout(() => copied = false, 2000); });"
                    >
                        <svg class="size-4 text-gray-500 me-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16.5v2.25A2.25 2.25 0 0010.25 21h7.5A2.25 2.25 0 0020 18.75v-7.5A2.25 2.25 0 0017.75 9h-2.25M8 16.5h-2.25A2.25 2.25 0 013.5 14.25v-7.5A2.25 2.25 0 015.75 4.5h7.5A2.25 2.25 0 0115.5 6.75V9M8 16.5h6.75A2.25 2.25 0 0017 14.25V7.5M8 16.5A2.25 2.25 0 015.75 14.25V7.5" />
                        </svg>
                        <span x-text="copied ? 'Copied!' : 'Copy to clipboard'"></span>
                    </button>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-900 whitespace-pre-line leading-relaxed">
                    {{ $promptResult }}
                </div>
            </div>
        </div>
    @endif
</div>
