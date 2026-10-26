<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\Setting;
use App\Support\Audit;
use App\Support\Iban;
use App\Support\Sepa;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEPA-betaalbestand voor goedgekeurde inkoopfacturen. Het bestand registreert geen betaling: die volgt pas als
 * de bank heeft uitgevoerd. Wel wordt vastgelegd welke facturen al in een bestand zaten, tegen dubbel betalen.
 */
class PaymentFileController extends Controller
{
    public function index(Request $request): View
    {
        $showExported = $request->boolean('ook_verwerkt');
        $invoices = PurchaseInvoice::with('supplier')
            ->whereIn('phase', ['akkoord', 'verwerkt'])->where('on_hold', false)->where('total_open', '>', 0.005)
            ->when(! $showExported, fn ($q) => $q->whereNull('sepa_exported_at'))
            ->orderBy('due_date')->orderBy('id')->get();

        return view('purchase_invoices.payments', [
            'invoices' => $invoices, 'showExported' => $showExported,
            'debtorIban' => (string) Setting::get('bedrijf.iban'), 'debtorOk' => Iban::valid((string) Setting::get('bedrijf.iban')),
        ]);
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'execution_date' => ['required', 'date', 'after_or_equal:today'],
        ], ['ids.required' => 'Kies minstens één factuur.'], ['execution_date' => 'uitvoeringsdatum']);
        $iban = (string) Setting::get('bedrijf.iban');
        if (! Iban::valid($iban)) {
            throw ValidationException::withMessages(['ids' => 'Vul eerst een geldig IBAN van het eigen bedrijf in (Beheer › Instellingen).']);
        }

        return DB::transaction(function () use ($data, $iban) {
            $invoices = PurchaseInvoice::with('supplier')->whereIn('id', $data['ids'])->lockForUpdate()->get();
            $problems = $invoices->mapWithKeys(fn ($i) => [$i->id => Sepa::problem($i)])->filter();
            if ($problems->isNotEmpty() || $invoices->count() !== count(array_unique($data['ids']))) {
                $first = $invoices->firstWhere('id', $problems->keys()->first());
                throw ValidationException::withMessages(['ids' => $first ? "Inkoopfactuur {$first->booking_number}: {$problems->first()}." : 'Een of meer facturen bestaan niet meer.']);
            }
            $file = Sepa::creditTransfer($invoices, CarbonImmutable::parse($data['execution_date']), (string) Setting::get('bedrijf.naam', config('app.name')), $iban, Setting::get('bedrijf.bic'));
            PurchaseInvoice::whereIn('id', $invoices->pluck('id'))->update(['sepa_exported_at' => now(), 'sepa_batch' => $file['batch']]);
            Audit::log('sepa_bestand', null, "Betaalbestand {$file['batch']}: {$file['count']} facturen, € {$file['total']}", ['facturen' => $invoices->pluck('booking_number')->implode(', ')]);

            return response($file['xml'], 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="betaalbestand-'.$file['batch'].'.xml"',
            ]);
        });
    }
}
