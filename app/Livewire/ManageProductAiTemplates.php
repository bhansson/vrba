<?php

namespace App\Livewire;

use App\Models\ProductAiTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ManageProductAiTemplates extends Component
{
    public bool $showForm = false;

    public ?int $editingTemplateId = null;

    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $contextVariables = [];

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->contextVariables = config('product-ai.context_variables', []);
        $this->resetForm();
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        abort_if(! $team, 404);

        ProductAiTemplate::syncDefaultTemplates();

        $templates = ProductAiTemplate::query()
            ->forTeam($team->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('livewire.manage-product-ai-templates', [
            'templates' => $templates,
            'team' => $team,
        ]);
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $templateId): void
    {
        $team = Auth::user()->currentTeam;
        abort_if(! $team, 404);

        $template = ProductAiTemplate::query()
            ->where('id', $templateId)
            ->where('team_id', $team->id)
            ->first();

        if (! $template) {
            $this->statusMessage = 'Only custom templates can be edited.';

            return;
        }

        $this->editingTemplateId = $template->id;
        $this->showForm = true;

        $this->form = [
            'name' => $template->name,
            'description' => $template->description,
            'prompt' => $template->prompt,
            'content_type' => (string) ($template->settings['content_type'] ?? 'text'),
        ];

    }

    public function duplicate(int $templateId): void
    {
        $team = Auth::user()->currentTeam;
        abort_if(! $team, 404);

        $template = ProductAiTemplate::query()
            ->forTeam($team->id)
            ->where('id', $templateId)
            ->firstOrFail();

        $copy = $template->replicate([
            'slug',
            'team_id',
            'is_default',
            'is_active',
        ]);

        $copy->team_id = $team->id;
        $copy->is_default = false;
        $copy->is_active = true;
        $copy->name = $template->name.' (Copy)';
        $copy->slug = $this->generateUniqueSlug($template->slug.'-copy', $team->id);
        $copy->save();

        $this->statusMessage = 'Template duplicated.';
    }

    public function delete(int $templateId): void
    {
        $team = Auth::user()->currentTeam;
        abort_if(! $team, 404);

        $template = ProductAiTemplate::query()
            ->where('id', $templateId)
            ->where('team_id', $team->id)
            ->first();

        if (! $template) {
            $this->statusMessage = 'Only custom templates can be deleted.';

            return;
        }

        if ($template->jobs()->exists() || $template->generations()->exists()) {
            $this->statusMessage = 'Cannot delete a template that has history.';

            return;
        }

        $template->delete();

        if ($this->editingTemplateId === $templateId) {
            $this->resetForm();
        }

        $this->statusMessage = 'Template deleted.';
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $team = Auth::user()->currentTeam;
        abort_if(! $team, 404);

        $this->validate($this->rules());

        $context = $this->buildContextCollection();

        $settings = [
            'content_type' => $this->form['content_type'],
            'options' => $this->currentOptions(),
        ];

        if ($this->editingTemplateId) {
            $template = ProductAiTemplate::query()
                ->where('id', $this->editingTemplateId)
                ->where('team_id', $team->id)
                ->firstOrFail();

            $template->forceFill([
                'name' => $this->form['name'],
                'description' => $this->form['description'] ?: null,
                'prompt' => $this->form['prompt'],
                'context' => $context,
                'settings' => $settings,
                'is_active' => true,
            ])->save();

            $message = 'Template updated.';
        } else {
            $slug = $this->generateUniqueSlug($this->form['name'], $team->id);

            ProductAiTemplate::create([
                'team_id' => $team->id,
                'slug' => $slug,
                'name' => $this->form['name'],
                'description' => $this->form['description'] ?: null,
                'prompt' => $this->form['prompt'],
                'context' => $context,
                'settings' => $settings,
                'is_default' => false,
                'is_active' => true,
            ]);

            $message = 'Template created.';
        }

        $this->statusMessage = $message;
        $this->resetForm();
    }

    protected function currentOptions(): array
    {
        if (! $this->editingTemplateId) {
            return [];
        }

        $template = ProductAiTemplate::query()->find($this->editingTemplateId);

        return data_get($template?->settings, 'options', []);
    }

    protected function buildContextCollection(): array
    {
        return collect($this->contextVariables)
            ->keys()
            ->filter(fn ($key) => str_contains($this->form['prompt'], '{{ '.$key.' }}'))
            ->unique()
            ->map(fn ($key) => ['key' => $key])
            ->values()
            ->all();
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:191'],
            'form.description' => ['nullable', 'string', 'max:255'],
            'form.prompt' => ['required', 'string'],
            'form.content_type' => ['required', Rule::in(['text', 'usps', 'faq'])],
        ];
    }

    protected function generateUniqueSlug(string $name, int $teamId): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = Str::random(8);
        }

        $slug = $base;
        $counter = 1;

        while (ProductAiTemplate::query()
            ->where('team_id', $teamId)
            ->where('slug', $slug)
            ->when($this->editingTemplateId, fn ($query) => $query->where('id', '!=', $this->editingTemplateId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function resetForm(): void
    {
        $this->editingTemplateId = null;
        $this->showForm = false;
        $this->form = [
            'name' => '',
            'description' => '',
            'prompt' => '',
            'content_type' => 'text',
        ];
    }
}
