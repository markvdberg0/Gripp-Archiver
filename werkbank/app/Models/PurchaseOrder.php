<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLines;
use App\Models\Concerns\HasTags;
use App\Support\Zoek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use Auditable, FromGripp, HasAttachments, HasFactory, HasLines, HasTags, SoftDeletes;

    public const STATUS = ['concept' => 'Concept', 'verzonden' => 'Verzonden', 'bevestigd' => 'Bevestigd', 'afgerond' => 'Afgerond'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'delivery_date' => 'date', 'archived' => 'boolean',
            'total_excl' => 'decimal:2', 'total_vat' => 'decimal:2', 'total_incl' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('sort')->orderBy('id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEntry::class)->latest('occurred_at');
    }

    /** Al gefactureerd door de leverancier (excl. btw), uit gekoppelde inkoopfacturen. */
    public function invoicedExcl(): float
    {
        return (float) PurchaseInvoiceLine::query()->whereIn('purchase_order_line_id', $this->lines()->pluck('id'))->sum('total_excl')
            ?: (float) $this->invoices()->sum('total_excl');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return Zoek::in($q, $term, ['name', 'number', 'reference']);
    }

    public function auditLabel(): string
    {
        return "Inkoopopdracht {$this->number}";
    }
}
