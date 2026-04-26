<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installment extends Model
{
    protected $fillable = [
        'installment_plan_id', 'transaction_id',
        'number', 'amount', 'currency',
        'status', 'due_date', 'paid_at',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'due_date' => 'date',
        'paid_at'  => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
