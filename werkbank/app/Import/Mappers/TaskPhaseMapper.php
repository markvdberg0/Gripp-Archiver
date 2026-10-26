<?php

namespace App\Import\Mappers;

use App\Import\PhaseMapper;
use App\Models\TaskPhase;

class TaskPhaseMapper extends PhaseMapper
{
    public function entity(): string
    {
        return 'taskphase';
    }

    public function model(): string
    {
        return TaskPhase::class;
    }
}
