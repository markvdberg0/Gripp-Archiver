<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectPhase;
use Illuminate\Database\Eloquent\Model;

/** Opdrachten (Gripp: project). Totalen zoals Gripp ze heeft; de controle herberekent ze uit de regels. */
class ProjectMapper extends Mapper
{
    public function entity(): string
    {
        return 'project';
    }

    public function model(): string
    {
        return Project::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $excl = Gripp::money($row['totalexclvat'] ?? 0);
        $incl = Gripp::money($row['totalinclvat'] ?? 0);

        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : 800000000 + (int) $row['id'],
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null) ?? 'Opdracht '.$row['id'],
            'company_id' => $ctx->id(Company::class, $row['company'] ?? null),
            'contact_id' => $ctx->id(Contact::class, $row['contact'] ?? null),
            'phase_id' => $ctx->id(ProjectPhase::class, $row['phase'] ?? null),
            'account_manager_id' => $ctx->id(Employee::class, $row['accountmanager'] ?? null),
            'start_date' => Gripp::day($row['startdate'] ?? null),
            'end_date' => Gripp::day($row['enddate'] ?? null),
            'deadline' => Gripp::day($row['deadline'] ?? null),
            'delivery_date' => Gripp::day($row['deliverydate'] ?? null),
            'client_reference' => Gripp::str($row['clientreference'] ?? null),
            'work_address' => Gripp::str($row['workdeliveraddress'] ?? null),
            'description' => Gripp::text($row['description'] ?? null),
            'color' => Gripp::str($row['color'] ?? null, 20),
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
        if (! $ctx->dryRun) {
            $model->employees()->sync($ctx->ids(Employee::class, $row['employees'] ?? []));
        }
        $this->syncTags($model, $row, $ctx);
        $this->attachFiles($model, $row, $ctx, ['files', 'extrapdf1', 'extrapdf2']);
        $this->attachDocument($model, $ctx->snapshot->document('project', $row['id']), "Opdrachtbevestiging {$model->number} (Gripp).pdf", $ctx);
    }

    public function hashExtra(array $row, Context $ctx): string
    {
        return (string) ($ctx->snapshot->document('project', $row['id'])['sha256'] ?? '');
    }

    public function checks(Context $ctx): array
    {
        $source = array_sum(array_map(fn ($r) => (float) ($r['totalexclvat'] ?? 0), $ctx->snapshot->rows('project')));
        $local = (float) Project::withTrashed()->whereNotNull('gripp_id')->sum('total_excl');

        return array_merge(parent::checks($ctx), [[
            'label' => 'project: som totaal excl. btw',
            'source' => number_format($source, 2, ',', '.'), 'local' => number_format($local, 2, ',', '.'), 'ok' => abs($source - $local) < 0.01,
        ]]);
    }
}
