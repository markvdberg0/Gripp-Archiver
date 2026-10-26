<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Memorial;

class MemorialMapper extends Mapper
{
    public function entity(): string
    {
        return 'memorial';
    }

    public function model(): string
    {
        return Memorial::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : null,
            'name' => Gripp::str($row['name'] ?? null),
            'type' => Gripp::str(Gripp::label($row['type'] ?? null), 50),
            'date' => Gripp::day($row['reportdate'] ?? null) ?? Gripp::day($row['createdon'] ?? null),
            'description' => Gripp::text($row['description'] ?? null),
        ];
    }
}
