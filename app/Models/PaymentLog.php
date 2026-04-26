<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'gateway', 'event_type', 'direction',
        'payload',
        'ip_address', 'user_agent', 'signature', 'signature_valid',
        'status_code', 'http_method', 'endpoint',
        'gateway_reference', 'correlation_id',
        'created_at',
    ];

    protected $casts = [
        'payload'         => 'array',
        'signature_valid' => 'boolean',
        'created_at'      => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
