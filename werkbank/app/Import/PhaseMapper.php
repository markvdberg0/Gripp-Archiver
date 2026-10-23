<?php

namespace App\Import;

/** Eenvoudige keuzelijsten met naam, kleur en volgorde (opdrachtfasen, taakfasen, taaksoorten). */
abstract class PhaseMapper extends Mapper
{
    public function map(array $row, Context $ctx): ?array
    {
        return [
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null, 100) ?? '#'.$row['id'],
            'color' => Gripp::str($row['color'] ?? null, 20),
            'sort' => (int) ($row['_ordering'] ?? 0),
        ];
    }
}
