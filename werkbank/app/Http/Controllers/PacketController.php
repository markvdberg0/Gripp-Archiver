<?php

namespace App\Http\Controllers;

use App\Models\Packet;
use App\Models\Product;
use App\Models\Unit;
use App\Models\VatRate;
use App\Support\Regels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PacketController extends Controller
{
    public function index(): View
    {
        return view('packets.index', ['packets' => Packet::withCount('lines')->orderBy('archived')->orderBy('name')->get()]);
    }

    public function show(Packet $pakket): View
    {
        $pakket->load(['lines.unit', 'lines.vatRate', 'lines.product']);
        $t = $pakket->computeTotals();
        $pakket->forceFill(['total_excl' => $t['excl'], 'total_vat' => $t['vat'], 'total_incl' => $t['incl']]);

        return view('packets.show', ['packet' => $pakket, 'vat' => $pakket->vatBreakdown()]);
    }

    public function create(): View
    {
        return view('packets.form', $this->formData(new Packet));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $packet = DB::transaction(function () use ($data) {
            $packet = Packet::create(Arr::except($data, ['lines']));
            Regels::sync($packet, $data['lines'] ?? []);

            return $packet;
        });

        return redirect()->route('pakketten.show', $packet)->with('status', 'Pakket aangemaakt.');
    }

    public function edit(Packet $pakket): View
    {
        return view('packets.form', $this->formData($pakket->load('lines')));
    }

    public function update(Request $request, Packet $pakket): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($pakket, $data) {
            $pakket->update(Arr::except($data, ['lines']));
            Regels::sync($pakket, $data['lines'] ?? []);
        });

        return redirect()->route('pakketten.show', $pakket)->with('status', 'Pakket opgeslagen.');
    }

    private function formData(Packet $packet): array
    {
        return ['packet' => $packet, 'products' => Product::forPicker(), 'units' => Unit::options(), 'vatRates' => VatRate::options('verkoop')];
    }

    private function validated(Request $request): array
    {
        Regels::prepare($request);

        return $request->validate(Regels::rules([
            'number' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'archived' => ['boolean'],
        ]), [], Regels::attributes() + ['name' => 'naam']);
    }
}
