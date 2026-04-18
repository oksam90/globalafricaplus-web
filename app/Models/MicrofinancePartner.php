<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrofinancePartner extends Model
{
    protected $fillable = [
        'name', 'slug', 'country', 'description', 'type',
        'products', 'min_amount', 'max_amount', 'interest_rate',
        'website', 'contact_email', 'logo', 'is_active',
    ];

    protected $casts = [
        'products' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForCountry($q, string $country)
    {
        return $q->where('country', $country);
    }
}
