<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Support\Zoek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use Auditable, FromGripp, HasAttachments, HasFactory, HasTags;

    public const INVOICE_BASIS = ['vast' => 'Vaste prijs', 'nacalculatie' => 'Nacalculatie'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['selling_price' => 'decimal:4', 'buying_price' => 'decimal:4', 'extra_fields' => 'array', 'archived' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function purchaseVatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class, 'purchase_vat_rate_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function purchaseLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'purchase_ledger_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    public function margin(): ?float
    {
        $sell = (float) $this->selling_price;

        return $sell > 0 ? round(($sell - (float) $this->buying_price) / $sell * 100, 1) : null;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return Zoek::in($q, $term, ['name', 'number', 'supplier_code', 'description']);
    }

    /** Producten voor de regels-editor (actief, met prijs, eenheid en btw als data-attributen). */
    public static function forPicker(): array
    {
        return static::query()->where('archived', false)->orderBy('name')->get(['id', 'number', 'name', 'selling_price', 'buying_price', 'unit_id', 'vat_rate_id'])->all();
    }
}
