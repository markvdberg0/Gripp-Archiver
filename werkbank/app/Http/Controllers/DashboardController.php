<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Hour;
use App\Models\ImportRun;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\PurchaseInvoice;
use App\Models\Task;
use App\Models\TimelineEntry;
use App\Support\Format;
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
            'myTasks' => $user->employee ? Task::with('project')->open()->where('employee_id', $user->employee->id)->orderByRaw('deadline is null')->orderBy('deadline')->limit(8)->get() : collect(),
        ]);
    }

    /** Tegels voor offertes, facturen en inkoop, volgens de rechten van de gebruiker. */
    protected function extraTiles(Request $request): array
    {
        $user = $request->user();
        $tiles = [];
        if ($user->can('offertes.bekijken')) {
            $open = Offer::query()->whereNull('acceptance')->where('archived', false)->where('is_template', false)->where('status', 'verzonden');
            $tiles[] = ['Open offertes (verzonden)', (clone $open)->count().' · '.Format::euro((clone $open)->sum('total_excl')), route('offertes.index', ['status' => 'verzonden'])];
        }
        if ($user->can('facturen.bekijken')) {
            $tiles[] = ['Openstaand debiteuren', Format::euro(Invoice::query()->where('status', '!=', 'concept')->sum('total_open')), route('facturen.index', ['status' => 'open'])];
            $tiles[] = ['Vervallen facturen', (string) Invoice::query()->state('vervallen')->count(), route('facturen.index', ['status' => 'vervallen'])];
            $tiles[] = ['Conceptfacturen', (string) Invoice::query()->where('status', 'concept')->count(), route('facturen.index', ['status' => 'concept'])];
        }
        if ($user->can('inkoop.bekijken')) {
            $tiles[] = ['Openstaand crediteuren', Format::euro(PurchaseInvoice::query()->where('phase', '!=', 'concept')->sum('total_open')), route('inkoopfacturen.index', ['openstaand' => 1])];
            $tiles[] = ['Inkoopfacturen ter goedkeuring', (string) PurchaseInvoice::query()->whereIn('phase', ['concept', 'controle'])->where('on_hold', false)->count(), route('inkoopfacturen.index', ['fase' => 'controle'])];
        }
        if ($user->can('uren.fiatteren')) {
            $tiles[] = ['Uren te fiatteren', Format::number(Hour::query()->where('status', 'definitief')->sum('hours')), route('uren.fiatteren')];
        }

        return $tiles;
    }
}
