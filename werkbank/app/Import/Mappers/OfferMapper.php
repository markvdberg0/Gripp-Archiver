<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Offer;
use App\Models\OfferPhase;
use Illuminate\Database\Eloquent\Model;

/**
 * Offertes. Totalen worden overgenomen zoals Gripp ze heeft berekend (historische documenten veranderen
 * niet); de controle na de import vergelijkt ze met een herberekening uit de regels.
 */
class OfferMapper extends Mapper
{
    public function entity(): string
    {
        return 'offer';
    }

    public function model(): string
    {
        return Offer::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $date = Gripp::date($row['date'] ?? null) ?? Gripp::date($row['createdon'] ?? null) ?? now()->toImmutable();
        $days = preg_match('/(\d+)\s*dag/i', (string) Gripp::label($row['validity'] ?? null), $m) ? (int) $m[1] : null;
        $excl = Gripp::money($row['totalexclvat'] ?? 0);
        $incl = Gripp::money($row['totalinclvat'] ?? 0);

        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : null,
            'is_template' => Gripp::bool($row['isbasis'] ?? false),
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null) ?? 'Offerte '.$row['id'],
            'company_id' => $ctx->id(Company::class, $row['company'] ?? null),
            'contact_id' => $ctx->id(Contact::class, $row['contact'] ?? null),
            'phase_id' => $ctx->id(OfferPhase::class, $row['phase'] ?? null),
            'account_manager_id' => $ctx->id(Employee::class, $row['accountmanager'] ?? null),
            'status' => Gripp::is($row['status'] ?? null, 'concept') ? 'concept' : 'verzonden',
            'acceptance' => match (true) {
                Gripp::is($row['acceptancestatus'] ?? null, 'geaccepteerd', 'accepted') => 'geaccepteerd',
                Gripp::is($row['acceptancestatus'] ?? null, 'afgewezen', 'rejected') => 'afgewezen',
                default => null,
            },
            'accepted_at' => Gripp::date($row['acceptedon'] ?? null),
            'date' => $date->toDateString(),
            'valid_until' => $days ? $date->addDays($days)->toDateString() : null,
            'deadline' => Gripp::day($row['deadline'] ?? null),
            'chance' => is_numeric($row['chance'] ?? null) ? max(0, min(100, (int) $row['chance'])) : null,
            'client_reference' => Gripp::str($row['clientreference'] ?? null),
            'description' => Gripp::text($row['description'] ?? null),
            'template' => Gripp::label($row['template'] ?? null),
            'extra_fields' => ProductMapper::customFields($row) ?: null,
            'total_excl' => $excl,
            'total_incl' => $incl,
            'total_vat' => number_format((float) $incl - (float) $excl, 2, '.', ''),
            'archived' => Gripp::bool($row['archived'] ?? false),
            'archived_at' => Gripp::date($row['archivedon'] ?? null),
        ];
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->syncTags($model, $row, $ctx);
        $this->attachFiles($model, $row, $ctx, ['files', 'extrapdf1', 'extrapdf2']);
        $this->attachDocument($model, $ctx->snapshot->document('offer', $row['id']), "Offerte {$model->number} (Gripp).pdf", $ctx);
    }

    public function hashExtra(array $row, Context $ctx): string
    {
        return (string) ($ctx->snapshot->document('offer', $row['id'])['sha256'] ?? '');
    }

    public function checks(Context $ctx): array
    {
        $rows = $ctx->snapshot->rows('offer');
        $source = array_sum(array_map(fn ($r) => (float) ($r['totalexclvat'] ?? 0), $rows));
        $local = (float) Offer::withTrashed()->whereNotNull('gripp_id')->sum('total_excl');

        return array_merge(parent::checks($ctx), [[
            'label' => 'offer: som totaal excl. btw',
            'source' => number_format($source, 2, ',', '.'),
            'local' => number_format($local, 2, ',', '.'),
            'ok' => abs($source - $local) < 0.01,
        ]]);
    }
}
