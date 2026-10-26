<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestLine;
use App\Notifications\VerlofBeslissing;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Verlof aanvragen (eigen verlof) en goedkeuren (administratie). Eigen verlof keurt iemand anders goed.
 * Wie mag goedkeuren, ziet alle aanvragen; anderen alleen die van zichzelf.
 */
class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $year = (int) $request->query('jaar', now()->year);
        $all = $user->can('verlof.fiatteren');
        $employee = $all && $request->filled('medewerker') ? Employee::find($request->integer('medewerker')) : $user->employee;

        $requests = LeaveRequest::with(['lines', 'employee', 'decider'])
            ->when(! $all || $employee, fn ($q) => $q->where('employee_id', $employee?->id ?? 0))
            ->where(fn ($q) => $q->whereHas('lines', fn ($l) => $l->whereYear('date', $year))->orWhereDoesntHave('lines'))
            ->latest('id')->get()
            ->sortByDesc(fn ($r) => $r->lines->first()?->date?->toDateString() ?? '0000');

        return view('leave.index', [
            'year' => $year, 'employee' => $employee, 'requests' => $requests, 'all' => $all,
            'balance' => $employee?->leaveBalance($year), 'employees' => $all ? Employee::options() : [],
            'pending' => $all ? LeaveRequest::where('status', 'aangevraagd')->count() : 0,
        ]);
    }

    public function create(Request $request): View
    {
        $all = $request->user()->can('verlof.fiatteren');
        abort_unless($request->user()->employee || $all, 422, 'Je account is niet gekoppeld aan een medewerker.');

        return view('leave.form', ['employees' => $all ? Employee::options() : [], 'employee' => $request->user()->employee]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $all = $user->can('verlof.fiatteren');
        $data = $request->validate([
            'employee_id' => [$all ? 'nullable' : 'prohibited', 'exists:employees,id'],
            'type' => ['required', Rule::in(array_keys(LeaveRequest::TYPES))],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'description' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], [], ['from' => 'eerste dag', 'to' => 'laatste dag', 'hours_per_day' => 'uren per dag', 'type' => 'soort']);
        $employee = isset($data['employee_id']) ? Employee::find($data['employee_id']) : $user->employee;
        abort_unless($employee, 422, 'Je account is niet gekoppeld aan een medewerker.');

        $from = CarbonImmutable::parse($data['from']);
        $to = CarbonImmutable::parse($data['to']);
        if ($from->diffInDays($to) > 92) {
            throw ValidationException::withMessages(['to' => 'Vraag maximaal drie maanden in één keer aan.']);
        }
        $days = collect(CarbonPeriod::create($from, $to))->filter(fn ($d) => ! $d->isWeekend())->map(fn ($d) => $d->toDateString())->values();
        if ($days->isEmpty()) {
            throw ValidationException::withMessages(['from' => 'Deze periode bevat geen werkdagen.']);
        }
        // whereDate: datums staan per database verschillend opgeslagen (SQLite met tijd erbij).
        $taken = LeaveRequestLine::whereDate('date', '>=', $days->first())->whereDate('date', '<=', $days->last())
            ->whereHas('request', fn ($q) => $q->where('employee_id', $employee->id)->whereIn('status', ['aangevraagd', 'goedgekeurd']))
            ->get()->map(fn ($l) => $l->date->toDateString())->all();
        if (array_intersect($taken, $days->all())) {
            throw ValidationException::withMessages(['from' => 'Voor een of meer van deze dagen loopt al een aanvraag.']);
        }

        $leave = DB::transaction(function () use ($data, $employee, $user, $days) {
            $leave = LeaveRequest::create([
                'employee_id' => $employee->id, 'type' => $data['type'], 'description' => $data['description'] ?? null,
                'comment' => $data['comment'] ?? null, 'status' => 'aangevraagd', 'created_by' => $user->id,
            ]);
            foreach ($days as $day) {
                $leave->lines()->create(['date' => $day, 'hours' => $data['hours_per_day']]);
            }

            return $leave;
        });

        return redirect()->route('verlof.index', $employee->id !== $user->employee?->id ? ['medewerker' => $employee->id] : [])
            ->with('status', 'Verlof aangevraagd: '.$days->count().' '.($days->count() === 1 ? 'dag' : 'dagen').', '.number_format($days->count() * $data['hours_per_day'], 1, ',', '.').' uur.');
    }

    public function withdraw(Request $request, LeaveRequest $verlof): RedirectResponse
    {
        abort_unless($verlof->employee_id === $request->user()->employee?->id || $request->user()->can('verlof.fiatteren'), 403);
        $verlof->load('lines');
        abort_unless($verlof->canWithdraw(), 422, 'Deze aanvraag kan niet meer worden ingetrokken.');
        $verlof->update(['status' => 'ingetrokken']);

        return back()->with('status', 'Aanvraag ingetrokken.');
    }

    public function approvals(): View
    {
        return view('leave.approvals', [
            'requests' => LeaveRequest::with(['lines', 'employee'])->where('status', 'aangevraagd')->oldest('id')->get(),
        ]);
    }

    public function decide(Request $request, LeaveRequest $verlof): RedirectResponse
    {
        $data = $request->validate([
            'actie' => ['required', 'in:goedkeuren,afwijzen'],
            'decision_note' => ['nullable', 'string', 'max:255', 'required_if:actie,afwijzen'],
        ], ['decision_note.required_if' => 'Geef een reden bij afwijzen.']);
        abort_unless($verlof->status === 'aangevraagd', 422, 'Over deze aanvraag is al beslist.');
        abort_if($verlof->employee_id === $request->user()->employee?->id, 403, 'Eigen verlof keurt iemand anders goed.');

        $verlof->update([
            'status' => $data['actie'] === 'goedkeuren' ? 'goedgekeurd' : 'afgewezen',
            'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $data['decision_note'] ?? null,
        ]);
        Audit::log('verlof_'.$verlof->status, $verlof, $verlof->employee?->name.' · '.$verlof->load('lines')->period());

        // Bericht aan de medewerker; een mislukte mail houdt de beslissing niet tegen.
        if ($recipient = $verlof->employee?->user) {
            try {
                $recipient->notify(new VerlofBeslissing($verlof));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'Aanvraag van '.$verlof->employee?->name.' '.$verlof->status.'.');
    }
}
