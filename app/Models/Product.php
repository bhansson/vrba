<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    public function feed()
    {
        return $this->belongsTo(ProductFeed::class, 'product_feed_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function aiDescriptionSummaries()
    {
        return $this->hasMany(ProductAiDescriptionSummary::class)->latest();
    }

    public function aiDescriptions()
    {
        return $this->hasMany(ProductAiDescription::class);
    }

    public function latestAiDescriptionSummary()
    {
        return $this->hasOne(ProductAiDescriptionSummary::class)->latestOfMany('updated_at');
    }

    public function latestAiDescription()
    {
        return $this->hasOne(ProductAiDescription::class)->latestOfMany('updated_at');
    }

    public function aiUsps()
    {
        return $this->hasMany(ProductAiUsp::class);
    }

    public function latestAiUsp()
    {
        return $this->hasOne(ProductAiUsp::class)->latestOfMany('updated_at');
    }

    public function aiFaqs()
    {
        return $this->hasMany(ProductAiFaq::class);
    }

    public function latestAiFaq()
    {
        return $this->hasOne(ProductAiFaq::class)->latestOfMany('updated_at');
    }

    public function latestAiReviewSummary()
    {
        return $this->hasOne(ProductAiReviewSummary::class)->latestOfMany('updated_at');
    }
}
