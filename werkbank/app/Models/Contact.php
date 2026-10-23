<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasTags;
use App\Support\Zoek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use Auditable, FromGripp, HasFactory, HasTags, SoftDeletes;

    public const AANHEF = ['dhr' => 'De heer', 'mevr' => 'Mevrouw', 'dhr_mevr' => 'De heer of mevrouw'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'show_on_company' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name ?: $this->initials, $this->infix, $this->last_name])));
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return Zoek::in($q, $term, ['first_name', 'last_name', 'email']);
    }
}
