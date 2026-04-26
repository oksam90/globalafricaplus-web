<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowMilestone extends Model
{
    protected $fillable = [
        'project_id', 'investment_id',
        'position', 'title', 'description',
        'amount', 'currency', 'percentage',
        'status',
        'due_at', 'approved_at', 'released_at',
        'release_transaction_id',
        'evidence', 'admin_notes',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'due_at'       => 'date',
        'approved_at'  => 'datetime',
        'released_at'  => 'datetime',
        'evidence'     => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function releaseTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'release_transaction_id');
    }
}
