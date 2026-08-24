<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WelcomeContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'hero_title',
        'hero_description',
        'hero_image',
        'hero_background_image',
        'additional_images',
        'slideshow_images',
        'achievement_images',
        'is_active',
    ];

    protected $casts = [
        'additional_images' => 'array',
        'slideshow_images' => 'array',
        'achievement_images' => 'array',
        'is_active' => 'boolean',
    ];

    public static function getActive()
    {
        return self::where('is_active', true)->first();
    }
}
