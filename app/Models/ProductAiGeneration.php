<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAiGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_sku',
        'description',
        'summary',
        'usps',
        'faq',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_sku', 'sku');
    }
}
