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
        'description',
        'url',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
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

    public function latestAiDescriptionSummary()
    {
        return $this->hasOne(ProductAiDescriptionSummary::class)->latestOfMany();
    }

    public function latestAiDescription()
    {
        return $this->hasOne(ProductAiDescription::class)->latestOfMany();
    }

    public function latestAiUsp()
    {
        return $this->hasOne(ProductAiUsp::class)->latestOfMany();
    }

    public function latestAiFaq()
    {
        return $this->hasOne(ProductAiFaq::class)->latestOfMany();
    }

    public function latestAiReviewSummary()
    {
        return $this->hasOne(ProductAiReviewSummary::class)->latestOfMany();
    }
}
