<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\TimelineEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $tiles = [];
        if ($user->can('relaties.bekijken')) {
            $tiles[] = ['Actieve klanten', Company::where('active', true)->where('is_customer', true)->count(), route('relaties.index', ['rol' => 'is_customer'])];
            $tiles[] = ['Leveranciers', Company::where('active', true)->where('is_supplier', true)->count(), route('relaties.index', ['rol' => 'is_supplier'])];
            $tiles[] = ['Contactpersonen', Contact::where('active', true)->count(), route('contactpersonen.index')];
        }

        return view('dashboard', [
            'tiles' => array_merge($tiles, $this->extraTiles($request)),
            'recent' => $user->can('relaties.bekijken')
                ? TimelineEntry::with(['company', 'employee', 'user'])->where('type', '!=', 'systeem')->latest('occurred_at')->limit(10)->get()
                : collect(),
            'lastImport' => $user->can('beheer.import') ? ImportRun::where('dry_run', false)->latest()->first() : null,
        ]);
    }

    /** Tegels uit latere fasen (offertes, facturen, inkoop). */
    protected function extraTiles(Request $request): array
    {
        return [];
    }
}
