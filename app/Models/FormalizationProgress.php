<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormalizationProgress extends Model
{
    protected $table = 'formalization_progress';

    protected $fillable = [
        'user_id', 'step_id', 'status', 'notes', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FormalizationStep::class, 'step_id');
    }
}
