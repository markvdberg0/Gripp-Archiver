<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLines;
use App\Models\Concerns\HasTags;
use App\Support\Nummering;
use App\Support\Zoek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Verkoopfactuur. Een concept is vrij te wijzigen en heeft geen nummer. Bij definitief maken krijgt de
 * factuur een doorlopend nummer, datum en vervaldatum en is hij niet meer te wijzigen; corrigeren gaat
 * met een creditfactuur. Openstaand = totaal incl. btw − betalingen.
 */
class Invoice extends Model
{
    use Auditable, FromGripp, HasAttachments, HasFactory, HasLines, HasTags, SoftDeletes;

    public const REMINDERS = [0 => 'Factuur', 1 => 'Herinnering', 2 => 'Tweede herinnering', 3 => 'Aanmaning'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'due_date' => 'date', 'sent_at' => 'datetime', 'last_reminder_at' => 'datetime', 'extra_fields' => 'array',
            'total_excl' => 'decimal:2', 'total_vat' => 'decimal:2', 'total_incl' => 'decimal:2', 'total_paid' => 'decimal:2', 'total_open' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('date')->orderBy('id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creditOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credit_of_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(self::class, 'credit_of_id');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEntry::class)->latest('occurred_at');
    }

    public function recalculate(): void
    {
        $t = $this->computeTotals();
        $this->forceFill(['total_excl' => $t['excl'], 'total_vat' => $t['vat'], 'total_incl' => $t['incl']]);
        $this->refreshPaid();
    }

    /** Betaald en openstaand bijwerken uit de betalingen, en opslaan. */
    public function refreshPaid(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);
        $this->forceFill(['total_paid' => $paid, 'total_open' => round((float) $this->total_incl - $paid, 2)])->save();
    }

    /** Concept → definitief: nummer, datum, vervaldatum. Moet binnen een transactie. */
    public function finalize(): void
    {
        $date = $this->date ?? today();
        $term = $this->company?->payment_term_days ?? (int) Setting::get('factuur.betaaltermijn', '30');
        $this->forceFill([
            'number' => $this->number ?? Nummering::next('factuur', 'invoices'),
            'date' => $date,
            'due_date' => $this->due_date ?? $date->copy()->addDays($term),
            'status' => 'verzonden',
            'sent_at' => now(),
        ])->save();
    }

    public function isEditable(): bool
    {
        return $this->status === 'concept';
    }

    public function isPaid(): bool
    {
        return $this->status !== 'concept' && abs((float) $this->total_open) < 0.005;
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'concept' && (float) $this->total_open > 0.005 && $this->due_date && $this->due_date->isBefore(today());
    }

    public function isCredit(): bool
    {
        return (float) $this->total_incl < 0 || $this->credit_of_id !== null;
    }

    public function state(): string
    {
        return match (true) {
            $this->status === 'concept' => 'concept',
            $this->isPaid() => 'betaald',
            $this->isOverdue() => 'vervallen',
            default => 'open',
        };
    }

    public function stateLabel(): string
    {
        $label = ['concept' => 'Concept', 'betaald' => 'Betaald', 'vervallen' => 'Vervallen', 'open' => 'Openstaand'][$this->state()];
        if ($this->reminder_level > 0 && ! $this->isPaid()) {
            $label .= ' · '.mb_strtolower(self::REMINDERS[$this->reminder_level] ?? 'herinnering');
        }

        return $label;
    }

    public function title(): string
    {
        return ($this->isCredit() ? 'Creditfactuur ' : 'Factuur ').($this->number ?? '(concept)');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return Zoek::in($q, $term, ['subject', 'number', 'client_reference']);
    }

    public function scopeState(Builder $q, ?string $state): Builder
    {
        return match ($state) {
            'concept' => $q->where('status', 'concept'),
            'open' => $q->where('status', '!=', 'concept')->where('total_open', '>', 0.005),
            'vervallen' => $q->where('status', '!=', 'concept')->where('total_open', '>', 0.005)->whereDate('due_date', '<', today()),
            'betaald' => $q->where('status', '!=', 'concept')->whereBetween('total_open', [-0.005, 0.005]),
            'credit' => $q->where('total_incl', '<', 0),
            default => $q,
        };
    }

    public function auditLabel(): string
    {
        return $this->title();
    }
}
