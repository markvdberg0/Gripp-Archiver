<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimelineEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class CompanyController extends Controller
{
    private const SORT = ['name' => 'name', 'number' => 'number', 'city' => 'visit_city', 'created' => 'created_at'];

    public function index(Request $request): View
    {
        $sort = self::SORT[$request->query('sort')] ?? 'name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $companies = Company::query()
            ->search($request->query('q'))
            ->when($request->query('rol'), fn ($q, $rol) => array_key_exists($rol, Company::ROLLEN) ? $q->where($rol, true) : $q)
            ->when($request->query('actief', '1') !== 'alle', fn ($q) => $q->where('active', $request->query('actief', '1') === '1'))
            ->with('accountManager')
            ->withCount('contacts')
            ->orderBy($sort, $dir)->orderBy('id')
            ->paginate(50)->withQueryString();

        return view('companies.index', compact('companies'));
    }

    public function create(): View
    {
        return view('companies.form', ['company' => new Company(['relation_type' => 'bedrijf', 'is_customer' => true, 'active' => true, 'invoice_send_by' => 'email', 'payment_method' => 'overboeking', 'payment_term_days' => 30]), 'employees' => Employee::options()]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = new Company(Arr::except($request->validated(), ['tags']));
        $company->number ??= (int) Company::withTrashed()->max('number') + 1;
        $company->save();
        $company->syncTagNames(explode(',', (string) $request->input('tags')));

        return redirect()->route('relaties.show', $company)->with('status', 'Relatie aangemaakt.');
    }

    public function show(Request $request, Company $relatie): View
    {
        $relatie->load(['accountManager', 'tags', 'files', 'contacts']);
        $timeline = $relatie->timeline()->with(['employee', 'user', 'files'])->paginate(25, ['*'], 'tl')->withQueryString();

        return view('companies.show', ['company' => $relatie, 'timeline' => $timeline, 'related' => $this->related($relatie)]);
    }

    /** Documenten bij de relatie; wordt per fase aangevuld. */
    private function related(Company $company): array
    {
        $out = [];
        foreach (['offers' => 'offertes.bekijken', 'projects' => 'opdrachten.bekijken', 'invoices' => 'facturen.bekijken', 'purchaseOrders' => 'inkoop.bekijken', 'purchaseInvoices' => 'inkoop.bekijken'] as $rel => $ability) {
            if (method_exists($company, $rel) && auth()->user()->can($ability)) {
                $out[$rel] = $company->{$rel}()->latest('date')->limit(10)->get();
            }
        }

        return $out;
    }

    public function edit(Company $relatie): View
    {
        return view('companies.form', ['company' => $relatie->load('tags'), 'employees' => Employee::options()]);
    }

    public function update(CompanyRequest $request, Company $relatie): RedirectResponse
    {
        $relatie->update(Arr::except($request->validated(), ['tags']));
        $relatie->syncTagNames(explode(',', (string) $request->input('tags')));

        return redirect()->route('relaties.show', $relatie)->with('status', 'Relatie opgeslagen.');
    }

    public function destroy(Company $relatie): RedirectResponse
    {
        $this->authorize('relaties.bewerken');
        foreach (['offers', 'projects', 'invoices', 'purchaseOrders', 'purchaseInvoices'] as $rel) {
            if (method_exists($relatie, $rel) && $relatie->{$rel}()->exists()) {
                return back()->withErrors(['relatie' => 'Deze relatie heeft documenten en kan niet worden verwijderd. Zet hem op inactief.']);
            }
        }
        $relatie->delete();

        return redirect()->route('relaties.index')->with('status', "Relatie {$relatie->name} verwijderd. Een beheerder kan dit via het logboek terugzien.");
    }

    public function storeNote(Request $request, Company $relatie): RedirectResponse
    {
        $this->authorize('relaties.bewerken');
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(TimelineEntry::TYPES))],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
        ]);
        $relatie->timeline()->create($data + ['occurred_at' => now(), 'user_id' => $request->user()->id, 'employee_id' => $request->user()->employee?->id]);

        return redirect()->route('relaties.show', $relatie)->with('status', 'Notitie toegevoegd.');
    }
}
