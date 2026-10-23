<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'active' => true], $request->boolean('remember'))) {
            Audit::log('inloggen_mislukt', null, 'Mislukte inlogpoging voor '.mb_substr($data['email'], 0, 120));
            throw ValidationException::withMessages(['email' => 'Deze combinatie van e-mailadres en wachtwoord is onbekend, of het account is niet actief.']);
        }

        $request->session()->regenerate();
        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Audit::log('ingelogd', null, $user->email);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Audit::log('uitgelogd', null, $request->user()?->email);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
