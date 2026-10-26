<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\ImportSkip;
use App\Import\Mapper;
use App\Models\CostCenter;
use App\Models\Ledger;
use App\Models\Memorial;
use App\Models\MemorialLine;

class MemorialLineMapper extends Mapper
{
    public function entity(): string
    {
        return 'memorialline';
    }

    public function model(): string
    {
        return MemorialLine::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'memorial_id' => $ctx->id(Memorial::class, $row['memorial'] ?? null) ?? throw new ImportSkip('Memoriaalboeking bij deze regel ontbreekt.'),
            'ledger_id' => $ctx->id(Ledger::class, $row['ledger'] ?? null),
            'cost_center_id' => $ctx->id(CostCenter::class, $row['costheading'] ?? null),
            'vat_rate_id' => $ctx->vatRate($row['vat'] ?? null, 'beide'),
            'amount' => Gripp::money($row['value'] ?? 0),
            'description' => Gripp::str($row['description'] ?? null),
        ];
    }
}
