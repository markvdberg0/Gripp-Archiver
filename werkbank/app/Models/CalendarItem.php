<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Agenda-item in de planning (eventueel bij een taak). */
class CalendarItem extends Model
{
    use Auditable, FromGripp;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'hours' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function auditLabel(): string
    {
        return 'Agenda-item '.($this->subject ?: $this->id);
    }
}
