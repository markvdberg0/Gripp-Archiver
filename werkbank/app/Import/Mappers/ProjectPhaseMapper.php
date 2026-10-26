<?php

namespace App\Import\Mappers;

use App\Import\PhaseMapper;
use App\Models\ProjectPhase;

class ProjectPhaseMapper extends PhaseMapper
{
    public function entity(): string
    {
        return 'projectphase';
    }

    public function model(): string
    {
        return ProjectPhase::class;
    }
}
