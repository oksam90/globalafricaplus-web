<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mentorship extends Model
{
    protected $fillable = [
        'mentor_id', 'mentee_id', 'skill_id',
        'topic', 'message', 'goals', 'duration_weeks',
        'status', 'accepted_at', 'completed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ---------- Relations ----------

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MentorshipSession::class)->orderBy('scheduled_at');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MentorReview::class);
    }

    // ---------- Scopes ----------

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', ['requested', 'accepted']);
    }

    public function scopeForMentor(Builder $q, int $userId): Builder
    {
        return $q->where('mentor_id', $userId);
    }

    public function scopeForMentee(Builder $q, int $userId): Builder
    {
        return $q->where('mentee_id', $userId);
    }
}
