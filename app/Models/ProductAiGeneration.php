<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAiGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'product_id',
        'product_ai_template_id',
        'product_ai_job_id',
        'sku',
        'content',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected $touches = ['product'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductAiTemplate::class, 'product_ai_template_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductAiJob::class, 'product_ai_job_id');
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: static function ($value) {
                if ($value === null) {
                    return null;
                }

                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            },
            set: static function ($value) {
                if ($value === null) {
                    return null;
                }

                if (is_string($value)) {
                    return json_encode($value, JSON_UNESCAPED_UNICODE);
                }

                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        );
    }
}
