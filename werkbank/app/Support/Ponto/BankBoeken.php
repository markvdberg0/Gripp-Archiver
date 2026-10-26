<?php

namespace App\Support\Ponto;

use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Een banktransactie als betaling boeken op een verkoop- of inkoopfactuur, na bevestiging door een gebruiker.
 * Zelfde effect als "Betaling registreren" op de factuur, plus de verwijzing naar de banktransactie.
 */
class BankBoeken
{
    public function book(BankTransaction $tx, Model $doc, User $user): void
    {
        DB::transaction(function () use ($tx, $doc, $user) {
            $tx = BankTransaction::lockForUpdate()->findOrFail($tx->id);
            if (! $tx->isOpen()) {
                throw new InvalidArgumentException('Deze transactie is al verwerkt.');
            }
            $doc = $doc::lockForUpdate()->findOrFail($doc->getKey());
            $amount = (float) $tx->amount;

            if ($doc instanceof Invoice) {
                if (! $user->can('facturen.bewerken')) {
                    throw new InvalidArgumentException('Je mag geen betalingen op verkoopfacturen registreren.');
                }
                if ($doc->isEditable()) {
                    throw new InvalidArgumentException('Factuur is nog een concept.');
                }
                $this->guardAmount($amount, (float) $doc->total_open, 'factuur '.$doc->number);
                $payment = $doc->payments()->create(['date' => $tx->date, 'amount' => $amount, 'notes' => 'Bank (Ponto) '.$tx->ponto_id, 'user_id' => $user->id]);
                $doc->refreshPaid();
            } elseif ($doc instanceof PurchaseInvoice) {
                if (! $user->can('inkoop.goedkeuren')) {
                    throw new InvalidArgumentException('Je mag geen betalingen op inkoopfacturen registreren.');
                }
                if (! in_array($doc->phase, ['akkoord', 'verwerkt'], true) || $doc->on_hold) {
                    throw new InvalidArgumentException('Inkoopfactuur '.$doc->booking_number.' is niet goedgekeurd of staat on hold.');
                }
                $this->guardAmount(-$amount, (float) $doc->total_open, 'inkoopfactuur '.$doc->booking_number);
                $payment = $doc->payments()->create(['date' => $tx->date, 'amount' => -$amount, 'notes' => 'Bank (Ponto) '.$tx->ponto_id, 'user_id' => $user->id]);
                $doc->refreshPaid();
            } else {
                throw new InvalidArgumentException('Onbekend document.');
            }

            $tx->forceFill([
                'status' => 'gekoppeld', 'payment_type' => $payment->getMorphClass(), 'payment_id' => $payment->id,
                'proposal_type' => $doc->getMorphClass(), 'proposal_id' => $doc->getKey(),
                'handled_by' => $user->id, 'handled_at' => now(),
            ])->save();
            Audit::log('bank_geboekt', $doc, $doc->auditLabel().' · '.number_format(abs($amount), 2, ',', '.').' op '.$tx->date->format('d-m-Y'));
        });
    }

    /** Teken moet kloppen en er mag niet meer worden geboekt dan er openstaat. */
    private function guardAmount(float $amount, float $open, string $label): void
    {
        if (abs($amount) < 0.005 || $amount * $open <= 0) {
            throw new InvalidArgumentException("Het bedrag past niet bij {$label} (bij- of afschrijving omgekeerd, of niets open).");
        }
        if (abs($amount) > abs($open) + 0.005) {
            throw new InvalidArgumentException("Het bedrag is hoger dan wat er openstaat op {$label} (€ ".number_format(abs($open), 2, ',', '.').').');
        }
    }
}
