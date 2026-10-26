<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Packet;
use App\Models\Unit;

class PacketMapper extends Mapper
{
    public function entity(): string
    {
        return 'packet';
    }

    public function model(): string
    {
        return Packet::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : null,
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null) ?? 'Pakket '.$row['id'],
            'description' => Gripp::text($row['description'] ?? null),
            'unit_id' => $ctx->id(Unit::class, $row['unit'] ?? null),
            'archived' => Gripp::bool($row['archived'] ?? false),
        ];
    }
}
