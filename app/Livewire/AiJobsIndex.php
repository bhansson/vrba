<?php

namespace App\Livewire;

use App\Models\ProductAiJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class AiJobsIndex extends Component
{
    use WithPagination;

    public int $perPage = 15;
    public string $filter = 'active';

    protected $queryString = [
        'filter' => ['except' => 'active'],
        'perPage' => ['except' => 15],
    ];

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $jobsQuery = ProductAiJob::query()
            ->with(['product:id,title,sku'])
            ->where('team_id', $team->id);

        match ($this->filter) {
            'active' => $jobsQuery->whereIn('status', [
                ProductAiJob::STATUS_QUEUED,
                ProductAiJob::STATUS_PROCESSING,
            ]),
            'failed' => $jobsQuery->where('status', ProductAiJob::STATUS_FAILED),
            default => null,
        };

        $jobs = $jobsQuery
            ->orderByDesc('queued_at')
            ->orderByDesc('id')
            ->paginate($this->perPage)
            ->withQueryString();

        return view('livewire.ai-jobs-index', [
            'jobs' => $jobs,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    protected function statusOptions(): Collection
    {
        return collect([
            'active' => 'Active',
            'failed' => 'Failed',
            'all' => 'All jobs',
        ]);
    }
}
