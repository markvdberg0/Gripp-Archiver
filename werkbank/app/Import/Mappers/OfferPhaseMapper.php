<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\OfferPhase;

class OfferPhaseMapper extends Mapper
{
    public function entity(): string
    {
        return 'offerphase';
    }

    public function model(): string
    {
        return OfferPhase::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null, 100) ?? 'Fase '.$row['id'],
            'color' => Gripp::str($row['color'] ?? null, 20),
            'sort' => (int) ($row['_ordering'] ?? 0),
        ];
    }
}
