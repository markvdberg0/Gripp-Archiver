<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    use Auditable, FromGripp;

    /** soort => [label, telt mee voor het saldo] */
    public const TYPES = [
        'vakantie' => ['Vakantie / vrije dag', true],
        'bijzonder' => ['Bijzonder verlof', false],
        'onbetaald' => ['Onbetaald verlof', false],
        'overig' => ['Overig', false],
    ];

    public const STATUSSEN = [
        'aangevraagd' => 'Aangevraagd', 'goedgekeurd' => 'Goedgekeurd', 'afgewezen' => 'Afgewezen',
        'ingetrokken' => 'Ingetrokken', 'onbekend' => 'Onbekend (uit Gripp)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LeaveRequestLine::class)->orderBy('date');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSSEN[$this->status] ?? $this->status;
    }

    public function hours(): float
    {
        return (float) $this->lines->sum('hours');
    }

    public function period(): string
    {
        $first = $this->lines->first()?->date;
        $last = $this->lines->last()?->date;
        if (! $first) {
            return '—';
        }

        return $first->equalTo($last) ? $first->format('d-m-Y') : $first->format('d-m-Y').' t/m '.$last->format('d-m-Y');
    }

    /** Intrekken kan zolang de eerste dag nog niet voorbij is. */
    public function canWithdraw(): bool
    {
        return in_array($this->status, ['aangevraagd', 'goedgekeurd'], true) && ($this->lines->first()?->date?->isFuture() || $this->lines->first()?->date?->isToday());
    }
}
