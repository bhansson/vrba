<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoStudioGeneration extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'user_id',
        'product_id',
        'source_type',
        'source_reference',
        'prompt',
        'model',
        'storage_disk',
        'storage_path',
        'response_id',
        'response_model',
        'response_metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'response_metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
