<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorialLine extends Model
{
    use FromGripp;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }
}
