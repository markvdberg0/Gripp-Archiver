<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends DocumentLine
{
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
}
