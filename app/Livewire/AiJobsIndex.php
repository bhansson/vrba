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

    public int $perPage = 100;
    public string $filter = 'all';

    protected $queryString = [
        'filter' => ['except' => 'all'],
        'perPage' => ['except' => 100],
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
            ->with([
                'product:id,title,sku',
                'template:id,name,slug',
                'photoStudioGeneration:id,product_ai_job_id,storage_path,storage_disk',
            ])
            ->where('team_id', $team->id);

        match ($this->filter) {
            'active' => $jobsQuery->whereIn('status', [
                ProductAiJob::STATUS_QUEUED,
                ProductAiJob::STATUS_PROCESSING,
            ]),
            'failed' => $jobsQuery->where('status', ProductAiJob::STATUS_FAILED),
            'completed' => $jobsQuery->where('status', ProductAiJob::STATUS_COMPLETED),
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
            'all' => 'All jobs',
            'active' => 'Active',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ]);
    }
}
