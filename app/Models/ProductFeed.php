<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductFeed extends Model
{
    use HasFactory;

    public const LANGUAGE_OPTIONS = [
        'bg' => 'Bulgarian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'de' => 'German',
        'en' => 'English',
        'en-gb' => 'English (United Kingdom)',
        'en-us' => 'English (United States)',
        'es' => 'Spanish',
        'et' => 'Estonian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'hu' => 'Hungarian',
        'it' => 'Italian',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'nb' => 'Norwegian Bokmål',
        'nl' => 'Dutch',
        'no' => 'Norwegian',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'ro' => 'Romanian',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'sv' => 'Swedish',
    ];

    public const LANGUAGE_VALIDATION_RULE = 'required|string|in:bg,cs,da,de,en,en-gb,en-us,es,et,fi,fr,hu,it,lt,lv,nb,nl,no,pl,pt,ro,sk,sl,sv';

    protected $fillable = [
        'team_id',
        'name',
        'feed_url',
        'language',
        'field_mappings',
    ];

    protected $casts = [
        'field_mappings' => 'array',
    ];

    public static function languageOptions(): array
    {
        return self::LANGUAGE_OPTIONS;
    }

    public static function languageLabel(?string $code): ?string
    {
        return $code !== null ? (self::LANGUAGE_OPTIONS[$code] ?? null) : null;
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
