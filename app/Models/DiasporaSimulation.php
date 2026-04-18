<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiasporaSimulation extends Model
{
    protected $fillable = [
        'user_id', 'origin_country', 'destination_country',
        'amount', 'currency', 'investment_type', 'duration_months',
        'estimated_return', 'estimated_jobs', 'target_sector',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'estimated_return' => 'decimal:2',
        'estimated_jobs' => 'decimal:1',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
