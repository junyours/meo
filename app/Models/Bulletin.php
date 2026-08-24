<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bulletin extends Model
{
    protected $fillable = [
        'title', 'category', 'summary', 'is_public', 'is_archived', 'archived_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_archived' => 'boolean',
        'archived_at' => 'date',
    ];
}
