<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Unit;
use App\Models\VatRate;
use App\Support\ContractFacturatie;
use App\Support\Regels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $contracts = Contract::with('company')
            ->when($request->query('status', 'actief') !== 'alle', fn ($q) => $q->where('status', $request->query('status', 'actief')))
            ->orderBy('number')->get();
        $due = $contracts->filter(fn ($c) => $c->isDue())->count();

        return view('contracts.index', compact('contracts', 'due'));
    }

    public function show(Contract $contract): View
    {
        $contract->load(['company', 'contact', 'lines.unit', 'lines.vatRate', 'invoices', 'files']);

        return view('contracts.show', ['contract' => $contract, 'vat' => $contract->vatBreakdown()]);
    }

    public function create(Request $request): View
    {
        return view('contracts.form', $this->formData(new Contract(['status' => 'actief', 'frequency' => 'maand', 'start_date' => today(), 'next_invoice_date' => today(), 'company_id' => $request->integer('company_id') ?: null])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $contract = DB::transaction(function () use ($data) {
            $contract = Contract::create(Arr::except($data, ['lines']) + ['number' => (int) Contract::withTrashed()->max('number') + 1]);
            Regels::sync($contract, $data['lines'] ?? [], ['ledger_id']);
            $contract->recalculate();

            return $contract;
        });

        return redirect()->route('contracten.show', $contract)->with('status', 'Contract aangemaakt.');
    }

    public function edit(Contract $contract): View
    {
        return view('contracts.form', $this->formData($contract->load('lines')));
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($contract, $data) {
            $contract->update(Arr::except($data, ['lines']));
            Regels::sync($contract, $data['lines'] ?? [], ['ledger_id']);
            $contract->recalculate();
        });

        return redirect()->route('contracten.show', $contract)->with('status', 'Contract opgeslagen.');
    }

    /** Facturen aanmaken voor alle contracten die aan de beurt zijn (zelfde als de geplande taak). */
    public function invoiceDue(): RedirectResponse
    {
        $made = ContractFacturatie::run();

        return redirect()->route('facturen.index', ['status' => 'concept'])->with('status', count($made).' conceptfactuur/-facturen aangemaakt.');
    }

    private function formData(Contract $contract): array
    {
        return [
            'contract' => $contract, 'companies' => Company::options(),
            'contacts' => Contact::query()->where('active', true)->when($contract->company_id, fn ($q) => $q->where('company_id', $contract->company_id))->orderBy('last_name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'products' => Product::forPicker(), 'units' => Unit::options(), 'vatRates' => VatRate::options('verkoop'),
            'extra' => ['ledger_id' => ['Grootboek', Ledger::options()]],
        ];
    }

    private function validated(Request $request): array
    {
        Regels::prepare($request);

        return $request->validate(Regels::rules([
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['required', 'exists:companies,id'],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('company_id', $request->input('company_id'))],
            'status' => ['required', Rule::in(array_keys(Contract::STATUS))],
            'frequency' => ['required', Rule::in(array_keys(Contract::FREQUENCIES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'next_invoice_date' => ['nullable', 'date'],
            'auto_renew' => ['boolean'],
            'invoice_subject' => ['nullable', 'string', 'max:255'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'lines.*.ledger_id' => ['nullable', 'exists:ledgers,id'],
        ]), [], Regels::attributes() + ['name' => 'omschrijving', 'start_date' => 'startdatum', 'end_date' => 'einddatum']);
    }
}
