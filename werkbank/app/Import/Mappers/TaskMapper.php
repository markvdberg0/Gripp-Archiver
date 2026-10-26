<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Offer;
use App\Models\Project;
use App\Models\ProjectLine;
use App\Models\Task;
use App\Models\TaskPhase;
use App\Models\TaskType;
use Illuminate\Database\Eloquent\Model;

class TaskMapper extends Mapper
{
    public function entity(): string
    {
        return 'task';
    }

    public function model(): string
    {
        return Task::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $base = $row['offerprojectbase'] ?? null;
        $time = Gripp::date($row['deadlinetime'] ?? null);
        $completed = Gripp::bool($row['isafgerond'] ?? false);

        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : null,
            'title' => Gripp::str($row['content'] ?? $row['searchname'] ?? null, 500) ?? 'Taak '.$row['id'],
            'description' => Gripp::text($row['description'] ?? null),
            'type_id' => $ctx->id(TaskType::class, $row['type'] ?? null),
            'phase_id' => $ctx->id(TaskPhase::class, $row['phase'] ?? null),
            'company_id' => $ctx->id(Company::class, $row['company'] ?? null),
            'project_id' => $ctx->id(Project::class, $base),
            'offer_id' => (is_array($base) && ($base['discr'] ?? '') === 'offerte') || ! $ctx->id(Project::class, $base) ? $ctx->id(Offer::class, $base) : null,
            'project_line_id' => $ctx->id(ProjectLine::class, $row['offerprojectline'] ?? null),
            'employee_id' => $ctx->id(Employee::class, $row['preferredemployee'] ?? null),
            'start_date' => Gripp::day($row['startdate'] ?? null),
            'deadline' => Gripp::day($row['deadlinedate'] ?? null),
            'deadline_time' => $time?->format('H:i:s'),
            'estimated_hours' => is_numeric($row['estimatedhours'] ?? null) ? $row['estimatedhours'] : null,
            'completed' => $completed,
            'completed_at' => $completed ? (Gripp::date($row['updatedon'] ?? null) ?? now()) : null,
        ];
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->attachFiles($model, $row, $ctx);
    }
}
