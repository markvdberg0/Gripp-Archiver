<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use Auditable, FromGripp, HasAttachments;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'deadline' => 'date', 'completed' => 'boolean', 'completed_at' => 'datetime', 'estimated_hours' => 'decimal:2'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TaskType::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TaskPhase::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function calendarItems(): HasMany
    {
        return $this->hasMany(CalendarItem::class)->orderBy('date');
    }

    public function isOverdue(): bool
    {
        return ! $this->completed && $this->deadline && $this->deadline->isBefore(today());
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('completed', false);
    }

    public function auditLabel(): string
    {
        return 'Taak '.($this->number ?? $this->id);
    }
}
