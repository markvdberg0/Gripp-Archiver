<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\LineMapper;
use App\Models\Packet;
use App\Models\PacketLine;

class PacketLineMapper extends LineMapper
{
    public function entity(): string
    {
        return 'packetline';
    }

    public function model(): string
    {
        return PacketLine::class;
    }

    protected function parentColumn(): string
    {
        return 'packet_id';
    }

    protected function parentId(array $row, Context $ctx): ?int
    {
        return $ctx->id(Packet::class, $row['packet'] ?? null);
    }
}
