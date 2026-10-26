<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\CostCenter;

class CostCenterMapper extends Mapper
{
    public function entity(): string
    {
        return 'costheading';
    }

    public function model(): string
    {
        return CostCenter::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'number' => Gripp::str($row['number'] ?? null, 20),
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null) ?? 'Kostenplaats '.$row['id'],
        ];
    }
}
