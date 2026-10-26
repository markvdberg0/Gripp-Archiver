<?php

namespace App\Http\Controllers;

use App\Models\CalendarItem;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPhase;
use App\Models\TaskType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user()->employee?->id;
        $who = $request->query('wie', $me ? 'ik' : 'iedereen');
        $tasks = Task::query()->with(['employee', 'project', 'company', 'type'])
            ->when($who === 'ik', fn ($q) => $q->where('employee_id', $me ?? 0))
            ->when(is_numeric($who), fn ($q) => $q->where('employee_id', (int) $who))
            ->when($request->query('status', 'open') !== 'alle', fn ($q) => $q->where('completed', $request->query('status', 'open') === 'afgerond'))
            ->orderByRaw('deadline is null')->orderBy('deadline')->orderBy('id')
            ->paginate(50)->withQueryString();

        return view('tasks.index', ['tasks' => $tasks, 'who' => $who, 'employees' => Employee::options()]);
    }

    public function show(Task $taak): View
    {
        $taak->load(['employee', 'project', 'company', 'offer', 'type', 'phase', 'calendarItems.employee', 'files']);

        return view('tasks.show', ['task' => $taak, 'employees' => Employee::options()]);
    }

    public function create(Request $request): View
    {
        $project = $request->integer('project_id') ? Project::find($request->integer('project_id')) : null;

        return view('tasks.form', $this->formData(new Task([
            'project_id' => $project?->id, 'company_id' => $project?->company_id, 'employee_id' => $request->user()->employee?->id,
            'phase_id' => TaskPhase::query()->orderBy('sort')->value('id'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['company_id'] ??= isset($data['project_id']) ? Project::find($data['project_id'])?->company_id : null;
        $task = Task::create($data + ['number' => (int) Task::max('number') + 1]);

        return redirect()->route('taken.show', $task)->with('status', 'Taak aangemaakt.');
    }

    public function edit(Task $taak): View
    {
        return view('tasks.form', $this->formData($taak));
    }

    public function update(Request $request, Task $taak): RedirectResponse
    {
        $taak->update($this->validated($request));

        return redirect()->route('taken.show', $taak)->with('status', 'Taak opgeslagen.');
    }

    public function toggle(Task $taak): RedirectResponse
    {
        $taak->update(['completed' => ! $taak->completed, 'completed_at' => $taak->completed ? null : now()]);

        return back()->with('status', $taak->completed ? 'Taak afgerond.' : 'Taak heropend.');
    }

    /** Taak inplannen: agenda-item voor een medewerker op een dag. */
    public function schedule(Request $request, Task $taak): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
        ], [], ['hours' => 'uren', 'date' => 'datum']);
        CalendarItem::create($data + ['task_id' => $taak->id, 'subject' => $taak->title]);

        return back()->with('status', 'Ingepland.');
    }

    private function formData(Task $task): array
    {
        return [
            'task' => $task, 'employees' => Employee::options(), 'projects' => Project::options(), 'companies' => Company::options(),
            'types' => TaskType::options(), 'phases' => TaskPhase::options(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'type_id' => ['nullable', 'exists:task_types,id'],
            'phase_id' => ['nullable', 'exists:task_phases,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'deadline_time' => ['nullable', 'date_format:H:i'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ], [], ['title' => 'omschrijving', 'estimated_hours' => 'geschatte uren']);
    }
}
