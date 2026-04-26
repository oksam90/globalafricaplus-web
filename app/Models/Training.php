<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Training extends Model
{
    protected $fillable = [
        'user_id', 'title', 'slug', 'summary', 'description',
        'cover_image', 'video_preview_url', 'content_url',
        'category', 'level', 'duration_minutes', 'curriculum',
        'price', 'currency', 'is_published', 'purchases_count', 'rating_avg',
    ];

    protected $casts = [
        'curriculum'    => 'array',
        'price'         => 'decimal:2',
        'is_published'  => 'boolean',
        'rating_avg'    => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Training $t) {
            if (empty($t->slug)) {
                $base = Str::slug($t->title) ?: 'formation';
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $t->slug = $slug;
            }
        });
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(TrainingPurchase::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }
}
