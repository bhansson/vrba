<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ProductAiJob extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_TEMPLATE = 'template';
    public const TYPE_PHOTO_STUDIO = 'photo_studio';

    protected $fillable = [
        'team_id',
        'product_id',
        'sku',
        'product_ai_template_id',
        'job_type',
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

    protected $attributes = [
        'job_type' => self::TYPE_TEMPLATE,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductAiTemplate::class, 'product_ai_template_id');
    }

    public function photoStudioGeneration(): HasOne
    {
        return $this->hasOne(PhotoStudioGeneration::class, 'product_ai_job_id');
    }

    public function friendlyErrorMessage(): ?string
    {
        if ($this->status !== self::STATUS_FAILED) {
            return null;
        }

        $error = Str::lower($this->last_error ?? '');

        return match (true) {
            $error === '' => 'The job stopped before it could finish.',
            Str::contains($error, ['timeout', 'timed out']) => 'The job timed out before the AI responded.',
            Str::contains($error, ['rate limit', 'too many requests', '429']) => 'We hit the AI rate limit; wait a moment and try again.',
            Str::contains($error, ['unauthorized', '401', 'invalid api key']) => 'We could not reach the AI service with the current credentials.',
            Str::contains($error, ['payment required', '402', 'insufficient', 'credits']) => 'The AI service ran out of credits or the request needs fewer tokens.',
            Str::contains($error, ['400', 'bad request', 'invalid parameter', 'rejected the request']) => 'The AI request parameters were invalid. Refresh and try again.',
            Str::contains($error, ['403', 'flagged', 'moderation']) => 'The AI provider flagged the content for moderation.',
            Str::contains($error, ['502', 'model could not be reached']) => 'The selected AI model is temporarily unavailable.',
            Str::contains($error, ['503', 'no available provider']) => 'No AI provider is currently available for the selected model.',
            default => 'Something went wrong while generating the content. Please try again.',
        };
    }
}
