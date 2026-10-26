<?php

namespace App\Http\Controllers;

use App\Models\Cost;
use App\Models\Memorial;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Memoriaal en kosten uit Gripp: alleen-lezen archief ter naslag. */
class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $year = $request->query('jaar');

        return view('bookings.index', [
            'memorials' => Memorial::with(['lines.ledger', 'lines.vatRate'])->when($year, fn ($q) => $q->whereYear('date', (int) $year))->orderByDesc('date')->orderByDesc('number')->get(),
            'costs' => Cost::with(['ledger', 'costCenter'])->when($year, fn ($q) => $q->whereYear('date', (int) $year))->orderByDesc('date')->orderByDesc('number')->paginate(100, ['*'], 'kosten')->withQueryString(),
            'year' => $year,
        ]);
    }
}
