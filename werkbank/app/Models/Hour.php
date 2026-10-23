<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Geschreven uren. Status: concept (eigen invoer, nog te wijzigen) → definitief (ingediend) → gefiatteerd
 * (goedgekeurd door iemand met het recht uren.fiatteren). Gefiatteerde uren zijn niet meer te wijzigen.
 */
class Hour extends Model
{
    use Auditable, FromGripp;

    public const STATUS = ['concept' => 'Concept', 'definitief' => 'Definitief', 'gefiatteerd' => 'Gefiatteerd'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'hours' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function projectLine(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return $this->status === 'concept';
    }

    public function auditLabel(): string
    {
        return 'Uren '.$this->date?->format('d-m-Y').' '.$this->hours;
    }
}
