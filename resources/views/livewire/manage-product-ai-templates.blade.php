<div class="space-y-6">
    @if ($statusMessage)
        <div class="rounded-md bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Template Library</h1>
            <p class="mt-1 text-sm text-gray-600">
                Manage AI prompt templates used across product generations. Default templates are provided for every team;
                create custom ones tailored to your workflow.
            </p>
        </div>
        <div class="flex gap-3">
            <x-button type="button" wire:click="startCreate">
                Create template
            </x-button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            @forelse ($templates as $template)
            @php
                $context = collect($template->context ?? []);
                $placeholders = $context
                    ->pluck('key')
                    ->filter()
                    ->map(fn ($key) => '{{ '.$key.' }}')
                    ->join(', ');
                $contentType = \Illuminate\Support\Str::headline($template->settings['content_type'] ?? 'text');
                $isDefault = $template->team_id === null;
            @endphp
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="px-5 py-4 sm:flex sm:items-start sm:justify-between">
                        <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold text-gray-900">{{ $template->name }}</h2>
                                    @if ($isDefault)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                            Default
                                        </span>
                                    @endif
                                </div>
                                @if ($template->description)
                                    <p class="text-sm text-gray-600">{{ $template->description }}</p>
                                @endif
                            <dl class="grid gap-1 text-sm text-gray-600 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-500">Slug</dt>
                                    <dd>{{ $template->slug }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-500">Content type</dt>
                                    <dd>{{ $contentType }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-500">Context variables</dt>
                                    <dd>{{ $placeholders ?: 'None' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 sm:mt-0 sm:flex-col sm:items-end sm:gap-3">
                            <x-button type="button" wire:click="duplicate({{ $template->id }})" class="px-3 py-1.5">
                                Copy
                            </x-button>
                            @if ($template->team_id === optional($team)->id)
                                <x-button type="button" wire:click="startEdit({{ $template->id }})" class="px-3 py-1.5">
                                    Edit
                                </x-button>
                                <button type="button"
                                        x-data
                                        x-on:click.prevent="if (window.confirm('Delete the {{ addslashes($template->name) }} template? This action cannot be undone.')) { $wire.delete({{ $template->id }}) }"
                                        class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-medium text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                    Delete
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="border-t border-gray-100 bg-gray-50 px-5 py-3 text-xs text-gray-500">
                        Updated {{ optional($template->updated_at)->diffForHumans() ?? 'recently' }}
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-5 py-6 text-sm text-gray-600">
                    No templates found yet. Start by creating one above.
                </div>
            @endforelse
        </div>

        <div class="space-y-4">
            @if ($showForm)
                <div class="rounded-lg border border-indigo-200 bg-white shadow-sm">
                    <div class="border-b border-indigo-100 px-5 py-4">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ $editingTemplateId ? 'Edit template' : 'Create template' }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Fill out the details below to {{ $editingTemplateId ? 'update your' : 'add a new' }} template.
                        </p>
                    </div>
                    <form wire:submit.prevent="save" class="px-5 py-5 space-y-5"
                          x-data="{
                              insertPlaceholder(value) {
                                  const field = this.$refs.promptField;
                                  if (!field) {
                                      return;
                                  }

                                  const start = field.selectionStart ?? field.value.length;
                                  const end = field.selectionEnd ?? field.value.length;
                                  const before = field.value.slice(0, start);
                                  const after = field.value.slice(end);
                                  const needsWhitespace = before !== '' && !/\s$/u.test(before);
                                  const insertion = (needsWhitespace ? ' ' : '') + value;

                                  field.value = before + insertion + after;
                                  const cursor = before.length + insertion.length;

                                  field.setSelectionRange(cursor, cursor);
                                  field.focus();
                                  field.dispatchEvent(new Event('input'));
                              }
                          }">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" wire:model.defer="form.name" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('form.name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description <span class="text-xs text-gray-500">(optional)</span></label>
                            <textarea wire:model.defer="form.description" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            @error('form.description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Content type</label>
                            <select wire:model.defer="form.content_type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="text">Text</option>
                                <option value="usps">List</option>
                                <option value="faq">FAQ entries</option>
                            </select>
                            @error('form.content_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-gray-700">Prompt</label>
                                <div class="flex flex-wrap gap-2">
                                    @php
                                        $chipKeys = ['title', 'description', 'sku', 'gtin', 'brand'];
                                    @endphp
                                    @foreach ($chipKeys as $chipKey)
                                        @php
                                            $variable = $contextVariables[$chipKey] ?? [];
                                            $label = $variable['label'] ?? \Illuminate\Support\Str::headline($chipKey);
                                            $placeholder = '{'.'{ '.$chipKey.' }'.'}';
                                        @endphp
                                        <button type="button"
                                                class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-600"
                                                x-on:click.prevent="insertPlaceholder('{{ $placeholder }}')">
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <textarea id="template-prompt" x-ref="promptField" wire:model.defer="form.prompt" rows="6" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            @error('form.prompt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <x-button type="submit">
                                {{ $editingTemplateId ? 'Save changes' : 'Create template' }}
                            </x-button>
                            <x-secondary-button type="button" wire:click="cancelForm">
                                Cancel
                            </x-secondary-button>
                        </div>
                    </form>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-5 py-6 text-sm text-gray-600">
                    Select a template to edit or create a new one. The form will appear here.
                </div>
            @endif
        </div>
    </div>
</div>
