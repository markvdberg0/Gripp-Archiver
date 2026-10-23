<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\ImportSkip;
use App\Import\Mapper;
use App\Models\Invoice;
use App\Models\Payment;

class PaymentMapper extends Mapper
{
    public function entity(): string
    {
        return 'payment';
    }

    public function model(): string
    {
        return Payment::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'invoice_id' => $ctx->id(Invoice::class, $row['invoice'] ?? null) ?? throw new ImportSkip('Factuur bij deze betaling ontbreekt.'),
            'date' => Gripp::day($row['date'] ?? null) ?? Gripp::day($row['createdon'] ?? null) ?? now()->toDateString(),
            'amount' => Gripp::money($row['amount'] ?? 0),
            'notes' => Gripp::str($row['notes'] ?? null),
        ];
    }

    /** Openstaand opnieuw berekend uit de geïmporteerde betalingen, tegenover Gripp. */
    public function checks(Context $ctx): array
    {
        $invoices = Invoice::withTrashed()->whereNotNull('gripp_id')->withSum('payments as paid', 'amount')->get();
        $ok = $invoices->filter(fn ($i) => abs(round((float) $i->total_incl - (float) $i->paid, 2) - (float) $i->total_open) < 0.01)->count();

        return array_merge(parent::checks($ctx), [[
            'label' => 'payment: facturen waarvan totaal − betalingen = openstaand',
            'source' => $invoices->count(), 'local' => $ok, 'ok' => $ok === $invoices->count(),
        ]]);
    }
}
