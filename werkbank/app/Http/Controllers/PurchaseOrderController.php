<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Unit;
use App\Models\VatRate;
use App\Support\Audit;
use App\Support\Nummering;
use App\Support\Regels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    private const LINE_EXTRA = ['ledger_id', 'cost_center_id', 'project_id'];

    public function index(Request $request): View
    {
        $base = PurchaseOrder::query()->search($request->query('q'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('leverancier'), fn ($q, $c) => $q->where('supplier_id', $c))
            ->when($request->query('opdracht'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('archief') !== '1', fn ($q) => $q->where('archived', false));
        $sum = (clone $base)->selectRaw('count(*) as n, coalesce(sum(total_excl),0) as total')->toBase()->first();
        $orders = $base->with(['supplier', 'project'])->orderByDesc('number')->paginate(50)->withQueryString();

        return view('purchase_orders.index', ['orders' => $orders, 'sum' => $sum, 'suppliers' => Company::options('is_supplier')]);
    }

    public function create(Request $request): View
    {
        $project = $request->integer('project_id') ? Project::find($request->integer('project_id')) : null;

        return view('purchase_orders.form', $this->formData(new PurchaseOrder([
            'date' => today(), 'status' => 'concept', 'project_id' => $project?->id, 'employee_id' => $request->user()->employee?->id,
            'supplier_id' => $request->integer('supplier_id') ?: null,
        ])) + ['nextNumber' => Nummering::peek('inkoopopdracht', 'purchase_orders')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $order = DB::transaction(function () use ($data) {
            $order = PurchaseOrder::create(Arr::except($data, ['lines']) + ['number' => Nummering::next('inkoopopdracht', 'purchase_orders'), 'status' => 'concept']);
            Regels::sync($order, $this->withProject($data), self::LINE_EXTRA);
            $order->recalculate();

            return $order;
        });

        return redirect()->route('inkoopopdrachten.show', $order)->with('status', "Inkoopopdracht {$order->number} aangemaakt.");
    }

    public function show(PurchaseOrder $inkoopopdracht): View
    {
        $inkoopopdracht->load(['supplier', 'contact', 'project', 'employee', 'files', 'invoices', 'lines.unit', 'lines.vatRate', 'lines.ledger', 'lines.project']);

        return view('purchase_orders.show', [
            'order' => $inkoopopdracht, 'vat' => $inkoopopdracht->vatBreakdown(), 'invoiced' => $inkoopopdracht->invoicedExcl(),
            'timeline' => $inkoopopdracht->timeline()->with(['employee', 'user', 'files'])->paginate(25),
        ]);
    }

    public function edit(PurchaseOrder $inkoopopdracht): View
    {
        return view('purchase_orders.form', $this->formData($inkoopopdracht->load('lines')));
    }

    public function update(Request $request, PurchaseOrder $inkoopopdracht): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($inkoopopdracht, $data) {
            $inkoopopdracht->update(Arr::except($data, ['lines']));
            Regels::sync($inkoopopdracht, $this->withProject($data), self::LINE_EXTRA);
            $inkoopopdracht->recalculate();
        });

        return redirect()->route('inkoopopdrachten.show', $inkoopopdracht)->with('status', 'Inkoopopdracht opgeslagen.');
    }

    public function destroy(PurchaseOrder $inkoopopdracht): RedirectResponse
    {
        if ($inkoopopdracht->status !== 'concept' || $inkoopopdracht->invoices()->exists()) {
            return back()->withErrors(['inkoopopdracht' => 'Alleen een concept zonder inkoopfacturen kan worden verwijderd. Archiveer hem anders.']);
        }
        $inkoopopdracht->delete();

        return redirect()->route('inkoopopdrachten.index')->with('status', 'Inkoopopdracht verwijderd.');
    }

    public function status(Request $request, PurchaseOrder $inkoopopdracht, string $status): RedirectResponse
    {
        if ($status === 'archiveren') {
            $inkoopopdracht->update(['archived' => ! $inkoopopdracht->archived]);

            return back()->with('status', $inkoopopdracht->archived ? 'Gearchiveerd.' : 'Uit het archief gehaald.');
        }
        abort_unless(array_key_exists($status, PurchaseOrder::STATUS), 404);
        $before = $inkoopopdracht->status;
        $inkoopopdracht->update(['status' => $status]);
        $inkoopopdracht->timeline()->create(['type' => 'systeem', 'body' => "Status gewijzigd van {$before} naar {$status}.", 'occurred_at' => now(), 'user_id' => $request->user()->id, 'company_id' => $inkoopopdracht->supplier_id]);

        return back()->with('status', 'Status: '.PurchaseOrder::STATUS[$status].'.');
    }

    public function pdf(PurchaseOrder $inkoopopdracht): Response
    {
        $inkoopopdracht->load(['supplier', 'contact', 'employee', 'lines.unit', 'lines.vatRate', 'lines.product']);
        Audit::log('pdf', $inkoopopdracht, $inkoopopdracht->auditLabel());

        return Pdf::loadView('pdf.purchase_order', ['order' => $inkoopopdracht, 'vat' => $inkoopopdracht->vatBreakdown()])->setPaper('a4')
            ->stream("Inkoopopdracht {$inkoopopdracht->number}.pdf");
    }

    /** Regels zonder eigen opdracht krijgen de opdracht van de inkoopopdracht. */
    private function withProject(array $data): array
    {
        return array_map(fn ($l) => $l + ['project_id' => $data['project_id'] ?? null], $data['lines'] ?? []);
    }

    private function formData(PurchaseOrder $order): array
    {
        return [
            'order' => $order, 'suppliers' => Company::options('is_supplier') + ($order->supplier_id ? [$order->supplier_id => $order->supplier?->name] : []),
            'contacts' => Contact::query()->where('active', true)->when($order->supplier_id, fn ($q) => $q->where('company_id', $order->supplier_id))->orderBy('last_name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'projects' => Project::options(), 'employees' => Employee::options(),
            'products' => Product::forPicker(), 'units' => Unit::options(), 'vatRates' => VatRate::options('inkoop'),
            'extra' => ['ledger_id' => ['Grootboek', Ledger::options()], 'cost_center_id' => ['Kostenplaats', CostCenter::options()]],
        ];
    }

    private function validated(Request $request): array
    {
        Regels::prepare($request);

        return $request->validate(Regels::rules([
            'name' => ['required', 'string', 'max:255'],
            'supplier_id' => ['required', 'exists:companies,id'],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('company_id', $request->input('supplier_id'))],
            'project_id' => ['nullable', 'exists:projects,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:100000'],
            'lines.*.ledger_id' => ['nullable', 'exists:ledgers,id'],
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
        ]), ['contact_id.exists' => 'De contactpersoon hoort niet bij de gekozen leverancier.'], Regels::attributes() + ['name' => 'omschrijving', 'supplier_id' => 'leverancier']);
    }
}
