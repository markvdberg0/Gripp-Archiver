<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Hour;
use App\Models\Project;
use App\Models\ProjectLine;
use App\Support\Audit;
use App\Support\Bedragen;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Urenregistratie per week. Iedereen schrijft eigen uren (gekoppelde medewerker); met het recht
 * uren.fiatteren kun je uren van anderen bekijken, invoeren en fiatteren.
 */
class HourController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $this->employee($request);
        $week = $this->week($request);
        $hours = $employee ? Hour::with(['project', 'projectLine', 'offer'])->where('employee_id', $employee->id)
            ->whereBetween('date', [$week->toDateString(), $week->addDays(6)->toDateString()])->orderBy('date')->orderBy('id')->get() : collect();
        $projectId = $request->integer('project_id') ?: null;

        return view('hours.index', [
            'employee' => $employee, 'week' => $week, 'hours' => $hours,
            'employees' => $request->user()->can('uren.fiatteren') ? Employee::options() : [],
            'projects' => Project::options(), 'projectId' => $projectId,
            'lines' => $projectId ? ProjectLine::where('project_id', $projectId)->where('row_type', 'normaal')->orderBy('sort')->get()->mapWithKeys(fn ($l) => [$l->id => $l->label()])->all() : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employee($request) ?? abort(422, 'Je account is niet gekoppeld aan een medewerker.');
        Hour::create($this->validated($request) + ['employee_id' => $employee->id, 'status' => 'concept']);

        return back()->with('status', 'Uren toegevoegd.');
    }

    public function update(Request $request, Hour $uur): RedirectResponse
    {
        $this->authorizeOwn($request, $uur);
        abort_unless($uur->isEditable(), 422, 'Alleen uren met status concept kunnen worden gewijzigd.');
        $uur->update($this->validated($request));

        return back()->with('status', 'Uren opgeslagen.');
    }

    public function destroy(Request $request, Hour $uur): RedirectResponse
    {
        $this->authorizeOwn($request, $uur);
        abort_unless($uur->isEditable(), 422, 'Alleen uren met status concept kunnen worden verwijderd.');
        $uur->delete();

        return back()->with('status', 'Uren verwijderd.');
    }

    /** Alle concepturen van de week definitief maken (indienen). */
    public function submit(Request $request): RedirectResponse
    {
        $employee = $this->employee($request) ?? abort(422);
        $week = $this->week($request);
        $n = Hour::where('employee_id', $employee->id)->where('status', 'concept')
            ->whereBetween('date', [$week->toDateString(), $week->addDays(6)->toDateString()])->update(['status' => 'definitief', 'updated_at' => now()]);
        Audit::log('uren_ingediend', null, "{$n} regel(s) van {$employee->name}, week ".$week->isoWeek());

        return back()->with('status', "{$n} regel(s) definitief gemaakt.");
    }

    public function approvals(Request $request): View
    {
        $hours = Hour::with(['employee', 'project', 'projectLine'])->where('status', 'definitief')
            ->when($request->query('medewerker'), fn ($q, $e) => $q->where('employee_id', $e))
            ->when($request->query('opdracht'), fn ($q, $p) => $q->where('project_id', $p))
            ->orderBy('date')->orderBy('employee_id')->paginate(100)->withQueryString();

        return view('hours.approvals', ['hours' => $hours, 'employees' => Employee::options(), 'projects' => Project::options()]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer'], 'actie' => ['required', 'in:fiatteren,afwijzen']]);
        $query = Hour::whereIn('id', $data['ids'])->where('status', 'definitief');
        // Eigen uren fiatteert iemand anders.
        if ($data['actie'] === 'fiatteren' && ($me = $request->user()->employee?->id)) {
            $query->where('employee_id', '!=', $me);
        }
        $n = $data['actie'] === 'fiatteren'
            ? $query->update(['status' => 'gefiatteerd', 'approved_by' => $request->user()->employee?->id, 'approved_at' => now(), 'updated_at' => now()])
            : $query->update(['status' => 'concept', 'updated_at' => now()]);
        Audit::log('uren_'.($data['actie'] === 'fiatteren' ? 'gefiatteerd' : 'teruggezet'), null, "{$n} regel(s)", ['ids' => $data['ids']]);

        return back()->with('status', $data['actie'] === 'fiatteren' ? "{$n} regel(s) gefiatteerd." : "{$n} regel(s) teruggezet naar concept.");
    }

    private function employee(Request $request): ?Employee
    {
        if ($request->filled('medewerker') && $request->user()->can('uren.fiatteren')) {
            return Employee::find($request->integer('medewerker'));
        }

        return $request->user()->employee;
    }

    private function week(Request $request): CarbonImmutable
    {
        try {
            $d = $request->filled('week') ? CarbonImmutable::parse($request->input('week')) : CarbonImmutable::today();
        } catch (\Throwable) {
            $d = CarbonImmutable::today();
        }

        return $d->startOfWeek();
    }

    private function authorizeOwn(Request $request, Hour $hour): void
    {
        abort_unless($hour->employee_id === $request->user()->employee?->id || $request->user()->can('uren.fiatteren'), 403);
    }

    private function validated(Request $request): array
    {
        $request->merge(['hours' => Bedragen::parse($request->input('hours'))]);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'project_id' => ['required', 'exists:projects,id'],
            'project_line_id' => ['nullable', Rule::exists('project_lines', 'id')->where('project_id', $request->input('project_id'))],
            'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], ['project_id' => 'opdracht', 'project_line_id' => 'regel', 'hours' => 'uren', 'date' => 'datum']);

        return $data;
    }
}
