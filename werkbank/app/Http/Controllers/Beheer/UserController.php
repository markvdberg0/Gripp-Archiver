<?php

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit;
use App\Support\Rechten;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('beheer.users.index', ['users' => User::with('employee')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('beheer.users.form', ['user' => new User(['role' => 'medewerker', 'active' => true]), 'employees' => $this->employeeOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $password = Str::password(16, symbols: false);
        $user = DB::transaction(function () use ($data, $password) {
            $user = User::create(Arr::except($data, ['employee_id']) + ['password' => $password, 'must_change_password' => true]);
            $this->linkEmployee($user, $data['employee_id'] ?? null);

            return $user;
        });

        return redirect()->route('beheer.gebruikers.edit', $user)
            ->with('status', "Gebruiker aangemaakt. Tijdelijk wachtwoord (wordt maar één keer getoond): {$password}");
    }

    public function edit(User $gebruiker): View
    {
        return view('beheer.users.form', ['user' => $gebruiker->load('employee'), 'employees' => $this->employeeOptions($gebruiker)]);
    }

    public function update(Request $request, User $gebruiker): RedirectResponse
    {
        $data = $this->validated($request, $gebruiker);
        if ($gebruiker->is($request->user()) && (! $data['active'] || $data['role'] !== $gebruiker->role)) {
            return back()->withErrors(['role' => 'Je kunt je eigen rol niet wijzigen en jezelf niet deactiveren.']);
        }
        if ($gebruiker->role === 'beheerder' && ($data['role'] !== 'beheerder' || ! $data['active']) && User::where('role', 'beheerder')->where('active', true)->count() <= 1) {
            return back()->withErrors(['role' => 'Er moet minstens één actieve beheerder zijn.']);
        }
        DB::transaction(function () use ($gebruiker, $data) {
            $gebruiker->update(Arr::except($data, ['employee_id']));
            $this->linkEmployee($gebruiker, $data['employee_id'] ?? null);
        });

        return redirect()->route('beheer.gebruikers.index')->with('status', 'Gebruiker opgeslagen.');
    }

    public function resetPassword(User $gebruiker): RedirectResponse
    {
        $password = Str::password(16, symbols: false);
        $gebruiker->forceFill(['password' => $password, 'must_change_password' => true, 'remember_token' => null])->saveQuietly();
        DB::table('sessions')->where('user_id', $gebruiker->id)->delete();
        Audit::log('wachtwoord_reset', $gebruiker, $gebruiker->email);

        return back()->with('status', "Nieuw tijdelijk wachtwoord voor {$gebruiker->name} (wordt maar één keer getoond): {$password}");
    }

    /** Telefoon kwijt en geen herstelcodes: de beheerder zet tweestapsverificatie uit; de gebruiker stelt hem opnieuw in. */
    public function resetTwoFactor(User $gebruiker): RedirectResponse
    {
        $gebruiker->disableTwoFactor();
        DB::table('sessions')->where('user_id', $gebruiker->id)->delete();
        Audit::log('tweestaps_reset', $gebruiker, $gebruiker->email);

        return back()->with('status', "Tweestapsverificatie van {$gebruiker->name} staat uit. Laat de gebruiker hem na het inloggen opnieuw instellen.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(Rechten::ROLLEN))],
            'active' => ['boolean'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ], [], ['name' => 'naam', 'role' => 'rol', 'employee_id' => 'medewerker']);
        $data['active'] = (bool) ($data['active'] ?? false);

        return $data;
    }

    private function linkEmployee(User $user, ?int $employeeId): void
    {
        Employee::where('user_id', $user->id)->where('id', '!=', $employeeId)->update(['user_id' => null]);
        if ($employeeId) {
            Employee::whereKey($employeeId)->update(['user_id' => $user->id]);
        }
    }

    private function employeeOptions(?User $user = null): array
    {
        return Employee::query()->where(fn ($q) => $q->whereNull('user_id')->when($user, fn ($q) => $q->orWhere('user_id', $user->id)))
            ->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->name])->all();
    }
}
