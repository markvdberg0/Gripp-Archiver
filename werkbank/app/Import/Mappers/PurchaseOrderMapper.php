<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderMapper extends Mapper
{
    public function entity(): string
    {
        return 'purchaseorder';
    }

    public function model(): string
    {
        return PurchaseOrder::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $excl = Gripp::money($row['totalincldiscountexclvat'] ?? $row['totalexclvat'] ?? 0);

        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : 600000000 + (int) $row['id'],
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null) ?? 'Inkoopopdracht '.$row['id'],
            // In Gripp is "company" de leverancier; "supplier" verwijst soms naar iets dat geen relatie is.
            'supplier_id' => $ctx->id(Company::class, $row['company'] ?? null) ?? $ctx->id(Company::class, $row['supplier'] ?? null),
            'contact_id' => $ctx->id(Contact::class, $row['contact'] ?? null),
            'employee_id' => $ctx->id(Employee::class, $row['accountmanager'] ?? null),
            'status' => match (true) {
                Gripp::is($row['purchaseorderstatus'] ?? null, 'afgerond') || Gripp::is($row['phase'] ?? null, 'afgerond') => 'afgerond',
                Gripp::is($row['phase'] ?? null, 'bevestigd') => 'bevestigd',
                Gripp::is($row['purchaseorderstatus'] ?? null, 'concept') => 'concept',
                default => 'verzonden',
            },
            'date' => Gripp::day($row['date'] ?? null) ?? Gripp::day($row['createdon'] ?? null) ?? now()->toDateString(),
            'delivery_date' => Gripp::day($row['deadline'] ?? null),
            'reference' => Gripp::str($row['clientreference'] ?? null),
            'delivery_address' => Gripp::str($row['workdeliveraddress'] ?? null),
            'description' => Gripp::text($row['description'] ?? null),
            'total_excl' => $excl,
            'archived' => Gripp::bool($row['archived'] ?? false),
        ];
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->syncTags($model, $row, $ctx);
        $this->attachFiles($model, $row, $ctx, ['files', 'extrapdf1', 'extrapdf2']);
        $this->attachDocument($model, $ctx->snapshot->document('purchaseorder', $row['id']), "Inkoopopdracht {$model->number} (Gripp).pdf", $ctx);
    }

    public function hashExtra(array $row, Context $ctx): string
    {
        return (string) ($ctx->snapshot->document('purchaseorder', $row['id'])['sha256'] ?? '');
    }

    public function checks(Context $ctx): array
    {
        $source = array_sum(array_map(fn ($r) => (float) ($r['totalincldiscountexclvat'] ?? 0), $ctx->snapshot->rows('purchaseorder')));
        $local = (float) PurchaseOrder::withTrashed()->whereNotNull('gripp_id')->sum('total_excl');

        return array_merge(parent::checks($ctx), [[
            'label' => 'purchaseorder: som totaal excl. btw', 'source' => number_format($source, 2, ',', '.'), 'local' => number_format($local, 2, ',', '.'), 'ok' => abs($source - $local) < 0.01,
        ]]);
    }
}
