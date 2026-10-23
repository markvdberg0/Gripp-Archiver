<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Unit;

class UnitMapper extends Mapper
{
    public function entity(): string
    {
        return 'unit';
    }

    public function model(): string
    {
        return Unit::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null, 50) ?? 'eenheid '.$row['id'],
            'type' => Gripp::is($row['unittype'] ?? null, 'uren', 'hours') ? 'uren' : 'aantal',
            'hours_per_unit' => is_numeric($row['hoursperunit'] ?? null) ? $row['hoursperunit'] : null,
        ];
    }
}
