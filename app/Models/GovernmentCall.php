<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GovernmentCall extends Model
{
    protected $table = 'government_calls';

    protected $fillable = [
        'user_id', 'title', 'slug', 'description', 'country', 'geographic_zone',
        'sector', 'eligibility_criteria', 'required_documents', 'evaluation_criteria',
        'budget', 'currency', 'opens_at', 'closes_at', 'status',
        'views_count', 'applications_count', 'published_at',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'opens_at' => 'date',
        'closes_at' => 'date',
        'published_at' => 'datetime',
    ];

    // ─── Relationships ───

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CallApplication::class, 'call_id');
    }

    // ─── Scopes ───

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePublic($query)
    {
        return $query->whereIn('status', ['open', 'closed', 'awarded']);
    }

    // ─── Helpers ───

    public function isOpen(): bool
    {
        return $this->status === 'open'
            && (!$this->closes_at || $this->closes_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->closes_at && $this->closes_at->isPast();
    }

    public static function generateUniqueSlug(string $title, ?int $exceptId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $counter = 1;
        while (
            static::where('slug', $slug)
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $original . '-' . $counter++;
        }
        return $slug;
    }
}
