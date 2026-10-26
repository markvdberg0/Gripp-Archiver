<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Support\Audit;
use App\Support\Ponto\BankBoeken;
use App\Support\Ponto\BankOphalen;
use App\Support\Ponto\PontoClient;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/** Banktransacties uit Ponto met koppelvoorstellen; boeken alleen na bevestiging. */
class BankController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');
        $transactions = BankTransaction::with(['account', 'handler', 'proposal' => fn (MorphTo $m) => $m->morphWith([Invoice::class => ['company'], PurchaseInvoice::class => ['supplier']])])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['nieuw', 'voorstel']))
            ->when($status !== 'open' && isset(BankTransaction::STATUSSEN[$status]), fn ($q) => $q->where('status', $status))
            ->when($request->query('rekening'), fn ($q, $id) => $q->where('bank_account_id', $id))
            ->orderByDesc('date')->orderByDesc('id')->paginate(50)->withQueryString();

        return view('bank.index', [
            'accounts' => BankAccount::orderBy('iban')->get(), 'transactions' => $transactions, 'status' => $status,
            'configured' => PontoClient::configured(),
            'counts' => BankTransaction::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'certain' => BankTransaction::where('status', 'voorstel')->where('proposal_certain', true)->count(),
        ]);
    }

    /** Nu ophalen: leest wat Ponto heeft; start geen synchronisatie bij de bank. */
    public function fetch(BankOphalen $ophalen): RedirectResponse
    {
        abort_unless(PontoClient::configured(), 422, 'Ponto is niet ingesteld.');
        try {
            $s = $ophalen->run();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['bank' => 'Ophalen mislukt: '.$e->getMessage()]);
        }

        return back()->with('status', "{$s['new']} nieuwe transactie(s) opgehaald; {$s['proposals']} met een koppelvoorstel.");
    }

    /** Voorstel bevestigen, of zelf een document kiezen (soort + nummer). */
    public function book(Request $request, BankTransaction $transactie, BankBoeken $boeken): RedirectResponse
    {
        $data = $request->validate([
            'soort' => ['nullable', 'in:factuur,inkoopfactuur'],
            'nummer' => ['nullable', 'required_with:soort', 'integer'],
        ], [], ['nummer' => 'nummer']);
        $doc = match ($data['soort'] ?? null) {
            'factuur' => Invoice::where('number', $data['nummer'])->first(),
            'inkoopfactuur' => PurchaseInvoice::where('booking_number', $data['nummer'])->first(),
            default => $transactie->proposal,
        };
        if (! $doc) {
            throw ValidationException::withMessages(['nummer' => isset($data['soort']) ? 'Geen document met dit nummer gevonden.' : 'Er is geen voorstel; kies zelf een factuur.']);
        }
        try {
            $boeken->book($transactie, $doc, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bank' => $e->getMessage()]);
        }

        return back()->with('status', 'Betaling geboekt op '.$doc->auditLabel().'.');
    }

    /** Alle zekere voorstellen in één keer boeken (kenmerk uit het betaalbestand of factuurnummer + exact bedrag). */
    public function bookCertain(Request $request, BankBoeken $boeken): RedirectResponse
    {
        $done = 0;
        $failed = [];
        foreach (BankTransaction::with('proposal')->where('status', 'voorstel')->where('proposal_certain', true)->orderBy('date')->get() as $tx) {
            try {
                $boeken->book($tx, $tx->proposal, $request->user());
                $done++;
            } catch (InvalidArgumentException $e) {
                $failed[] = $tx->date->format('d-m-Y').': '.$e->getMessage();
            }
        }

        return back()->with('status', "{$done} betaling(en) geboekt.".($failed ? ' Niet geboekt: '.implode(' ', array_slice($failed, 0, 3)) : ''));
    }

    public function ignore(Request $request, BankTransaction $transactie): RedirectResponse
    {
        abort_unless($transactie->isOpen(), 422, 'Deze transactie is al verwerkt.');
        $transactie->forceFill(['status' => 'genegeerd', 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();

        return back()->with('status', 'Transactie genegeerd.');
    }

    public function reopen(Request $request, BankTransaction $transactie): RedirectResponse
    {
        abort_unless($transactie->status === 'genegeerd', 422, 'Alleen genegeerde transacties kunnen terug.');
        $transactie->forceFill(['status' => 'nieuw', 'handled_by' => null, 'handled_at' => null])->save();
        Audit::log('bank_heropend', null, 'Banktransactie '.$transactie->id.' weer open gezet');

        return back()->with('status', 'Transactie staat weer open.');
    }
}
