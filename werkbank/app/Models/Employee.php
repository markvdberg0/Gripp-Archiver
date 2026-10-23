<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use Auditable, FromGripp, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'employed_since' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->infix, $this->last_name])));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public static function options(): array
    {
        return static::query()->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->name.($e->active ? '' : ' (inactief)')])->all();
    }
}
