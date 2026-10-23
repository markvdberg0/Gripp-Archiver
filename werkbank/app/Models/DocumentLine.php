<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use App\Support\Bedragen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Basis voor regels van pakketten, offertes, opdrachten, facturen en inkoopdocumenten. */
abstract class DocumentLine extends Model
{
    use FromGripp;

    public const ROW_TYPES = ['normaal' => 'Regel', 'groep' => 'Groepskop', 'tekst' => 'Tekst'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'buying_price' => 'decimal:4', 'discount' => 'decimal:2', 'total_excl' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (DocumentLine $line) {
            $line->total_excl = Bedragen::lineTotal($line->row_type ?? 'normaal', $line->quantity, $line->unit_price, $line->discount);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function label(): string
    {
        return $this->title ?: ($this->product?->name ?? '');
    }
}
