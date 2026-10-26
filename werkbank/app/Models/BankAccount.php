<?php

namespace App\Models;

use App\Support\Iban;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2', 'available_balance' => 'decimal:2',
            'authorization_expires_at' => 'datetime', 'synchronized_at' => 'datetime', 'fetched_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function label(): string
    {
        return trim(($this->iban ? Iban::format($this->iban) : '').' '.($this->description ? '· '.$this->description : ''), ' ·');
    }

    /** De toestemming bij de bank verloopt binnenkort (of is verlopen): vernieuwen in het Ponto-dashboard. */
    public function authorizationExpiresSoon(): bool
    {
        return $this->authorization_expires_at !== null && $this->authorization_expires_at->lessThan(now()->addDays(14));
    }
}
