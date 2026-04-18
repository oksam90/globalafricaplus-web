<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdBanner extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'description', 'image_url',
        'cta_text', 'cta_url', 'sponsor', 'sponsor_logo',
        'position', 'sort_order', 'is_active',
        'starts_at', 'ends_at', 'impressions', 'clicks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopePosition($q, string $pos)
    {
        return $q->where('position', $pos);
    }
}
