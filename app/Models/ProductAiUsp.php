<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAiUsp extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'product_id',
        'sku',
        'content',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected $touches = ['product'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
