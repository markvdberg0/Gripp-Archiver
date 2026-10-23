<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Cost;
use App\Models\CostCenter;
use App\Models\Ledger;

class CostMapper extends Mapper
{
    public function entity(): string
    {
        return 'cost';
    }

    public function model(): string
    {
        return Cost::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : null,
            'date' => Gripp::day($row['date'] ?? null),
            'amount' => Gripp::money($row['value'] ?? 0),
            'description' => Gripp::str($row['description'] ?? null),
            'ledger_id' => $ctx->id(Ledger::class, $row['ledger'] ?? null),
            'cost_center_id' => $ctx->id(CostCenter::class, $row['costheading'] ?? null),
        ];
    }

    public function checks(Context $ctx): array
    {
        $source = array_sum(array_map(fn ($r) => (float) ($r['value'] ?? 0), $ctx->snapshot->rows('cost')));
        $local = (float) Cost::whereNotNull('gripp_id')->sum('amount');

        return array_merge(parent::checks($ctx), [[
            'label' => 'cost: som bedragen', 'source' => number_format($source, 2, ',', '.'), 'local' => number_format($local, 2, ',', '.'), 'ok' => abs($source - $local) < 0.01,
        ]]);
    }
}
