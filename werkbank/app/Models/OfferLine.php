<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferLine extends DocumentLine
{
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
