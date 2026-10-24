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
    /** Zoveel seconden heeft iemand na het wachtwoord om de code in te vullen. */
    private const TWEESTAPS_GELDIG = 300;

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

        $credentials = ['email' => $data['email'], 'password' => $data['password'], 'active' => true];
        $provider = Auth::guard('web')->getProvider();
        /** @var User|null $user */
        $user = $provider->retrieveByCredentials($credentials);
        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            Audit::log('inloggen_mislukt', null, 'Mislukte inlogpoging voor '.mb_substr($data['email'], 0, 120));
            throw ValidationException::withMessages(['email' => 'Deze combinatie van e-mailadres en wachtwoord is onbekend, of het account is niet actief.']);
        }

        if ($user->hasTwoFactor()) {
            // Nog niet inloggen: eerst de code. Alleen het id staat in de sessie, met een vervaltijd.
            $request->session()->regenerate();
            $request->session()->put('tweestaps.login', ['id' => $user->id, 'remember' => $request->boolean('remember'), 'tot' => time() + self::TWEESTAPS_GELDIG, 'pogingen' => 0]);

            return redirect()->route('login.tweestaps');
        }

        return $this->complete($request, $user, $request->boolean('remember'));
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $this->pending($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:30']]);
        $pending = $this->pending($request);
        $user = $pending ? User::where('active', true)->find($pending['id']) : null;
        if (! $user) {
            $request->session()->forget('tweestaps.login');

            return redirect()->route('login')->withErrors(['email' => 'Log opnieuw in; de tijd voor de code is verstreken.']);
        }

        $method = $user->verifyTwoFactor($request->input('code'));
        if (! $method) {
            Audit::log('tweestaps_mislukt', $user, $user->email);
            $pending['pogingen']++;
            if ($pending['pogingen'] >= 5) {
                $request->session()->forget('tweestaps.login');

                return redirect()->route('login')->withErrors(['email' => 'Te veel onjuiste codes. Log opnieuw in.']);
            }
            $request->session()->put('tweestaps.login', $pending);
            throw ValidationException::withMessages(['code' => 'Deze code klopt niet.']);
        }
        $request->session()->forget('tweestaps.login');
        if ($method === 'herstelcode') {
            Audit::log('tweestaps_herstelcode_gebruikt', $user, $user->email.' (nog '.count($user->two_factor_recovery_codes ?? []).' over)');
        }

        return $this->complete($request, $user, (bool) $pending['remember']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Audit::log('uitgelogd', null, $request->user()?->email);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function complete(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Audit::log('ingelogd', null, $user->email);

        return redirect()->intended(route('dashboard'));
    }

    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('tweestaps.login');

        return $pending && $pending['tot'] >= time() ? $pending : null;
    }
}
