<?php

namespace App\Models;

use App\Support\ProductAiContentParser;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function content(): Attribute
    {
        return Attribute::make(
            get: static function ($value) {
                return ProductAiContentParser::parseUsps($value);
            },
            set: static function ($value) {
                return json_encode(
                    ProductAiContentParser::parseUsps($value),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            },
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
