@php use Illuminate\Support\Str; @endphp
@php
    $statusStyles = [
        \App\Models\ProductAiJob::STATUS_QUEUED => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        \App\Models\ProductAiJob::STATUS_PROCESSING => 'bg-blue-100 text-blue-800 border-blue-300',
        \App\Models\ProductAiJob::STATUS_COMPLETED => 'bg-green-100 text-green-800 border-green-300',
        \App\Models\ProductAiJob::STATUS_FAILED => 'bg-red-100 text-red-800 border-red-300',
    ];

    $promptLabels = [
        \App\Models\ProductAiJob::PROMPT_DESCRIPTION => 'Description',
        \App\Models\ProductAiJob::PROMPT_DESCRIPTION_SUMMARY => 'Description Summary',
        \App\Models\ProductAiJob::PROMPT_USPS => 'Unique Selling Points',
        \App\Models\ProductAiJob::PROMPT_FAQ => 'FAQ',
        \App\Models\ProductAiJob::PROMPT_REVIEW_SUMMARY => 'Review Summary',
    ];
@endphp

<div wire:poll.10s class="max-w-6xl mx-auto py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">AI Job Progress</h1>
            <p class="mt-1 text-sm text-gray-600">
                Monitor queued and running AI generations. Refreshes every 10 seconds.
            </p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:space-x-3">
            <div>
                <label for="filter" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Scope</label>
                <select wire:model.live="filter" id="filter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="perPage" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Per page</label>
                <select wire:model.live.number="perPage" id="perPage" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="min-w-full divide-y divide-gray-200">
            <div class="grid grid-cols-12 px-4 py-3 bg-gray-50 text-xs font-semibold uppercase text-gray-600 gap-2">
                <div class="col-span-4">Product</div>
                <div class="col-span-2">Prompt</div>
                <div class="col-span-2">Status</div>
                <div class="col-span-2">Progress</div>
                <div class="col-span-2 text-right">Queued</div>
            </div>

            @forelse ($jobs as $job)
                <div wire:key="job-{{ $job->id }}" class="grid grid-cols-12 gap-2 px-4 py-3 text-sm text-gray-700 border-t border-gray-100">
                    <div class="col-span-4">
                        <div class="font-medium text-gray-900">
                            {{ $job->product?->title ?: 'Unknown product' }}
                        </div>
                        <div class="text-xs text-gray-500">
                            SKU: {{ $job->sku ?? '—' }}
                        </div>
                    </div>
                    <div class="col-span-2">
                        {{ $promptLabels[$job->prompt_type] ?? Str::headline($job->prompt_type) }}
                    </div>
                    <div class="col-span-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-xs font-medium {{ $statusStyles[$job->status] ?? 'bg-gray-100 text-gray-800 border-gray-300' }}">
                            {{ Str::headline($job->status) }}
                        </span>
                        @if ($job->status === \App\Models\ProductAiJob::STATUS_FAILED && $job->last_error)
                            <div class="mt-1 text-xs text-red-600">
                                {{ Str::limit($job->last_error, 140) }}
                            </div>
                        @endif
                    </div>
                    <div class="col-span-2">
                        <div class="flex items-center space-x-2">
                            <div class="flex-1 h-2 rounded-full bg-gray-200 overflow-hidden">
                                <div class="h-2 bg-indigo-500" style="width: {{ $job->progress }}%"></div>
                            </div>
                            <span class="text-xs text-gray-500 w-10 text-right">{{ $job->progress }}%</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Attempts: {{ $job->attempts }}
                        </div>
                    </div>
                    <div class="col-span-2 text-right text-xs text-gray-500">
                        <div>Queued {{ optional($job->queued_at)->diffForHumans() ?? 'N/A' }}</div>
                        @if ($job->started_at)
                            <div>Started {{ $job->started_at->diffForHumans() }}</div>
                        @endif
                        @if ($job->finished_at)
                            <div>Finished {{ $job->finished_at->diffForHumans() }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-6 text-sm text-gray-600">
                    @if ($filter === 'active')
                        No active jobs right now. Trigger an AI request to see it appear here.
                    @elseif ($filter === 'failed')
                        No failed jobs found. If an AI run errors we'll display details here.
                    @else
                        No AI jobs found yet.
                    @endif
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        {{ $jobs->links() }}
    </div>
</div>
