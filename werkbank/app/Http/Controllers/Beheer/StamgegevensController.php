<?php

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\Ledger;
use App\Models\OfferPhase;
use App\Models\Unit;
use App\Models\VatRate;
use App\Support\Audit;
use App\Support\Bedragen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Eenvoudige keuzelijsten (btw-tarieven, eenheden, grootboek, kostenplaatsen, fasen). Records worden niet
 * verwijderd (er kan naar verwezen worden); zet ze op inactief waar dat kan.
 */
class StamgegevensController extends Controller
{
    /** soort => [model, titel, velden: naam => [label, type, regels, opties?]] */
    protected function types(): array
    {
        $types = [
            'btw' => [VatRate::class, 'Btw-tarieven', [
                'name' => ['Naam', 'text', ['required', 'string', 'max:100']],
                'percentage' => ['Percentage', 'decimal', ['required', 'numeric', 'between:0,100']],
                'kind' => ['Gebruik', 'select', ['required', 'in:verkoop,inkoop,beide'], VatRate::KINDS],
                'reverse_charge' => ['Btw verlegd (wel tonen, niet betalen)', 'bool', ['boolean']],
                'active' => ['Actief', 'bool', ['boolean']],
            ]],
            'eenheden' => [Unit::class, 'Eenheden', [
                'name' => ['Naam', 'text', ['required', 'string', 'max:50']],
                'type' => ['Soort', 'select', ['required', 'in:aantal,uren'], Unit::TYPES],
                'hours_per_unit' => ['Uren per eenheid', 'decimal', ['nullable', 'numeric', 'min:0']],
            ]],
            'grootboek' => [Ledger::class, 'Grootboekrekeningen', [
                'number' => ['Nummer', 'text', ['required', 'string', 'max:20']],
                'name' => ['Naam', 'text', ['required', 'string', 'max:255']],
                'type' => ['Soort', 'select', ['nullable', 'in:resultaat,balans'], ['resultaat' => 'Resultaat', 'balans' => 'Balans']],
                'side' => ['Debet/credit', 'select', ['nullable', 'in:debet,credit'], ['debet' => 'Debet (inkoop)', 'credit' => 'Credit (verkoop)']],
                'category' => ['Categorie', 'text', ['nullable', 'string', 'max:255']],
                'active' => ['Actief', 'bool', ['boolean']],
            ]],
            'kostenplaatsen' => [CostCenter::class, 'Kostenplaatsen', [
                'number' => ['Code', 'text', ['nullable', 'string', 'max:20']],
                'name' => ['Naam', 'text', ['required', 'string', 'max:255']],
                'active' => ['Actief', 'bool', ['boolean']],
            ]],
            'offertefasen' => [OfferPhase::class, 'Offertefasen', [
                'name' => ['Naam', 'text', ['required', 'string', 'max:100']],
                'sort' => ['Volgorde', 'number', ['nullable', 'integer', 'min:0']],
            ]],
        ];

        return $types + $this->extraTypes();
    }

    /** Fasen uit latere fasen (opdrachten, taken). */
    protected function extraTypes(): array
    {
        $out = [];
        foreach (['opdrachtfasen' => ['App\Models\ProjectPhase', 'Opdrachtfasen'], 'taakfasen' => ['App\Models\TaskPhase', 'Taakfasen'], 'taaksoorten' => ['App\Models\TaskType', 'Taaksoorten']] as $key => [$class, $title]) {
            if (class_exists($class)) {
                $out[$key] = [$class, $title, ['name' => ['Naam', 'text', ['required', 'string', 'max:100']], 'sort' => ['Volgorde', 'number', ['nullable', 'integer', 'min:0']]]];
            }
        }

        return $out;
    }

    public function index(): View
    {
        $types = collect($this->types())->map(fn ($t) => ['title' => $t[1], 'count' => $t[0]::count()]);

        return view('beheer.stamgegevens.index', compact('types'));
    }

    public function show(string $soort): View
    {
        [$class, $title, $fields] = $this->type($soort);
        $order = array_key_exists('sort', $fields) ? 'sort' : (array_key_exists('number', $fields) ? 'number' : 'name');

        return view('beheer.stamgegevens.show', ['soort' => $soort, 'title' => $title, 'fields' => $fields, 'items' => $class::query()->orderBy($order)->orderBy('id')->get(), 'item' => new $class]);
    }

    public function store(Request $request, string $soort): RedirectResponse
    {
        [$class, , $fields] = $this->type($soort);
        $class::create($this->validated($request, $fields));

        return redirect()->route('beheer.stamgegevens.show', $soort)->with('status', 'Toegevoegd.');
    }

    public function edit(string $soort, int $id): View
    {
        [$class, $title, $fields] = $this->type($soort);

        return view('beheer.stamgegevens.edit', ['soort' => $soort, 'title' => $title, 'fields' => $fields, 'item' => $class::findOrFail($id)]);
    }

    public function update(Request $request, string $soort, int $id): RedirectResponse
    {
        [$class, , $fields] = $this->type($soort);
        /** @var Model $item */
        $item = $class::findOrFail($id);
        $item->fill($this->validated($request, $fields));
        if ($item->isDirty()) {
            Audit::log('gewijzigd', $item, class_basename($item).' '.($item->name ?? $item->id), Audit::diff(tap($item)->save()));
        }

        return redirect()->route('beheer.stamgegevens.show', $soort)->with('status', 'Opgeslagen.');
    }

    private function type(string $soort): array
    {
        return $this->types()[$soort] ?? abort(404);
    }

    private function validated(Request $request, array $fields): array
    {
        foreach ($fields as $name => [, $type]) {
            if ($type === 'decimal') {
                $request->merge([$name => Bedragen::parse($request->input($name))]);
            }
        }

        return $request->validate(collect($fields)->map(fn ($f) => $f[2])->all(), [], collect($fields)->map(fn ($f) => mb_strtolower($f[0]))->all());
    }
}
