<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CostCenter;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Unit;
use App\Models\VatRate;
use App\Support\Audit;
use App\Support\Bedragen;
use App\Support\Nummering;
use App\Support\Regels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseInvoiceController extends Controller
{
    private const LINE_EXTRA = ['ledger_id', 'cost_center_id', 'project_id', 'purchase_order_line_id'];

    public function index(Request $request): View
    {
        $base = PurchaseInvoice::query()->search($request->query('q'))
            ->when($request->query('fase'), fn ($q, $f) => $f === 'on_hold' ? $q->where('on_hold', true) : $q->where('phase', $f))
            ->when($request->query('openstaand') === '1', fn ($q) => $q->where('total_open', '>', 0.005))
            ->when($request->query('vervallen') === '1', fn ($q) => $q->where('total_open', '>', 0.005)->whereDate('due_date', '<', today()))
            ->when($request->query('leverancier'), fn ($q, $c) => $q->where('supplier_id', $c))
            ->when($request->query('jaar'), fn ($q, $y) => $q->whereYear('date', (int) $y));
        $sum = (clone $base)->selectRaw('count(*) as n, coalesce(sum(total_incl),0) as incl, coalesce(sum(total_open),0) as open')->toBase()->first();
        $invoices = $base->with('supplier')->orderByDesc('booking_number')->orderByDesc('id')->paginate(50)->withQueryString();
        $counts = PurchaseInvoice::query()->selectRaw('phase, count(*) as n')->groupBy('phase')->pluck('n', 'phase');

        return view('purchase_invoices.index', ['invoices' => $invoices, 'sum' => $sum, 'counts' => $counts, 'onHold' => PurchaseInvoice::where('on_hold', true)->count(), 'suppliers' => Company::options('is_supplier')]);
    }

    /** Nieuwe inkoopfactuur; met ?purchase_order_id=… worden de regels van de inkoopopdracht voorgesteld. */
    public function create(Request $request): View
    {
        $order = $request->integer('purchase_order_id') ? PurchaseOrder::with('lines')->findOrFail($request->integer('purchase_order_id')) : null;
        $invoice = new PurchaseInvoice([
            'supplier_id' => $order?->supplier_id ?? ($request->integer('supplier_id') ?: null), 'purchase_order_id' => $order?->id, 'project_id' => $order?->project_id,
            'subject' => $order?->name, 'date' => today(), 'phase' => 'concept',
        ]);
        $lines = $order ? $order->lines->map(fn ($l) => $l->only(['row_type', 'product_id', 'title', 'description', 'quantity', 'unit_id', 'unit_price', 'discount', 'vat_rate_id', 'ledger_id', 'cost_center_id', 'project_id']) + ['purchase_order_line_id' => $l->id]) : collect();

        return view('purchase_invoices.form', $this->formData($invoice) + ['prefill' => $lines]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $invoice = DB::transaction(function () use ($data, $request) {
            $invoice = PurchaseInvoice::create(Arr::except($data, ['lines']) + [
                'booking_number' => Nummering::next('boeknummer', 'purchase_invoices', 'booking_number'),
                'phase' => 'concept', 'created_by' => $request->user()->id, 'due_date' => $data['due_date'] ?? $this->dueDate($data),
            ]);
            Regels::sync($invoice, $this->withProject($data), self::LINE_EXTRA);
            $invoice->recalculate();

            return $invoice;
        });

        return redirect()->route('inkoopfacturen.show', $invoice)->with('status', "Inkoopfactuur geboekt onder nummer {$invoice->booking_number}.");
    }

    public function show(PurchaseInvoice $inkoopfactuur): View
    {
        $inkoopfactuur->load(['supplier', 'contact', 'purchaseOrder', 'project', 'creator', 'approver', 'payments', 'files', 'lines.unit', 'lines.vatRate', 'lines.ledger', 'lines.costCenter', 'lines.project']);

        return view('purchase_invoices.show', [
            'invoice' => $inkoopfactuur, 'vat' => $inkoopfactuur->vatBreakdown(), 'controls' => $inkoopfactuur->controls(),
            'denial' => $inkoopfactuur->approvalDenial(request()->user()),
            'timeline' => $inkoopfactuur->timeline()->with(['employee', 'user', 'files'])->paginate(25),
        ]);
    }

    public function edit(PurchaseInvoice $inkoopfactuur): View|RedirectResponse
    {
        if (! $inkoopfactuur->isEditable()) {
            return redirect()->route('inkoopfacturen.show', $inkoopfactuur)->withErrors(['inkoopfactuur' => 'Een goedgekeurde of verwerkte inkoopfactuur kan niet worden gewijzigd. Zet hem eerst terug naar concept.']);
        }

        return view('purchase_invoices.form', $this->formData($inkoopfactuur->load('lines')) + ['prefill' => $inkoopfactuur->lines]);
    }

    public function update(Request $request, PurchaseInvoice $inkoopfactuur): RedirectResponse
    {
        abort_unless($inkoopfactuur->isEditable(), 422, 'Deze inkoopfactuur kan niet meer worden gewijzigd.');
        $data = $this->validated($request);
        DB::transaction(function () use ($inkoopfactuur, $data) {
            $inkoopfactuur->update(Arr::except($data, ['lines']));
            Regels::sync($inkoopfactuur, $this->withProject($data), self::LINE_EXTRA);
            $inkoopfactuur->recalculate();
        });

        return redirect()->route('inkoopfacturen.show', $inkoopfactuur)->with('status', 'Inkoopfactuur opgeslagen.');
    }

    public function destroy(PurchaseInvoice $inkoopfactuur): RedirectResponse
    {
        if ($inkoopfactuur->phase !== 'concept' || $inkoopfactuur->payments()->exists()) {
            return back()->withErrors(['inkoopfactuur' => 'Alleen een concept zonder betalingen kan worden verwijderd.']);
        }
        $inkoopfactuur->delete();

        return redirect()->route('inkoopfacturen.index')->with('status', 'Inkoopfactuur verwijderd.');
    }

    /**
     * Fase wijzigen: indienen (concept → controle), goedkeuren (→ akkoord), verwerken (akkoord → verwerkt),
     * terug (→ concept), hold / vrijgeven.
     */
    public function transition(Request $request, PurchaseInvoice $inkoopfactuur, string $actie): RedirectResponse
    {
        $user = $request->user();
        $before = $inkoopfactuur->stateLabel();
        switch ($actie) {
            case 'indienen':
                abort_unless($inkoopfactuur->phase === 'concept', 422);
                $inkoopfactuur->update(['phase' => 'controle']);
                break;
            case 'goedkeuren':
                abort_unless(in_array($inkoopfactuur->phase, ['concept', 'controle'], true) && ! $inkoopfactuur->on_hold, 422, 'Alleen een niet-geblokkeerde factuur in concept of controle kan worden goedgekeurd.');
                if ($denial = $inkoopfactuur->approvalDenial($user)) {
                    return back()->withErrors(['inkoopfactuur' => $denial]);
                }
                if ($problems = $inkoopfactuur->blockingProblems()) {
                    return back()->withErrors(['inkoopfactuur' => 'Niet goedgekeurd: '.implode('; ', $problems).'.']);
                }
                $inkoopfactuur->update(['phase' => 'akkoord', 'approved_by' => $user->id, 'approved_at' => now(), 'inbox' => false]);
                break;
            case 'verwerken':
                abort_unless($user->can('inkoop.goedkeuren') && $inkoopfactuur->phase === 'akkoord' && ! $inkoopfactuur->on_hold, 422);
                $inkoopfactuur->update(['phase' => 'verwerkt']);
                break;
            case 'terug':
                abort_unless($user->can('inkoop.goedkeuren') || $inkoopfactuur->phase === 'controle', 403);
                $inkoopfactuur->update(['phase' => 'concept', 'approved_by' => null, 'approved_at' => null]);
                break;
            case 'hold':
                $data = $request->validate(['hold_reason' => ['required', 'string', 'max:255']], [], ['hold_reason' => 'reden']);
                abort_if($inkoopfactuur->phase === 'verwerkt', 422);
                $inkoopfactuur->update(['on_hold' => true, 'hold_reason' => $data['hold_reason']]);
                break;
            case 'vrijgeven':
                $inkoopfactuur->update(['on_hold' => false, 'hold_reason' => null]);
                break;
            default:
                abort(404);
        }
        $inkoopfactuur->timeline()->create(['type' => 'systeem', 'body' => "Van {$before} naar {$inkoopfactuur->stateLabel()}".($inkoopfactuur->hold_reason ? " ({$inkoopfactuur->hold_reason})" : '').'.', 'occurred_at' => now(), 'user_id' => $user->id, 'company_id' => $inkoopfactuur->supplier_id]);

        return back()->with('status', 'Inkoopfactuur: '.mb_strtolower($inkoopfactuur->stateLabel()).'.');
    }

    public function storePayment(Request $request, PurchaseInvoice $inkoopfactuur): RedirectResponse
    {
        abort_unless($request->user()->can('inkoop.goedkeuren'), 403);
        abort_unless(in_array($inkoopfactuur->phase, ['akkoord', 'verwerkt'], true), 422, 'Registreer betalingen pas na goedkeuring.');
        $request->merge(['amount' => Bedragen::parse($request->input('amount'))]);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0', 'between:-999999999,999999999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['amount' => 'bedrag', 'date' => 'datum']);
        DB::transaction(function () use ($inkoopfactuur, $data, $request) {
            $inkoopfactuur->payments()->create($data + ['user_id' => $request->user()->id]);
            $inkoopfactuur->refreshPaid();
        });
        Audit::log('inkoopbetaling', $inkoopfactuur, $inkoopfactuur->auditLabel().' '.$data['amount']);

        return back()->with('status', 'Betaling geregistreerd.');
    }

    private function dueDate(array $data): ?string
    {
        $term = ($data['supplier_id'] ?? null) ? Company::find($data['supplier_id'])?->supplier_payment_term_days : null;

        return isset($data['date']) ? Carbon::parse($data['date'])->addDays($term ?? 30)->toDateString() : null;
    }

    private function withProject(array $data): array
    {
        return array_map(fn ($l) => $l + ['project_id' => $data['project_id'] ?? null], $data['lines'] ?? []);
    }

    private function formData(PurchaseInvoice $invoice): array
    {
        return [
            'invoice' => $invoice, 'suppliers' => Company::options('is_supplier') + ($invoice->supplier_id ? [$invoice->supplier_id => Company::find($invoice->supplier_id)?->name] : []),
            'contacts' => Contact::query()->where('active', true)->when($invoice->supplier_id, fn ($q) => $q->where('company_id', $invoice->supplier_id))->orderBy('last_name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'orders' => PurchaseOrder::query()->where('archived', false)->orderByDesc('number')->get(['id', 'number', 'name'])->mapWithKeys(fn ($o) => [$o->id => $o->number.' · '.$o->name])->all(),
            'projects' => Project::options(), 'products' => Product::forPicker(), 'units' => Unit::options(), 'vatRates' => VatRate::options('inkoop'),
            'extra' => ['ledger_id' => ['Grootboek', Ledger::options()], 'cost_center_id' => ['Kostenplaats', CostCenter::options()]],
        ];
    }

    private function validated(Request $request): array
    {
        Regels::prepare($request);
        $request->merge(['expected_total' => Bedragen::parse($request->input('expected_total'))]);

        return $request->validate(Regels::rules([
            'supplier_id' => ['required', 'exists:companies,id'],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('company_id', $request->input('supplier_id'))],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'expected_total' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'payment_method' => ['required', Rule::in(array_keys(Company::BETAALWIJZEN))],
            'lines.*.ledger_id' => ['nullable', 'exists:ledgers,id'],
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'lines.*.project_id' => ['nullable', 'exists:projects,id'],
            'lines.*.purchase_order_line_id' => ['nullable', 'exists:purchase_order_lines,id'],
        ]), [], Regels::attributes() + ['supplier_id' => 'leverancier', 'expected_total' => 'totaal volgens factuur']);
    }
}
