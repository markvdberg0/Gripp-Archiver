<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLines;
use App\Models\Concerns\HasTags;
use App\Support\Format;
use App\Support\Zoek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inkoopfactuur. Fasen: concept (ingevoerd) → controle (ter goedkeuring) → akkoord (goedgekeurd)
 * → verwerkt (betaalbaar/geboekt). "On hold" kan in elke fase vóór verwerkt, met een reden.
 * Boven de vier-ogendrempel (instelling) mag niet dezelfde gebruiker invoeren en goedkeuren.
 */
class PurchaseInvoice extends Model
{
    use Auditable, FromGripp, HasAttachments, HasFactory, HasLines, HasTags, SoftDeletes;

    public const PHASES = ['concept' => 'Concept', 'controle' => 'Controle', 'akkoord' => 'Akkoord', 'verwerkt' => 'Verwerkt'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'due_date' => 'date', 'approved_at' => 'datetime', 'on_hold' => 'boolean', 'inbox' => 'boolean',
            'expected_total' => 'decimal:2', 'total_excl' => 'decimal:2', 'total_vat' => 'decimal:2', 'total_incl' => 'decimal:2',
            'total_paid' => 'decimal:2', 'total_open' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class)->orderBy('sort')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->orderBy('date')->orderBy('id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    public function refreshPaid(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);
        $this->forceFill(['total_paid' => $paid, 'total_open' => round((float) $this->total_incl - $paid, 2)])->save();
    }

    public function isEditable(): bool
    {
        return in_array($this->phase, ['concept', 'controle'], true);
    }

    public function isOverdue(): bool
    {
        return (float) $this->total_open > 0.005 && $this->due_date && $this->due_date->isBefore(today());
    }

    public function stateLabel(): string
    {
        return (self::PHASES[$this->phase] ?? $this->phase).($this->on_hold ? ' · on hold' : '').($this->inbox ? ' · inbox' : '');
    }

    /**
     * Controle vóór goedkeuring. Elke controle: [omschrijving, ok?, blokkerend?].
     *
     * @return list<array{0: string, 1: bool, 2: bool}>
     */
    public function controls(): array
    {
        $out = [];
        $out[] = ['Leverancier gekozen', $this->supplier_id !== null, true];
        $out[] = ['Factuurnummer van de leverancier ingevuld', (string) $this->invoice_number !== '', false];
        $duplicate = $this->invoice_number && self::query()->where('supplier_id', $this->supplier_id)->where('invoice_number', $this->invoice_number)->whereKeyNot($this->id)->exists();
        $out[] = ['Factuurnummer niet eerder geboekt bij deze leverancier', ! $duplicate, true];
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        $out[] = ['Heeft regels', $lines->where('row_type', 'normaal')->isNotEmpty(), true];
        if ($this->expected_total !== null) {
            $out[] = ['Totaal van de regels ('.Format::euro($this->total_incl).') gelijk aan de factuur ('.Format::euro($this->expected_total).')', abs((float) $this->expected_total - (float) $this->total_incl) < 0.01, true];
        }
        $coded = $lines->where('row_type', 'normaal')->every(fn ($l) => $l->purchase_order_line_id || $l->cost_center_id || $l->project_id || $this->purchase_order_id);
        $out[] = ['Elke regel gekoppeld aan een inkoopopdracht, opdracht of kostenplaats', $coded, false];
        $out[] = ['Elke regel heeft een grootboekrekening', $lines->where('row_type', 'normaal')->every(fn ($l) => $l->ledger_id), false];
        if ($this->purchase_order_id && $this->purchaseOrder) {
            $po = (float) $this->purchaseOrder->total_excl;
            $out[] = ['Bedrag binnen de inkoopopdracht ('.Format::euro($po).' excl.)', (float) $this->total_excl <= $po + 0.01, false];
        }

        return $out;
    }

    public function blockingProblems(): array
    {
        return array_values(array_map(fn ($c) => $c[0], array_filter($this->controls(), fn ($c) => ! $c[1] && $c[2])));
    }

    /** Waarom deze gebruiker (niet) mag goedkeuren; null = mag. */
    public function approvalDenial(User $user): ?string
    {
        if (! $user->can('inkoop.goedkeuren')) {
            return 'Je hebt geen recht om inkoopfacturen goed te keuren.';
        }
        $threshold = (float) Setting::get('inkoop.vier_ogen_drempel', '2500');
        // Wie de factuur heeft ingevoerd óf het laatst gewijzigd, mag hem boven de drempel niet zelf goedkeuren.
        if (in_array($user->id, array_filter([$this->created_by, $this->updated_by]), true) && abs((float) $this->total_incl) > $threshold) {
            return 'Boven '.Format::euro($threshold).' moet iemand anders dan de invoerder of de laatste wijziger goedkeuren (vier-ogenprincipe).';
        }

        return null;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return Zoek::in($q, $term, ['invoice_number', 'booking_number', 'subject']);
    }

    public function auditLabel(): string
    {
        return 'Inkoopfactuur '.($this->booking_number ?? '#'.$this->id);
    }
}
