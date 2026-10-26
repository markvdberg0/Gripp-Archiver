<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\RelatieSamenvoegen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Dubbele relaties opsporen en samenvoegen (recht relaties.samenvoegen). */
class CompanyMergeController extends Controller
{
    public function index(): View
    {
        return view('companies.duplicates', ['groups' => RelatieSamenvoegen::duplicates()]);
    }

    public function create(Request $request, Company $relatie): View
    {
        $target = $request->filled('doel') ? Company::whereKeyNot($relatie->id)->find($request->integer('doel')) : null;
        $candidates = RelatieSamenvoegen::duplicates()
            ->filter(fn ($g) => $g['relaties']->contains('id', $relatie->id))
            ->flatMap(fn ($g) => $g['relaties'])->unique('id')->reject(fn ($c) => $c->id === $relatie->id)->values();
        $results = collect();
        if ($q = trim((string) $request->query('q'))) {
            $results = Company::whereKeyNot($relatie->id)->search($q)->orderBy('name')->limit(20)->get();
        }

        return view('companies.merge', [
            'company' => $relatie, 'target' => $target, 'candidates' => $candidates, 'results' => $results,
            'preview' => $target ? RelatieSamenvoegen::preview($relatie) : [],
            'fill' => $target ? RelatieSamenvoegen::aanvulling($relatie, $target) : [],
        ]);
    }

    public function store(Request $request, Company $relatie): RedirectResponse
    {
        $data = $request->validate([
            'doel' => ['required', 'integer', 'exists:companies,id'],
            'aanvullen' => ['boolean'],
            'bevestig' => ['accepted'],
        ], ['bevestig.accepted' => 'Vink aan dat je begrijpt dat samenvoegen niet ongedaan te maken is.']);
        $target = Company::findOrFail($data['doel']);
        try {
            $moved = RelatieSamenvoegen::merge($relatie, $target, (bool) ($data['aanvullen'] ?? false));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['doel' => $e->getMessage()]);
        }

        return redirect()->route('relaties.show', $target)
            ->with('status', "Relatie {$relatie->number} {$relatie->name} is samengevoegd met deze relatie (".array_sum($moved).' koppelingen verplaatst).');
    }
}
