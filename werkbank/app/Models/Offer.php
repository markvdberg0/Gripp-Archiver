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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use Auditable, FromGripp, HasAttachments, HasFactory, HasLines, HasTags, SoftDeletes;

    public const STATUS = ['concept' => 'Concept', 'verzonden' => 'Verzonden'];

    public const ACCEPTANCE = ['geaccepteerd' => 'Geaccepteerd', 'afgewezen' => 'Afgewezen'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'valid_until' => 'date', 'deadline' => 'date', 'accepted_at' => 'datetime', 'archived_at' => 'datetime',
            'archived' => 'boolean', 'is_template' => 'boolean', 'extra_fields' => 'array',
            'total_excl' => 'decimal:2', 'total_vat' => 'decimal:2', 'total_incl' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OfferLine::class)->orderBy('sort')->orderBy('id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(OfferPhase::class);
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'account_manager_id');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEntry::class)->latest('occurred_at');
    }

    /** Samengevatte status voor weergave en filters. */
    public function state(): string
    {
        return $this->acceptance ?? $this->status;
    }

    public function stateLabel(): string
    {
        $label = self::ACCEPTANCE[$this->acceptance] ?? self::STATUS[$this->status] ?? $this->status;
        if ($this->status === 'verzonden' && ! $this->acceptance && $this->valid_until && $this->valid_until->isPast()) {
            $label .= ' (verlopen)';
        }

        return $label;
    }

    public function isEditable(): bool
    {
        return $this->acceptance === null && ! $this->archived;
    }

    public function weightedValue(): float
    {
        return round((float) $this->total_excl * (($this->chance ?? 0) / 100), 2);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return Zoek::in($q, $term, ['name', 'number', 'client_reference']);
    }

    public function scopeState(Builder $q, ?string $state): Builder
    {
        return match ($state) {
            'concept' => $q->where('status', 'concept')->whereNull('acceptance'),
            'verzonden' => $q->where('status', 'verzonden')->whereNull('acceptance'),
            'open' => $q->whereNull('acceptance'),
            'geaccepteerd', 'afgewezen' => $q->where('acceptance', $state),
            default => $q,
        };
    }

    public function auditLabel(): string
    {
        return $this->is_template ? "Offertesjabloon {$this->name}" : "Offerte {$this->number}";
    }

    /** Titel voor koppen en lijsten: nummer, of "Sjabloon" zonder nummer. */
    public function title(): string
    {
        return $this->is_template ? 'Sjabloon' : 'Offerte '.($this->number ?? '(concept)');
    }
}
