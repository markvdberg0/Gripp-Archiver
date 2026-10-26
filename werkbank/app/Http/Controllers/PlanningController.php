<?php

namespace App\Http\Controllers;

use App\Models\CalendarItem;
use App\Models\Employee;
use App\Models\LeaveRequestLine;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Weekplanning: agenda-items per medewerker per dag. */
class PlanningController extends Controller
{
    public function index(Request $request): View
    {
        try {
            $week = ($request->filled('week') ? CarbonImmutable::parse($request->input('week')) : CarbonImmutable::today())->startOfWeek();
        } catch (\Throwable) {
            $week = CarbonImmutable::today()->startOfWeek();
        }
        $items = CalendarItem::with(['task.project', 'employee'])
            ->whereDate('date', '>=', $week->toDateString())->whereDate('date', '<=', $week->addDays(6)->toDateString())->orderBy('start_time')->get();
        $employees = Employee::query()->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $items->pluck('employee_id')->filter()))
            ->orderBy('first_name')->get();

        // Verlof (goedgekeurd en aangevraagd) als afwezigheid; geen reden of omschrijving, alleen uren.
        $leave = LeaveRequestLine::query()->with('request:id,employee_id,status')
            ->whereDate('date', '>=', $week->toDateString())->whereDate('date', '<=', $week->addDays(6)->toDateString())
            ->whereHas('request', fn ($q) => $q->whereIn('status', ['goedgekeurd', 'aangevraagd']))->get()
            ->groupBy(fn ($l) => $l->request->employee_id.'|'.$l->date->toDateString())
            ->map(fn ($lines) => $lines->map(fn ($l) => ['hours' => $l->hours, 'status' => $l->request->status])->all());

        return view('planning.index', ['week' => $week, 'employees' => $employees, 'leave' => $leave, 'grid' => $items->groupBy(fn ($i) => ($i->employee_id ?? 0).'|'.$i->date->toDateString())]);
    }

    public function store(Request $request): RedirectResponse
    {
        CalendarItem::create($request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'subject' => ['required', 'string', 'max:255'],
        ], [], ['subject' => 'onderwerp', 'hours' => 'uren', 'date' => 'datum']));

        return back()->with('status', 'Ingepland.');
    }

    public function destroy(CalendarItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('status', 'Agenda-item verwijderd.');
    }
}
