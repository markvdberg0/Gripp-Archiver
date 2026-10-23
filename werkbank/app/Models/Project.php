<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLines;
use App\Models\Concerns\HasTags;
use App\Support\Zoek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Opdracht (in Gripp: project). */
class Project extends Model
{
    use Auditable, FromGripp, HasAttachments, HasFactory, HasLines, HasTags, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'end_date' => 'date', 'deadline' => 'date', 'delivery_date' => 'date', 'archived_at' => 'datetime',
            'archived' => 'boolean', 'extra_fields' => 'array',
            'total_excl' => 'decimal:2', 'total_vat' => 'decimal:2', 'total_incl' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProjectLine::class)->orderBy('sort')->orderBy('id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class);
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'account_manager_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(Hour::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEntry::class)->latest('occurred_at');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return Zoek::in($q, $term, ['name', 'number', 'client_reference']);
    }

    public function auditLabel(): string
    {
        return "Opdracht {$this->number}";
    }

    public static function options(): array
    {
        return static::query()->where('archived', false)->orderByDesc('number')->get(['id', 'number', 'name'])
            ->mapWithKeys(fn ($p) => [$p->id => $p->number.' · '.$p->name])->all();
    }
}
