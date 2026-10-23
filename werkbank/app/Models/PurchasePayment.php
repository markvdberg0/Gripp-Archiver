<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePayment extends Model
{
    use Auditable, FromGripp;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function auditLabel(): string
    {
        return 'Betaling '.$this->amount.' op inkoopfactuur #'.$this->purchase_invoice_id;
    }
}
