<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\ImportSkip;
use App\Import\Mapper;
use App\Models\PurchaseInvoice;
use App\Models\PurchasePayment;

class PurchasePaymentMapper extends Mapper
{
    public function entity(): string
    {
        return 'purchasepayment';
    }

    public function model(): string
    {
        return PurchasePayment::class;
    }

    public function parentConflict(array $row, Context $ctx): ?string
    {
        return $this->parentState(PurchaseInvoice::class, $ctx->id(PurchaseInvoice::class, $row['purchaseinvoice'] ?? null));
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'purchase_invoice_id' => $ctx->id(PurchaseInvoice::class, $row['purchaseinvoice'] ?? null) ?? throw new ImportSkip('Inkoopfactuur bij deze betaling ontbreekt.'),
            'date' => Gripp::day($row['date'] ?? null) ?? Gripp::day($row['createdon'] ?? null) ?? now()->toDateString(),
            'amount' => Gripp::money($row['amount'] ?? 0),
            'notes' => Gripp::str($row['notes'] ?? null),
        ];
    }

    public function checks(Context $ctx): array
    {
        $invoices = PurchaseInvoice::withTrashed()->whereNotNull('gripp_id')->withSum('payments as paid', 'amount')->get();
        $ok = $invoices->filter(fn ($i) => abs(round((float) $i->total_incl - (float) $i->paid, 2) - (float) $i->total_open) < 0.01)->count();

        return array_merge(parent::checks($ctx), [[
            'label' => 'purchasepayment: inkoopfacturen waarvan totaal − betalingen = openstaand',
            'source' => $invoices->count(), 'local' => $ok, 'ok' => $ok === $invoices->count(),
        ]]);
    }
}
