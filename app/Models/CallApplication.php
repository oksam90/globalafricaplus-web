<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallApplication extends Model
{
    protected $fillable = [
        'call_id', 'user_id', 'project_id', 'motivation', 'proposal',
        'status', 'review_notes', 'score', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(GovernmentCall::class, 'call_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
