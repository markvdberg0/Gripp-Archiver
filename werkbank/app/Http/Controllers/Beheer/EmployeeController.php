<?php

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(): View
    {
        return view('beheer.employees.index', ['employees' => Employee::with('user')->orderByDesc('active')->orderBy('first_name')->get()]);
    }

    public function create(): View
    {
        return view('beheer.employees.form', ['employee' => new Employee(['active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Employee::create($this->validated($request));

        return redirect()->route('beheer.medewerkers.index')->with('status', 'Medewerker aangemaakt.');
    }

    public function edit(Employee $medewerker): View
    {
        return view('beheer.employees.form', ['employee' => $medewerker]);
    }

    public function update(Request $request, Employee $medewerker): RedirectResponse
    {
        $medewerker->update($this->validated($request));

        return redirect()->route('beheer.medewerkers.index')->with('status', 'Medewerker opgeslagen.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'number' => ['nullable', 'integer', 'min:0'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'infix' => ['nullable', 'string', 'max:30'],
            'last_name' => ['required', 'string', 'max:100'],
            'initials' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'function' => ['nullable', 'string', 'max:100'],
            'employed_since' => ['nullable', 'date'],
            'leave_hours_per_year' => ['nullable', 'numeric', 'between:0,2000'],
            'active' => ['boolean'],
        ], [], ['last_name' => 'achternaam']);
    }
}
