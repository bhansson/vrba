<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAiJob extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const PROMPT_DESCRIPTION = 'description';
    public const PROMPT_DESCRIPTION_SUMMARY = 'description_summary';
    public const PROMPT_USPS = 'usps';
    public const PROMPT_FAQ = 'faq';
    public const PROMPT_REVIEW_SUMMARY = 'review_summary';

    protected $fillable = [
        'team_id',
        'product_id',
        'sku',
        'prompt_type',
        'status',
        'progress',
        'attempts',
        'queued_at',
        'started_at',
        'finished_at',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
