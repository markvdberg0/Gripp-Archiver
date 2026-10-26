<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Hour;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\Unit;
use App\Models\VatRate;
use App\Support\Audit;
use App\Support\Nummering;
use App\Support\Regels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    private const SORT = ['number' => 'number', 'name' => 'name', 'start' => 'start_date', 'deadline' => 'deadline', 'total' => 'total_excl'];

    public function index(Request $request): View
    {
        $sort = self::SORT[$request->query('sort')] ?? 'number';
        $dir = $request->query('dir', $request->has('sort') ? 'asc' : 'desc') === 'desc' ? 'desc' : 'asc';
        $base = Project::query()->search($request->query('q'))
            ->when($request->query('fase'), fn ($q, $f) => $q->where('phase_id', $f))
            ->when($request->query('relatie'), fn ($q, $c) => $q->where('company_id', $c))
            ->when($request->query('accountmanager'), fn ($q, $e) => $q->where('account_manager_id', $e))
            ->when($request->query('archief') !== '1', fn ($q) => $q->where('archived', false));
        $sum = (clone $base)->selectRaw('count(*) as n, coalesce(sum(total_excl),0) as total')->toBase()->first();
        $projects = $base->with(['company', 'phase'])->withSum('hours as hours_written', 'hours')
            ->orderBy($sort, $dir)->orderByDesc('id')->paginate(50)->withQueryString();

        return view('projects.index', ['projects' => $projects, 'sum' => $sum, 'phases' => ProjectPhase::options(), 'employees' => Employee::options()]);
    }

    public function create(Request $request): View
    {
        $company = $request->integer('company_id') ? Company::find($request->integer('company_id')) : null;

        return view('projects.form', $this->formData(new Project([
            'company_id' => $company?->id, 'start_date' => now(), 'account_manager_id' => $company?->account_manager_id ?? $request->user()->employee?->id,
            'phase_id' => ProjectPhase::query()->orderBy('sort')->value('id'),
        ])) + ['nextNumber' => Nummering::peek('opdracht', 'projects')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $project = DB::transaction(function () use ($data) {
            $project = Project::create(Arr::except($data, ['lines', 'employees']) + ['number' => Nummering::next('opdracht', 'projects')]);
            $project->employees()->sync($data['employees'] ?? []);
            Regels::sync($project, $data['lines'] ?? []);
            $project->recalculate();

            return $project;
        });

        return redirect()->route('opdrachten.show', $project)->with('status', "Opdracht {$project->number} aangemaakt.");
    }

    /** Geaccepteerde offerte → opdracht: gegevens en regels worden overgenomen. */
    public function fromOffer(Request $request, Offer $offerte): RedirectResponse
    {
        abort_unless($offerte->acceptance === 'geaccepteerd', 422, 'Alleen van een geaccepteerde offerte kan een opdracht worden gemaakt.');
        if ($existing = $offerte->projects()->first()) {
            return redirect()->route('opdrachten.show', $existing)->with('warning', 'Van deze offerte is al een opdracht gemaakt.');
        }
        $project = DB::transaction(function () use ($offerte, $request) {
            $project = Project::create([
                'number' => Nummering::next('opdracht', 'projects'), 'name' => $offerte->name, 'offer_id' => $offerte->id,
                'company_id' => $offerte->company_id, 'contact_id' => $offerte->contact_id, 'account_manager_id' => $offerte->account_manager_id,
                'client_reference' => $offerte->client_reference, 'description' => $offerte->description, 'start_date' => now(),
                'phase_id' => ProjectPhase::query()->orderBy('sort')->value('id'),
            ]);
            Regels::copy($offerte, $project);
            $project->recalculate();
            // Documenten van de offerte (bijlagen) gaan mee als verwijzing.
            $project->files()->syncWithoutDetaching($offerte->files()->pluck('files.id')->mapWithKeys(fn ($id) => [$id => ['role' => 'bijlage', 'created_at' => now()]])->all());
            $project->timeline()->create(['type' => 'systeem', 'body' => "Opdracht aangemaakt uit offerte {$offerte->number}.", 'occurred_at' => now(), 'user_id' => $request->user()->id, 'company_id' => $project->company_id, 'offer_id' => $offerte->id]);

            return $project;
        });

        return redirect()->route('opdrachten.show', $project)->with('status', "Opdracht {$project->number} gemaakt uit offerte {$offerte->number}.");
    }

    public function show(Project $opdracht): View
    {
        $opdracht->load(['company', 'contact', 'offer', 'phase', 'accountManager', 'employees', 'files', 'tags', 'lines.unit', 'lines.vatRate', 'lines.product']);
        $hoursPerLine = Hour::where('project_id', $opdracht->id)->groupBy('project_line_id')->selectRaw('project_line_id, sum(hours) as h')->pluck('h', 'project_line_id');
        $hoursPerEmployee = Hour::where('project_id', $opdracht->id)->with('employee')->get()->groupBy('employee_id')
            ->map(fn ($g) => ['name' => $g->first()->employee?->name, 'total' => $g->sum('hours'), 'approved' => $g->where('status', 'gefiatteerd')->sum('hours')]);
        $tasks = $opdracht->tasks()->with('employee')->orderBy('completed')->orderBy('deadline')->get();
        $timeline = $opdracht->timeline()->visibleTo(request()->user())->with(['employee', 'user', 'files'])->paginate(25);

        return view('projects.show', [
            'project' => $opdracht, 'vat' => $opdracht->vatBreakdown(), 'hoursPerLine' => $hoursPerLine, 'hoursPerEmployee' => $hoursPerEmployee,
            'tasks' => $tasks, 'timeline' => $timeline, 'related' => $this->related($opdracht),
        ]);
    }

    /** Facturen uit fase 4. */
    protected function related(Project $project): array
    {
        return method_exists($project, 'invoices') ? ['invoices' => $project->invoices()->latest('date')->get()] : [];
    }

    public function edit(Project $opdracht): View
    {
        return view('projects.form', $this->formData($opdracht->load(['lines', 'employees'])));
    }

    public function update(Request $request, Project $opdracht): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($opdracht, $data) {
            $opdracht->update(Arr::except($data, ['lines', 'employees']));
            $opdracht->employees()->sync($data['employees'] ?? []);
            Regels::sync($opdracht, $data['lines'] ?? []);
            $opdracht->recalculate();
        });

        return redirect()->route('opdrachten.show', $opdracht)->with('status', 'Opdracht opgeslagen.');
    }

    public function destroy(Project $opdracht): RedirectResponse
    {
        if ($opdracht->hours()->exists() || (method_exists($opdracht, 'invoices') && $opdracht->invoices()->exists())) {
            return back()->withErrors(['opdracht' => 'Op deze opdracht zijn uren geschreven of facturen gemaakt. Archiveer hem in plaats van verwijderen.']);
        }
        $opdracht->delete();

        return redirect()->route('opdrachten.index')->with('status', "Opdracht {$opdracht->number} verwijderd.");
    }

    public function archive(Project $opdracht): RedirectResponse
    {
        $opdracht->update(['archived' => ! $opdracht->archived, 'archived_at' => $opdracht->archived ? null : now()]);

        return back()->with('status', $opdracht->archived ? 'Opdracht gearchiveerd.' : 'Opdracht uit het archief gehaald.');
    }

    public function pdf(Project $opdracht): Response
    {
        $opdracht->load(['company', 'contact', 'accountManager', 'lines.unit', 'lines.vatRate', 'lines.product']);
        Audit::log('pdf', $opdracht, $opdracht->auditLabel());

        return Pdf::loadView('pdf.project', ['project' => $opdracht, 'vat' => $opdracht->vatBreakdown()])->setPaper('a4')->stream("Opdrachtbevestiging {$opdracht->number}.pdf");
    }

    private function formData(Project $project): array
    {
        return [
            'project' => $project,
            'companies' => Company::options(),
            'contacts' => Contact::query()->where('active', true)->when($project->company_id, fn ($q) => $q->where('company_id', $project->company_id))->orderBy('last_name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'phases' => ProjectPhase::options(),
            'employees' => Employee::options(),
            'products' => Product::forPicker(),
            'units' => Unit::options(),
            'vatRates' => VatRate::options('verkoop'),
        ];
    }

    private function validated(Request $request): array
    {
        Regels::prepare($request);

        return $request->validate(Regels::rules([
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['required', 'exists:companies,id'],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('company_id', $request->input('company_id'))],
            'phase_id' => ['nullable', 'exists:project_phases,id'],
            'account_manager_id' => ['nullable', 'exists:employees,id'],
            'employees' => ['nullable', 'array'],
            'employees.*' => ['exists:employees,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'deadline' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'work_address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:100000'],
        ]), ['contact_id.exists' => 'De contactpersoon hoort niet bij de gekozen relatie.'], Regels::attributes() + ['name' => 'omschrijving', 'end_date' => 'einddatum', 'start_date' => 'startdatum']);
    }
}
