<?php

namespace App\Support\Ponto;

use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Support\Iban;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Stelt per banktransactie de factuur voor waar hij waarschijnlijk bij hoort. Boekt zelf niets: een gebruiker
 * bevestigt het voorstel. Regels, van zeker naar minder zeker:
 *  1. afschrijving met kenmerk WB-<boeknummer> uit het betaalbestand → die inkoopfactuur (zeker bij gelijk bedrag);
 *  2. factuurnummer in de omschrijving → die verkoopfactuur (zeker bij gelijk openstaand bedrag), of bij een
 *     afschrijving het factuurnummer van de leverancier;
 *  3. zelfde IBAN als de relatie en precies één openstaande factuur met dit bedrag → voorstel.
 */
class Koppelvoorstellen
{
    /** @var Collection<string, Collection<int, int>>|null genormaliseerde IBAN → relatie-id's */
    private ?Collection $ibans = null;

    /** @return int aantal transacties met een voorstel */
    public function run(): int
    {
        $n = 0;
        BankTransaction::query()->whereIn('status', ['nieuw', 'voorstel'])->orderBy('id')->chunkById(200, function ($txs) use (&$n) {
            foreach ($txs as $tx) {
                [$doc, $reason, $certain] = $this->propose($tx) ?? [null, null, false];
                $tx->forceFill([
                    'status' => $doc ? 'voorstel' : 'nieuw',
                    'proposal_type' => $doc?->getMorphClass(), 'proposal_id' => $doc?->getKey(),
                    'proposal_reason' => $reason, 'proposal_certain' => $certain,
                ])->save();
                $n += $doc ? 1 : 0;
            }
        });

        return $n;
    }

    /** @return array{0: Model, 1: string, 2: bool}|null */
    public function propose(BankTransaction $tx): ?array
    {
        $amount = (float) $tx->amount;
        $text = trim($tx->remittance.' '.$tx->description);

        // 1. Betaling uit het eigen betaalbestand
        if ($amount < 0 && preg_match('/^WB-(\d+)$/', (string) $tx->end_to_end_id, $m)) {
            $pi = $this->openPurchase()->where('booking_number', (int) $m[1])->first();
            if ($pi) {
                $exact = self::same(-$amount, $pi->total_open);

                return [$pi, 'Kenmerk '.$tx->end_to_end_id.' uit het betaalbestand'.($exact ? '' : ', maar ander bedrag'), $exact];
            }
        }

        // 2. Factuurnummer in de omschrijving
        if ($text !== '') {
            preg_match_all('/(?<![\d])(\d{4,12})(?![\d])/', $text, $m);
            $numbers = array_unique(array_map('intval', $m[1]));
            if ($numbers) {
                $candidates = $this->openSales()->whereIn('number', $numbers)->get();
                $exact = $candidates->first(fn ($i) => self::same($amount, $i->total_open));
                if ($exact) {
                    return [$exact, 'Factuurnummer '.$exact->number.' in de omschrijving, bedrag gelijk aan openstaand', true];
                }
                if ($candidates->count() === 1 && $amount * (float) $candidates->first()->total_open > 0) {
                    return [$candidates->first(), 'Factuurnummer '.$candidates->first()->number.' in de omschrijving, ander bedrag (deelbetaling?)', false];
                }
            }
            if ($amount < 0) {
                $pi = $this->openPurchase()->whereNotNull('invoice_number')->get()
                    ->first(fn ($p) => mb_strlen($p->invoice_number) >= 4 && mb_stripos($text, $p->invoice_number) !== false && self::same(-$amount, $p->total_open));
                if ($pi) {
                    return [$pi, 'Factuurnummer van de leverancier ('.$pi->invoice_number.') in de omschrijving, bedrag gelijk', true];
                }
            }
        }

        // 3. Zelfde IBAN en uniek bedrag
        if ($tx->counterpart_iban && Iban::valid($tx->counterpart_iban)) {
            $this->ibans ??= Company::query()->whereNotNull('iban')->get(['id', 'iban'])->groupBy(fn ($c) => Iban::normalize($c->iban))->map->pluck('id');
            $companies = $this->ibans->get($tx->counterpart_iban, collect());
            if ($companies->isNotEmpty()) {
                $matches = $amount > 0
                    ? $this->openSales()->whereIn('company_id', $companies)->get()->filter(fn ($i) => self::same($amount, $i->total_open))
                    : $this->openPurchase()->whereIn('supplier_id', $companies)->get()->filter(fn ($p) => self::same(-$amount, $p->total_open));
                if ($matches->count() === 1) {
                    $doc = $matches->first();

                    return [$doc, 'Zelfde IBAN als de relatie en het enige openstaande document met dit bedrag', false];
                }
            }
        }

        return null;
    }

    private function openSales()
    {
        return Invoice::query()->where('status', '!=', 'concept')->where(fn ($q) => $q->where('total_open', '>', 0.005)->orWhere('total_open', '<', -0.005));
    }

    private function openPurchase()
    {
        // On hold: betaling registreren kan dan niet, dus ook geen voorstel.
        return PurchaseInvoice::query()->whereIn('phase', ['akkoord', 'verwerkt'])->where('on_hold', false)->where('total_open', '>', 0.005);
    }

    private static function same(float $a, mixed $b): bool
    {
        return abs(round($a, 2) - round((float) $b, 2)) < 0.005;
    }
}
