<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasLines;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pakket: een vaste set productregels die in één keer aan een offerte kan worden toegevoegd. */
class Packet extends Model
{
    use Auditable, FromGripp, HasLines;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['archived' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PacketLine::class)->orderBy('sort')->orderBy('id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Pakketten hebben geen opgeslagen totalen; berekenen voor weergave. */
    public function recalculate(): void {}
}
