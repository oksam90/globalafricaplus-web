<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormalizationStep extends Model
{
    protected $fillable = [
        'country', 'order', 'title', 'slug', 'description',
        'institution', 'required_documents', 'estimated_duration',
        'estimated_cost', 'link', 'tips',
    ];

    protected $casts = [
        'required_documents' => 'array',
    ];

    public function progress(): HasMany
    {
        return $this->hasMany(FormalizationProgress::class, 'step_id');
    }

    /**
     * Scope steps for a given country, ordered.
     */
    public function scopeForCountry($q, string $country)
    {
        return $q->where('country', $country)->orderBy('order');
    }
}
