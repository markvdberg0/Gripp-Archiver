<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Support\Zoek;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /** Zoekbronnen: label => [model, recht, kolommen, route, weergave]. Wordt per fase aangevuld. */
    private function sources(): array
    {
        $sources = [
            'Relaties' => [Company::class, 'relaties.bekijken', ['name', 'legal_name', 'email', 'visit_city', 'number'], 'relaties.show', fn ($m) => [$m->name, $m->visit_city]],
            'Contactpersonen' => [Contact::class, 'relaties.bekijken', ['first_name', 'last_name', 'email'], 'contactpersonen.show', fn ($m) => [$m->name, $m->email]],
        ];
        foreach ([
            'Offertes' => ['App\Models\Offer', 'offertes.bekijken', ['name', 'number'], 'offertes.show'],
            'Opdrachten' => ['App\Models\Project', 'opdrachten.bekijken', ['name', 'number'], 'opdrachten.show'],
            'Producten' => ['App\Models\Product', 'producten.bekijken', ['name', 'number', 'supplier_code'], 'producten.show'],
            'Verkoopfacturen' => ['App\Models\Invoice', 'facturen.bekijken', ['subject', 'number'], 'facturen.show'],
            'Inkoopopdrachten' => ['App\Models\PurchaseOrder', 'inkoop.bekijken', ['name', 'number'], 'inkoopopdrachten.show'],
            'Inkoopfacturen' => ['App\Models\PurchaseInvoice', 'inkoop.bekijken', ['number', 'subject'], 'inkoopfacturen.show'],
        ] as $label => [$class, $ability, $cols, $route]) {
            if (class_exists($class)) {
                $sources[$label] = [$class, $ability, $cols, $route, fn ($m) => [trim(($m->number ?? '').' '.($m->name ?? $m->subject ?? '')), $m->company?->name ?? null]];
            }
        }

        return $sources;
    }

    public function __invoke(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $results = [];
        if (mb_strlen($q) >= 2) {
            foreach ($this->sources() as $label => [$class, $ability, $cols, $route, $show]) {
                if (! $request->user()->can($ability)) {
                    continue;
                }
                $query = Zoek::in($class::query(), $q, $cols)->limit(20);
                if (method_exists($class, 'company')) {
                    $query->with('company');
                }
                $items = $query->get();
                if ($items->isNotEmpty()) {
                    $results[$label] = $items->map(fn ($m) => ['url' => route($route, $m), 'title' => $show($m)[0], 'sub' => $show($m)[1]]);
                }
            }
        }

        return view('search', compact('q', 'results'));
    }
}
