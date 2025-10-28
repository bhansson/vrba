<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAiTemplate extends Model
{
    use HasFactory;

    public const SLUG_DESCRIPTION_SUMMARY = 'description_summary';
    public const SLUG_DESCRIPTION = 'description';
    public const SLUG_USPS = 'usps';
    public const SLUG_FAQ = 'faq';

    protected $fillable = [
        'team_id',
        'slug',
        'name',
        'description',
        'is_default',
        'is_active',
        'system_prompt',
        'prompt',
        'context',
        'settings',
    ];

    protected $casts = [
        'is_default' => 'bool',
        'is_active' => 'bool',
        'context' => 'array',
        'settings' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(ProductAiGeneration::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ProductAiJob::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where(function (Builder $inner) use ($teamId): void {
            $inner->whereNull('team_id')
                ->orWhere('team_id', $teamId);
        });
    }

    public function historyLimit(): int
    {
        return (int) config('product-ai.defaults.history_limit', 10);
    }

    public function contentType(): string
    {
        return (string) ($this->settings['content_type'] ?? 'text');
    }

    public function options(): array
    {
        $default = config('product-ai.defaults.options', []);
        $custom = $this->settings['options'] ?? [];

        if (! is_array($default)) {
            $default = [];
        }

        if (! is_array($custom)) {
            $custom = [];
        }

        return array_filter(
            array_merge($default, $custom),
            static fn ($value) => $value !== null
        );
    }

    public static function syncDefaultTemplates(): void
    {
        $defaults = config('product-ai.default_templates', []);

        if (empty($defaults)) {
            return;
        }

        $slugs = [];

        foreach ($defaults as $slug => $template) {
            $slugs[] = $slug;

            static::query()->updateOrCreate(
                [
                    'team_id' => null,
                    'slug' => $slug,
                ],
                [
                    'name' => $template['name'],
                    'description' => $template['description'] ?? null,
                    'is_default' => true,
                    'is_active' => true,
                    'system_prompt' => $template['system_prompt'] ?? null,
                    'prompt' => $template['prompt'],
                    'context' => $template['context'] ?? [],
                    'settings' => $template['settings'] ?? [],
                ]
            );
        }

        static::query()
            ->whereNull('team_id')
            ->where('is_default', true)
            ->whereNotIn('slug', $slugs)
            ->delete();
    }
}
