<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'sub_category_id',
        'title', 'slug', 'summary', 'description',
        'country', 'city', 'amount_needed', 'amount_raised', 'currency',
        'payout_phone', 'payout_provider', 'payout_country',
        'stage', 'status', 'jobs_target', 'views_count', 'followers_count',
        'cover_image', 'gallery', 'website', 'video_url', 'pitch_deck_url',
        'tags', 'deadline', 'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'gallery' => 'array',
        'deadline' => 'date',
        'published_at' => 'datetime',
        'amount_needed' => 'decimal:2',
        'amount_raised' => 'decimal:2',
    ];

    protected $appends = ['progress_percent'];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->slug)) {
                $project->slug = self::generateUniqueSlug($project->title);
            }
        });

        static::updating(function (Project $project) {
            // Auto-set published_at on first publish
            if ($project->isDirty('status') && $project->status === 'published' && !$project->published_at) {
                $project->published_at = now();
            }
        });
    }

    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'projet';
        $slug = $base;
        $i = 1;
        $query = static::where('slug', $slug);
        if ($ignoreId) $query->where('id', '!=', $ignoreId);

        while ($query->exists()) {
            $slug = $base.'-'.$i++;
            $query = static::where('slug', $slug);
            if ($ignoreId) $query->where('id', '!=', $ignoreId);
        }
        return $slug;
    }

    // ---------- Relations ----------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function escrowMilestones(): HasMany
    {
        return $this->hasMany(EscrowMilestone::class)->orderBy('position');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function sdgs(): BelongsToMany
    {
        return $this->belongsToMany(Sdg::class, 'project_sdg')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_followers')->withTimestamps();
    }

    // ---------- Scopes ----------

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeForCategory(Builder $q, ?string $slug): Builder
    {
        if (!$slug) return $q;
        return $q->whereHas('category', fn ($c) => $c->where('slug', $slug));
    }

    public function scopeForSubCategory(Builder $q, ?string $slug): Builder
    {
        if (!$slug) return $q;
        return $q->whereHas('subCategory', fn ($c) => $c->where('slug', $slug));
    }

    public function scopeForSdg(Builder $q, int|string|null $number): Builder
    {
        if ($number === null || $number === '') return $q;
        return $q->whereHas('sdgs', fn ($s) => $s->where('number', (int) $number));
    }

    public function scopeSort(Builder $q, ?string $sort): Builder
    {
        return match ($sort) {
            'popular'     => $q->orderByDesc('views_count')->orderByDesc('followers_count'),
            'trending'    => $q->orderByDesc('followers_count')->orderByDesc('published_at'),
            'ending'      => $q->whereNotNull('deadline')->orderBy('deadline'),
            'progress'    => $q->orderByRaw('(CASE WHEN amount_needed > 0 THEN amount_raised / amount_needed ELSE 0 END) DESC'),
            'jobs'        => $q->orderByDesc('jobs_target'),
            default       => $q->latest('published_at')->latest('id'),
        };
    }

    // ---------- Accessors ----------

    public function getProgressPercentAttribute(): float
    {
        if ((float) $this->amount_needed <= 0) {
            return 0;
        }
        return round(min(100, ($this->amount_raised / $this->amount_needed) * 100), 1);
    }
}
