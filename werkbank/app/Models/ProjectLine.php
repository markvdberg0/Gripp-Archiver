<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectLine extends DocumentLine
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(Hour::class);
    }
}
