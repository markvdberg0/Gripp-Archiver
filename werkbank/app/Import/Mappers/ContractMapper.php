<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Model;

/**
 * Contracten. Gripp levert geen "volgende factuurdatum"; die blijft leeg zodat er na de import niet
 * automatisch facturen ontstaan. Stel hem per contract in als Werkbank het factureren overneemt.
 */
class ContractMapper extends Mapper
{
    public function entity(): string
    {
        return 'contract';
    }

    public function model(): string
    {
        return Contract::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $freq = mb_strtolower((string) Gripp::label($row['frequency'] ?? null));

        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : 700000000 + (int) $row['id'],
            'name' => Gripp::str($row['name'] ?? $row['subject'] ?? $row['searchname'] ?? null) ?? 'Contract '.$row['id'],
            'company_id' => $ctx->id(Company::class, $row['company'] ?? null),
            'contact_id' => $ctx->id(Contact::class, $row['contact'] ?? null),
            'status' => Gripp::is($row['status'] ?? null, 'actief', 'active', 'aflopend', 'ending') ? 'actief' : 'beeindigd',
            'frequency' => match (true) {
                str_contains($freq, '4 weken') => '4weken',
                str_contains($freq, 'week') => 'week',
                str_contains($freq, '12 maanden') || str_contains($freq, 'jaar') => 'jaar',
                str_contains($freq, '6 maanden') || str_contains($freq, 'half') => 'halfjaar',
                str_contains($freq, '3 maanden') || str_contains($freq, 'kwartaal') => 'kwartaal',
                default => 'maand',
            },
            'start_date' => Gripp::day($row['date'] ?? null) ?? Gripp::day($row['date_original'] ?? null) ?? Gripp::day($row['createdon'] ?? null) ?? now()->toDateString(),
            'end_date' => Gripp::day($row['expirydate'] ?? null),
            'auto_renew' => Gripp::bool($row['extendautomatically'] ?? false),
            'invoice_subject' => Gripp::str($row['subject_invoice'] ?? null),
            'client_reference' => Gripp::str($row['clientreference'] ?? null),
            'description' => Gripp::text($row['description'] ?? null),
        ];
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->attachFiles($model, $row, $ctx, ['files', 'extrapdf1', 'extrapdf2']);
    }
}
