<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacketLine extends DocumentLine
{
    public function packet(): BelongsTo
    {
        return $this->belongsTo(Packet::class);
    }
}
