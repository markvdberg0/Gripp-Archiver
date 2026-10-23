<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends DocumentLine
{
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectLine(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class);
    }
}
