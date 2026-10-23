<?php

namespace App\Models\Concerns;

use App\Models\VatRate;
use App\Support\Bedragen;

/**
 * Documenten met regels (offertes, opdrachten, facturen, inkoop). Het model definieert lines().
 * Totalen worden opgeslagen in total_excl, total_vat en total_incl.
 */
trait HasLines
{
    /** Herbereken de totalen uit de regels en sla op. */
    public function recalculate(): void
    {
        $t = $this->computeTotals();
        $this->forceFill(['total_excl' => $t['excl'], 'total_vat' => $t['vat'], 'total_incl' => $t['incl']])->save();
    }

    public function computeTotals(): array
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        return Bedragen::totals($lines, VatRate::percentages());
    }

    /** Btw-specificatie voor weergave: [naam, grondslag, btw]. */
    public function vatBreakdown(): array
    {
        $names = VatRate::query()->pluck('name', 'id');
        $out = [];
        foreach ($this->computeTotals()['per_rate'] as $rateId => $r) {
            $out[] = ['name' => $names[$rateId] ?? 'Geen btw', 'base' => $r['base'], 'vat' => $r['vat'], 'percentage' => $r['percentage']];
        }

        return $out;
    }
}
