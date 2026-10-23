<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Maakt conceptfacturen voor contracten waarvan de volgende factuurdatum is bereikt, en schuift die datum
 * één periode op. Er ontstaan alleen concepten: controleren en definitief maken blijft mensenwerk.
 */
final class ContractFacturatie
{
    /** @return list<Invoice> */
    public static function run(?CarbonInterface $on = null): array
    {
        $on ??= today();
        // Nooit twee runs tegelijk (geplande taak en knop): anders ontstaan dubbele facturen voor één periode.
        $lock = Cache::lock('werkbank:contracten-factureren', 600);
        if (! $lock->get()) {
            return [];
        }
        try {
            $made = [];
            foreach (Contract::with(['lines', 'company'])->where('status', 'actief')->whereNotNull('next_invoice_date')->whereDate('next_invoice_date', '<=', $on)->get() as $contract) {
                // Inhalen als er meerdere periodes achterstallig zijn, maar nooit meer dan 24 tegelijk.
                for ($i = 0; $i < 24 && $contract->isDue($on); $i++) {
                    $made[] = self::invoice($contract);
                }
            }

            return $made;
        } finally {
            $lock->release();
        }
    }

    public static function invoice(Contract $contract): Invoice
    {
        return DB::transaction(function () use ($contract) {
            $period = $contract->next_invoice_date;
            $next = Contract::advance($period, $contract->frequency);
            $invoice = Invoice::create([
                'company_id' => $contract->company_id, 'contact_id' => $contract->contact_id, 'contract_id' => $contract->id,
                'subject' => ($contract->invoice_subject ?: $contract->name).' · periode '.Format::date($period).' t/m '.Format::date($next->copy()->subDay()),
                'client_reference' => $contract->client_reference, 'payment_method' => $contract->company?->payment_method ?? 'overboeking', 'status' => 'concept',
            ]);
            foreach ($contract->lines as $line) {
                $invoice->lines()->create($line->only(['sort', 'row_type', 'product_id', 'title', 'description', 'quantity', 'unit_id', 'unit_price', 'buying_price', 'discount', 'vat_rate_id', 'ledger_id']) + ['contract_line_id' => $line->id]);
            }
            $invoice->recalculate();
            $contract->next_invoice_date = $next;
            if ($contract->end_date && $next->gt($contract->end_date) && ! $contract->auto_renew) {
                $contract->next_invoice_date = null;
            }
            $contract->save();

            return $invoice;
        });
    }
}
