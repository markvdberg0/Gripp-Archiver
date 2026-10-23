<?php

namespace App\Import\Mappers;

use App\Import\PhaseMapper;
use App\Models\TaskType;

class TaskTypeMapper extends PhaseMapper
{
    public function entity(): string
    {
        return 'tasktype';
    }

    public function model(): string
    {
        return TaskType::class;
    }
}
