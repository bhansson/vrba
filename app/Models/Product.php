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

    public function aiGeneration()
    {
        return $this->hasOne(ProductAiGeneration::class, 'product_sku', 'sku');
    }
}
