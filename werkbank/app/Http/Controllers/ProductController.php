<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Ledger;
use App\Models\OfferLine;
use App\Models\Product;
use App\Models\Unit;
use App\Models\VatRate;
use App\Support\Bedragen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()->search($request->query('q'))
            ->when($request->query('archief') !== '1', fn ($q) => $q->where('archived', false))
            ->with(['unit', 'supplier'])
            ->orderBy($request->query('sort') === 'number' ? 'number' : 'name')
            ->paginate(50)->withQueryString();

        return view('products.index', compact('products'));
    }

    public function show(Product $product): View
    {
        $product->load(['unit', 'vatRate', 'purchaseVatRate', 'ledger', 'purchaseLedger', 'costCenter', 'supplier', 'files', 'tags']);
        $usage = OfferLine::where('product_id', $product->id)->with('offer')->latest('id')->limit(15)->get();

        return view('products.show', compact('product', 'usage'));
    }

    public function create(): View
    {
        return view('products.form', $this->formData(new Product(['invoice_basis' => 'vast', 'vat_rate_id' => VatRate::options('verkoop')[0]->id ?? null])));
    }

    public function store(Request $request): RedirectResponse
    {
        $product = Product::create(Arr::except($this->validated($request), ['tags']));
        $product->syncTagNames(explode(',', (string) $request->input('tags')));

        return redirect()->route('producten.show', $product)->with('status', 'Product aangemaakt.');
    }

    public function edit(Product $product): View
    {
        return view('products.form', $this->formData($product->load('tags')));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update(Arr::except($this->validated($request, $product), ['tags']));
        $product->syncTagNames(explode(',', (string) $request->input('tags')));

        return redirect()->route('producten.show', $product)->with('status', 'Product opgeslagen.');
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product, 'units' => Unit::options(),
            'vatSales' => collect(VatRate::options('verkoop'))->pluck('name', 'id')->all(),
            'vatPurchase' => collect(VatRate::options('inkoop'))->pluck('name', 'id')->all(),
            'ledgers' => Ledger::options(), 'costCenters' => CostCenter::options(), 'suppliers' => Company::options('is_supplier'),
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $request->merge(['selling_price' => Bedragen::parse($request->input('selling_price')) ?? 0, 'buying_price' => Bedragen::parse($request->input('buying_price')) ?? 0]);

        return $request->validate([
            'number' => ['nullable', 'integer', 'min:1', Rule::unique('products', 'number')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'internal_note' => ['nullable', 'string', 'max:10000'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'selling_price' => ['required', 'numeric', 'between:-999999999,999999999'],
            'buying_price' => ['required', 'numeric', 'between:-999999999,999999999'],
            'vat_rate_id' => ['nullable', 'exists:vat_rates,id'],
            'purchase_vat_rate_id' => ['nullable', 'exists:vat_rates,id'],
            'ledger_id' => ['nullable', 'exists:ledgers,id'],
            'purchase_ledger_id' => ['nullable', 'exists:ledgers,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'supplier_id' => ['nullable', 'exists:companies,id'],
            'supplier_code' => ['nullable', 'string', 'max:100'],
            'invoice_basis' => ['required', Rule::in(array_keys(Product::INVOICE_BASIS))],
            'archived' => ['boolean'],
            'tags' => ['nullable', 'string', 'max:500'],
        ], [], ['name' => 'naam', 'selling_price' => 'verkoopprijs', 'buying_price' => 'inkoopprijs']);
    }
}
