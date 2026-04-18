<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EconomicZone extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'country', 'region', 'description',
        'incentives', 'sectors', 'area_hectares', 'status', 'website', 'contact_email',
    ];

    protected $casts = [
        'incentives' => 'array',
        'sectors' => 'array',
        'area_hectares' => 'decimal:2',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function generateUniqueSlug(string $name, ?int $exceptId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;
        while (
            static::where('slug', $slug)
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $original . '-' . $counter++;
        }
        return $slug;
    }
}
