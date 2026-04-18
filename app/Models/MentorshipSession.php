<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipSession extends Model
{
    protected $fillable = [
        'mentorship_id', 'title', 'notes', 'mentor_feedback',
        'scheduled_at', 'duration_minutes', 'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function mentorship(): BelongsTo
    {
        return $this->belongsTo(Mentorship::class);
    }
}
