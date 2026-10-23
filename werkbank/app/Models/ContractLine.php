<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractLine extends DocumentLine
{
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
