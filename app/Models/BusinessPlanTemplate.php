<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessPlanTemplate extends Model
{
    protected $fillable = [
        'title', 'slug', 'sector', 'description', 'sections',
        'language', 'is_free', 'downloads_count',
    ];

    protected $casts = [
        'sections' => 'array',
        'is_free' => 'boolean',
    ];
}
