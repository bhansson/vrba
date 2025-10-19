<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductFeed extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'feed_url',
        'field_mappings',
    ];

    protected $casts = [
        'field_mappings' => 'array',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
