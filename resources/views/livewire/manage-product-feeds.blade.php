@php use Illuminate\Support\Str; @endphp

<div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">
                    Submit Google Product Feed
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Provide a feed URL or upload an XML or CSV file. Once parsed, choose how each attribute maps to your products.
                </p>
            </div>

            <div class="px-6 py-6 space-y-6">
                @if ($statusMessage)
                    <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                        {{ $statusMessage }}
                    </div>
                @endif

                @if ($errorMessage)
                    <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                        {{ $errorMessage }}
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <x-label for="feedName" value="Feed Name" />
                        <x-input id="feedName" type="text" class="w-full" wire:model.defer="feedName" />
                        <x-input-error for="feedName" />
                    </div>

                    <div class="space-y-2">
                        <x-label for="feedUrl" value="Feed URL" />
                        <x-input id="feedUrl" type="url" class="w-full" placeholder="https://example.com/products.xml" wire:model.defer="feedUrl" />
                        <x-input-error for="feedUrl" />
                    </div>
                </div>

                <div class="space-y-2">
                    <x-label for="feedFile" value="Or Upload XML Feed" />
                    <input id="feedFile" type="file" wire:model="feedFile" class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200" />
                    <x-input-error for="feedFile" />
                    <p class="text-xs text-gray-500">
                        XML or CSV feeds up to 5MB are supported. If both a URL and file are provided, the uploaded file will be used.
                    </p>
                </div>

                <div class="flex items-center space-x-3">
                    <x-button type="button" wire:click="fetchFields" wire:loading.attr="disabled">
                        Load Feed Fields
                    </x-button>
                    <span class="text-sm text-gray-500" wire:loading>
                        Loading feed…
                    </span>
                </div>

                @if ($showMapping)
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-base font-semibold text-gray-800">
                            Field Mapping
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Select which feed element populates each product attribute.
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach ($mapping as $attribute => $value)
                                <div class="space-y-2">
                                    <x-label :for="'mapping_'.$attribute" :value="Str::headline($attribute)" />
                                    <select id="mapping_{{ $attribute }}" wire:model="mapping.{{ $attribute }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Select field --</option>
                                        @foreach ($availableFields as $field)
                                            <option value="{{ $field }}">{{ $field }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 flex items-center space-x-3">
                            <x-button type="button" wire:click="importFeed" wire:loading.attr="disabled">
                                Import Products
                            </x-button>
                            <span class="text-sm text-gray-500" wire:loading>
                                Importing products…
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-10">
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-6 py-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">
                    Imported Feeds
                </h2>
            </div>

            <div class="px-6 py-6">
                @if ($feeds->isEmpty())
                    <p class="text-sm text-gray-600">
                        No feeds imported yet. Submit a feed above to get started.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700 uppercase tracking-wider text-xs">Name</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700 uppercase tracking-wider text-xs">Products</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700 uppercase tracking-wider text-xs">Feed URL</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700 uppercase tracking-wider text-xs">Updated</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-700 uppercase tracking-wider text-xs">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($feeds as $feed)
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-gray-900">
                                            {{ $feed->name }}
                                        </td>
                                        <td class="px-4 py-2 text-gray-700">
                                            {{ $feed->products_count }}
                                        </td>
                                        <td class="px-4 py-2 text-gray-700">
                                            @if ($feed->feed_url)
                                                <a href="{{ $feed->feed_url }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline">
                                                    {{ Str::limit($feed->feed_url, 60) }}
                                                </a>
                                            @else
                                                <span class="text-gray-500">Uploaded file</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-gray-700">
                                            {{ $feed->updated_at->diffForHumans() }}
                                        </td>
                                        <td class="px-4 py-2 text-right space-x-2">
                                            <button type="button"
                                                wire:click="refreshFeed({{ $feed->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="refreshFeed({{ $feed->id }})"
                                                class="inline-flex items-center px-4 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                <span wire:loading.remove wire:target="refreshFeed({{ $feed->id }})">Refresh</span>
                                                <span wire:loading wire:target="refreshFeed({{ $feed->id }})">Refreshing…</span>
                                            </button>
                                            <button type="button"
                                                wire:click="deleteFeed({{ $feed->id }})"
                                                class="inline-flex items-center px-3 py-1.5 border border-red-500 rounded-md text-xs font-medium text-red-600 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
