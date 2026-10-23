<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Verkoopfacturen. Totalen, betaald en openstaand worden overgenomen zoals Gripp ze heeft; de controle
 * vergelijkt ze met de herberekening uit regels en betalingen. Het Gripp-PDF komt uit archive/invoices.
 */
class InvoiceMapper extends Mapper
{
    public function entity(): string
    {
        return 'invoice';
    }

    public function model(): string
    {
        return Invoice::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $incl = Gripp::money($row['totalinclvat'] ?? 0);
        $excl = Gripp::money($row['totalincldiscountexclvat'] ?? $row['totalexclvat'] ?? 0);
        $concept = Gripp::is($row['status'] ?? null, 'concept');
        $fase = mb_strtolower((string) Gripp::label($row['fase'] ?? null));

        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : null,
            'company_id' => $ctx->id(Company::class, $row['company'] ?? null),
            'contact_id' => $ctx->id(Contact::class, $row['contact'] ?? null),
            'subject' => Gripp::str($row['subject'] ?? $row['searchname'] ?? null),
            'description' => Gripp::text($row['description'] ?? null),
            'client_reference' => Gripp::str($row['clientreference'] ?? null),
            'date' => Gripp::day($row['date'] ?? null),
            'due_date' => Gripp::day($row['expirydate'] ?? null),
            'status' => $concept ? 'concept' : 'verzonden',
            'reminder_level' => match (true) {
                str_contains($fase, 'aanmaning') => 3,
                str_contains($fase, '2e') || str_contains($fase, 'tweede') => 2,
                str_contains($fase, 'herinnering') => 1,
                default => 0,
            },
            'payment_method' => CompanyMapper::paymentMethod($row['paymentmethod'] ?? null),
            'extra_fields' => ProductMapper::customFields($row) ?: null,
            'total_excl' => $excl,
            'total_incl' => $incl,
            'total_vat' => number_format((float) $incl - (float) $excl, 2, '.', ''),
            'total_paid' => Gripp::money($row['totalpayed'] ?? 0),
            'total_open' => Gripp::money($row['totalopeninclvat'] ?? 0),
        ];
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->syncTags($model, $row, $ctx);
        $this->attachFiles($model, $row, $ctx, ['files', 'extrapdf1', 'extrapdf2']);
        $this->attachDocument($model, $ctx->snapshot->invoicePdf($row['id']), 'Factuur '.($model->number ?? 'concept').' (Gripp).pdf', $ctx);
    }

    public function hashExtra(array $row, Context $ctx): string
    {
        return (string) ($ctx->snapshot->invoicePdf($row['id'])['sha256'] ?? '');
    }

    public function checks(Context $ctx): array
    {
        $rows = $ctx->snapshot->rows('invoice');
        $sum = fn (string $f) => array_sum(array_map(fn ($r) => (float) ($r[$f] ?? 0), $rows));
        $local = Invoice::withTrashed()->whereNotNull('gripp_id');
        $fmt = fn ($n) => number_format($n, 2, ',', '.');
        $out = parent::checks($ctx);
        foreach (['totalinclvat' => ['total_incl', 'som totaal incl. btw'], 'totalopeninclvat' => ['total_open', 'som openstaand incl. btw']] as $src => [$col, $label]) {
            $s = $sum($src);
            $l = (float) (clone $local)->sum($col);
            $out[] = ['label' => "invoice: {$label}", 'source' => $fmt($s), 'local' => $fmt($l), 'ok' => abs($s - $l) < 0.01];
        }

        return $out;
    }
}
