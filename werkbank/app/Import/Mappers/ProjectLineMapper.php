<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\LineMapper;
use App\Models\Project;
use App\Models\ProjectLine;

/** Opdrachtregels: de regels in offerprojectline die bij een opdracht horen. */
class ProjectLineMapper extends LineMapper
{
    public function entity(): string
    {
        return 'offerprojectline';
    }

    public function key(): string
    {
        return 'projectline';
    }

    public function model(): string
    {
        return ProjectLine::class;
    }

    public function accepts(array $row, Context $ctx): bool
    {
        $base = $row['offerprojectbase'] ?? null;
        $discr = is_array($base) ? ($base['discr'] ?? null) : null;

        return $discr !== null ? $discr === 'opdracht' : $ctx->id(Project::class, $base) !== null;
    }

    protected function parentClass(): string
    {
        return Project::class;
    }

    protected function parentColumn(): string
    {
        return 'project_id';
    }

    protected function parentId(array $row, Context $ctx): ?int
    {
        return $ctx->id(Project::class, $row['offerprojectbase'] ?? null);
    }

    public function checks(Context $ctx): array
    {
        $projects = Project::withTrashed()->whereNotNull('gripp_id')->with('lines')->get();

        return array_merge(parent::checks($ctx), [$this->totalsCheck($projects, $ctx, 'opdrachten waarvan regels optellen tot het totaal')]);
    }
}
