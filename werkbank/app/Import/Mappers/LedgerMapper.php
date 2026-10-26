<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Ledger;

class LedgerMapper extends Mapper
{
    public function entity(): string
    {
        return 'ledger';
    }

    public function model(): string
    {
        return Ledger::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $side = mb_strtolower((string) Gripp::label($row['debitcredit'] ?? null));

        return [
            'number' => Gripp::str($row['number'] ?? null, 20) ?? (string) $row['id'],
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null) ?? 'Grootboek '.$row['id'],
            'type' => match (true) {
                Gripp::is($row['type'] ?? null, 'balans') => 'balans',
                Gripp::is($row['type'] ?? null, 'resultatenrekening', 'resultaat') => 'resultaat',
                default => null,
            },
            'side' => str_contains($side, 'debet') ? 'debet' : (str_contains($side, 'credit') ? 'credit' : null),
            'category' => Gripp::label($row['categorie'] ?? null),
            'active' => Gripp::bool($row['active'] ?? true),
        ];
    }
}
