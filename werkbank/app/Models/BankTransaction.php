<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BankTransaction extends Model
{
    public const STATUSSEN = ['nieuw' => 'Geen voorstel', 'voorstel' => 'Voorstel', 'gekoppeld' => 'Geboekt', 'genegeerd' => 'Genegeerd'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'proposal_certain' => 'boolean', 'handled_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /** Voorgestelde factuur: Invoice of PurchaseInvoice. */
    public function proposal(): MorphTo
    {
        return $this->morphTo();
    }

    public function payment(): MorphTo
    {
        return $this->morphTo();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['nieuw', 'voorstel'], true);
    }
}
