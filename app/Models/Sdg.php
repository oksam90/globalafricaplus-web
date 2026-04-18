<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sdg extends Model
{
    protected $fillable = ['number', 'name', 'color', 'icon'];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_sdg')->withTimestamps();
    }
}
