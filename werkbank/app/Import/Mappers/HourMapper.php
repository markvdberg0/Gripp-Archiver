<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\ImportSkip;
use App\Import\Mapper;
use App\Models\Employee;
use App\Models\Hour;
use App\Models\Offer;
use App\Models\Project;
use App\Models\ProjectLine;
use App\Models\Task;

class HourMapper extends Mapper
{
    public function entity(): string
    {
        return 'hour';
    }

    public function model(): string
    {
        return Hour::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $employee = $ctx->id(Employee::class, $row['employee'] ?? null) ?? throw new ImportSkip('Uren zonder (bekende) medewerker.');
        $base = $row['offerprojectbase'] ?? null;
        $projectId = $ctx->id(Project::class, $base);

        return [
            'date' => Gripp::day($row['date'] ?? null) ?? throw new ImportSkip('Uren zonder datum.'),
            'employee_id' => $employee,
            'project_id' => $projectId,
            'offer_id' => $projectId ? null : $ctx->id(Offer::class, $base),
            'project_line_id' => $ctx->id(ProjectLine::class, $row['offerprojectline'] ?? null),
            'task_id' => $ctx->id(Task::class, $row['task'] ?? null),
            'hours' => is_numeric($row['amount'] ?? null) ? $row['amount'] : 0,
            'description' => Gripp::text($row['description'] ?? null),
            'status' => match (true) {
                Gripp::is($row['status'] ?? null, 'gefiatteerd', 'authorized', 'goedgekeurd') => 'gefiatteerd',
                Gripp::is($row['status'] ?? null, 'definitief', 'definitive') => 'definitief',
                default => 'concept',
            },
            'approved_by' => $ctx->id(Employee::class, $row['authorizedby'] ?? null),
            'approved_at' => Gripp::date($row['authorizedon'] ?? null),
        ];
    }

    public function checks(Context $ctx): array
    {
        $source = array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $ctx->snapshot->rows('hour')));
        $local = (float) Hour::whereNotNull('gripp_id')->sum('hours');

        return array_merge(parent::checks($ctx), [[
            'label' => 'hour: totaal aantal uren', 'source' => number_format($source, 2, ',', '.'), 'local' => number_format($local, 2, ',', '.'), 'ok' => abs($source - $local) < 0.01,
        ]]);
    }
}
