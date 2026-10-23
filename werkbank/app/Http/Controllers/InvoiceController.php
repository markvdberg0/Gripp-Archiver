<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CostCenter;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Project;
use App\Models\Unit;
use App\Models\VatRate;
use App\Support\Audit;
use App\Support\Bedragen;
use App\Support\Csv;
use App\Support\Regels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    private const SORT = ['number' => 'number', 'date' => 'date', 'due' => 'due_date', 'total' => 'total_incl', 'open' => 'total_open'];

    private const LINE_EXTRA = ['ledger_id', 'cost_center_id', 'project_id', 'project_line_id'];

    public function index(Request $request): View|StreamedResponse
    {
        $sort = self::SORT[$request->query('sort')] ?? 'number';
        $dir = $request->query('dir', $request->has('sort') ? 'asc' : 'desc') === 'desc' ? 'desc' : 'asc';
        $base = Invoice::query()->search($request->query('q'))->state($request->query('status'))
            ->when($request->query('relatie'), fn ($q, $c) => $q->where('company_id', $c))
            ->when($request->query('opdracht'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('jaar'), fn ($q, $y) => $q->whereYear('date', (int) $y));
        if ($request->query('export') === 'csv') {
            return Csv::download('verkoopfacturen.csv', ['Nummer', 'Datum', 'Vervaldatum', 'Relatie', 'Onderwerp', 'Status', 'Excl. btw', 'Btw', 'Incl. btw', 'Betaald', 'Openstaand'],
                (clone $base)->with('company')->orderByDesc('number')->lazy()->map(fn (Invoice $i) => [$i->number, $i->date, $i->due_date, $i->company?->name, $i->subject, $i->stateLabel(), $i->total_excl, $i->total_vat, $i->total_incl, $i->total_paid, $i->total_open]));
        }
        $sum = (clone $base)->selectRaw('count(*) as n, coalesce(sum(total_excl),0) as excl, coalesce(sum(total_incl),0) as incl, coalesce(sum(case when status <> ? then total_open else 0 end),0) as open', ['concept'])->toBase()->first();
        // Concepten (zonder nummer) bovenaan bij aflopend nummer.
        $invoices = $base->with('company')->when($sort === 'number', fn ($q) => $q->orderByRaw('number is null '.$dir))
            ->orderBy($sort, $dir)->orderByDesc('id')->paginate(50)->withQueryString();

        return view('invoices.index', ['invoices' => $invoices, 'sum' => $sum, 'companies' => Company::options('is_customer')]);
    }

    /** Nieuwe factuur; met ?project_id=… worden de nog niet gefactureerde regels (of een termijn) voorgesteld. */
    public function create(Request $request): View
    {
        $project = $request->integer('project_id') ? Project::with(['lines.product', 'company'])->findOrFail($request->integer('project_id')) : null;
        $company = $project?->company ?? ($request->integer('company_id') ? Company::find($request->integer('company_id')) : null);
        $invoice = new Invoice([
            'company_id' => $company?->id, 'contact_id' => $project?->contact_id, 'project_id' => $project?->id,
            'subject' => $project ? "Opdracht {$project->number} · {$project->name}" : null, 'client_reference' => $project?->client_reference,
            'payment_method' => $company?->payment_method ?? 'overboeking',
        ]);
        $lines = collect();
        if ($project) {
            $lines = $request->filled('percentage') ? $this->instalmentLine($project, (float) Bedragen::parse($request->input('percentage'))) : $this->remainingLines($project);
        }

        return view('invoices.form', $this->formData($invoice) + ['prefill' => $lines, 'project' => $project, 'invoiced' => $project ? $this->invoicedFor($project) : null]);
    }

    /** Al gefactureerd (excl. btw, definitieve facturen incl. creditfacturen) voor een opdracht. */
    private function invoicedFor(Project $project): float
    {
        return (float) InvoiceLine::query()->where('project_id', $project->id)
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'concept'))->sum('total_excl');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $invoice = DB::transaction(function () use ($data) {
            $invoice = Invoice::create(Arr::except($data, ['lines']) + ['status' => 'concept']);
            Regels::sync($invoice, $data['lines'] ?? [], self::LINE_EXTRA);
            $invoice->recalculate();

            return $invoice;
        });

        return redirect()->route('facturen.show', $invoice)->with('status', 'Conceptfactuur aangemaakt.');
    }

    public function show(Invoice $factuur): View
    {
        $factuur->load(['company', 'contact', 'project', 'contract', 'creditOf', 'credits', 'payments.user', 'files', 'lines.unit', 'lines.vatRate', 'lines.ledger', 'lines.project']);

        return view('invoices.show', ['invoice' => $factuur, 'vat' => $factuur->vatBreakdown(), 'timeline' => $factuur->timeline()->with(['employee', 'user', 'files'])->paginate(25)]);
    }

    public function edit(Invoice $factuur): View|RedirectResponse
    {
        if (! $factuur->isEditable()) {
            return redirect()->route('facturen.show', $factuur)->withErrors(['factuur' => 'Een definitieve factuur kan niet worden gewijzigd. Maak een creditfactuur om te corrigeren.']);
        }

        return view('invoices.form', $this->formData($factuur->load('lines')) + ['prefill' => $factuur->lines, 'project' => $factuur->project]);
    }

    public function update(Request $request, Invoice $factuur): RedirectResponse
    {
        abort_unless($factuur->isEditable(), 422, 'Een definitieve factuur kan niet worden gewijzigd.');
        $data = $this->validated($request);
        DB::transaction(function () use ($factuur, $data) {
            $factuur->update(Arr::except($data, ['lines']));
            Regels::sync($factuur, $data['lines'] ?? [], self::LINE_EXTRA);
            $factuur->recalculate();
        });

        return redirect()->route('facturen.show', $factuur)->with('status', 'Factuur opgeslagen.');
    }

    public function destroy(Invoice $factuur): RedirectResponse
    {
        if (! $factuur->isEditable()) {
            return back()->withErrors(['factuur' => 'Alleen een conceptfactuur kan worden verwijderd.']);
        }
        $factuur->delete();

        return redirect()->route('facturen.index')->with('status', 'Conceptfactuur verwijderd.');
    }

    /** Definitief maken: nummer, datum en vervaldatum; daarna niet meer te wijzigen. */
    public function finalize(Request $request, Invoice $factuur): RedirectResponse
    {
        abort_unless($factuur->isEditable(), 422);
        if ($factuur->lines()->where('row_type', 'normaal')->doesntExist()) {
            return back()->withErrors(['factuur' => 'Een factuur zonder regels kan niet definitief worden gemaakt.']);
        }
        if (! $factuur->company_id) {
            return back()->withErrors(['factuur' => 'Kies eerst een relatie.']);
        }
        DB::transaction(function () use ($factuur, $request) {
            $factuur->recalculate();
            $factuur->finalize();
            $factuur->timeline()->create(['type' => 'systeem', 'body' => "Factuur {$factuur->number} definitief gemaakt.", 'occurred_at' => now(), 'user_id' => $request->user()->id, 'company_id' => $factuur->company_id]);
        });

        return back()->with('status', "Factuur {$factuur->number} is definitief.");
    }

    public function storePayment(Request $request, Invoice $factuur): RedirectResponse
    {
        abort_if($factuur->isEditable(), 422, 'Maak de factuur eerst definitief.');
        $request->merge(['amount' => Bedragen::parse($request->input('amount'))]);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0', 'between:-999999999,999999999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['amount' => 'bedrag', 'date' => 'datum']);
        DB::transaction(function () use ($factuur, $data, $request) {
            $factuur->payments()->create($data + ['user_id' => $request->user()->id]);
            $factuur->refreshPaid();
        });

        return back()->with('status', 'Betaling geregistreerd. Openstaand: € '.number_format((float) $factuur->total_open, 2, ',', '.'));
    }

    public function destroyPayment(Invoice $factuur, int $payment): RedirectResponse
    {
        DB::transaction(function () use ($factuur, $payment) {
            $factuur->payments()->findOrFail($payment)->delete();
            $factuur->refreshPaid();
        });

        return back()->with('status', 'Betaling verwijderd.');
    }

    /** Herinnering: volgende herinneringsfase vastleggen. De PDF toont daarna de herinnering. */
    public function remind(Request $request, Invoice $factuur): RedirectResponse
    {
        abort_unless($factuur->isOverdue(), 422, 'Alleen vervallen, openstaande facturen krijgen een herinnering.');
        $factuur->update(['reminder_level' => min(3, $factuur->reminder_level + 1), 'last_reminder_at' => now()]);
        $factuur->timeline()->create(['type' => 'systeem', 'body' => Invoice::REMINDERS[$factuur->reminder_level].' vastgelegd.', 'occurred_at' => now(), 'user_id' => $request->user()->id, 'company_id' => $factuur->company_id]);

        return back()->with('status', Invoice::REMINDERS[$factuur->reminder_level].' vastgelegd. Download de PDF om hem te versturen.');
    }

    /** Creditfactuur: concept met dezelfde regels, negatief. */
    public function credit(Invoice $factuur): RedirectResponse
    {
        abort_if($factuur->isEditable(), 422, 'Een concept kun je gewoon wijzigen of verwijderen.');
        $credit = DB::transaction(function () use ($factuur) {
            $credit = Invoice::create(Arr::only($factuur->getAttributes(), ['company_id', 'contact_id', 'project_id', 'contract_id', 'client_reference', 'payment_method'])
                + ['credit_of_id' => $factuur->id, 'subject' => "Creditering van factuur {$factuur->number}", 'status' => 'concept']);
            foreach ($factuur->lines as $line) {
                $credit->lines()->create(array_merge($line->only(['sort', 'row_type', 'product_id', 'title', 'description', 'unit_id', 'unit_price', 'buying_price', 'discount', 'vat_rate_id', 'ledger_id', 'cost_center_id', 'project_id', 'project_line_id']), ['quantity' => -1 * (float) $line->quantity]));
            }
            $credit->recalculate();

            return $credit;
        });

        return redirect()->route('facturen.edit', $credit)->with('status', 'Conceptcreditfactuur gemaakt. Controleer de regels en maak hem definitief.');
    }

    public function pdf(Invoice $factuur): Response
    {
        $factuur->load(['company', 'contact', 'creditOf', 'lines.unit', 'lines.vatRate', 'lines.product']);
        Audit::log('pdf', $factuur, $factuur->auditLabel());

        return Pdf::loadView('pdf.invoice', ['invoice' => $factuur, 'vat' => $factuur->vatBreakdown()])->setPaper('a4')
            ->stream(($factuur->reminder_level ? 'Herinnering ' : '').$factuur->title().'.pdf');
    }

    /** Voorstel: de nog niet (volledig) gefactureerde regels van de opdracht. */
    private function remainingLines(Project $project)
    {
        $done = $project->invoicedQuantities();

        return $project->lines->map(function ($l) use ($done, $project) {
            $rest = (float) $l->quantity - ($done[$l->id] ?? 0);
            if ($l->row_type === 'normaal' && $rest <= 0) {
                return null;
            }

            return $l->only(['row_type', 'product_id', 'title', 'description', 'unit_id', 'unit_price', 'buying_price', 'discount', 'vat_rate_id'])
                + ['quantity' => $l->row_type === 'normaal' ? $rest : 0, 'project_id' => $project->id, 'project_line_id' => $l->row_type === 'normaal' ? $l->id : null, 'ledger_id' => $l->product?->ledger_id];
        })->filter()->values();
    }

    /** Voorstel: één termijnregel met een percentage van het opdrachttotaal. */
    private function instalmentLine(Project $project, float $percentage)
    {
        $vat = $project->lines->where('row_type', 'normaal')->groupBy('vat_rate_id')->sortByDesc(fn ($g) => $g->count())->keys()->first();

        return collect([[
            'row_type' => 'normaal', 'title' => "Termijn {$percentage}% van opdracht {$project->number}", 'quantity' => 1,
            'unit_price' => round((float) $project->total_excl * $percentage / 100, 2), 'discount' => 0, 'vat_rate_id' => $vat ?: null, 'project_id' => $project->id,
        ]]);
    }

    private function formData(Invoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'companies' => Company::options(),
            'contacts' => Contact::query()->where('active', true)->when($invoice->company_id, fn ($q) => $q->where('company_id', $invoice->company_id))->orderBy('last_name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'projects' => Project::options(),
            'products' => Product::forPicker(),
            'units' => Unit::options(),
            'vatRates' => VatRate::options('verkoop'),
            'extra' => ['ledger_id' => ['Grootboek', Ledger::options()], 'cost_center_id' => ['Kostenplaats', CostCenter::options()]],
        ];
    }

    private function validated(Request $request): array
    {
        Regels::prepare($request);

        return $request->validate(Regels::rules([
            'company_id' => ['required', 'exists:companies,id'],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('company_id', $request->input('company_id'))],
            'project_id' => ['nullable', 'exists:projects,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:100000'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'payment_method' => ['required', Rule::in(array_keys(Company::BETAALWIJZEN))],
            'lines.*.ledger_id' => ['nullable', 'exists:ledgers,id'],
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'lines.*.project_id' => ['nullable', 'exists:projects,id'],
            'lines.*.project_line_id' => ['nullable', 'exists:project_lines,id'],
        ]), ['contact_id.exists' => 'De contactpersoon hoort niet bij de gekozen relatie.'], Regels::attributes() + ['subject' => 'onderwerp', 'due_date' => 'vervaldatum']);
    }
}
