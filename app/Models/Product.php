<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_feed_id',
        'team_id',
        'sku',
        'gtin',
        'title',
        'brand',
        'description',
        'url',
        'image_link',
        'additional_image_link',
    ];

    public function feed()
    {
        return $this->belongsTo(ProductFeed::class, 'product_feed_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function aiGenerations(): HasMany
    {
        return $this->hasMany(ProductAiGeneration::class);
    }

    public function latestAiGeneration(): HasOne
    {
        return $this->hasOne(ProductAiGeneration::class)->latestOfMany('updated_at');
    }

    public function aiDescriptionSummaries(): HasMany
    {
        return $this->aiGenerationsForTemplate(ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY);
    }

    public function aiDescriptions(): HasMany
    {
        return $this->aiGenerationsForTemplate(ProductAiTemplate::SLUG_DESCRIPTION);
    }

    public function aiUsps(): HasMany
    {
        return $this->aiGenerationsForTemplate(ProductAiTemplate::SLUG_USPS);
    }

    public function aiFaqs(): HasMany
    {
        return $this->aiGenerationsForTemplate(ProductAiTemplate::SLUG_FAQ);
    }

    public function latestAiDescriptionSummary(): HasOne
    {
        return $this->latestAiGenerationForTemplate(ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY);
    }

    public function latestAiDescription(): HasOne
    {
        return $this->latestAiGenerationForTemplate(ProductAiTemplate::SLUG_DESCRIPTION);
    }

    public function latestAiUsp(): HasOne
    {
        return $this->latestAiGenerationForTemplate(ProductAiTemplate::SLUG_USPS);
    }

    public function latestAiFaq(): HasOne
    {
        return $this->latestAiGenerationForTemplate(ProductAiTemplate::SLUG_FAQ);
    }

    public function latestAiGenerationForTemplate(string $slug): HasOne
    {
        return $this->hasOne(ProductAiGeneration::class)
            ->whereHas('template', static function ($query) use ($slug): void {
                $query->where('slug', $slug);
            })
            ->latestOfMany('updated_at');
    }

    public function aiGenerationsForTemplate(string $slug): HasMany
    {
        return $this->hasMany(ProductAiGeneration::class)
            ->whereHas('template', static function ($query) use ($slug): void {
                $query->where('slug', $slug);
            })
            ->latest();
    }
}
